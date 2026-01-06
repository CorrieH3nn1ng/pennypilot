<template>
  <div class="tax-dashboard">
    <!-- Tax Gap Insight Card -->
    <q-card v-if="gapAnalysis" class="insight-card q-mb-md" flat bordered>
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
              <span class="text-weight-medium text-teal-9">Penny's Tax Insight</span>
              <q-chip
                :color="statusColor"
                text-color="white"
                size="sm"
                dense
                class="q-ml-sm"
              >
                {{ statusLabel }}
              </q-chip>
            </div>

            <div class="text-subtitle2 text-grey-9">
              {{ fuzzyInsight }}
            </div>
          </div>
        </div>
      </q-card-section>

      <!-- Confidence Bar -->
      <q-linear-progress
        :value="gapAnalysis.confidence"
        color="teal"
        track-color="teal-2"
        size="4px"
      />
    </q-card>

    <!-- Tax Summary Card -->
    <q-card class="summary-card q-mb-md" flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium text-teal-9 q-mb-md">
          <q-icon name="receipt" class="q-mr-sm" />
          Tax Summary
        </div>

        <div class="row q-col-gutter-md">
          <!-- Annual Income -->
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-7">Annual Income</div>
            <div class="text-h6">R {{ formatAmount(taxResult?.grossIncome || 0) }}</div>
          </div>

          <!-- Taxable Income -->
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-7">Taxable Income</div>
            <div class="text-h6">R {{ formatAmount(taxResult?.taxableIncome || 0) }}</div>
          </div>

          <!-- Estimated Tax -->
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-7">Estimated Tax</div>
            <div class="text-h6 text-negative">R {{ formatAmount(taxResult?.taxPayable || 0) }}</div>
          </div>

          <!-- Monthly Tax -->
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-7">Monthly Tax</div>
            <div class="text-h6 text-negative">R {{ formatAmount(taxResult?.monthlyTax || 0) }}</div>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Tax Provision Gap -->
    <q-card v-if="gapAnalysis" class="gap-card q-mb-md" flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium text-teal-9 q-mb-md">
          <q-icon name="trending_up" class="q-mr-sm" />
          Tax Provision Gap
        </div>

        <div class="row items-center q-mb-md">
          <div class="col">
            <div class="text-caption text-grey-7">Current Provision</div>
            <div class="text-h6">R {{ formatAmount(gapAnalysis.currentProvision) }}/year</div>
          </div>
          <div class="col text-center">
            <q-icon
              :name="gapIcon"
              :color="statusColor"
              size="32px"
            />
          </div>
          <div class="col text-right">
            <div class="text-caption text-grey-7">Estimated Tax</div>
            <div class="text-h6">R {{ formatAmount(gapAnalysis.estimatedTax) }}/year</div>
          </div>
        </div>

        <!-- Gap Progress Bar -->
        <div class="q-mb-sm">
          <div class="row justify-between q-mb-xs">
            <span class="text-caption">Provision Progress</span>
            <span class="text-caption text-weight-medium">
              {{ Math.round(provisionPercentage) }}%
            </span>
          </div>
          <q-linear-progress
            :value="Math.min(1, provisionPercentage / 100)"
            :color="statusColor"
            track-color="grey-3"
            size="12px"
            rounded
          />
        </div>

        <!-- Gap Amount -->
        <div class="text-center q-mt-md">
          <q-chip
            :color="gapAnalysis.gap > 0 ? 'negative' : gapAnalysis.gap < 0 ? 'positive' : 'grey'"
            text-color="white"
            size="md"
          >
            <q-icon :name="gapAnalysis.gap > 0 ? 'arrow_downward' : 'arrow_upward'" class="q-mr-xs" />
            Gap: R {{ formatAmount(Math.abs(gapAnalysis.gap)) }}
            {{ gapAnalysis.gap > 0 ? 'under' : gapAnalysis.gap < 0 ? 'over' : '' }}
          </q-chip>
        </div>
      </q-card-section>
    </q-card>

    <!-- Tax Breakdown -->
    <q-card class="breakdown-card q-mb-md" flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium text-teal-9 q-mb-md">
          <q-icon name="pie_chart" class="q-mr-sm" />
          Tax Breakdown
        </div>

        <q-list separator>
          <q-item>
            <q-item-section>
              <q-item-label>Tax Before Rebates</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label>R {{ formatAmount(taxResult?.taxBeforeRebates || 0) }}</q-item-label>
            </q-item-section>
          </q-item>

          <q-item>
            <q-item-section>
              <q-item-label>Primary Rebate</q-item-label>
              <q-item-label caption>All taxpayers</q-item-label>
            </q-item-section>
            <q-item-section side class="text-positive">
              <q-item-label>-R {{ formatAmount(taxResult?.primaryRebate || 0) }}</q-item-label>
            </q-item-section>
          </q-item>

          <q-item v-if="taxResult?.secondaryRebate">
            <q-item-section>
              <q-item-label>Secondary Rebate</q-item-label>
              <q-item-label caption>Age 65+</q-item-label>
            </q-item-section>
            <q-item-section side class="text-positive">
              <q-item-label>-R {{ formatAmount(taxResult.secondaryRebate) }}</q-item-label>
            </q-item-section>
          </q-item>

          <q-item v-if="taxResult?.tertiaryRebate">
            <q-item-section>
              <q-item-label>Tertiary Rebate</q-item-label>
              <q-item-label caption>Age 75+</q-item-label>
            </q-item-section>
            <q-item-section side class="text-positive">
              <q-item-label>-R {{ formatAmount(taxResult.tertiaryRebate) }}</q-item-label>
            </q-item-section>
          </q-item>

          <q-separator />

          <q-item>
            <q-item-section>
              <q-item-label class="text-weight-bold">Tax Payable</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label class="text-weight-bold text-negative">
                R {{ formatAmount(taxResult?.taxPayable || 0) }}
              </q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>

    <!-- Tax Rates Info -->
    <q-card class="rates-card" flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-medium text-teal-9 q-mb-md">
          <q-icon name="info" class="q-mr-sm" />
          Your Tax Rates
        </div>

        <div class="row q-col-gutter-md">
          <div class="col-6">
            <div class="text-caption text-grey-7">Marginal Rate</div>
            <div class="text-h5 text-teal-8">
              {{ Math.round((taxResult?.marginalRate || 0) * 100) }}%
            </div>
            <div class="text-caption text-grey-6">Rate on next R1 earned</div>
          </div>

          <div class="col-6">
            <div class="text-caption text-grey-7">Effective Rate</div>
            <div class="text-h5 text-teal-8">
              {{ ((taxResult?.effectiveRate || 0) * 100).toFixed(1) }}%
            </div>
            <div class="text-caption text-grey-6">Overall tax rate</div>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type {
  TaxCalculationResult,
  TaxGapAnalysis,
} from '@/services/intelligence/TaxGapService';
import { taxGapService } from '@/services/intelligence/TaxGapService';

const props = defineProps<{
  annualIncome: number;
  annualDeductions?: number;
  age?: number;
  monthlyProvision?: number;
}>();

// Calculate tax result
const taxResult = computed<TaxCalculationResult | null>(() => {
  if (!props.annualIncome) return null;

  return taxGapService.calculateTax({
    annualIncome: props.annualIncome,
    annualDeductions: props.annualDeductions || 0,
    age: props.age || 30,
  });
});

// Analyze tax gap
const gapAnalysis = computed<TaxGapAnalysis | null>(() => {
  if (!taxResult.value || !props.monthlyProvision) return null;

  return taxGapService.analyzeTaxGap(
    taxResult.value.taxPayable,
    props.monthlyProvision,
    12
  );
});

// Get fuzzy insight
const fuzzyInsight = computed(() => {
  if (!gapAnalysis.value) return '';
  return taxGapService.getFuzzyInsight(gapAnalysis.value);
});

// Provision percentage
const provisionPercentage = computed(() => {
  if (!gapAnalysis.value || gapAnalysis.value.estimatedTax === 0) return 0;
  return (gapAnalysis.value.currentProvision / gapAnalysis.value.estimatedTax) * 100;
});

// Status color
const statusColor = computed(() => {
  if (!gapAnalysis.value) return 'grey';
  switch (gapAnalysis.value.status) {
    case 'on-track':
      return 'positive';
    case 'under':
      return 'warning';
    case 'over':
      return 'info';
    default:
      return 'grey';
  }
});

// Status label
const statusLabel = computed(() => {
  if (!gapAnalysis.value) return '';
  switch (gapAnalysis.value.status) {
    case 'on-track':
      return 'On Track';
    case 'under':
      return 'Under-provisioned';
    case 'over':
      return 'Over-provisioned';
    default:
      return '';
  }
});

// Gap icon
const gapIcon = computed(() => {
  if (!gapAnalysis.value) return 'remove';
  switch (gapAnalysis.value.status) {
    case 'on-track':
      return 'check_circle';
    case 'under':
      return 'warning';
    case 'over':
      return 'savings';
    default:
      return 'remove';
  }
});

// Format amount
function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<style scoped>
/* Deep Teal institutional styling per CLAUDE.md */
.insight-card {
  background-color: #E0F2F1; /* teal-1 */
  border-radius: 12px;
  border-color: #B2DFDB; /* teal-2 */
  overflow: hidden;
}

.summary-card,
.gap-card,
.breakdown-card,
.rates-card {
  border-radius: 12px;
}

.summary-card {
  border-left: 4px solid #004D40;
}

.gap-card {
  border-left: 4px solid #00796B;
}

.breakdown-card {
  border-left: 4px solid #009688;
}

.rates-card {
  border-left: 4px solid #4DB6AC;
}
</style>
