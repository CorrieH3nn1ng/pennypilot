<template>
  <q-card class="financial-snapshot" flat bordered>
    <q-card-section class="q-pa-sm">
      <!-- Salaried User View: Net Income focused -->
      <div v-if="isSalaried && netMonthlyIncome" class="snapshot-grid">
        <!-- Net Income (Budget Basis) -->
        <div class="snapshot-col">
          <div class="snapshot-label">Net Income</div>
          <div class="snapshot-value text-teal-9">
            R {{ formatCompact(netMonthlyIncome) }}
          </div>
          <div class="snapshot-trend">
            <q-icon name="account_balance_wallet" size="12px" color="teal" />
            <span class="text-teal">Budget basis</span>
          </div>
        </div>

        <q-separator vertical class="snapshot-divider" />

        <!-- Expenses -->
        <div class="snapshot-col">
          <div class="snapshot-label">Spent</div>
          <div class="snapshot-value text-negative">
            R {{ formatCompact(Math.abs(totalExpenses)) }}
          </div>
          <div class="snapshot-trend" v-if="budgetProgress">
            <q-icon
              :name="budgetProgress.isOver ? 'warning' : 'check_circle'"
              :color="budgetProgress.isOver ? 'negative' : 'positive'"
              size="12px"
            />
            <span :class="budgetProgress.isOver ? 'text-negative' : 'text-positive'">
              {{ Math.round(budgetProgress.percent) }}% of 80%
            </span>
          </div>
        </div>

        <q-separator vertical class="snapshot-divider" />

        <!-- Remaining (for needs + wants) -->
        <div class="snapshot-col">
          <div class="snapshot-label">Remaining</div>
          <div
            class="snapshot-value"
            :class="budgetProgress && budgetProgress.remaining >= 0 ? 'text-positive' : 'text-negative'"
          >
            R {{ formatCompact(budgetProgress ? budgetProgress.remaining : 0) }}
          </div>
          <div class="snapshot-trend" v-if="budgetAllocation">
            <q-icon name="savings" size="12px" color="amber" />
            <span class="text-amber-9">R{{ formatCompact(budgetAllocation.savings) }} to save</span>
          </div>
        </div>
      </div>

      <!-- Self-Employed / Default View: Transaction Income focused -->
      <div v-else class="snapshot-grid">
        <!-- Income -->
        <div class="snapshot-col">
          <div class="snapshot-label">Income</div>
          <div class="snapshot-value text-positive">
            R {{ formatCompact(totalIncome) }}
          </div>
          <div class="snapshot-trend" v-if="incomeTrend !== 0">
            <q-icon
              :name="incomeTrend > 0 ? 'trending_up' : 'trending_down'"
              :color="incomeTrend > 0 ? 'positive' : 'negative'"
              size="12px"
            />
            <span :class="incomeTrend > 0 ? 'text-positive' : 'text-negative'">
              {{ Math.abs(incomeTrend).toFixed(0) }}%
            </span>
          </div>
        </div>

        <q-separator vertical class="snapshot-divider" />

        <!-- Expenses -->
        <div class="snapshot-col">
          <div class="snapshot-label">Expenses</div>
          <div class="snapshot-value text-negative">
            R {{ formatCompact(Math.abs(totalExpenses)) }}
          </div>
          <div class="snapshot-trend" v-if="expenseTrend !== 0">
            <q-icon
              :name="expenseTrend > 0 ? 'trending_up' : 'trending_down'"
              :color="expenseTrend > 0 ? 'negative' : 'positive'"
              size="12px"
            />
            <span :class="expenseTrend > 0 ? 'text-negative' : 'text-positive'">
              {{ Math.abs(expenseTrend).toFixed(0) }}%
            </span>
          </div>
        </div>

        <q-separator vertical class="snapshot-divider" />

        <!-- Balance -->
        <div class="snapshot-col" @click="!hasSetBalance && $emit('set-balance')">
          <div class="snapshot-label">
            Balance
            <q-icon
              v-if="!hasSetBalance"
              name="edit"
              size="10px"
              color="grey-6"
              class="q-ml-xs"
            />
          </div>
          <div
            class="snapshot-value"
            :class="calculatedBalance >= 0 ? 'text-teal-9' : 'text-negative'"
          >
            R {{ formatCompact(calculatedBalance) }}
          </div>
          <div class="snapshot-trend" v-if="savingsRate > 0">
            <q-icon name="savings" size="12px" color="teal" />
            <span class="text-teal">{{ savingsRate.toFixed(0) }}% saved</span>
          </div>
        </div>
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useAccountStore } from '@/stores/account.store';
import { useUserStore } from '@/stores/user.store';

const emit = defineEmits<{
  (e: 'set-balance'): void;
}>();

const transactionsStore = useTransactionsStore();
const accountStore = useAccountStore();
const userStore = useUserStore();

// Persona checks
const isSalaried = computed(() => userStore.isSalaried);
const netMonthlyIncome = computed(() => userStore.netMonthlyIncome);

// Core metrics
const totalIncome = computed(() => transactionsStore.totalIncome);
const totalExpenses = computed(() => transactionsStore.totalExpenses);
const calculatedBalance = computed(() => accountStore.calculatedBalance);
const hasSetBalance = computed(() => accountStore.hasSetBalance);

// Savings rate (for salaried: use net income as basis)
const savingsRate = computed(() => {
  const income = isSalaried.value && netMonthlyIncome.value
    ? netMonthlyIncome.value
    : totalIncome.value;
  if (income <= 0) return 0;
  const saved = income - Math.abs(totalExpenses.value);
  return Math.max(0, (saved / income) * 100);
});

// Budget basis for salaried users (50/30/20 allocation)
const budgetAllocation = computed(() => {
  if (!isSalaried.value || !netMonthlyIncome.value) return null;
  const net = netMonthlyIncome.value;
  return {
    needs: Math.round(net * 0.5),
    wants: Math.round(net * 0.3),
    savings: Math.round(net * 0.2),
  };
});

// Spending against budget for salaried users
const budgetProgress = computed(() => {
  if (!budgetAllocation.value) return null;
  const spent = Math.abs(totalExpenses.value);
  const needsWantsLimit = budgetAllocation.value.needs + budgetAllocation.value.wants;
  const percent = (spent / needsWantsLimit) * 100;
  return {
    spent,
    limit: needsWantsLimit,
    percent: Math.min(100, percent),
    remaining: needsWantsLimit - spent,
    isOver: spent > needsWantsLimit,
  };
});

// Trend calculations (comparing this month vs last month)
const incomeTrend = computed(() => {
  const transactions = transactionsStore.transactions;
  const now = new Date();
  const thisMonth = now.getMonth();
  const lastMonth = thisMonth === 0 ? 11 : thisMonth - 1;
  const thisYear = now.getFullYear();
  const lastYear = thisMonth === 0 ? thisYear - 1 : thisYear;

  let thisMonthIncome = 0;
  let lastMonthIncome = 0;

  transactions.forEach((tx) => {
    if (tx.amount > 0) {
      const txDate = new Date(tx.transaction_date);
      if (txDate.getMonth() === thisMonth && txDate.getFullYear() === thisYear) {
        thisMonthIncome += tx.amount;
      } else if (txDate.getMonth() === lastMonth && txDate.getFullYear() === lastYear) {
        lastMonthIncome += tx.amount;
      }
    }
  });

  if (lastMonthIncome === 0) return 0;
  return ((thisMonthIncome - lastMonthIncome) / lastMonthIncome) * 100;
});

const expenseTrend = computed(() => {
  const transactions = transactionsStore.transactions;
  const now = new Date();
  const thisMonth = now.getMonth();
  const lastMonth = thisMonth === 0 ? 11 : thisMonth - 1;
  const thisYear = now.getFullYear();
  const lastYear = thisMonth === 0 ? thisYear - 1 : thisYear;

  let thisMonthExpense = 0;
  let lastMonthExpense = 0;

  transactions.forEach((tx) => {
    if (tx.amount < 0) {
      const txDate = new Date(tx.transaction_date);
      if (txDate.getMonth() === thisMonth && txDate.getFullYear() === thisYear) {
        thisMonthExpense += Math.abs(tx.amount);
      } else if (txDate.getMonth() === lastMonth && txDate.getFullYear() === lastYear) {
        lastMonthExpense += Math.abs(tx.amount);
      }
    }
  });

  if (lastMonthExpense === 0) return 0;
  return ((thisMonthExpense - lastMonthExpense) / lastMonthExpense) * 100;
});

// Compact number formatting
function formatCompact(amount: number): string {
  const abs = Math.abs(amount);
  if (abs >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (abs >= 1000) {
    return (amount / 1000).toFixed(1) + 'K';
  }
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}
</script>

<style scoped>
.financial-snapshot {
  border-radius: 12px;
  border-color: #B2DFDB;
  background: white;
}

.snapshot-grid {
  display: flex;
  align-items: stretch;
}

.snapshot-col {
  flex: 1;
  text-align: center;
  padding: 4px 8px;
  cursor: default;
}

.snapshot-col:last-child {
  cursor: pointer;
}

.snapshot-divider {
  margin: 0 4px;
  background: #ECEFF1;
}

.snapshot-label {
  font-size: 11px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.snapshot-value {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
}

.snapshot-trend {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
  font-size: 10px;
  margin-top: 2px;
}

/* Colors */
.text-positive { color: #2E7D32; }
.text-negative { color: #C62828; }
.text-teal { color: #00796B; }
.text-teal-9 { color: #004D40; }
.text-amber-9 { color: #FF6F00; }
</style>
