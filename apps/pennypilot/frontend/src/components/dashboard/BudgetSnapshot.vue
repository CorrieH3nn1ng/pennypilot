<template>
  <div class="budget-snapshot">
    <div v-if="currentBudget" class="snapshot-content">
      <!-- 50/30/20 Visual -->
      <div class="bucket-bars">
        <!-- Needs -->
        <div class="bucket-row">
          <div class="bucket-label">
            <q-icon name="home" size="16px" color="blue" />
            <span>Needs</span>
          </div>
          <div class="bucket-bar-container">
            <div
              class="bucket-bar needs"
              :style="{ width: `${needsPercent}%` }"
            />
            <div class="bucket-target" :style="{ left: '50%' }" />
          </div>
          <div class="bucket-value">{{ needsPercent.toFixed(0) }}%</div>
        </div>

        <!-- Wants -->
        <div class="bucket-row">
          <div class="bucket-label">
            <q-icon name="shopping_bag" size="16px" color="pink" />
            <span>Wants</span>
          </div>
          <div class="bucket-bar-container">
            <div
              class="bucket-bar wants"
              :style="{ width: `${wantsPercent}%` }"
            />
            <div class="bucket-target" :style="{ left: '30%' }" />
          </div>
          <div class="bucket-value">{{ wantsPercent.toFixed(0) }}%</div>
        </div>

        <!-- Savings -->
        <div class="bucket-row">
          <div class="bucket-label">
            <q-icon name="savings" size="16px" color="green" />
            <span>Save</span>
          </div>
          <div class="bucket-bar-container">
            <div
              class="bucket-bar savings"
              :style="{ width: `${savingsPercent}%` }"
            />
            <div class="bucket-target" :style="{ left: '20%' }" />
          </div>
          <div class="bucket-value">{{ savingsPercent.toFixed(0) }}%</div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="snapshot-stats">
        <div class="stat">
          <div class="stat-value">R {{ formatCompact(totalSpent) }}</div>
          <div class="stat-label">Spent</div>
        </div>
        <div class="stat">
          <div class="stat-value text-positive">R {{ formatCompact(remaining) }}</div>
          <div class="stat-label">Remaining</div>
        </div>
        <div class="stat">
          <div class="stat-value">{{ daysLeft }}</div>
          <div class="stat-label">Days Left</div>
        </div>
      </div>

      <!-- Quick Action -->
      <q-btn
        flat
        dense
        color="teal"
        icon="visibility"
        label="View Full Budget"
        to="/budget"
        class="view-budget-btn"
      />
    </div>

    <div v-else class="no-budget">
      <q-icon name="account_balance_wallet" size="32px" color="grey-5" />
      <div class="text-grey-7">No budget set up yet</div>
      <q-btn
        flat
        dense
        color="primary"
        label="Create Budget"
        to="/budget"
        class="q-mt-sm"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useBudgetStore } from '@/stores/budget.store';
import { useIncomeStore } from '@/stores/income.store';

const budgetStore = useBudgetStore();
const incomeStore = useIncomeStore();

const currentBudget = computed(() => budgetStore.currentBudget);
const bucketTotals = computed(() => budgetStore.bucketTotals);
const targetAllocations = computed(() => budgetStore.targetAllocations);
const monthlyIncome = computed(() => incomeStore.totalMonthlyGross);

// Calculate percentages of income spent in each bucket
const needsPercent = computed(() => {
  if (!monthlyIncome.value) return 0;
  return ((bucketTotals.value?.needs.actual || 0) / monthlyIncome.value) * 100;
});

const wantsPercent = computed(() => {
  if (!monthlyIncome.value) return 0;
  return ((bucketTotals.value?.wants.actual || 0) / monthlyIncome.value) * 100;
});

const savingsPercent = computed(() => {
  if (!monthlyIncome.value) return 0;
  return ((bucketTotals.value?.savings.actual || 0) / monthlyIncome.value) * 100;
});

const totalSpent = computed(() => {
  return (
    (bucketTotals.value?.needs.actual || 0) +
    (bucketTotals.value?.wants.actual || 0)
  );
});

const remaining = computed(() => {
  if (!targetAllocations.value) return 0;
  const totalBudget = targetAllocations.value.needs + targetAllocations.value.wants;
  return Math.max(0, totalBudget - totalSpent.value);
});

const daysLeft = computed(() => {
  const now = new Date();
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return endOfMonth.getDate() - now.getDate();
});

function formatCompact(amount: number): string {
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
.budget-snapshot {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.snapshot-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: 100%;
}

.bucket-bars {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bucket-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bucket-label {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 70px;
  font-size: 13px;
  color: #546E7A;
}

.bucket-bar-container {
  flex: 1;
  height: 12px;
  background: #ECEFF1;
  border-radius: 6px;
  position: relative;
  overflow: hidden;
}

.bucket-bar {
  height: 100%;
  border-radius: 6px;
  transition: width 0.3s ease;
}

.bucket-bar.needs {
  background: linear-gradient(90deg, #1976D2, #42A5F5);
}

.bucket-bar.wants {
  background: linear-gradient(90deg, #C2185B, #F06292);
}

.bucket-bar.savings {
  background: linear-gradient(90deg, #388E3C, #66BB6A);
}

.bucket-target {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #263238;
  opacity: 0.3;
}

.bucket-value {
  width: 40px;
  text-align: right;
  font-size: 13px;
  font-weight: 600;
  color: #37474F;
}

.snapshot-stats {
  display: flex;
  justify-content: space-around;
  padding: 12px 0;
  border-top: 1px solid #ECEFF1;
  border-bottom: 1px solid #ECEFF1;
}

.stat {
  text-align: center;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #263238;
}

.stat-label {
  font-size: 11px;
  color: #90A4AE;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.view-budget-btn {
  margin-top: auto;
  align-self: center;
}

.no-budget {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 8px;
}

.text-positive {
  color: #2E7D32;
}
</style>
