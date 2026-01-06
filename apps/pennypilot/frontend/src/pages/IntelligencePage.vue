<template>
  <q-page class="intelligence-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-title">Financial Intelligence</div>
      <div class="header-subtitle">Insights powered by Penny</div>
    </div>

    <!-- Tool Cards -->
    <div class="tools-grid">
      <!-- Tax Gap Analysis -->
      <q-card class="tool-card" flat bordered @click="showTaxGap = true">
        <q-card-section class="tool-content">
          <div class="tool-icon tax">
            <q-icon name="receipt_long" size="28px" />
          </div>
          <div class="tool-info">
            <div class="tool-name">Tax Gap Analysis</div>
            <div class="tool-desc">See if you're provisioning enough for SARS</div>
          </div>
          <q-icon name="chevron_right" color="grey-5" />
        </q-card-section>

        <q-linear-progress
          v-if="taxProvisionPercent !== null"
          :value="Math.min(1, taxProvisionPercent / 100)"
          :color="taxProvisionPercent >= 90 ? 'positive' : taxProvisionPercent >= 70 ? 'warning' : 'negative'"
          size="4px"
        />
      </q-card>

      <!-- Monthly Trends -->
      <q-card class="tool-card" flat bordered @click="showTrends = true">
        <q-card-section class="tool-content">
          <div class="tool-icon trends">
            <q-icon name="trending_up" size="28px" />
          </div>
          <div class="tool-info">
            <div class="tool-name">Spending Trends</div>
            <div class="tool-desc">Track your income vs expenses over time</div>
          </div>
          <q-icon name="chevron_right" color="grey-5" />
        </q-card-section>
      </q-card>

      <!-- Category Breakdown -->
      <q-card class="tool-card" flat bordered @click="showCategories = true">
        <q-card-section class="tool-content">
          <div class="tool-icon categories">
            <q-icon name="pie_chart" size="28px" />
          </div>
          <div class="tool-info">
            <div class="tool-name">Category Breakdown</div>
            <div class="tool-desc">See where your money goes each month</div>
          </div>
          <q-icon name="chevron_right" color="grey-5" />
        </q-card-section>
      </q-card>

      <!-- Budget Health -->
      <q-card class="tool-card" flat bordered @click="goToBudget">
        <q-card-section class="tool-content">
          <div class="tool-icon budget">
            <q-icon name="speed" size="28px" />
          </div>
          <div class="tool-info">
            <div class="tool-name">Budget Health</div>
            <div class="tool-desc">50/30/20 rule tracking</div>
          </div>
          <q-icon name="chevron_right" color="grey-5" />
        </q-card-section>

        <q-linear-progress
          v-if="budgetHealth !== null"
          :value="budgetHealth / 100"
          :color="budgetHealth >= 80 ? 'positive' : budgetHealth >= 50 ? 'warning' : 'negative'"
          size="4px"
        />
      </q-card>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
      <div class="stat-item">
        <div class="stat-value text-positive">R {{ formatCompact(monthlyIncome) }}</div>
        <div class="stat-label">This Month Income</div>
      </div>
      <div class="stat-item">
        <div class="stat-value text-negative">R {{ formatCompact(monthlyExpenses) }}</div>
        <div class="stat-label">This Month Expenses</div>
      </div>
      <div class="stat-item">
        <div class="stat-value" :class="savingsRate >= 20 ? 'text-positive' : 'text-warning'">
          {{ savingsRate.toFixed(0) }}%
        </div>
        <div class="stat-label">Savings Rate</div>
      </div>
    </div>

    <!-- Tax Gap Dialog -->
    <q-dialog v-model="showTaxGap" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="dialog-toolbar">
          <q-btn flat round icon="close" v-close-popup />
          <q-toolbar-title>Tax Gap Analysis</q-toolbar-title>
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <TaxDashboard
            :annual-income="annualIncome"
            :annual-deductions="annualDeductions"
            :age="userAge"
            :monthly-provision="monthlyTaxProvision"
          />
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Trends Dialog -->
    <q-dialog v-model="showTrends" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="dialog-toolbar">
          <q-btn flat round icon="close" v-close-popup />
          <q-toolbar-title>Spending Trends</q-toolbar-title>
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <MonthlyTrend />
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Categories Dialog -->
    <q-dialog v-model="showCategories" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="dialog-toolbar">
          <q-btn flat round icon="close" v-close-popup />
          <q-toolbar-title>Category Breakdown</q-toolbar-title>
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <SpendingByCategory />
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useIncomeStore } from '@/stores/income.store';
import { useBudgetStore } from '@/stores/budget.store';
import { useTaxStore } from '@/stores/tax.store';
import TaxDashboard from '@/components/TaxDashboard.vue';
import MonthlyTrend from '@/components/charts/MonthlyTrend.vue';
import SpendingByCategory from '@/components/charts/SpendingByCategory.vue';

const router = useRouter();
const transactionsStore = useTransactionsStore();
const incomeStore = useIncomeStore();
const budgetStore = useBudgetStore();
const taxStore = useTaxStore();

// Dialog state
const showTaxGap = ref(false);
const showTrends = ref(false);
const showCategories = ref(false);

// This month calculations
const monthlyIncome = computed(() => {
  const now = new Date();
  const thisMonth = now.getMonth();
  const thisYear = now.getFullYear();

  return transactionsStore.transactions
    .filter((tx) => {
      const txDate = new Date(tx.transaction_date);
      return tx.amount > 0 && txDate.getMonth() === thisMonth && txDate.getFullYear() === thisYear;
    })
    .reduce((sum, tx) => sum + tx.amount, 0);
});

const monthlyExpenses = computed(() => {
  const now = new Date();
  const thisMonth = now.getMonth();
  const thisYear = now.getFullYear();

  return Math.abs(
    transactionsStore.transactions
      .filter((tx) => {
        const txDate = new Date(tx.transaction_date);
        return tx.amount < 0 && txDate.getMonth() === thisMonth && txDate.getFullYear() === thisYear;
      })
      .reduce((sum, tx) => sum + tx.amount, 0)
  );
});

const savingsRate = computed(() => {
  if (monthlyIncome.value <= 0) return 0;
  const saved = monthlyIncome.value - monthlyExpenses.value;
  return Math.max(0, (saved / monthlyIncome.value) * 100);
});

// Tax data
const annualIncome = computed(() => incomeStore.totalMonthlyGross * 12);
const annualDeductions = computed(() => 0); // Could be from tax settings
const userAge = computed(() => 35); // Could be from user profile
const monthlyTaxProvision = computed(() => taxStore.settings?.monthly_provision || 0);

const taxProvisionPercent = computed(() => {
  if (!monthlyTaxProvision.value || annualIncome.value <= 0) return null;
  // Rough estimate: 25% effective tax rate
  const estimatedAnnualTax = annualIncome.value * 0.25;
  const annualProvision = monthlyTaxProvision.value * 12;
  return (annualProvision / estimatedAnnualTax) * 100;
});

// Budget health
const budgetHealth = computed(() => {
  if (!budgetStore.hasBudget) return null;
  // Simple calculation based on staying within budget
  const totals = budgetStore.bucketTotals;
  const targets = budgetStore.targetAllocations;
  if (!totals || !targets) return null;

  const needsScore = targets.needs > 0 ? Math.min(100, (totals.needs.actual / targets.needs) * 100) : 100;
  const wantsScore = targets.wants > 0 ? Math.min(100, (totals.wants.actual / targets.wants) * 100) : 100;

  return Math.round((200 - needsScore - wantsScore) / 2 + 50);
});

function goToBudget() {
  router.push('/budget');
}

function formatCompact(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(1) + 'K';
  }
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}
</script>

<style scoped>
.intelligence-page {
  padding: 16px;
  padding-bottom: 80px;
  background: #FAFAFA;
}

/* Page Header */
.page-header {
  margin-bottom: 20px;
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #004D40;
}

.header-subtitle {
  font-size: 14px;
  color: #78909C;
}

/* Tools Grid */
.tools-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}

.tool-card {
  border-radius: 12px;
  border-color: #E0E0E0;
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
}

.tool-card:hover {
  border-color: #004D40;
  box-shadow: 0 2px 8px rgba(0, 77, 64, 0.1);
}

.tool-content {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
}

.tool-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.tool-icon.tax {
  background: #E8F5E9;
  color: #2E7D32;
}

.tool-icon.trends {
  background: #E3F2FD;
  color: #1565C0;
}

.tool-icon.categories {
  background: #FFF3E0;
  color: #E65100;
}

.tool-icon.budget {
  background: #F3E5F5;
  color: #7B1FA2;
}

.tool-info {
  flex: 1;
}

.tool-name {
  font-size: 16px;
  font-weight: 600;
  color: #263238;
}

.tool-desc {
  font-size: 13px;
  color: #78909C;
  margin-top: 2px;
}

/* Quick Stats */
.quick-stats {
  display: flex;
  justify-content: space-around;
  padding: 16px;
  background: white;
  border-radius: 12px;
  border: 1px solid #E0E0E0;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
}

.stat-label {
  font-size: 11px;
  color: #90A4AE;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-top: 4px;
}

/* Dialogs */
.dialog-card {
  background: #FAFAFA;
}

.dialog-toolbar {
  background: #004D40;
  color: white;
}

/* Colors */
.text-positive { color: #2E7D32; }
.text-negative { color: #C62828; }
.text-warning { color: #F57C00; }
</style>
