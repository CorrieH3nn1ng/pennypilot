<template>
  <q-card class="methodology-card" flat bordered>
    <!-- 50/30/20 Rule: Three Progress Bars -->
    <template v-if="methodology === '50-30-20'">
      <q-card-section class="q-pb-sm">
        <div class="section-header">
          <q-icon name="pie_chart" size="20px" color="teal-8" />
          <span class="section-title">50/30/20 Budget</span>
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <!-- Needs (50%) -->
        <div class="bucket-row">
          <div class="bucket-header">
            <span class="bucket-label">Needs</span>
            <span class="bucket-values">
              R{{ formatAmount(actualNeeds) }} / R{{ formatAmount(targetNeeds) }}
            </span>
          </div>
          <q-linear-progress
            :value="needsProgress"
            :color="needsProgress > 1 ? 'negative' : 'teal-8'"
            track-color="teal-2"
            size="12px"
            rounded
          />
          <div class="bucket-percent" :class="needsProgress > 1 ? 'text-negative' : ''">
            {{ Math.round(needsProgress * 100) }}% of 50%
          </div>
        </div>

        <!-- Wants (30%) -->
        <div class="bucket-row">
          <div class="bucket-header">
            <span class="bucket-label">Wants</span>
            <span class="bucket-values">
              R{{ formatAmount(actualWants) }} / R{{ formatAmount(targetWants) }}
            </span>
          </div>
          <q-linear-progress
            :value="wantsProgress"
            :color="wantsProgress > 1 ? 'negative' : 'amber-8'"
            track-color="amber-2"
            size="12px"
            rounded
          />
          <div class="bucket-percent" :class="wantsProgress > 1 ? 'text-negative' : ''">
            {{ Math.round(wantsProgress * 100) }}% of 30%
          </div>
        </div>

        <!-- Savings (20%) -->
        <div class="bucket-row">
          <div class="bucket-header">
            <span class="bucket-label">Savings</span>
            <span class="bucket-values">
              R{{ formatAmount(actualSavings) }} / R{{ formatAmount(targetSavings) }}
            </span>
          </div>
          <q-linear-progress
            :value="savingsProgress"
            :color="savingsProgress >= 1 ? 'positive' : 'blue-8'"
            track-color="blue-2"
            size="12px"
            rounded
          />
          <div class="bucket-percent" :class="savingsProgress >= 1 ? 'text-positive' : ''">
            {{ Math.round(savingsProgress * 100) }}% of 20%
          </div>
        </div>
      </q-card-section>
    </template>

    <!-- Zero-Based: Unallocated Counter -->
    <template v-else-if="methodology === 'zero-based'">
      <q-card-section class="q-pb-sm">
        <div class="section-header">
          <q-icon name="assignment" size="20px" color="teal-8" />
          <span class="section-title">Zero-Based Budget</span>
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none text-center">
        <div class="zero-based-display">
          <div class="unallocated-label">Rands Unallocated</div>
          <div
            class="unallocated-amount"
            :class="{
              'text-positive': unallocated === 0,
              'text-warning': unallocated > 0,
              'text-negative': unallocated < 0
            }"
          >
            R {{ formatAmount(Math.abs(unallocated)) }}
            <span v-if="unallocated < 0" class="overbudget-tag">OVER</span>
          </div>
          <div class="zero-target-hint">
            <q-icon
              :name="unallocated === 0 ? 'check_circle' : 'info'"
              :color="unallocated === 0 ? 'positive' : 'grey-6'"
              size="16px"
            />
            <span>{{ unallocated === 0 ? 'Perfect! Every rand has a job.' : 'Allocate until this reaches R0' }}</span>
          </div>

          <q-linear-progress
            :value="allocationProgress"
            :color="unallocated === 0 ? 'positive' : unallocated < 0 ? 'negative' : 'amber'"
            track-color="grey-3"
            size="8px"
            rounded
            class="q-mt-md"
          />
          <div class="allocation-summary q-mt-xs">
            R{{ formatAmount(totalAllocated) }} allocated of R{{ formatAmount(totalIncome) }}
          </div>
        </div>
      </q-card-section>
    </template>

    <!-- Profit First: Profit Taken Highlight -->
    <template v-else-if="methodology === 'profit-first'">
      <q-card-section class="q-pb-sm">
        <div class="section-header">
          <q-icon name="trending_up" size="20px" color="teal-8" />
          <span class="section-title">Profit First</span>
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <!-- Primary Metric: Profit Taken -->
        <div class="profit-highlight">
          <div class="profit-icon">
            <q-icon name="savings" color="white" size="28px" />
          </div>
          <div class="profit-content">
            <div class="profit-label">Profit Taken This Month</div>
            <div class="profit-amount">R {{ formatAmount(profitTaken) }}</div>
            <div class="profit-target">Target: R{{ formatAmount(targetProfit) }} (10%)</div>
          </div>
          <q-circular-progress
            :value="profitProgress * 100"
            size="56px"
            :thickness="0.15"
            :color="profitProgress >= 1 ? 'positive' : 'amber'"
            track-color="grey-3"
            class="profit-gauge"
          >
            <span class="gauge-text">{{ Math.round(profitProgress * 100) }}%</span>
          </q-circular-progress>
        </div>

        <!-- Secondary Metrics -->
        <div class="profit-buckets q-mt-md">
          <div class="pf-bucket">
            <div class="pf-bucket-label">Tax Reserve</div>
            <div class="pf-bucket-value">R{{ formatAmount(taxReserve) }}</div>
            <div class="pf-bucket-target">15% target</div>
          </div>
          <div class="pf-bucket">
            <div class="pf-bucket-label">Owner Pay</div>
            <div class="pf-bucket-value">R{{ formatAmount(ownerPay) }}</div>
            <div class="pf-bucket-target">5% target</div>
          </div>
          <div class="pf-bucket">
            <div class="pf-bucket-label">OpEx</div>
            <div class="pf-bucket-value">R{{ formatAmount(opex) }}</div>
            <div class="pf-bucket-target">70% target</div>
          </div>
        </div>
      </q-card-section>
    </template>

    <!-- Fallback -->
    <template v-else>
      <q-card-section>
        <div class="text-center text-grey-6">
          <q-icon name="info" size="32px" />
          <div class="q-mt-sm">No budget methodology set</div>
        </div>
      </q-card-section>
    </template>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useBudgetStore } from '@/stores/budget.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import type { BudgetMethodology } from '@/types';

const budgetStore = useBudgetStore();
const transactionsStore = useTransactionsStore();

// Current methodology from budget
const methodology = computed<BudgetMethodology | null>(() => {
  return budgetStore.currentBudget?.methodology || null;
});

// Total income for the period
const totalIncome = computed(() => {
  return budgetStore.currentBudget?.total_income || 0;
});

// Net available after tax provision
const netAvailable = computed(() => {
  return budgetStore.currentBudget?.net_available || 0;
});

// =========================================
// 50/30/20 Calculations
// =========================================
const targetNeeds = computed(() => netAvailable.value * 0.5);
const targetWants = computed(() => netAvailable.value * 0.3);
const targetSavings = computed(() => netAvailable.value * 0.2);

// Actual spending by bucket (from transactions categorized)
const actualNeeds = computed(() => {
  // For now, use bucket totals from budget store if available
  const buckets = budgetStore.bucketTotals;
  return buckets?.needs?.actual || 0;
});

const actualWants = computed(() => {
  const buckets = budgetStore.bucketTotals;
  return buckets?.wants?.actual || 0;
});

const actualSavings = computed(() => {
  const buckets = budgetStore.bucketTotals;
  return buckets?.savings?.actual || 0;
});

const needsProgress = computed(() => targetNeeds.value > 0 ? actualNeeds.value / targetNeeds.value : 0);
const wantsProgress = computed(() => targetWants.value > 0 ? actualWants.value / targetWants.value : 0);
const savingsProgress = computed(() => targetSavings.value > 0 ? actualSavings.value / targetSavings.value : 0);

// =========================================
// Zero-Based Calculations
// =========================================
const totalAllocated = computed(() => {
  return budgetStore.currentBudget?.total_planned || 0;
});

const unallocated = computed(() => {
  return netAvailable.value - totalAllocated.value;
});

const allocationProgress = computed(() => {
  return netAvailable.value > 0 ? totalAllocated.value / netAvailable.value : 0;
});

// =========================================
// Profit First Calculations
// =========================================
const targetProfit = computed(() => netAvailable.value * 0.10);
const targetTax = computed(() => netAvailable.value * 0.15);
const targetOwnerPay = computed(() => netAvailable.value * 0.05);
const targetOpex = computed(() => netAvailable.value * 0.70);

// For now, simulate profit taken (would come from actual transfers/savings)
const profitTaken = computed(() => {
  // This would ideally come from actual savings transactions
  // For now, use the savings bucket actual
  const buckets = budgetStore.bucketTotals;
  return buckets?.savings?.actual || 0;
});

const taxReserve = computed(() => {
  return budgetStore.currentBudget?.tax_provision || 0;
});

const ownerPay = computed(() => {
  // Would come from owner draw transactions
  return targetOwnerPay.value; // Placeholder
});

const opex = computed(() => {
  // Total expenses excluding profit/tax/owner
  return Math.abs(transactionsStore.totalExpenses);
});

const profitProgress = computed(() => {
  return targetProfit.value > 0 ? profitTaken.value / targetProfit.value : 0;
});

// =========================================
// Formatting
// =========================================
function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}
</script>

<style scoped>
.methodology-card {
  border-radius: 12px;
  border-color: #B2DFDB;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #004D40;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* 50/30/20 Styles */
.bucket-row {
  margin-bottom: 16px;
}

.bucket-row:last-child {
  margin-bottom: 0;
}

.bucket-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.bucket-label {
  font-size: 13px;
  font-weight: 600;
  color: #37474F;
}

.bucket-values {
  font-size: 12px;
  color: #78909C;
}

.bucket-percent {
  font-size: 11px;
  color: #90A4AE;
  margin-top: 4px;
  text-align: right;
}

/* Zero-Based Styles */
.zero-based-display {
  padding: 16px 0;
}

.unallocated-label {
  font-size: 12px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.unallocated-amount {
  font-size: 36px;
  font-weight: 700;
  line-height: 1;
}

.overbudget-tag {
  font-size: 12px;
  background: #FFEBEE;
  color: #C62828;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 8px;
  vertical-align: middle;
}

.zero-target-hint {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 12px;
  color: #78909C;
}

.allocation-summary {
  font-size: 11px;
  color: #90A4AE;
}

/* Profit First Styles */
.profit-highlight {
  display: flex;
  align-items: center;
  gap: 16px;
  background: linear-gradient(135deg, #004D40 0%, #00796B 100%);
  border-radius: 12px;
  padding: 16px;
  color: white;
}

.profit-icon {
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.profit-content {
  flex: 1;
}

.profit-label {
  font-size: 11px;
  opacity: 0.8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.profit-amount {
  font-size: 28px;
  font-weight: 700;
  line-height: 1.2;
}

.profit-target {
  font-size: 12px;
  opacity: 0.7;
}

.profit-gauge {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.gauge-text {
  font-size: 12px;
  font-weight: 600;
  color: white;
}

.profit-buckets {
  display: flex;
  gap: 8px;
}

.pf-bucket {
  flex: 1;
  background: #FAFAFA;
  border-radius: 8px;
  padding: 12px;
  text-align: center;
}

.pf-bucket-label {
  font-size: 10px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.pf-bucket-value {
  font-size: 16px;
  font-weight: 600;
  color: #37474F;
  margin: 4px 0;
}

.pf-bucket-target {
  font-size: 10px;
  color: #B0BEC5;
}

/* Color utilities */
.text-positive { color: #2E7D32; }
.text-negative { color: #C62828; }
.text-warning { color: #F57C00; }
</style>
