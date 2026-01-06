<template>
  <q-page class="invoices-page" padding>
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5 page-title">Invoice Command Center</div>
      <div class="row q-gutter-xs">
        <q-btn
          outline
          dense
          icon="delete_sweep"
          :label="$q.screen.gt.xs ? 'Cleanup' : ''"
          color="negative"
          @click="showCleanupDialog = true"
        />
        <q-btn
          outline
          dense
          icon="upload_file"
          :label="$q.screen.gt.xs ? 'Upload' : ''"
          class="upload-btn"
          @click="showUploadDialog = true"
        />
        <q-btn
          class="add-btn"
          dense
          icon="add"
          :label="$q.screen.gt.xs ? 'New Invoice' : ''"
          :to="{ name: 'invoice-new' }"
        />
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row q-col-gutter-md q-mb-md" v-if="summary">
      <div class="col-12 col-md-4">
        <q-card class="summary-card total-card">
          <q-card-section class="q-pa-md">
            <div class="row items-center">
              <q-icon name="receipt_long" size="32px" class="text-teal q-mr-md" />
              <div>
                <div class="text-caption text-grey-7">Total Invoiced (All Time)</div>
                <div class="text-h5 text-teal">{{ formatRands(totalInvoiced) }}</div>
              </div>
            </div>
            <div class="text-caption text-grey-6 q-mt-xs">{{ summary.total_count }} invoices</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4">
        <q-card class="summary-card awaiting-card">
          <q-card-section class="q-pa-md">
            <div class="row items-center">
              <q-icon name="hourglass_top" size="32px" class="text-orange q-mr-md" />
              <div>
                <div class="text-caption text-grey-7">Awaiting Payment</div>
                <div class="text-h5 text-orange">{{ formatRands(summary.total_outstanding) }}</div>
              </div>
            </div>
            <div class="text-caption text-grey-6 q-mt-xs">
              {{ summary.sent_count + summary.overdue_count }} pending
              <span v-if="summary.overdue_count > 0" class="text-negative">
                ({{ summary.overdue_count }} overdue)
              </span>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4">
        <q-card class="summary-card tax-card">
          <q-card-section class="q-pa-md">
            <div class="row items-center">
              <q-icon name="savings" size="32px" class="text-deep-purple q-mr-md" />
              <div>
                <div class="text-caption text-grey-7">Tax Set-Aside ({{ taxYearLabel }})</div>
                <div class="text-h5 text-deep-purple">{{ formatRands(taxSetAside) }}</div>
              </div>
            </div>
            <div class="text-caption text-grey-6 q-mt-xs">
              39.1% Income Tax on Business Income
            </div>
            <div class="text-caption text-grey-6">
              Business Income: {{ formatRands(businessPaidTotal) }}
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Staged Invoices Alert -->
    <q-banner v-if="stagedCount > 0" class="staged-banner q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="pending_actions" color="warning" />
      </template>
      <div class="text-weight-medium">{{ stagedCount }} Invoice(s) Awaiting Review</div>
      <div class="text-caption">Imported invoices need client assignment</div>
      <template v-slot:action>
        <q-btn flat label="Review" color="warning" @click="showStagedDialog = true" />
      </template>
    </q-banner>

    <!-- Filters -->
    <div class="row q-col-gutter-sm q-mb-md">
      <div class="col-6 col-md-3">
        <q-select
          v-model="filterStatus"
          :options="statusOptions"
          label="Status"
          emit-value
          map-options
          clearable
          dense
          filled
        />
      </div>
      <div class="col-6 col-md-3">
        <q-select
          v-model="filterClient"
          :options="clientOptions"
          label="Client"
          emit-value
          map-options
          clearable
          dense
          filled
        />
      </div>
      <div class="col-12 col-md-6">
        <q-input
          v-model="searchQuery"
          label="Search invoices..."
          dense
          filled
          clearable
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>
    </div>

    <!-- Invoice List -->
    <q-card class="invoice-list-card">
      <!-- List Header -->
      <div class="list-header q-pa-sm row text-caption text-grey-7">
        <div class="col-2">Invoice #</div>
        <div class="col-3">Client</div>
        <div class="col-2">Due Date</div>
        <div class="col-2 text-right">Amount</div>
        <div class="col-2 text-center">Status</div>
        <div class="col-1"></div>
      </div>

      <q-list separator v-if="filteredInvoices.length > 0">
        <q-item
          v-for="invoice in filteredInvoices"
          :key="invoice.id"
          clickable
          :to="{ name: 'invoice-view', params: { id: invoice.id } }"
          class="invoice-item"
          :class="{ 'overdue-item': invoice.status === 'overdue' }"
        >
          <!-- Invoice Number -->
          <q-item-section class="col-2">
            <q-item-label class="text-weight-medium invoice-number">
              {{ invoice.invoice_number }}
            </q-item-label>
            <q-item-label caption>{{ formatDate(invoice.invoice_date) }}</q-item-label>
          </q-item-section>

          <!-- Client -->
          <q-item-section class="col-3">
            <q-item-label>{{ invoice.client?.name || 'Unknown' }}</q-item-label>
            <q-item-label caption v-if="invoice.title">{{ invoice.title }}</q-item-label>
          </q-item-section>

          <!-- Due Date -->
          <q-item-section class="col-2">
            <q-item-label :class="{ 'text-negative': isOverdue(invoice) }">
              {{ formatDate(invoice.due_date) }}
            </q-item-label>
            <q-item-label caption v-if="isOverdue(invoice)" class="text-negative">
              {{ getDaysOverdue(invoice) }} days overdue
            </q-item-label>
          </q-item-section>

          <!-- Amount -->
          <q-item-section class="col-2 text-right">
            <q-item-label class="text-weight-bold amount-label">
              {{ formatRands(invoice.total) }}
            </q-item-label>
            <q-item-label v-if="(invoice.tax_amount ?? 0) > 0" caption>
              VAT: {{ formatRands(invoice.tax_amount) }}
            </q-item-label>
          </q-item-section>

          <!-- Status -->
          <q-item-section class="col-2 text-center">
            <q-badge
              :color="getStatusColor(invoice.status)"
              :class="getStatusClass(invoice.status)"
              class="status-badge"
            >
              {{ getStatusLabel(invoice.status) }}
            </q-badge>
          </q-item-section>

          <!-- Actions -->
          <q-item-section class="col-1" side>
            <q-btn flat round size="sm" icon="more_vert" @click.stop.prevent>
              <q-menu>
                <q-list dense>
                  <q-item clickable v-close-popup :to="{ name: 'invoice-view', params: { id: invoice.id } }">
                    <q-item-section avatar><q-icon name="visibility" size="xs" /></q-item-section>
                    <q-item-section>View</q-item-section>
                  </q-item>
                  <q-item v-if="invoice.status === 'draft'" clickable v-close-popup @click="markAsSent(invoice)">
                    <q-item-section avatar><q-icon name="send" size="xs" /></q-item-section>
                    <q-item-section>Mark Sent</q-item-section>
                  </q-item>
                  <q-item v-if="['sent', 'overdue'].includes(invoice.status)" clickable v-close-popup @click="showPayDialog(invoice)">
                    <q-item-section avatar><q-icon name="payments" size="xs" /></q-item-section>
                    <q-item-section>Mark Paid</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="downloadPdf(invoice)">
                    <q-item-section avatar><q-icon name="picture_as_pdf" size="xs" /></q-item-section>
                    <q-item-section>Download PDF</q-item-section>
                  </q-item>
                  <q-separator />
                  <q-item clickable v-close-popup @click="confirmDeleteInvoice(invoice)">
                    <q-item-section avatar><q-icon name="delete" size="xs" color="negative" /></q-item-section>
                    <q-item-section class="text-negative">Delete</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-item-section>
        </q-item>
      </q-list>

      <q-card-section v-else class="text-center text-grey">
        <q-icon name="receipt_long" size="48px" class="q-mb-sm empty-icon" />
        <div>No invoices found</div>
        <q-btn class="add-btn q-mt-md" label="Create First Invoice" :to="{ name: 'invoice-new' }" />
      </q-card-section>
    </q-card>

    <!-- Mark as Paid Dialog -->
    <q-dialog v-model="payDialogVisible">
      <q-card style="min-width: 350px">
        <q-card-section class="bg-teal text-white">
          <div class="text-h6">Mark Invoice as Paid</div>
        </q-card-section>
        <q-card-section class="q-pt-md">
          <div v-if="selectedInvoice" class="q-mb-md">
            <div class="text-subtitle2">{{ selectedInvoice.invoice_number }}</div>
            <div class="text-h6 text-teal">{{ formatRands(selectedInvoice.total) }}</div>
          </div>
          <q-input
            v-model="payData.paid_date"
            label="Payment Date"
            type="date"
            filled
            dense
            class="q-mb-sm"
          />
          <q-toggle v-model="payData.create_income" label="Add to Income Sources" />

          <!-- Tax Set-Aside Suggestion -->
          <q-banner v-if="selectedInvoice" class="bg-deep-purple-1 q-mt-md" rounded dense>
            <template v-slot:avatar>
              <q-icon name="savings" color="deep-purple" />
            </template>
            <div class="text-caption">
              <strong>Suggested Tax Savings:</strong>
              {{ formatRands(calculateTaxSetAside(selectedInvoice.total)) }}
            </div>
            <div class="text-caption text-grey-7">
              Based on 2025/26 SARS brackets
            </div>
          </q-banner>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn class="add-btn" label="Confirm Payment" @click="confirmPay" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Upload Historical Dialog -->
    <q-dialog v-model="showUploadDialog" :maximized="$q.screen.lt.sm" transition-show="slide-up" transition-hide="slide-down">
      <q-card :style="$q.screen.lt.sm ? '' : 'width: 500px; max-width: 90vw'">
        <q-toolbar class="bg-teal text-white">
          <q-btn flat round dense icon="close" v-close-popup @click="closeUploadDialog" />
          <q-toolbar-title>Upload Invoice</q-toolbar-title>
          <q-btn flat label="Save" :loading="isLoading" @click="handleUploadHistorical" />
        </q-toolbar>

        <q-scroll-area :style="$q.screen.lt.sm ? 'height: calc(100vh - 50px)' : 'max-height: 75vh'">
          <div class="q-pa-md">
            <InvoiceUploader
              ref="uploaderRef"
              @extracted="handleExtracted"
              @file-selected="handleFileSelected"
            />

            <q-separator class="q-my-md" />
            <div class="text-subtitle2 text-grey-7 q-mb-sm">Invoice Details</div>

            <q-select
              v-model="uploadData.client_id"
              :options="clientOptions"
              label="Client *"
              emit-value
              map-options
              filled
              dense
              class="q-mb-sm"
            />

            <q-input
              v-model="uploadData.invoice_number"
              label="Invoice Number"
              filled
              dense
              class="q-mb-sm"
            />

            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6">
                <q-input v-model="uploadData.invoice_date" label="Invoice Date" type="date" filled dense />
              </div>
              <div class="col-6">
                <q-input v-model="uploadData.due_date" label="Due Date" type="date" filled dense />
              </div>
            </div>

            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6">
                <q-input v-model="uploadData.paid_date" label="Paid Date" type="date" filled dense hint="If already paid" />
              </div>
              <div class="col-6">
                <q-input v-model.number="uploadData.total" label="Total" type="number" :prefix="configStore.currencySymbol" filled dense />
              </div>
            </div>

            <q-input v-model="uploadData.title" label="Description" filled dense />
          </div>
        </q-scroll-area>
      </q-card>
    </q-dialog>

    <!-- Staged Invoices Review Dialog -->
    <q-dialog v-model="showStagedDialog" :maximized="$q.screen.lt.sm">
      <q-card :style="$q.screen.lt.sm ? '' : 'width: 600px; max-width: 90vw'">
        <q-toolbar class="bg-warning text-white">
          <q-btn flat round dense icon="close" v-close-popup />
          <q-toolbar-title>Review Staged Invoices</q-toolbar-title>
        </q-toolbar>

        <q-card-section v-if="stagedInvoices.length === 0" class="text-center text-grey">
          <q-icon name="check_circle" size="48px" color="positive" />
          <div class="q-mt-md">All invoices have been reviewed!</div>
        </q-card-section>

        <q-list v-else separator>
          <q-item v-for="staged in stagedInvoices" :key="staged.id">
            <q-item-section>
              <q-item-label>{{ staged.extracted_data?.invoice_number || 'No Invoice #' }}</q-item-label>
              <q-item-label caption>
                {{ staged.matched_client?.name || 'Unmatched Client' }} ·
                {{ formatRands(staged.extracted_data?.total || 0) }}
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="row q-gutter-xs">
                <q-btn size="sm" color="positive" icon="check" @click="approveStaged(staged)" />
                <q-btn size="sm" color="negative" icon="close" @click="rejectStaged(staged)" />
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </q-dialog>

    <!-- Cleanup Dialog -->
    <q-dialog v-model="showCleanupDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-negative text-white">
          <div class="text-h6">Invoice Cleanup</div>
        </q-card-section>
        <q-card-section>
          <p class="q-mb-md">Delete all invoices before a specific date. This action cannot be undone.</p>
          <q-banner class="bg-amber-1 q-mb-md" rounded>
            <template v-slot:avatar>
              <q-icon name="warning" color="amber" />
            </template>
            <div class="text-caption">
              This will permanently delete invoices and their associated files.
            </div>
          </q-banner>
          <q-select
            v-model="cleanupBeforeDate"
            :options="cleanupDateOptions"
            label="Delete invoices dated before"
            emit-value
            map-options
            filled
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="negative"
            icon="delete_sweep"
            label="Delete"
            :loading="isDeleting"
            @click="bulkDeleteInvoices"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useInvoiceStore } from '@/stores/invoice.store';
import { useClientStore } from '@/stores/client.store';
import { useConfigStore } from '@/stores/config.store';
import InvoiceUploader from '@/components/InvoiceUploader.vue';
import { invoiceApi, invoiceIngestionApi, type StagedInvoice } from '@/services/api/invoice.api';
import type { N8nExtractedInvoice } from '@/services/api/n8n.api';
import type { Invoice } from '@/types';

// SARS 2025/26 Tax Constants
const PRIMARY_REBATE = 17235;
const TAX_BRACKETS = [
  { min: 1, max: 237100, rate: 0.18 },
  { min: 237101, max: 370500, rate: 0.26 },
  { min: 370501, max: 512800, rate: 0.31 },
  { min: 512801, max: 673000, rate: 0.36 },
  { min: 673001, max: 857900, rate: 0.39 },
  { min: 857901, max: 1817000, rate: 0.41 },
  { min: 1817001, max: Infinity, rate: 0.45 },
];

const route = useRoute();
const router = useRouter();
const $q = useQuasar();
const invoiceStore = useInvoiceStore();
const clientStore = useClientStore();
const configStore = useConfigStore();

// Refs
const uploaderRef = ref<InstanceType<typeof InvoiceUploader> | null>(null);

// State
const filterStatus = ref<string | null>(null);
const filterClient = ref<string | null>(null);
const searchQuery = ref('');
const payDialogVisible = ref(false);
const selectedInvoice = ref<Invoice | null>(null);
const payData = ref({
  paid_date: new Date().toISOString().split('T')[0],
  create_income: true,
});

const showUploadDialog = ref(false);
const showStagedDialog = ref(false);
const showCleanupDialog = ref(false);
const stagedInvoices = ref<StagedInvoice[]>([]);
const stagedCount = ref(0);
const cleanupBeforeDate = ref('2025-01-01');
const isDeleting = ref(false);

const cleanupDateOptions = [
  { label: 'Before Jan 1, 2025 (2024 data)', value: '2025-01-01' },
  { label: 'Before Jan 1, 2024 (2023 data)', value: '2024-01-01' },
  { label: 'Before Jul 1, 2024 (older than 6 months)', value: '2024-07-01' },
];

const uploadData = ref({
  client_id: '',
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  paid_date: '',
  total: 0,
  title: '',
});

// Computed
const invoices = computed(() => invoiceStore.invoices);
const summary = computed(() => invoiceStore.summary);
const isLoading = computed(() => invoiceStore.isLoading);
const clientOptions = computed(() => clientStore.clientOptions);

// Total invoiced (paid + outstanding)
const totalInvoiced = computed(() => {
  if (!summary.value) return 0;
  return summary.value.total_paid + summary.value.total_outstanding;
});

// Tax set-aside from PAID BUSINESS invoices in current tax year (March-Feb)
const taxSetAside = computed(() => {
  return summary.value?.tax_year?.total_tax_set_aside || 0;
});

const taxYearLabel = computed(() => {
  return summary.value?.tax_year?.label || '2025/26';
});

const businessPaidTotal = computed(() => {
  return summary.value?.tax_year?.business_paid_total || 0;
});

// Filtered invoices
const filteredInvoices = computed(() => {
  let result = invoices.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(inv =>
      inv.invoice_number.toLowerCase().includes(query) ||
      inv.client?.name?.toLowerCase().includes(query) ||
      inv.title?.toLowerCase().includes(query)
    );
  }

  return result;
});

const statusOptions = [
  { label: 'All', value: null },
  { label: 'Draft', value: 'draft' },
  { label: 'Sent', value: 'sent' },
  { label: 'Paid', value: 'paid' },
  { label: 'Overdue', value: 'overdue' },
];

// Methods

// Institutional format: R 00 000,00
function formatRands(amount: number | undefined | null): string {
  const num = Number(amount) || 0;
  const formatted = Math.abs(num).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `R ${formatted}`;
}

function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    draft: 'grey',
    sent: 'blue',
    paid: 'teal-8', // Deep Teal for paid
    overdue: 'red-10', // High-alert red for overdue
    cancelled: 'grey-6',
  };
  return colors[status] || 'grey';
}

function getStatusClass(status: string): string {
  if (status === 'overdue') return 'overdue-badge';
  if (status === 'paid') return 'paid-badge';
  return '';
}

function getStatusLabel(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function isOverdue(invoice: Invoice): boolean {
  if (invoice.status === 'overdue') return true;
  if (['paid', 'cancelled', 'draft'].includes(invoice.status)) return false;
  return new Date(invoice.due_date) < new Date();
}

function getDaysOverdue(invoice: Invoice): number {
  const dueDate = new Date(invoice.due_date);
  const today = new Date();
  const diffTime = today.getTime() - dueDate.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

// Calculate tax set-aside using SARS 2025/26 brackets
function calculateTaxSetAside(amount: number): number {
  if (amount <= 0) return 0;

  // Annualize (assuming this is a monthly equivalent)
  const annualized = amount * 12;

  // Find bracket rate
  let bracketRate = 0.18;
  for (const bracket of TAX_BRACKETS) {
    if (annualized >= bracket.min && annualized <= bracket.max) {
      bracketRate = bracket.rate;
      break;
    }
  }

  // Calculate tax with pro-rated rebate
  const taxBeforeRebate = amount * bracketRate;
  const proRatedRebate = PRIMARY_REBATE / 12;
  return Math.max(0, taxBeforeRebate - proRatedRebate);
}

async function loadInvoices() {
  const filters: Record<string, string> = {};
  if (filterStatus.value) filters.status = filterStatus.value;
  if (filterClient.value) filters.client_id = filterClient.value;
  await invoiceStore.loadInvoices(filters);
}

async function loadStagedInvoices() {
  try {
    const response = await invoiceIngestionApi.getStaged();
    stagedInvoices.value = response.data;
    stagedCount.value = response.count;
  } catch {
    // Silently fail - staged invoices are optional
    stagedCount.value = 0;
  }
}

function showPayDialog(invoice: Invoice) {
  selectedInvoice.value = invoice;
  // Default to invoice date, not today's date
  const invoiceDate = invoice.invoice_date
    ? new Date(invoice.invoice_date).toISOString().split('T')[0]
    : new Date().toISOString().split('T')[0];
  payData.value = { paid_date: invoiceDate, create_income: true };
  payDialogVisible.value = true;
}

async function confirmPay() {
  if (!selectedInvoice.value) return;
  try {
    const result = await invoiceStore.markAsPaid(selectedInvoice.value.id, payData.value);
    if (result) {
      $q.notify({
        type: 'positive',
        message: 'Invoice marked as paid',
        caption: `Suggested savings: ${formatRands(calculateTaxSetAside(selectedInvoice.value.total))}`,
      });
      payDialogVisible.value = false;
    }
  } catch (e) {
    const message = e instanceof Error ? e.message : 'Failed to mark invoice as paid';
    $q.notify({
      type: 'negative',
      message: 'Cannot mark as paid',
      caption: message,
      icon: 'warning',
    });
  }
}

async function markAsSent(invoice: Invoice) {
  const result = await invoiceStore.markAsSent(invoice.id);
  if (result) {
    const taxSuggestion = calculateTaxSetAside(invoice.total);
    $q.notify({
      type: 'info',
      message: 'Invoice marked as sent',
      caption: `Tax set-aside suggestion: ${formatRands(taxSuggestion)}`,
    });
  }
}

async function downloadPdf(invoice: Invoice) {
  // This will be implemented with jsPDF
  $q.notify({
    type: 'info',
    message: 'Generating PDF...',
    caption: invoice.invoice_number,
  });
  // Navigate to PDF generation
  // router.push({ name: 'invoice-pdf', params: { id: invoice.id } });
}

function confirmDeleteInvoice(invoice: Invoice) {
  $q.dialog({
    title: 'Delete Invoice',
    message: `Are you sure you want to delete invoice ${invoice.invoice_number}? This action cannot be undone.`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    await deleteInvoice(invoice);
  });
}

async function deleteInvoice(invoice: Invoice) {
  try {
    await invoiceStore.deleteInvoice(invoice.id);
    $q.notify({ type: 'positive', message: `Invoice ${invoice.invoice_number} deleted` });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to delete invoice' });
  }
}

async function bulkDeleteInvoices() {
  $q.dialog({
    title: 'Confirm Bulk Delete',
    message: `This will permanently delete ALL invoices dated before ${cleanupBeforeDate.value}. Are you absolutely sure?`,
    cancel: true,
    persistent: true,
    ok: {
      label: 'Yes, Delete',
      color: 'negative',
    },
  }).onOk(async () => {
    isDeleting.value = true;
    try {
      const result = await invoiceApi.bulkDeleteBefore(cleanupBeforeDate.value);
      $q.notify({
        type: 'positive',
        message: result.message,
        caption: `${result.deleted_count} invoices removed`,
      });
      showCleanupDialog.value = false;
      await loadInvoices();
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to delete invoices' });
    } finally {
      isDeleting.value = false;
    }
  });
}

async function approveStaged(staged: { id: string }) {
  // Show client selection dialog
  $q.dialog({
    title: 'Select Client',
    message: 'Assign this invoice to a client:',
    options: {
      type: 'radio',
      model: '',
      items: clientOptions.value.map(c => ({ label: c.label, value: c.value })),
    },
    cancel: true,
  }).onOk(async (clientId: string) => {
    try {
      await invoiceIngestionApi.approveStaged(staged.id, { client_id: clientId });
      $q.notify({ type: 'positive', message: 'Invoice approved and imported' });
      await loadStagedInvoices();
      await loadInvoices();
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to approve invoice' });
    }
  });
}

async function rejectStaged(staged: { id: string }) {
  try {
    await invoiceIngestionApi.rejectStaged(staged.id);
    $q.notify({ type: 'info', message: 'Invoice rejected' });
    await loadStagedInvoices();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to reject invoice' });
  }
}

function handleFileSelected(_file: File) {
  // File selected
}

function handleExtracted(data: N8nExtractedInvoice) {
  // Map n8n extracted data to upload form
  if (data.invoice_number) uploadData.value.invoice_number = data.invoice_number;
  if (data.invoice_date) uploadData.value.invoice_date = data.invoice_date;

  // Map 'amount' from n8n to 'total' for upload
  if (data.amount) uploadData.value.total = data.amount;

  // Auto-calculate due_date if not extracted (invoice_date + 30 days)
  if (data.due_date) {
    uploadData.value.due_date = data.due_date;
  } else if (data.invoice_date) {
    const invoiceDate = new Date(data.invoice_date);
    invoiceDate.setDate(invoiceDate.getDate() + 30);
    uploadData.value.due_date = invoiceDate.toISOString().split('T')[0];
  }

  // Client matching: try multiple strategies in order of reliability
  let matchedClient: { value: string; label: string } | null = null;

  // Strategy 1: Match by client code in invoice number (most reliable for user's own invoices)
  if (data.invoice_number) {
    matchedClient = findClientByInvoiceNumber(data.invoice_number);
  }

  // Strategy 2: Match by extracted client name/email
  if (!matchedClient && data.client_name) {
    matchedClient = findMatchingClient(data.client_name, data.client_email);
  }

  if (matchedClient) {
    uploadData.value.client_id = matchedClient.value;
    $q.notify({
      type: 'info',
      message: `Matched to client: ${matchedClient.label}`,
      icon: 'person',
    });
  }
}

/**
 * Find client by matching client_code in invoice number
 * Invoice number formats: INV-{client_code}-{seq}, {client_code}-{seq}, etc.
 */
function findClientByInvoiceNumber(invoiceNumber: string): { value: string; label: string } | null {
  const clients = clientStore.clients;
  if (!clients.length) return null;

  const invLower = invoiceNumber.toLowerCase();

  for (const client of clients) {
    if (!client.client_code) continue;
    const codeLower = client.client_code.toLowerCase();

    // Check if invoice number contains the client code
    if (invLower.includes(codeLower)) {
      const option = clientOptions.value.find(c => c.value === client.id);
      if (option) return option;
    }
  }

  return null;
}

/**
 * Find matching client from user's client list based on extracted name/email
 */
function findMatchingClient(
  extractedName: string,
  extractedEmail?: string | null
): { value: string; label: string } | null {
  const clients = clientOptions.value;
  if (!clients.length) return null;

  const nameLower = extractedName.toLowerCase().trim();

  // Strategy 1: Exact email match
  if (extractedEmail) {
    const emailLower = extractedEmail.toLowerCase();
    const emailMatch = clients.find(c =>
      clientStore.clients.find(client =>
        client.id === c.value && client.email?.toLowerCase() === emailLower
      )
    );
    if (emailMatch) return emailMatch;
  }

  // Strategy 2: Exact name match
  const exactMatch = clients.find(c => c.label.toLowerCase() === nameLower);
  if (exactMatch) return exactMatch;

  // Strategy 3: Name contains match (at least 5 chars)
  if (nameLower.length >= 5) {
    const containsMatch = clients.find(c => {
      const clientName = c.label.toLowerCase();
      return clientName.includes(nameLower) || nameLower.includes(clientName);
    });
    if (containsMatch) return containsMatch;
  }

  // Strategy 4: Word-based matching (require at least 2 matching words or 50% match)
  const extractedWords = getSignificantWords(nameLower);
  if (extractedWords.length > 0) {
    let bestMatch: { value: string; label: string } | null = null;
    let bestScore = 0;

    for (const client of clients) {
      const clientWords = getSignificantWords(client.label.toLowerCase());
      if (clientWords.length === 0) continue;

      const matchingWords = extractedWords.filter(w => clientWords.includes(w));
      const matchCount = matchingWords.length;
      const ratio = matchCount / clientWords.length;

      if (matchCount >= 2 || (matchCount >= 1 && ratio >= 0.5)) {
        if (matchCount > bestScore) {
          bestScore = matchCount;
          bestMatch = client;
        }
      }
    }

    if (bestMatch) return bestMatch;
  }

  return null;
}

/**
 * Get significant words from a name (filter out common business suffixes)
 */
function getSignificantWords(name: string): string[] {
  const words = name.split(/[\s,.-]+/);
  const stopWords = ['pty', 'ltd', 'limited', 'inc', 'incorporated', 'cc', 'the', 'and', 'of', 'for', 'a', 'an'];
  return words.filter(word => word.length >= 2 && !stopWords.includes(word));
}

async function handleUploadHistorical() {
  const file = uploaderRef.value?.file;
  if (!file) {
    $q.notify({ type: 'negative', message: 'Please select a file' });
    return;
  }
  if (!uploadData.value.client_id) {
    $q.notify({ type: 'negative', message: 'Please select a client' });
    return;
  }
  if (!uploadData.value.invoice_number) {
    $q.notify({ type: 'negative', message: 'Please enter an invoice number' });
    return;
  }
  if (!uploadData.value.invoice_date) {
    $q.notify({ type: 'negative', message: 'Please enter an invoice date' });
    return;
  }
  if (!uploadData.value.due_date) {
    // Auto-calculate due_date as invoice_date + 30 days
    const invoiceDate = new Date(uploadData.value.invoice_date);
    invoiceDate.setDate(invoiceDate.getDate() + 30);
    uploadData.value.due_date = invoiceDate.toISOString().split('T')[0];
  }
  if (!uploadData.value.total || uploadData.value.total <= 0) {
    $q.notify({ type: 'negative', message: 'Please enter a valid total amount' });
    return;
  }

  const result = await invoiceStore.uploadHistoricalInvoice({
    client_id: uploadData.value.client_id,
    invoice_number: uploadData.value.invoice_number,
    invoice_date: uploadData.value.invoice_date,
    due_date: uploadData.value.due_date,
    paid_date: uploadData.value.paid_date || undefined,
    total: uploadData.value.total,
    title: uploadData.value.title || undefined,
    file: file,
  });

  if (result) {
    $q.notify({ type: 'positive', message: 'Invoice uploaded!' });
    closeUploadDialog();
  }
}

function closeUploadDialog() {
  showUploadDialog.value = false;
  uploaderRef.value?.clearFile();
  uploadData.value = {
    client_id: '',
    invoice_number: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: '',
    paid_date: '',
    total: 0,
    title: '',
  };
}

// Sync filters to URL query params for preservation across navigation
function updateQueryParams() {
  const query: Record<string, string> = {};
  if (filterStatus.value) query.status = filterStatus.value;
  if (filterClient.value) query.client = filterClient.value;
  if (searchQuery.value) query.search = searchQuery.value;

  // Only update if changed to avoid infinite loops
  const currentQuery = { ...route.query };
  const hasChanges =
    (query.status || null) !== (currentQuery.status || null) ||
    (query.client || null) !== (currentQuery.client || null) ||
    (query.search || null) !== (currentQuery.search || null);

  if (hasChanges) {
    router.replace({ query: Object.keys(query).length > 0 ? query : undefined });
  }
}

// Watch filters and sync to URL + reload invoices
watch([filterStatus, filterClient], () => {
  updateQueryParams();
  loadInvoices();
});

watch(searchQuery, updateQueryParams);

onMounted(async () => {
  // Restore filters from URL query params
  if (route.query.status && typeof route.query.status === 'string') {
    filterStatus.value = route.query.status;
  }
  if (route.query.client && typeof route.query.client === 'string') {
    filterClient.value = route.query.client;
  }
  if (route.query.search && typeof route.query.search === 'string') {
    searchQuery.value = route.query.search;
  }

  await clientStore.loadClients();
  await loadInvoices();
  await invoiceStore.updateOverdueStatus();
  await loadStagedInvoices();
});
</script>

<style scoped>
/* Deep Teal Institutional Branding */
.invoices-page {
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

.upload-btn {
  border-color: #004D40 !important;
  color: #004D40 !important;
}

/* Summary Cards */
.summary-card {
  border-radius: 12px;
  border: 1px solid #E0E0E0;
}

.total-card {
  border-left: 4px solid #004D40;
}

.awaiting-card {
  border-left: 4px solid #FF9800;
}

.tax-card {
  border-left: 4px solid #673AB7;
}

/* Staged Banner */
.staged-banner {
  background: #FFF3E0 !important;
  border-left: 4px solid #FF9800;
}

/* Invoice List */
.invoice-list-card {
  border-radius: 12px;
  overflow: hidden;
}

.list-header {
  background: #F5F5F5;
  border-bottom: 1px solid #E0E0E0;
  font-weight: 600;
}

.invoice-item {
  border-left: 3px solid transparent;
  transition: all 0.2s;
}

.invoice-item:hover {
  border-left-color: #004D40;
  background: #F5F5F5;
}

.overdue-item {
  background: #FFEBEE;
  border-left-color: #C62828 !important;
}

.invoice-number {
  color: #004D40;
  font-family: monospace;
}

.amount-label {
  color: #004D40;
}

/* Status Badges */
.status-badge {
  font-weight: 600;
  padding: 4px 12px;
}

.paid-badge {
  background: #004D40 !important;
}

.overdue-badge {
  background: #C62828 !important;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.empty-icon {
  color: #004D40 !important;
  opacity: 0.3;
}

/* Text Colors */
.text-teal {
  color: #004D40 !important;
}

/* Dialogs */
:deep(.bg-teal) {
  background: #004D40 !important;
}
</style>
