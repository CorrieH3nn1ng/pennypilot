<template>
  <q-card v-if="hasInsight" class="penny-insight-card" flat bordered>
    <q-card-section class="q-pa-md">
      <div class="row items-start no-wrap">
        <!-- Penny Avatar -->
        <q-avatar
          color="teal-8"
          text-color="white"
          size="40px"
          class="q-mr-md"
        >
          <q-icon name="face" />
        </q-avatar>

        <!-- Insight Content -->
        <div class="col">
          <div class="row items-center q-mb-xs">
            <span class="text-weight-medium text-teal-9">Penny</span>
            <q-chip
              v-if="insight.type"
              :color="insightTypeColor"
              text-color="white"
              size="sm"
              dense
              class="q-ml-sm"
            >
              {{ insight.type }}
            </q-chip>
          </div>

          <div class="text-subtitle2 text-grey-9 insight-message">
            {{ insight.message }}
          </div>

          <!-- Action Button (if applicable) -->
          <div v-if="insight.action" class="q-mt-sm">
            <q-btn
              flat
              dense
              color="teal"
              :label="insight.action.label"
              :icon="insight.action.icon"
              class="q-px-sm"
              @click="handleAction"
            />
          </div>
        </div>

        <!-- Dismiss Button -->
        <q-btn
          flat
          round
          dense
          icon="close"
          size="sm"
          color="grey-5"
          class="q-ml-sm"
          @click="dismiss"
        />
      </div>
    </q-card-section>

    <!-- Confidence Bar -->
    <q-linear-progress
      :value="insight.confidence"
      color="teal"
      track-color="teal-2"
      size="4px"
      class="confidence-bar"
    />
  </q-card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useBudgetStore } from '@/stores/budget.store';
import { useIncomeStore } from '@/stores/income.store';
import { taxGapService } from '@/services/intelligence/TaxGapService';

// Types
export interface PennyInsight {
  id: string;
  type: 'tip' | 'alert' | 'celebration' | 'nudge' | 'tax';
  message: string;
  confidence: number; // 0-1
  action?: {
    label: string;
    icon: string;
    route?: string;
    handler?: () => void;
  };
  category?: string;
  dismissedAt?: string;
}

interface SpendingData {
  categoryId: string;
  categoryName: string;
  currentWeek: number;
  weeklyAverage: number;
  percentOfBudget: number;
  trend: 'up' | 'down' | 'stable';
}

const router = useRouter();
const transactionsStore = useTransactionsStore();
const budgetStore = useBudgetStore();
const incomeStore = useIncomeStore();

const insight = ref<PennyInsight>({
  id: '',
  type: 'tip',
  message: '',
  confidence: 0,
});

const dismissed = ref(false);

const hasInsight = computed(() => {
  return insight.value.message && !dismissed.value;
});

const insightTypeColor = computed(() => {
  switch (insight.value.type) {
    case 'celebration':
      return 'positive';
    case 'alert':
      return 'warning';
    case 'nudge':
      return 'amber-8';
    case 'tax':
      return 'deep-purple';
    default:
      return 'teal';
  }
});

// ============================================
// Fuzzy Insight Message Generator
// ============================================

function formatRands(amount: number): string {
  const num = Number(amount) || 0;
  const formatted = Math.abs(num).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `R ${formatted}`;
}

function getInsightMessage(data: {
  spending: SpendingData[];
  uncategorizedCount: number;
  budgetUsed: number;
  savingsRate: number;
  hasGoals: boolean;
  daysUntilPayday: number;
  totalIncome: number;
  monthlySetAside: number;
}): PennyInsight | null {
  const insights: PennyInsight[] = [];

  // TAX GAP INSIGHT - High priority when income detected
  if (data.totalIncome > 0 && data.monthlySetAside > 0) {
    insights.push({
      id: `tax-gap-${Date.now()}`,
      type: 'tax',
      message: `I've calculated your Tax Gap based on the 2025/26 SARS brackets. You should set aside ${formatRands(data.monthlySetAside)} this month to stay compliant.`,
      confidence: 0.96,
      action: {
        label: 'View Tax Details',
        icon: 'calculate',
        route: '/tax',
      },
    });
  }

  // Check for spending spikes in specific categories
  for (const cat of data.spending) {
    if (cat.weeklyAverage > 0) {
      const ratio = cat.currentWeek / cat.weeklyAverage;

      // Food spending spike (> 20% above average)
      if (cat.categoryName.toLowerCase().includes('food') && ratio > 1.2) {
        insights.push({
          id: `food-spike-${Date.now()}`,
          type: 'nudge',
          message: `I noticed a few extra treats this week! Life happens—should we adjust the 'Wants' bucket for next week to stay on track for your goals?`,
          confidence: Math.min(0.9, 0.5 + (ratio - 1) * 0.5),
          action: {
            label: 'Adjust Budget',
            icon: 'tune',
            route: '/budget',
          },
        });
      }

      // Entertainment spike
      if (cat.categoryName.toLowerCase().includes('entertainment') && ratio > 1.3) {
        insights.push({
          id: `entertainment-spike-${Date.now()}`,
          type: 'tip',
          message: `Looks like you've been having some fun lately! That's important too. Want me to help balance things out for the rest of the month?`,
          confidence: Math.min(0.85, 0.4 + (ratio - 1) * 0.4),
          action: {
            label: 'View Spending',
            icon: 'bar_chart',
            route: '/transactions',
          },
        });
      }
    }
  }

  // Uncategorized transactions reminder
  if (data.uncategorizedCount > 5) {
    insights.push({
      id: `uncategorized-${Date.now()}`,
      type: 'nudge',
      message: `You have ${data.uncategorizedCount} transactions waiting for categories. Quick swipe right to confirm my suggestions, or swipe left to pick your own!`,
      confidence: 0.95,
      action: {
        label: 'Categorize Now',
        icon: 'category',
        route: '/transactions?filter=uncategorized',
      },
    });
  }

  // Budget usage alerts
  if (data.budgetUsed > 0.9) {
    insights.push({
      id: `budget-high-${Date.now()}`,
      type: 'alert',
      message: `We're at ${Math.round(data.budgetUsed * 100)}% of this month's budget. Let's take a quick look and see if we need to tighten up for the last stretch.`,
      confidence: 0.92,
      action: {
        label: 'Check Budget',
        icon: 'account_balance_wallet',
        route: '/budget',
      },
    });
  } else if (data.budgetUsed > 0.75) {
    insights.push({
      id: `budget-moderate-${Date.now()}`,
      type: 'tip',
      message: `You're doing well! About ${Math.round((1 - data.budgetUsed) * 100)}% of your budget is still available. Keep it up!`,
      confidence: 0.75,
    });
  }

  // Savings celebration
  if (data.savingsRate > 0.2) {
    insights.push({
      id: `savings-win-${Date.now()}`,
      type: 'celebration',
      message: `Amazing! You're saving ${Math.round(data.savingsRate * 100)}% of your income this month. That's above the recommended 20%—you're crushing it!`,
      confidence: 0.98,
    });
  }

  // No goals set nudge
  if (!data.hasGoals) {
    insights.push({
      id: `no-goals-${Date.now()}`,
      type: 'nudge',
      message: `Setting a financial goal can make saving feel more meaningful. What are you dreaming about—a holiday, a new car, or maybe your own place?`,
      confidence: 0.7,
      action: {
        label: 'Set a Goal',
        icon: 'flag',
        route: '/goals',
      },
    });
  }

  // Payday countdown
  if (data.daysUntilPayday <= 3 && data.daysUntilPayday > 0) {
    insights.push({
      id: `payday-soon-${Date.now()}`,
      type: 'tip',
      message: `Just ${data.daysUntilPayday} day${data.daysUntilPayday > 1 ? 's' : ''} until payday! You've got this. Let's make sure we're set up for a strong start next month.`,
      confidence: 0.8,
    });
  }

  // Return highest confidence insight
  if (insights.length === 0) return null;

  return insights.sort((a, b) => b.confidence - a.confidence)[0];
}

// ============================================
// Data Collection & Insight Generation
// ============================================

async function generateInsight(): Promise<void> {
  // Gather spending data
  const transactions = transactionsStore.transactions;
  const now = new Date();
  const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
  const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);

  // Calculate weekly spending by category
  const weeklySpending = new Map<string, number>();
  const monthlySpending = new Map<string, number>();

  transactions.forEach((tx) => {
    if (tx.amount < 0 && tx.category_id) {
      const txDate = new Date(tx.transaction_date);
      const amount = Math.abs(tx.amount);

      if (txDate >= weekAgo) {
        weeklySpending.set(
          tx.category_id,
          (weeklySpending.get(tx.category_id) || 0) + amount
        );
      }
      if (txDate >= monthAgo) {
        monthlySpending.set(
          tx.category_id,
          (monthlySpending.get(tx.category_id) || 0) + amount
        );
      }
    }
  });

  // Build spending data array
  const spending: SpendingData[] = [];
  weeklySpending.forEach((currentWeek, categoryId) => {
    const monthlyTotal = monthlySpending.get(categoryId) || 0;
    const weeklyAverage = monthlyTotal / 4;

    spending.push({
      categoryId,
      categoryName: getCategoryName(categoryId),
      currentWeek,
      weeklyAverage,
      percentOfBudget: 0, // Could be calculated from budget store
      trend: currentWeek > weeklyAverage * 1.1 ? 'up' : currentWeek < weeklyAverage * 0.9 ? 'down' : 'stable',
    });
  });

  // Calculate other metrics
  const totalIncome = transactionsStore.totalIncome;
  const totalExpenses = Math.abs(transactionsStore.totalExpenses);
  const savingsRate = totalIncome > 0 ? (totalIncome - totalExpenses) / totalIncome : 0;

  // Budget usage (simplified)
  const budgetUsed = budgetStore.currentBudget
    ? (budgetStore.bucketTotals?.needs.actual || 0) / (budgetStore.targetAllocations?.needs || 1)
    : 0;

  // Days until typical payday (25th of month)
  const payday = 25;
  const today = now.getDate();
  let daysUntilPayday = payday - today;
  if (daysUntilPayday < 0) daysUntilPayday += 30;

  // Calculate tax data for insights
  let monthlySetAside = 0;
  if (totalIncome > 0) {
    const projectedAnnual = totalIncome * 12;
    const taxResult = taxGapService.calculateTax({ annualIncome: projectedAnnual });
    monthlySetAside = taxResult.taxPayable / 12;
  }

  // Generate insight
  const newInsight = getInsightMessage({
    spending,
    uncategorizedCount: transactionsStore.uncategorizedCount,
    budgetUsed: Math.min(1, budgetUsed),
    savingsRate: Math.max(0, savingsRate),
    hasGoals: false, // TODO: integrate with quest store
    daysUntilPayday,
    totalIncome,
    monthlySetAside,
  });

  if (newInsight) {
    insight.value = newInsight;
    dismissed.value = false;
  }
}

function getCategoryName(categoryId: string): string {
  // Simple lookup - in real implementation, use categories store
  const categoryMap: Record<string, string> = {
    food: 'Food & Dining',
    groceries: 'Groceries',
    entertainment: 'Entertainment',
    transport: 'Transport',
    utilities: 'Utilities',
  };
  return categoryMap[categoryId] || 'Other';
}

function handleAction(): void {
  if (insight.value.action?.route) {
    router.push(insight.value.action.route);
  } else if (insight.value.action?.handler) {
    insight.value.action.handler();
  }
}

function dismiss(): void {
  dismissed.value = true;
  // Could store dismissed insight ID in localStorage to prevent re-showing
}

// Generate insight on mount and when transactions change
onMounted(() => {
  generateInsight();
});

watch(
  () => transactionsStore.transactions.length,
  () => {
    generateInsight();
  }
);
</script>

<style scoped>
.penny-insight-card {
  background-color: #E0F2F1; /* teal-1 */
  border-radius: 12px;
  border-color: #B2DFDB; /* teal-2 */
  overflow: hidden;
}

.insight-message {
  line-height: 1.5;
  white-space: pre-wrap;
}

.confidence-bar {
  border-radius: 0 0 12px 12px;
}
</style>
