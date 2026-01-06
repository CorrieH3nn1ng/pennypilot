<template>
  <q-page class="tax-page q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 tax-header">Tax Settings</div>
      <q-space />
      <q-badge color="deep-teal" class="q-pa-xs">
        2025/26 Tax Year
      </q-badge>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <template v-else>
      <!-- Tax Year Summary Card -->
      <q-card class="q-mb-md" v-if="currentYearSummary">
        <q-card-section>
          <div class="row items-center q-mb-md">
            <div class="text-subtitle1 text-weight-medium">
              Tax Year {{ currentYearSummary.tax_year }}
            </div>
            <q-space />
            <q-badge color="teal" class="q-pa-xs">
              SARS 2025/26 Rates
            </q-badge>
          </div>
          <!-- Primary Metrics Row -->
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3">
              <div class="metric-card">
                <div class="metric-label">Total Income</div>
                <div class="metric-value text-positive">{{ formatRands(currentYearSummary.total_income) }}</div>
                <div class="metric-sub">{{ incomeTransactionCount }} income transaction{{ incomeTransactionCount !== 1 ? 's' : '' }}</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="metric-card">
                <div class="metric-label">Est. Annual Tax</div>
                <div class="metric-value text-negative">{{ formatRands(currentYearSummary.estimated_annual_tax) }}</div>
                <div v-if="medicalAid.enabled && medicalCredit.annualCredit > 0" class="metric-sub text-positive">
                  Includes {{ formatRands(medicalCredit.annualCredit) }} credit
                </div>
                <div v-else class="metric-sub">After {{ formatRands(primaryRebate) }} rebate</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="metric-card metric-highlight">
                <div class="metric-label">Set Aside Monthly</div>
                <div class="metric-value text-white">{{ formatRands(monthlySetAside) }}</div>
                <div class="metric-sub text-white-70">To stay SARS-compliant</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="metric-card">
                <div class="metric-label">Effective Rate</div>
                <div class="metric-value">{{ currentYearSummary.effective_rate }}%</div>
                <div class="metric-sub">On taxable income</div>
              </div>
            </div>
          </div>

          <!-- Annual Projection Banner -->
          <div class="projection-banner q-mt-md">
            <q-icon name="trending_up" size="sm" class="q-mr-sm" />
            <span>
              <strong>Projected Annual:</strong> {{ formatRands(projectedAnnualIncome) }}
              ({{ formatRands(currentYearSummary.total_income) }} × 12 months)
            </span>
          </div>
        </q-card-section>
      </q-card>

      <!-- Next Provisional Payment Alert -->
      <q-card v-if="nextProvisionalPayment && isFreelancer" class="q-mb-md bg-warning text-dark">
        <q-card-section>
          <div class="row items-center">
            <q-icon name="warning" size="md" class="q-mr-md" />
            <div class="col">
              <div class="text-weight-medium">Next Provisional Tax Payment</div>
              <div class="text-caption">
                R {{ formatMoney(nextProvisionalPayment.amount) }} due {{ formatDate(nextProvisionalPayment.due_date) }}
              </div>
            </div>
            <q-btn color="dark" label="Mark as Paid" @click="showPaymentDialog = true" />
          </div>
        </q-card-section>
      </q-card>

      <!-- Settings Card -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Employment Settings</div>
          <div class="row q-gutter-md">
            <q-select
              v-model="settings.employment_type"
              :options="employmentOptions"
              label="Employment Type"
              outlined
              emit-value
              map-options
              class="col-12 col-sm-4"
              @update:model-value="saveSettings"
            />

            <q-input
              v-model="settings.tax_number"
              label="Tax Reference Number"
              outlined
              class="col-12 col-sm-4"
              @blur="saveSettings"
            />

            <q-toggle
              v-model="settings.provisional_tax_enabled"
              label="Track Provisional Tax"
              class="col-12 col-sm-4"
              @update:model-value="saveSettings"
            />
          </div>

          <div class="row q-gutter-md q-mt-md">
            <q-toggle
              v-model="settings.vat_registered"
              label="VAT Registered"
              class="col-12 col-sm-4"
              @update:model-value="saveSettings"
            />

            <q-input
              v-if="settings.vat_registered"
              v-model="settings.vat_number"
              label="VAT Number"
              outlined
              class="col-12 col-sm-4"
              @blur="saveSettings"
            />
          </div>
        </q-card-section>
      </q-card>

      <!-- Medical Aid Tax Credit (Pro Feature) -->
      <q-card class="q-mb-md medical-card">
        <q-card-section>
          <div class="row items-center q-mb-md">
            <div class="text-subtitle1 text-weight-medium">Medical Aid Tax Credit</div>
            <q-space />
            <q-badge color="deep-purple" class="q-pa-xs">PRO</q-badge>
          </div>

          <div class="row items-center q-mb-md">
            <q-toggle
              v-model="medicalAid.enabled"
              label="I have Medical Aid"
              color="teal"
            />
          </div>

          <div v-if="medicalAid.enabled" class="medical-settings">
            <!-- Dependents Counter -->
            <div class="row items-center q-mb-md">
              <div class="col">
                <div class="text-body2 text-weight-medium">Dependents</div>
                <div class="text-caption text-grey">Excluding yourself (main member)</div>
              </div>
              <div class="col-auto">
                <div class="dependent-counter">
                  <q-btn
                    round
                    flat
                    dense
                    icon="remove"
                    color="grey"
                    :disable="medicalAid.dependents <= 0"
                    @click="medicalAid.dependents = Math.max(0, medicalAid.dependents - 1)"
                  />
                  <div class="dependent-value">{{ medicalAid.dependents }}</div>
                  <q-btn
                    round
                    flat
                    dense
                    icon="add"
                    class="add-btn"
                    @click="medicalAid.dependents++"
                  />
                </div>
              </div>
            </div>

            <!-- Employer Contribution -->
            <div class="employer-section q-mb-md">
              <div class="row items-center">
                <q-toggle
                  v-model="medicalAid.employerContributes"
                  label="Employer Contributes to Medical Aid"
                  color="teal"
                  class="employer-toggle"
                />
              </div>
              <div v-if="medicalAid.employerContributes" class="row items-center q-mt-sm q-gutter-sm">
                <q-input
                  v-model.number="medicalAid.employerPercentage"
                  type="number"
                  label="Employer Contribution"
                  suffix="%"
                  outlined
                  dense
                  class="employer-input"
                  :rules="[val => val >= 0 && val <= 100 || 'Must be 0-100%']"
                />
                <div class="text-caption text-grey q-ml-sm">
                  You pay {{ 100 - medicalAid.employerPercentage }}% of premiums
                </div>
              </div>
            </div>

            <!-- Credit Breakdown -->
            <div class="credit-breakdown">
              <div class="breakdown-row">
                <span>Main Member</span>
                <span>{{ formatRands(medicalCreditRates.mainMember) }}/month</span>
              </div>
              <div v-if="medicalAid.dependents >= 1" class="breakdown-row">
                <span>1st Dependent</span>
                <span>{{ formatRands(medicalCreditRates.firstDependant) }}/month</span>
              </div>
              <div v-if="medicalAid.dependents > 1" class="breakdown-row">
                <span>{{ medicalAid.dependents - 1 }} Additional Dependent{{ medicalAid.dependents > 2 ? 's' : '' }}</span>
                <span>{{ formatRands((medicalAid.dependents - 1) * medicalCreditRates.additionalDependants) }}/month</span>
              </div>
              <q-separator class="q-my-sm" />
              <div class="breakdown-row total">
                <span>Monthly Credit</span>
                <span class="text-positive">{{ formatRands(medicalCredit.monthlyCredit) }}</span>
              </div>
              <div class="breakdown-row total">
                <span>Annual Credit</span>
                <span class="text-positive text-weight-bold">{{ formatRands(medicalCredit.annualCredit) }}</span>
              </div>
              <div v-if="medicalAid.employerContributes" class="breakdown-note q-mt-sm">
                <q-icon name="info" size="xs" color="grey" class="q-mr-xs" />
                <span class="text-caption text-grey">
                  Credit applies in full regardless of employer contribution
                </span>
              </div>
            </div>

            <!-- Pro Tip: Savings -->
            <div v-if="medicalCredit.annualCredit > 0 && !medicalAid.employerContributes" class="pro-tip q-mt-md">
              <q-icon name="tips_and_updates" color="amber" size="sm" class="q-mr-sm" />
              <span>
                <strong>Pro Tip:</strong> Your Medical Aid is saving you
                <strong class="text-positive">{{ formatRands(medicalCredit.annualCredit) }}</strong> in tax this year.
                That's <strong class="text-positive">{{ formatRands(medicalCredit.monthlyCredit) }}</strong> back in your pocket every month!
              </span>
            </div>

            <!-- Pro Tip: Employer Contribution IRP5 Warning -->
            <div v-if="medicalAid.employerContributes" class="pro-tip irp5-tip q-mt-md">
              <q-icon name="assignment" color="deep-purple" size="sm" class="q-mr-sm" />
              <span>
                <strong>IRP5 Note:</strong> Since your employer contributes {{ medicalAid.employerPercentage }}%,
                ensure your IRP5 reflects this as a <strong>fringe benefit</strong> (Code 3810/4474)
                so your Year-End SARS filing matches PennyPilot's forecast.
                <br /><br />
                <span class="text-caption">
                  Your full <strong class="text-positive">{{ formatRands(medicalCredit.annualCredit) }}</strong> credit still applies!
                </span>
              </span>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Accountant Intelligence Section -->
      <AccountantReviewSection />

      <!-- Tax Calculator -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Tax Calculator</div>
          <div class="row q-gutter-md">
            <q-input
              v-model.number="calcAmount"
              label="Amount"
              outlined
              type="number"
              :prefix="configStore.currencySymbol"
              class="col-12 col-sm-4"
            />
            <q-select
              v-model="calcType"
              :options="[
                { label: 'Annual', value: 'annual' },
                { label: 'Monthly', value: 'monthly' },
              ]"
              label="Type"
              outlined
              emit-value
              map-options
              class="col-12 col-sm-3"
            />
            <q-btn
              color="deep-teal"
              label="Calculate"
              :loading="isCalculating"
              @click="calculateTax"
              class="self-center calculate-btn"
            />
          </div>

          <!-- Calculation Result -->
          <div v-if="calcResult" class="q-mt-md">
            <q-separator class="q-mb-md" />
            <div class="row q-gutter-md">
              <div class="col">
                <div class="text-caption text-grey">Tax Payable</div>
                <div class="text-h6 text-negative">R {{ formatMoney(getCalcTaxPayable) }}</div>
              </div>
              <div class="col">
                <div class="text-caption text-grey">Effective Rate</div>
                <div class="text-h6">{{ getCalcEffectiveRate }}%</div>
              </div>
              <div class="col" v-if="calcType === 'monthly'">
                <div class="text-caption text-grey">Net After Tax</div>
                <div class="text-h6 text-positive">R {{ formatMoney(getCalcNetAfterTax) }}</div>
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Tax Brackets -->
      <q-card>
        <q-card-section>
          <div class="row items-center q-mb-md">
            <div class="text-subtitle1 text-weight-medium">SA Tax Brackets</div>
            <q-space />
            <q-badge v-if="taxYearInfo" color="primary">{{ taxYearInfo.tax_year }}</q-badge>
          </div>
          <q-list separator dense>
            <q-item v-for="(bracket, index) in taxBrackets" :key="index">
              <q-item-section>
                <q-item-label>{{ bracket.description }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </template>

    <!-- Mark Payment Dialog -->
    <q-dialog v-model="showPaymentDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Record Provisional Payment</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="paymentDate"
            label="Payment Date"
            outlined
            type="date"
          />
          <q-input
            v-model="paymentNotes"
            label="Notes (optional)"
            outlined
            type="textarea"
            rows="2"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Record Payment" @click="recordPayment" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useTaxStore } from '@/stores/tax.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useConfigStore } from '@/stores/config.store';
import { useIncomeStore } from '@/stores/income.store';
import { taxGapService, type TaxCalculationResult } from '@/services/intelligence/TaxGapService';
import AccountantReviewSection from '@/components/AccountantReviewSection.vue';
import type { AnnualTaxCalculation, MonthlyProvisionCalculation } from '@/types';

const $q = useQuasar();
const taxStore = useTaxStore();
const transactionsStore = useTransactionsStore();
const incomeStore = useIncomeStore();
const configStore = useConfigStore();

// Local tax calculation state
const localTaxResult = ref<TaxCalculationResult | null>(null);

// State
const showPaymentDialog = ref(false);
const paymentDate = ref(new Date().toISOString().split('T')[0]);
const paymentNotes = ref('');
const calcAmount = ref(0);
const calcType = ref<'annual' | 'monthly'>('annual');
const calcResult = ref<AnnualTaxCalculation | MonthlyProvisionCalculation | null>(null);
const isCalculating = ref(false);

const settings = reactive({
  employment_type: 'employed' as 'employed' | 'self_employed' | 'mixed',
  tax_number: '',
  vat_registered: false,
  vat_number: '',
  provisional_tax_enabled: false,
});

// Medical Aid state (Pro Feature)
const medicalAid = reactive({
  enabled: false,
  dependents: 0, // Excluding main member
  employerContributes: false,
  employerPercentage: 50, // Default 50%
});

// Computed - Transactions-based income calculation
const totalIncomeFromTransactions = computed(() => {
  return transactionsStore.transactions
    .filter(t => Number(t.amount) > 0 && !t.is_transfer)
    .reduce((sum, t) => sum + Number(t.amount), 0);
});

// Projected annual income (extrapolate from current data)
const projectedAnnualIncome = computed(() => {
  // For now, multiply by 12 to annualize (assuming monthly transactions)
  // In a real scenario, we'd look at date ranges
  const monthlyIncome = totalIncomeFromTransactions.value;
  // Assume seed data represents one month
  return monthlyIncome * 12;
});

// Local tax calculation (reactive to transactions)
const localTaxCalculation = computed(() => {
  const income = projectedAnnualIncome.value;
  if (income <= 0) return null;
  return taxGapService.calculateTax({ annualIncome: income });
});

// Tax Year Summary - use local calculation or fall back to store
const currentYearSummary = computed(() => {
  const localCalc = localTaxCalculation.value;

  // Return local calculation if we have transactions
  if (localCalc && totalIncomeFromTransactions.value > 0) {
    // Apply medical credit (non-refundable)
    const taxBeforeCredit = localCalc.taxPayable;
    const medicalCreditAmount = medicalCredit.value.annualCredit;
    const finalTax = Math.max(0, taxBeforeCredit - medicalCreditAmount);
    const income = projectedAnnualIncome.value;
    const effectiveRate = income > 0 ? (finalTax / income) * 100 : 0;

    return {
      tax_year: taxGapService.taxYear,
      total_income: totalIncomeFromTransactions.value,
      total_provision: 0, // User-managed
      estimated_annual_tax: finalTax,
      tax_before_credit: taxBeforeCredit,
      medical_credit: medicalCreditAmount,
      effective_rate: Number(effectiveRate.toFixed(1)),
      provisional_payments: null,
    };
  }

  // Fall back to store data
  return taxStore.currentYearSummary;
});

// Tax brackets - use local service
const taxBrackets = computed(() => {
  return taxGapService.getFormattedBrackets();
});

// Tax year info - use local service
const taxYearInfo = computed(() => ({
  tax_year: `Tax Year ${taxGapService.taxYear}`,
  effective_date: taxGapService.effectiveDate,
}));

// Income transaction count
const incomeTransactionCount = computed(() => {
  return transactionsStore.transactions
    .filter(t => Number(t.amount) > 0 && !t.is_transfer)
    .length;
});

// Primary rebate for display
const primaryRebate = computed(() => taxGapService.getPrimaryRebate());

// Medical Credit calculation (Pro Feature)
const medicalCreditRates = computed(() => taxGapService.getMedicalCreditRates());

const medicalCredit = computed(() => {
  if (!medicalAid.enabled) {
    return { monthlyCredit: 0, annualCredit: 0, breakdown: { mainMember: 0, firstDep: 0, additionalDeps: 0 } };
  }
  return taxGapService.calculateMedicalTaxCredit(medicalAid.dependents);
});

// Tax after medical credit (non-refundable - cap at 0)
const taxAfterMedicalCredit = computed(() => {
  const localCalc = localTaxCalculation.value;
  if (!localCalc) return 0;
  const taxBeforeCredit = localCalc.taxPayable;
  const credit = medicalCredit.value.annualCredit;
  return Math.max(0, taxBeforeCredit - credit); // Non-refundable: cap at 0
});

// Effective rate after medical credit
const effectiveRateAfterCredit = computed(() => {
  const income = projectedAnnualIncome.value;
  if (income <= 0) return 0;
  return (taxAfterMedicalCredit.value / income) * 100;
});

// Monthly Set Aside calculation (Est. Annual Tax - Medical Credit) / 12
const monthlySetAside = computed(() => {
  return taxAfterMedicalCredit.value / 12;
});

// Other computed
const isLoading = computed(() => taxStore.isLoading && transactionsStore.transactions.length === 0);
const isFreelancer = computed(() => taxStore.isFreelancer);
const nextProvisionalPayment = computed(() => taxStore.nextProvisionalPayment);

const getCalcTaxPayable = computed(() => {
  if (!calcResult.value) return 0;
  if ('tax_payable' in calcResult.value) return calcResult.value.tax_payable;
  if ('monthly_provision' in calcResult.value) return calcResult.value.monthly_provision;
  return 0;
});

const getCalcEffectiveRate = computed(() => {
  if (!calcResult.value) return 0;
  return calcResult.value.effective_rate;
});

const getCalcNetAfterTax = computed(() => {
  if (!calcResult.value) return 0;
  if ('net_after_provision' in calcResult.value) return calcResult.value.net_after_provision;
  return 0;
});

// Options
const employmentOptions = [
  { label: 'Employed (PAYE)', value: 'employed' },
  { label: 'Self-Employed / Freelancer', value: 'self_employed' },
  { label: 'Mixed (Both)', value: 'mixed' },
];

// Helpers
function formatMoney(amount: number): string {
  return amount.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Format currency in high-precision institutional format: R 78 500,00
 */
function formatRands(amount: number): string {
  const num = Number(amount) || 0;
  // Use South African locale with proper spacing
  const formatted = Math.abs(num).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  // Add R prefix with space, and handle negatives
  return num < 0 ? `-R ${formatted}` : `R ${formatted}`;
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

// Actions
async function saveSettings() {
  await taxStore.updateSettings({
    employment_type: settings.employment_type,
    tax_number: settings.tax_number || undefined,
    vat_registered: settings.vat_registered,
    vat_number: settings.vat_number || undefined,
    provisional_tax_enabled: settings.provisional_tax_enabled,
  });
}

async function calculateTax() {
  if (calcAmount.value <= 0) {
    $q.notify({ type: 'warning', message: 'Please enter an amount' });
    return;
  }

  isCalculating.value = true;

  try {
    // Use local TaxGapService for instant calculation (no API call needed)
    const amount = calcType.value === 'monthly' ? calcAmount.value * 12 : calcAmount.value;
    const result = taxGapService.calculateTax({ annualIncome: amount });

    if (calcType.value === 'annual') {
      calcResult.value = {
        taxable_income: result.taxableIncome,
        tax_payable: result.taxPayable,
        effective_rate: Number((result.effectiveRate * 100).toFixed(1)),
        marginal_rate: Number((result.marginalRate * 100).toFixed(0)),
      } as AnnualTaxCalculation;
    } else {
      calcResult.value = {
        annual_income: amount,
        taxable_income: result.taxableIncome,
        annual_tax: result.taxPayable,
        monthly_provision: result.monthlyTax,
        net_after_provision: calcAmount.value - result.monthlyTax,
        effective_rate: Number((result.effectiveRate * 100).toFixed(1)),
        provision_rate: Number((result.effectiveRate * 100).toFixed(1)),
      } as MonthlyProvisionCalculation;
    }
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to calculate tax' });
  } finally {
    isCalculating.value = false;
  }
}

async function recordPayment() {
  // TODO: Implement payment recording
  $q.notify({ type: 'info', message: 'Payment recorded - Coming soon!' });
  showPaymentDialog.value = false;
}

// Lifecycle
onMounted(async () => {
  // Load transactions first (for reactive tax calculation)
  if (transactionsStore.transactions.length === 0) {
    await transactionsStore.loadFromLocal();
  }

  // Then load tax settings from server
  await taxStore.loadAll();

  // Populate settings from store
  if (taxStore.settings) {
    settings.employment_type = taxStore.settings.employment_type;
    settings.tax_number = taxStore.settings.tax_number || '';
    settings.vat_registered = taxStore.settings.vat_registered;
    settings.vat_number = taxStore.settings.vat_number || '';
    settings.provisional_tax_enabled = taxStore.settings.provisional_tax_enabled;
  }
});

// Watch for transaction changes to recalculate tax
watch(
  () => transactionsStore.transactions.length,
  () => {
    // Tax calculation is reactive via computed properties
    console.log('Transactions updated, recalculating tax...');
  }
);
</script>

<style scoped>
/* Deep Teal Branding */
.tax-page {
  background: #FAFAFA;
}

.tax-header {
  color: #004D40;
  font-weight: 700;
}

/* Metric Cards */
.metric-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  border: 1px solid #E0E0E0;
  text-align: center;
}

.metric-card.metric-highlight {
  background: #004D40;
  border-color: #004D40;
}

.metric-label {
  font-size: 11px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.metric-highlight .metric-label {
  color: rgba(255, 255, 255, 0.8);
}

.metric-value {
  font-size: 20px;
  font-weight: 700;
  color: #263238;
  line-height: 1.2;
}

.metric-sub {
  font-size: 11px;
  color: #90A4AE;
  margin-top: 4px;
}

.text-white-70 {
  color: rgba(255, 255, 255, 0.7) !important;
}

/* Projection Banner */
.projection-banner {
  background: #E0F2F1;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  color: #004D40;
  font-size: 13px;
}

/* Calculate Button */
.calculate-btn {
  background: #004D40 !important;
  min-height: 44px;
  font-weight: 600;
}

/* Text Colors */
.text-positive {
  color: #2E7D32 !important;
}

.text-negative {
  color: #C62828 !important;
}

/* Deep Teal color class for Quasar */
:deep(.bg-deep-teal) {
  background: #004D40 !important;
}

:deep(.text-deep-teal) {
  color: #004D40 !important;
}

/* Medical Aid Card */
.medical-card {
  border-radius: 12px;
  border: 1px solid #E0E0E0;
}

.medical-settings {
  padding: 16px;
  background: #FAFAFA;
  border-radius: 8px;
}

/* Dependent Counter */
.dependent-counter {
  display: flex;
  align-items: center;
  gap: 8px;
  background: white;
  border-radius: 24px;
  padding: 4px 8px;
  border: 2px solid #004D40;
}

.dependent-value {
  font-size: 20px;
  font-weight: 700;
  color: #004D40;
  min-width: 32px;
  text-align: center;
}

.add-btn {
  color: #004D40 !important;
}

/* Credit Breakdown */
.credit-breakdown {
  background: white;
  border-radius: 8px;
  padding: 12px;
  margin-top: 12px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 13px;
  color: #546E7A;
}

.breakdown-row.total {
  font-weight: 600;
  color: #263238;
}

/* Pro Tip */
.pro-tip {
  background: #FFF8E1;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  align-items: flex-start;
  font-size: 13px;
  color: #5D4037;
  line-height: 1.5;
}

.pro-tip strong.text-positive {
  color: #2E7D32;
}

/* Employer Contribution Section */
.employer-section {
  background: #E8F5E9;
  border-radius: 8px;
  padding: 12px;
  border-left: 3px solid #004D40;
}

.employer-toggle :deep(.q-toggle__label) {
  font-weight: 500;
}

.employer-input {
  max-width: 140px;
}

.employer-input :deep(.q-field__control) {
  border-color: #004D40;
}

.breakdown-note {
  display: flex;
  align-items: center;
}

/* IRP5 Pro Tip */
.pro-tip.irp5-tip {
  background: #EDE7F6;
  border-left: 3px solid #7C4DFF;
}

.pro-tip.irp5-tip strong {
  color: #4527A0;
}
</style>
