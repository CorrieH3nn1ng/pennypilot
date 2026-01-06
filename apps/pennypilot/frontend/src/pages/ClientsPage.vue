<template>
  <q-page class="clients-page" padding>
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5 page-title">Clients</div>
      <q-btn
        class="add-btn"
        icon="add"
        label="Add Client"
        @click="openDialog()"
      />
    </div>

    <!-- VAT Watchdog Alert -->
    <q-banner v-if="vatWarningClients.length > 0" class="vat-warning q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="warning" color="warning" />
      </template>
      <div class="text-weight-medium">VAT Registration Threshold Alert</div>
      <div class="text-caption" v-for="client in vatWarningClients" :key="client.id">
        <strong>{{ client.name }}</strong>: {{ formatRands(client.total_revenue || 0) }}
        ({{ Math.round(((client.total_revenue || 0) / VAT_THRESHOLD) * 100) }}% of R1M threshold)
      </div>
      <template v-slot:action>
        <q-btn flat label="Learn More" color="warning" @click="showVatInfo = true" />
      </template>
    </q-banner>

    <!-- Summary -->
    <div class="row q-col-gutter-md q-mb-lg" v-if="summary">
      <div class="col-4">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Total Clients</div>
            <div class="text-h6">{{ summary.total_count }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-4">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Active</div>
            <div class="text-h6 text-positive">{{ summary.active_count }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-4">
        <q-card class="summary-card">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-grey-7">Total Revenue</div>
            <div class="text-h6 text-teal">{{ formatRands(totalRevenue) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Client List -->
    <q-card class="client-list-card">
      <q-list separator v-if="clients.length > 0">
        <q-item v-for="client in clients" :key="client.id" clickable @click="openDialog(client)" class="client-item">
          <q-item-section avatar>
            <q-avatar class="client-avatar" text-color="white">
              {{ getInitials(client.name) }}
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-weight-medium">
              {{ client.name }}
              <q-badge v-if="!client.is_active" color="grey" class="q-ml-sm">
                Inactive
              </q-badge>
              <q-badge v-if="isNearVatThreshold(client)" color="warning" class="q-ml-sm">
                VAT Alert
              </q-badge>
            </q-item-label>
            <q-item-label caption>
              Code: {{ client.client_code }}
              <span v-if="client.payment_day"> · Pay: {{ client.payment_day }}th</span>
            </q-item-label>
          </q-item-section>

          <q-item-section side class="revenue-section">
            <q-item-label class="revenue-amount">
              {{ formatRands(client.total_revenue || 0) }}
            </q-item-label>
            <q-item-label caption>
              {{ client.invoices_count || 0 }} inv · {{ client.income_sources_count || 0 }} sources
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <q-icon name="chevron_right" color="grey" />
          </q-item-section>
        </q-item>
      </q-list>

      <q-card-section v-else class="text-center text-grey">
        <q-icon name="people" size="48px" class="q-mb-sm empty-icon" />
        <div>No clients yet</div>
        <q-btn
          class="add-btn q-mt-md"
          label="Add First Client"
          @click="openDialog()"
        />
      </q-card-section>
    </q-card>

    <!-- Add/Edit Dialog - Full screen on mobile -->
    <q-dialog v-model="dialogVisible" :maximized="$q.screen.lt.sm" transition-show="slide-up" transition-hide="slide-down">
      <q-card :style="$q.screen.lt.sm ? '' : 'width: 500px; max-width: 90vw'">
        <!-- Header -->
        <q-toolbar class="bg-primary text-white">
          <q-btn flat round dense icon="close" v-close-popup />
          <q-toolbar-title>{{ editingClient ? 'Edit Client' : 'Add Client' }}</q-toolbar-title>
          <q-btn flat :label="editingClient ? 'Save' : 'Add'" :loading="isLoading" @click="handleSubmit" />
        </q-toolbar>

        <!-- Scrollable content -->
        <q-scroll-area :style="$q.screen.lt.sm ? 'height: calc(100vh - 50px)' : 'max-height: 70vh'">
          <div class="q-pa-md">
            <!-- Basic Info -->
            <div class="text-subtitle2 text-grey-7 q-mb-sm">Basic Information</div>
            <q-input
              v-model="formData.name"
              label="Client Name *"
              filled
              dense
              class="q-mb-sm"
              :rules="[val => !!val || 'Name is required']"
            />
            <q-input
              v-model="formData.email"
              label="Email"
              type="email"
              filled
              dense
              class="q-mb-sm"
            />
            <q-input
              v-model="formData.phone"
              label="Phone"
              filled
              dense
              class="q-mb-sm"
            />
            <q-input
              v-model="formData.contact_person"
              label="Contact Person"
              filled
              dense
              class="q-mb-sm"
            />
            <q-input
              v-model="formData.address"
              label="Address"
              type="textarea"
              filled
              dense
              rows="2"
              class="q-mb-md"
            />

            <!-- Billing Settings -->
            <q-separator class="q-my-md" />
            <div class="text-subtitle2 text-grey-7 q-mb-xs">Billing & Payment</div>
            <div class="text-caption text-grey q-mb-sm">
              Auto-fill invoice dates when AI can't extract them
            </div>

            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6">
                <q-input
                  v-model.number="formData.billing_period_start"
                  label="Period Start"
                  type="number"
                  filled
                  dense
                  hint="Day (1-31)"
                />
              </div>
              <div class="col-6">
                <q-input
                  v-model.number="formData.billing_period_end"
                  label="Period End"
                  type="number"
                  filled
                  dense
                  hint="Empty = last day"
                />
              </div>
            </div>

            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6">
                <q-input
                  v-model.number="formData.payment_day"
                  label="Payment Day"
                  type="number"
                  filled
                  dense
                  hint="Day of month"
                />
              </div>
              <div class="col-6">
                <q-input
                  v-model.number="formData.payment_terms"
                  label="Terms (days)"
                  type="number"
                  filled
                  dense
                  hint="Days from invoice"
                />
              </div>
            </div>

            <div class="row q-col-gutter-sm q-mb-md">
              <div class="col-6">
                <CurrencyInput
                  v-model="formData.default_rate"
                  label="Default Rate"
                  filled
                  dense
                />
              </div>
              <div class="col-6">
                <q-select
                  v-model="formData.rate_type"
                  label="Rate Type"
                  :options="rateTypeOptions"
                  filled
                  dense
                  emit-value
                  map-options
                  clearable
                />
              </div>
            </div>

            <!-- Notes -->
            <q-separator class="q-my-md" />
            <q-input
              v-model="formData.notes"
              label="Notes"
              type="textarea"
              filled
              dense
              rows="2"
              class="q-mb-sm"
            />

            <q-toggle
              v-model="formData.is_active"
              label="Active"
            />

            <!-- Delete button for existing clients -->
            <template v-if="editingClient && editingClient.invoices_count === 0">
              <q-separator class="q-my-md" />
              <q-btn
                flat
                color="negative"
                label="Delete Client"
                icon="delete"
                class="full-width"
                @click="handleDelete"
              />
            </template>
          </div>
        </q-scroll-area>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useClientStore } from '@/stores/client.store';
import { useUserStore } from '@/stores/user.store';
import type { Client, RateType } from '@/types';
import { formatCurrency } from '@/utils/currency';
import CurrencyInput from '@/components/CurrencyInput.vue';

const $q = useQuasar();
const clientStore = useClientStore();
const userStore = useUserStore();

// Persona-based visibility - only self-employed users track VAT threshold
const showVatThreshold = computed(() => userStore.showVatThreshold);

// VAT Registration Threshold (South Africa)
const VAT_THRESHOLD = 1000000; // R1,000,000
const VAT_WARNING_THRESHOLD = 0.8; // Warn at 80% (R800,000)

// Options
const rateTypeOptions = [
  { label: 'Hourly', value: 'hourly' },
  { label: 'Daily', value: 'daily' },
  { label: 'Fixed', value: 'fixed' },
];

// State
const dialogVisible = ref(false);
const editingClient = ref<Client | null>(null);
const showVatInfo = ref(false);
const formData = ref({
  name: '',
  email: '',
  contact_person: '',
  phone: '',
  address: '',
  notes: '',
  billing_period_start: 1 as number | null,
  billing_period_end: null as number | null,
  payment_day: null as number | null,
  payment_terms: null as number | null,
  default_rate: null as number | null,
  rate_type: null as RateType | null,
  is_active: true,
});

// Computed
const clients = computed(() => clientStore.clients);
const summary = computed(() => clientStore.summary);
const isLoading = computed(() => clientStore.isLoading);

// Total revenue across all clients
const totalRevenue = computed(() => {
  return clients.value.reduce((sum, c) => sum + (c.total_revenue || 0), 0);
});

// Clients approaching or exceeding VAT threshold (only for self-employed)
const vatWarningClients = computed(() => {
  if (!showVatThreshold.value) return [];
  return clients.value.filter(c =>
    (c.total_revenue || 0) >= VAT_THRESHOLD * VAT_WARNING_THRESHOLD
  );
});

// Check if single client is near VAT threshold (only for self-employed)
function isNearVatThreshold(client: Client): boolean {
  if (!showVatThreshold.value) return false;
  return (client.total_revenue || 0) >= VAT_THRESHOLD * VAT_WARNING_THRESHOLD;
}

// Get initials for avatar
function getInitials(name: string): string {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();
}

// Format currency in institutional format
function formatRands(amount: number): string {
  return formatCurrency(amount);
}

// Methods
function resetForm() {
  formData.value = {
    name: '',
    email: '',
    contact_person: '',
    phone: '',
    address: '',
    notes: '',
    billing_period_start: 1,
    billing_period_end: null,
    payment_day: null,
    payment_terms: null,
    default_rate: null,
    rate_type: null,
    is_active: true,
  };
}

function openDialog(client?: Client) {
  if (client) {
    editingClient.value = client;
    formData.value = {
      name: client.name,
      email: client.email || '',
      contact_person: client.contact_person || '',
      phone: client.phone || '',
      address: client.address || '',
      notes: client.notes || '',
      billing_period_start: client.billing_period_start || 1,
      billing_period_end: client.billing_period_end,
      payment_day: client.payment_day,
      payment_terms: client.payment_terms,
      default_rate: client.default_rate,
      rate_type: client.rate_type,
      is_active: client.is_active,
    };
  } else {
    editingClient.value = null;
    resetForm();
  }
  dialogVisible.value = true;
}

async function handleSubmit() {
  if (!formData.value.name) {
    $q.notify({ type: 'negative', message: 'Client name is required' });
    return;
  }

  let result;
  if (editingClient.value) {
    result = await clientStore.updateClient(editingClient.value.id, formData.value);
  } else {
    result = await clientStore.createClient(formData.value);
  }

  if (result) {
    $q.notify({
      type: 'positive',
      message: editingClient.value ? 'Client updated' : 'Client added',
    });
    dialogVisible.value = false;
  } else {
    $q.notify({
      type: 'negative',
      message: 'Failed to save client',
      caption: clientStore.error || 'Please check your input',
    });
  }
}

function handleDelete() {
  if (!editingClient.value) return;

  const client = editingClient.value;
  const invoiceCount = client.invoices_count || 0;
  const incomeSourceCount = client.income_sources_count || 0;
  const hasLinkedData = invoiceCount > 0 || incomeSourceCount > 0;

  // Build warning message
  let message = `Are you sure you want to delete <strong>${client.name}</strong>?`;
  let warningDetails = '';

  if (hasLinkedData) {
    warningDetails = '<br><br><strong class="text-warning">Warning:</strong> This client has linked data:';
    if (invoiceCount > 0) {
      warningDetails += `<br>• ${invoiceCount} invoice${invoiceCount > 1 ? 's' : ''}`;
    }
    if (incomeSourceCount > 0) {
      warningDetails += `<br>• ${incomeSourceCount} income source${incomeSourceCount > 1 ? 's' : ''}`;
    }
    warningDetails += '<br><br>These will be <strong>unlinked</strong> (not deleted) but may require manual cleanup.';
  }

  $q.dialog({
    title: hasLinkedData ? 'Delete Client with Linked Data?' : 'Delete Client',
    message: message + warningDetails,
    html: true,
    cancel: {
      label: 'Cancel',
      flat: true,
    },
    ok: {
      label: hasLinkedData ? 'Delete Anyway' : 'Delete',
      color: 'negative',
    },
    persistent: true,
  }).onOk(async () => {
    const result = await clientStore.deleteClient(client.id);
    if (result) {
      $q.notify({
        type: 'positive',
        message: hasLinkedData
          ? `Client deleted. ${invoiceCount + incomeSourceCount} linked items unlinked.`
          : 'Client deleted',
      });
      dialogVisible.value = false;
    }
  });
}

// Lifecycle
onMounted(async () => {
  await clientStore.loadClients();
});
</script>

<style scoped>
/* Deep Teal Institutional Branding */
.clients-page {
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

/* Client List */
.client-list-card {
  border-radius: 12px;
  overflow: hidden;
}

.client-item {
  border-left: 3px solid transparent;
  transition: border-color 0.2s;
}

.client-item:hover {
  border-left-color: #004D40;
  background: #F5F5F5;
}

.client-avatar {
  background: #004D40 !important;
  font-weight: 600;
  font-size: 14px;
}

.revenue-section {
  text-align: right;
}

.revenue-amount {
  font-weight: 700;
  color: #004D40;
  font-size: 14px;
}

.empty-icon {
  color: #004D40 !important;
  opacity: 0.3;
}

/* VAT Warning Banner */
.vat-warning {
  background: #FFF3E0 !important;
  border-left: 4px solid #FF9800;
}

/* Dialog Toolbar */
:deep(.bg-primary) {
  background: #004D40 !important;
}

/* Text Colors */
.text-teal {
  color: #004D40 !important;
}
</style>
