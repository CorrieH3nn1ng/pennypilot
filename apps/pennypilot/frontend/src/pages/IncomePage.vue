<template>
  <q-page class="income-page" padding>
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5 page-title">Income Sources</div>
      <q-btn class="add-btn" icon="add" label="Add Income" @click="openAddDialog" />
    </div>

    <!-- Summary Cards -->
    <div class="row q-col-gutter-md q-mb-md" v-if="summary">
      <div class="col-6 col-md-3">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Monthly Gross</div>
            <div class="text-h6 text-teal">{{ formatRands(summary.total_monthly_gross) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Annual Gross</div>
            <div class="text-h6">{{ formatRands(summary.total_annual_gross) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card class="summary-card tax-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Monthly Tax Provision</div>
            <div class="text-h6 text-warning">{{ formatRands(summary.total_monthly_tax || 0) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Est. Annual Tax</div>
            <div class="text-h6 text-warning">{{ formatRands(summary.total_annual_tax || 0) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Projected Income Bar -->
    <q-card v-if="summary && summary.total_annual_gross > 0" class="income-projection-card q-mb-lg">
      <q-card-section class="q-pa-md">
        <div class="row items-center q-mb-sm">
          <div class="text-subtitle2 text-grey-8">Projected Annual Income</div>
          <q-space />
          <div class="text-caption text-grey-6">SARS 2025/26</div>
        </div>
        <q-linear-progress
          :value="1"
          size="24px"
          color="teal-8"
          class="income-bar"
          rounded
        >
          <div class="absolute-full flex flex-center">
            <div class="text-white text-weight-bold">{{ formatRands(summary.total_annual_gross) }}</div>
          </div>
        </q-linear-progress>
        <div class="row q-mt-sm text-caption">
          <div class="col text-grey-7">
            Net After Tax: <span class="text-teal text-weight-medium">{{ formatRands(summary.total_annual_gross - (summary.total_annual_tax || 0)) }}</span>
          </div>
          <div class="col text-right text-grey-7">
            Effective Rate: <span class="text-weight-medium">{{ effectiveRate }}%</span>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <!-- Empty State -->
    <q-card v-else-if="incomeSources.length === 0" class="income-list-card">
      <q-card-section class="text-center text-grey">
        <q-icon name="payments" size="48px" class="q-mb-sm empty-icon" />
        <div>No income sources yet</div>
        <q-btn class="add-btn q-mt-md" label="Add Your First Income" @click="openAddDialog" />
      </q-card-section>
    </q-card>

    <!-- Income List -->
    <q-card v-else class="income-list-card">
      <q-list separator>
        <q-item v-for="income in incomeSources" :key="income.id" clickable @click="editIncome(income)" class="income-item">
          <q-item-section avatar>
            <q-avatar class="income-avatar" text-color="white">
              <q-icon :name="getIncomeIcon(income.type)" />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-weight-medium">
              {{ income.name }}
              <q-badge v-if="income.client_id" color="teal-8" class="q-ml-sm" dense>
                {{ getClientName(income.client_id) || 'Client' }}
              </q-badge>
              <q-badge v-if="income.invoices_count" color="grey-6" class="q-ml-sm" dense>
                {{ income.invoices_count }} inv
              </q-badge>
              <q-badge v-if="!income.is_active" color="grey" class="q-ml-sm" dense>
                Inactive
              </q-badge>
            </q-item-label>
            <q-item-label caption>
              {{ getIncomeTypeLabel(income.type) }} · {{ getFrequencyLabel(income.frequency) }}
            </q-item-label>
            <!-- Income Projection Bar -->
            <div class="income-source-bar q-mt-xs" v-if="income.is_active && summary">
              <q-linear-progress
                :value="income.annual_amount / summary.total_annual_gross"
                size="6px"
                color="teal-8"
                rounded
              />
            </div>
          </q-item-section>

          <q-item-section side class="amount-section">
            <q-item-label class="amount-label">
              {{ formatRands(income.monthly_amount) }}/mo
            </q-item-label>
            <q-item-label v-if="income.tax_estimate" caption class="text-warning">
              Tax: {{ formatRands(income.tax_estimate.monthly_tax) }}/mo
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <q-btn flat round icon="more_vert" @click.stop>
              <q-menu>
                <q-list>
                  <q-item clickable v-close-popup @click="editIncome(income)">
                    <q-item-section>Edit</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="toggleActive(income)">
                    <q-item-section>{{ income.is_active ? 'Deactivate' : 'Activate' }}</q-item-section>
                  </q-item>
                  <q-separator />
                  <q-item clickable v-close-popup @click="confirmDelete(income)">
                    <q-item-section class="text-negative">Delete</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-item-section>

          <q-item-section side>
            <q-icon name="chevron_right" color="grey" />
          </q-item-section>
        </q-item>
      </q-list>
    </q-card>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ isEditing ? 'Edit Income Source' : 'Add Income Source' }}</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="formData.name"
            label="Name *"
            outlined
            placeholder="e.g., Main Salary, Freelance Work"
            :rules="[(v) => !!v || 'Name is required']"
          />

          <q-select
            v-model="formData.type"
            :options="incomeTypeOptions"
            label="Type *"
            outlined
            emit-value
            map-options
          />

          <!-- Client Selection (for freelance/contract income) -->
          <q-select
            v-if="showClientOption"
            v-model="formData.client_id"
            :options="clientOptions"
            label="Linked Client (Optional)"
            outlined
            emit-value
            map-options
            clearable
            hint="Link this income to a specific client for tracking"
          >
            <template v-slot:prepend>
              <q-icon name="business" color="teal" />
            </template>
            <template v-slot:no-option>
              <q-item>
                <q-item-section class="text-grey">
                  No clients found. Add clients first.
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <!-- Rate Type for Freelancers -->
          <template v-if="showRateOptions">
            <q-btn-toggle
              v-model="formData.rate_type"
              toggle-color="primary"
              :options="[
                { label: 'Fixed Amount', value: 'fixed' },
                { label: 'Hourly Rate', value: 'hourly' },
                { label: 'Daily Rate', value: 'daily' },
              ]"
              spread
              class="q-mb-md"
            />
          </template>

          <!-- Hourly Rate Input -->
          <template v-if="showRateOptions && formData.rate_type === 'hourly'">
            <div class="row q-gutter-md">
              <CurrencyInput
                v-model="formData.hourly_rate"
                label="Hourly Rate *"
                outlined
                class="col"
                :rules="[(v: number) => v > 0 || 'Rate must be greater than 0']"
              />
              <q-input
                v-model.number="formData.estimated_hours"
                label="Est. Hours/Month *"
                outlined
                type="number"
                class="col"
                hint="e.g., 160 hours = full time"
                :rules="[(v) => v > 0 || 'Hours must be greater than 0']"
              />
            </div>
            <q-banner class="bg-blue-1 q-my-md" rounded dense>
              <template v-slot:avatar>
                <q-icon name="calculate" color="primary" />
              </template>
              Estimated monthly: <strong>R {{ formatMoney(calculatedMonthlyAmount) }}</strong>
            </q-banner>
          </template>

          <!-- Daily Rate Input -->
          <template v-else-if="showRateOptions && formData.rate_type === 'daily'">
            <div class="row q-gutter-md">
              <CurrencyInput
                v-model="formData.daily_rate"
                label="Daily Rate *"
                outlined
                class="col"
                :rules="[(v: number) => v > 0 || 'Rate must be greater than 0']"
              />
              <q-input
                v-model.number="formData.estimated_days"
                label="Est. Days/Month *"
                outlined
                type="number"
                class="col"
                hint="e.g., 20-22 working days"
                :rules="[(v) => v > 0 || 'Days must be greater than 0']"
              />
            </div>
            <q-banner class="bg-blue-1 q-my-md" rounded dense>
              <template v-slot:avatar>
                <q-icon name="calculate" color="primary" />
              </template>
              Estimated monthly: <strong>R {{ formatMoney(calculatedMonthlyAmount) }}</strong>
            </q-banner>
          </template>

          <!-- Fixed Amount Input (for salary or fixed freelance) -->
          <template v-else>
            <CurrencyInput
              v-model="formData.gross_amount"
              label="Gross Amount *"
              outlined
              :rules="[(v: number) => v > 0 || 'Amount must be greater than 0']"
            />
          </template>

          <q-select
            v-model="formData.frequency"
            :options="frequencyOptions"
            label="Frequency *"
            outlined
            emit-value
            map-options
            :hint="showRateOptions ? 'How often you typically invoice/get paid' : undefined"
          />

          <CurrencyInput
            v-model="formData.net_amount"
            label="Net Amount (after deductions)"
            outlined
            hint="Optional - your take-home pay"
          />

          <CurrencyInput
            v-model="formData.tax_amount"
            label="Tax Amount (PAYE/deducted)"
            outlined
            hint="Optional - tax already deducted"
          />

          <q-input
            v-model="formData.notes"
            label="Notes"
            outlined
            type="textarea"
            rows="2"
          />

          <q-toggle v-model="formData.is_active" label="Active" />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            :label="isEditing ? 'Update' : 'Add'"
            :loading="isSaving"
            @click="saveIncome"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useIncomeStore } from '@/stores/income.store';
import { useClientStore } from '@/stores/client.store';
import type { IncomeSource, IncomeSourceType, IncomeFrequency } from '@/types';
import { formatCurrency, formatNumber } from '@/utils/currency';
import CurrencyInput from '@/components/CurrencyInput.vue';

const $q = useQuasar();
const incomeStore = useIncomeStore();
const clientStore = useClientStore();

// State
const showDialog = ref(false);
const isEditing = ref(false);
const isSaving = ref(false);
const editingId = ref<string | null>(null);

const formData = ref({
  name: '',
  type: 'salary' as IncomeSourceType,
  client_id: null as string | null, // Link to client
  gross_amount: 0,
  net_amount: undefined as number | undefined,
  tax_amount: undefined as number | undefined,
  frequency: 'monthly' as IncomeFrequency,
  is_active: true,
  notes: '',
  // For freelancers - UI only fields to help calculate gross_amount
  rate_type: 'fixed' as 'fixed' | 'hourly' | 'daily',
  hourly_rate: 0,
  estimated_hours: 0,
  daily_rate: 0,
  estimated_days: 0,
});

// Computed
const isLoading = computed(() => incomeStore.isLoading);
const incomeSources = computed(() => incomeStore.incomeSources);
const summary = computed(() => incomeStore.summary);

// Effective tax rate for display
const effectiveRate = computed(() => {
  if (!summary.value || summary.value.total_annual_gross <= 0) return '0.0';
  const rate = (summary.value.total_annual_tax / summary.value.total_annual_gross) * 100;
  return rate.toFixed(1);
});

// Show rate options for freelance/contractor types
const showRateOptions = computed(() =>
  ['freelance', 'side_hustle', 'consulting', 'retainer', 'project', 'other'].includes(formData.value.type)
);

// Show client selection for freelance/contract income
const showClientOption = computed(() =>
  ['freelance', 'side_hustle', 'consulting', 'retainer', 'project', 'royalties', 'rental', 'other'].includes(formData.value.type)
);

// Client options for dropdown
const clientOptions = computed(() => clientStore.clientOptions);

// Calculate estimated monthly amount based on rate type
const calculatedMonthlyAmount = computed(() => {
  if (formData.value.rate_type === 'hourly') {
    return formData.value.hourly_rate * formData.value.estimated_hours;
  }
  if (formData.value.rate_type === 'daily') {
    return formData.value.daily_rate * formData.value.estimated_days;
  }
  return formData.value.gross_amount;
});

// Options - Income Types for Provisional Taxpayers
const incomeTypeOptions = [
  { label: 'Salary', value: 'salary' },
  { label: 'Consulting', value: 'consulting' },
  { label: 'Retainer', value: 'retainer' },
  { label: 'Project', value: 'project' },
  { label: 'Royalties', value: 'royalties' },
  { label: 'Freelance/Contract', value: 'freelance' },
  { label: 'Investment', value: 'investment' },
  { label: 'Rental', value: 'rental' },
  { label: 'Side Hustle', value: 'side_hustle' },
  { label: 'Other', value: 'other' },
];

const frequencyOptions = [
  { label: 'Monthly', value: 'monthly' },
  { label: 'Weekly', value: 'weekly' },
  { label: 'Bi-Weekly', value: 'bi_weekly' },
  { label: 'Annual', value: 'annual' },
  { label: 'Irregular', value: 'irregular' },
];

// Helpers - use centralized currency utilities
function formatMoney(amount: number | undefined | null): string {
  return formatNumber(amount);
}

function formatRands(amount: number | undefined | null): string {
  return formatCurrency(amount);
}

function getIncomeIcon(type: IncomeSourceType): string {
  const icons: Record<IncomeSourceType, string> = {
    salary: 'work',
    consulting: 'support_agent',
    retainer: 'handshake',
    project: 'architecture',
    royalties: 'copyright',
    freelance: 'laptop_mac',
    investment: 'trending_up',
    rental: 'home',
    side_hustle: 'lightbulb',
    other: 'attach_money',
  };
  return icons[type] || 'attach_money';
}

function getIncomeTypeLabel(type: IncomeSourceType): string {
  return incomeTypeOptions.find((o) => o.value === type)?.label || type;
}

function getFrequencyLabel(frequency: IncomeFrequency): string {
  return frequencyOptions.find((o) => o.value === frequency)?.label || frequency;
}

function getClientName(clientId: string | null): string | null {
  if (!clientId) return null;
  const client = clientStore.getClientById(clientId);
  return client?.name || null;
}

// Actions
function openAddDialog() {
  isEditing.value = false;
  editingId.value = null;
  formData.value = {
    name: '',
    type: 'salary',
    client_id: null,
    gross_amount: 0,
    net_amount: undefined,
    tax_amount: undefined,
    frequency: 'monthly',
    is_active: true,
    notes: '',
    rate_type: 'fixed',
    hourly_rate: 0,
    estimated_hours: 0,
    daily_rate: 0,
    estimated_days: 0,
  };
  showDialog.value = true;
}

function editIncome(income: IncomeSource) {
  isEditing.value = true;
  editingId.value = income.id;

  // Try to parse rate info from notes
  let rateType: 'fixed' | 'hourly' | 'daily' = 'fixed';
  let hourlyRate = 0;
  let estimatedHours = 0;
  let dailyRate = 0;
  let estimatedDays = 0;
  let cleanNotes = income.notes || '';

  // Parse hourly rate from notes
  const hourlyMatch = cleanNotes.match(/Hourly: R(\d+(?:\.\d+)?)\/(hr|hour?) x (\d+(?:\.\d+)?)(hrs?|hours?)\./i);
  if (hourlyMatch) {
    rateType = 'hourly';
    hourlyRate = parseFloat(hourlyMatch[1]);
    estimatedHours = parseFloat(hourlyMatch[3]);
    cleanNotes = cleanNotes.replace(hourlyMatch[0], '').trim();
  }

  // Parse daily rate from notes
  const dailyMatch = cleanNotes.match(/Daily: R(\d+(?:\.\d+)?)\/(day) x (\d+(?:\.\d+)?)(days?)\./i);
  if (dailyMatch) {
    rateType = 'daily';
    dailyRate = parseFloat(dailyMatch[1]);
    estimatedDays = parseFloat(dailyMatch[3]);
    cleanNotes = cleanNotes.replace(dailyMatch[0], '').trim();
  }

  formData.value = {
    name: income.name,
    type: income.type,
    client_id: income.client_id || null,
    gross_amount: income.gross_amount,
    net_amount: income.net_amount || undefined,
    tax_amount: income.tax_amount || undefined,
    frequency: income.frequency,
    is_active: income.is_active,
    notes: cleanNotes,
    rate_type: rateType,
    hourly_rate: hourlyRate,
    estimated_hours: estimatedHours,
    daily_rate: dailyRate,
    estimated_days: estimatedDays,
  };
  showDialog.value = true;
}

async function saveIncome() {
  // Determine the gross amount based on rate type
  let grossAmount = formData.value.gross_amount;
  let notesWithRate = formData.value.notes || '';

  if (showRateOptions.value) {
    if (formData.value.rate_type === 'hourly') {
      if (formData.value.hourly_rate <= 0 || formData.value.estimated_hours <= 0) {
        $q.notify({ type: 'warning', message: 'Please fill in hourly rate and estimated hours' });
        return;
      }
      grossAmount = calculatedMonthlyAmount.value;
      notesWithRate = `Hourly: R${formData.value.hourly_rate}/hr x ${formData.value.estimated_hours}hrs. ${notesWithRate}`.trim();
    } else if (formData.value.rate_type === 'daily') {
      if (formData.value.daily_rate <= 0 || formData.value.estimated_days <= 0) {
        $q.notify({ type: 'warning', message: 'Please fill in daily rate and estimated days' });
        return;
      }
      grossAmount = calculatedMonthlyAmount.value;
      notesWithRate = `Daily: R${formData.value.daily_rate}/day x ${formData.value.estimated_days}days. ${notesWithRate}`.trim();
    }
  }

  if (!formData.value.name || grossAmount <= 0) {
    $q.notify({ type: 'warning', message: 'Please fill in required fields' });
    return;
  }

  isSaving.value = true;

  try {
    const data = {
      name: formData.value.name,
      type: formData.value.type,
      client_id: formData.value.client_id || undefined,
      gross_amount: grossAmount,
      net_amount: formData.value.net_amount,
      tax_amount: formData.value.tax_amount,
      frequency: formData.value.frequency,
      is_active: formData.value.is_active,
      notes: notesWithRate || undefined,
    };

    if (isEditing.value && editingId.value) {
      await incomeStore.updateIncomeSource(editingId.value, data);
      $q.notify({ type: 'positive', message: 'Income source updated' });
    } else {
      await incomeStore.createIncomeSource(data);
      $q.notify({ type: 'positive', message: 'Income source added' });
    }
    showDialog.value = false;
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to save income source' });
  } finally {
    isSaving.value = false;
  }
}

async function toggleActive(income: IncomeSource) {
  await incomeStore.updateIncomeSource(income.id, { is_active: !income.is_active });
  $q.notify({
    type: 'info',
    message: income.is_active ? 'Income source deactivated' : 'Income source activated',
  });
}

function confirmDelete(income: IncomeSource) {
  $q.dialog({
    title: 'Delete Income Source',
    message: `Are you sure you want to delete "${income.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    const success = await incomeStore.deleteIncomeSource(income.id);
    if (success) {
      $q.notify({ type: 'positive', message: 'Income source deleted' });
    } else {
      $q.notify({ type: 'negative', message: 'Failed to delete income source' });
    }
  });
}

// Lifecycle
onMounted(() => {
  incomeStore.loadIncomeSources();
  clientStore.loadClients(); // Load clients for dropdown
});
</script>

<style scoped>
/* Deep Teal Institutional Branding */
.income-page {
  background: #FAFAFA;
}

.page-title {
  color: #004D40;
  font-weight: 700;
}

.add-btn {
  background: #004D40 !important;
  color: white !important;
  font-weight: 600;
}

/* Summary Cards */
.summary-card {
  border-radius: 12px;
  border: 1px solid #E0E0E0;
}

.tax-card {
  border-left: 3px solid #FF9800;
}

/* Income Projection Card */
.income-projection-card {
  border-radius: 12px;
  border: 1px solid #E0E0E0;
  background: linear-gradient(135deg, #FAFAFA 0%, #E8F5E9 100%);
}

.income-bar {
  border-radius: 8px;
  overflow: hidden;
}

:deep(.income-bar .q-linear-progress__track) {
  background: #E0E0E0 !important;
}

:deep(.income-bar .q-linear-progress__model) {
  background: linear-gradient(90deg, #004D40 0%, #00695C 100%) !important;
}

/* Income List */
.income-list-card {
  border-radius: 12px;
  overflow: hidden;
}

.income-item {
  border-left: 3px solid transparent;
  transition: border-color 0.2s;
}

.income-item:hover {
  border-left-color: #004D40;
  background: #F5F5F5;
}

.income-avatar {
  background: #004D40 !important;
  font-weight: 600;
}

/* Per-source income bar */
.income-source-bar {
  max-width: 150px;
}

:deep(.income-source-bar .q-linear-progress__model) {
  background: #004D40 !important;
}

.amount-section {
  text-align: right;
  min-width: 110px;
}

.amount-label {
  font-weight: 700;
  color: #004D40;
  font-size: 14px;
}

.empty-icon {
  color: #004D40 !important;
  opacity: 0.3;
}

/* Text Colors */
.text-teal {
  color: #004D40 !important;
}

/* Dialog Toolbar */
:deep(.bg-primary) {
  background: #004D40 !important;
}
</style>
