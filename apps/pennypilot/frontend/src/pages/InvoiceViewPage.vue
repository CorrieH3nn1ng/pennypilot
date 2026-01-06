<template>
  <q-page padding>
    <div v-if="invoice">
      <!-- Header -->
      <div class="row items-center justify-between q-mb-md">
        <div>
          <q-btn flat icon="arrow_back" @click="goBack" class="q-mr-sm" />
          <span class="text-h5">Invoice {{ invoice.invoice_number }}</span>
          <q-badge :color="getStatusColor(invoice.status)" class="q-ml-sm">
            {{ invoice.status.toUpperCase() }}
          </q-badge>
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            outline
            color="primary"
            icon="picture_as_pdf"
            label="Download PDF"
            :loading="isGeneratingPdf"
            @click="downloadPdf"
          />
          <q-btn
            v-if="invoice.status === 'draft'"
            outline
            color="primary"
            icon="edit"
            label="Edit"
            :to="{ name: 'invoice-edit', params: { id: invoice.id } }"
          />
          <q-btn
            v-if="invoice.status === 'draft'"
            color="primary"
            icon="send"
            label="Mark as Sent"
            @click="handleSend"
          />
          <q-btn
            v-if="['sent', 'overdue'].includes(invoice.status)"
            color="positive"
            icon="check"
            label="Mark as Paid"
            @click="openPayDialog"
          />
        </div>
      </div>

      <!-- Invoice Preview Card -->
      <q-card class="q-mb-lg">
        <q-card-section>
          <div class="row q-col-gutter-lg">
            <!-- From (Business) -->
            <div class="col-12 col-md-6">
              <div class="text-weight-bold q-mb-sm">From</div>
              <div v-if="profile">
                <div class="text-subtitle1">{{ profile.business_name || profile.trading_name || 'Your Business' }}</div>
                <div v-if="profile.email" class="text-caption">{{ profile.email }}</div>
                <div v-if="profile.phone" class="text-caption">{{ profile.phone }}</div>
                <div v-if="profile.address" class="text-caption" style="white-space: pre-line">{{ profile.address }}</div>
              </div>
              <div v-else class="text-grey-6">
                <router-link :to="{ name: 'business-profile' }">Set up business profile</router-link>
              </div>
            </div>

            <!-- To (Client) -->
            <div class="col-12 col-md-6">
              <div class="text-weight-bold q-mb-sm">To</div>
              <div v-if="invoice.client">
                <div class="text-subtitle1">{{ invoice.client.name }}</div>
                <div v-if="invoice.client.contact_person" class="text-caption">{{ invoice.client.contact_person }}</div>
                <div v-if="invoice.client.email" class="text-caption">{{ invoice.client.email }}</div>
                <div v-if="invoice.client.address" class="text-caption" style="white-space: pre-line">{{ invoice.client.address }}</div>
              </div>
            </div>
          </div>
        </q-card-section>

        <q-separator />

        <!-- Invoice Details -->
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Invoice Number</div>
              <div class="text-body1">{{ invoice.invoice_number }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Invoice Date</div>
              <div class="text-body1">{{ formatDate(invoice.invoice_date) }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Due Date</div>
              <div class="text-body1">{{ formatDate(invoice.due_date) }}</div>
            </div>
            <div class="col-6 col-md-3" v-if="invoice.paid_date">
              <div class="text-caption text-grey-7">Paid Date</div>
              <div class="text-body1 text-positive">{{ formatDate(invoice.paid_date) }}</div>
            </div>
          </div>
          <div v-if="invoice.title" class="q-mt-md">
            <div class="text-caption text-grey-7">Description</div>
            <div class="text-body1">{{ invoice.title }}</div>
          </div>
        </q-card-section>

        <q-separator />

        <!-- Line Items (Zebra-Striped Deep Teal) -->
        <q-card-section class="q-pa-none">
          <!-- Header Row -->
          <div class="line-items-header">
            <div class="col-desc">Description</div>
            <div class="col-qty">Qty</div>
            <div class="col-unit">Unit</div>
            <div class="col-rate">Rate</div>
            <div class="col-amount">Amount</div>
          </div>

          <!-- Line Item Rows (Zebra Striped) -->
          <div
            v-for="(item, index) in invoice.items"
            :key="item.id || index"
            class="line-item-row"
            :class="{ 'zebra-stripe': index % 2 === 0 }"
          >
            <div class="col-desc">{{ item.description }}</div>
            <div class="col-qty">{{ item.quantity }}</div>
            <div class="col-unit">{{ item.unit || '-' }}</div>
            <div class="col-rate">R {{ formatMoney(item.unit_price) }}</div>
            <div class="col-amount">R {{ formatMoney(item.amount) }}</div>
          </div>
        </q-card-section>

        <q-separator />

        <!-- Totals -->
        <q-card-section>
          <div class="row justify-end">
            <div class="col-12 col-md-4">
              <div class="row justify-between q-mb-sm">
                <span class="text-grey-7">Subtotal</span>
                <span>R {{ formatMoney(invoice.subtotal) }}</span>
              </div>
              <div class="row justify-between q-mb-sm" v-if="invoice.tax_rate > 0">
                <span class="text-grey-7">VAT ({{ invoice.tax_rate }}%)</span>
                <span>R {{ formatMoney(invoice.tax_amount) }}</span>
              </div>
              <q-separator class="q-my-sm" />
              <div class="row justify-between text-h6">
                <span>Total</span>
                <span>R {{ formatMoney(invoice.total) }}</span>
              </div>
            </div>
          </div>
        </q-card-section>

        <!-- Banking Details -->
        <q-separator v-if="profile && profile.bank_name" />
        <q-card-section v-if="profile && profile.bank_name">
          <div class="text-weight-bold q-mb-sm">Banking Details</div>
          <div class="row q-col-gutter-md">
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Bank</div>
              <div>{{ profile.bank_name }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Account Holder</div>
              <div>{{ profile.account_holder }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-caption text-grey-7">Account Number</div>
              <div>{{ profile.account_number }}</div>
            </div>
            <div class="col-6 col-md-3" v-if="profile.bank_branch_code">
              <div class="text-caption text-grey-7">Branch Code</div>
              <div>{{ profile.bank_branch_code }}</div>
            </div>
          </div>
        </q-card-section>

        <!-- Notes -->
        <q-separator v-if="invoice.notes" />
        <q-card-section v-if="invoice.notes">
          <div class="text-weight-bold q-mb-sm">Notes</div>
          <div style="white-space: pre-line">{{ invoice.notes }}</div>
        </q-card-section>
      </q-card>

      <!-- Transaction Match -->
      <q-card v-if="invoice.transaction" class="q-mb-lg bg-positive-1">
        <q-card-section>
          <div class="row items-center justify-between">
            <div class="row items-center">
              <q-icon name="link" color="positive" size="sm" class="q-mr-sm" />
              <div>
                <span class="text-weight-bold">Matched to transaction</span>
                <q-badge :color="invoice.match_method === 'auto' ? 'blue' : 'primary'" class="q-ml-sm">
                  {{ invoice.match_method === 'auto' ? 'Auto' : 'Manual' }}
                  {{ invoice.match_confidence ? `(${invoice.match_confidence}%)` : '' }}
                </q-badge>
              </div>
            </div>
            <q-btn
              flat
              size="sm"
              color="negative"
              label="Unmatch"
              icon="link_off"
              @click="handleUnmatch"
            />
          </div>
          <div class="q-mt-sm">
            <div class="row q-col-gutter-md text-body2">
              <div class="col-auto">
                <span class="text-grey-7">Date:</span>
                {{ formatDate(invoice.transaction.transaction_date) }}
              </div>
              <div class="col-auto">
                <span class="text-grey-7">Amount:</span>
                R {{ formatMoney(invoice.transaction.amount) }}
              </div>
              <div class="col">
                <span class="text-grey-7">Description:</span>
                {{ invoice.transaction.description }}
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Find Transaction Match (for unmatched sent/overdue invoices) -->
      <q-card v-if="canMatch && !invoice.transaction" class="q-mb-lg">
        <q-card-section>
          <div class="row items-center justify-between">
            <div class="row items-center">
              <q-icon name="search" color="grey" size="sm" class="q-mr-sm" />
              <span>No matching transaction found</span>
            </div>
            <q-btn
              color="primary"
              label="Find Matches"
              icon="find_replace"
              :loading="isLoadingMatches"
              @click="findMatches"
            />
          </div>
        </q-card-section>
      </q-card>

      <!-- Income Link -->
      <q-card v-if="invoice.income_source" class="q-mb-lg bg-positive-1">
        <q-card-section>
          <div class="row items-center">
            <q-icon name="check_circle" color="positive" size="sm" class="q-mr-sm" />
            <span>This invoice is linked to income entry: {{ invoice.income_source.name }}</span>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <div v-else-if="isLoading" class="text-center q-pa-xl">
      <q-spinner size="lg" />
    </div>

    <div v-else class="text-center q-pa-xl">
      <q-icon name="error" size="48px" color="negative" />
      <div class="q-mt-md">Invoice not found</div>
    </div>

    <!-- Mark as Paid Dialog -->
    <q-dialog v-model="showPayDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Mark Invoice as Paid</div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="payData.paid_date"
            label="Payment Date"
            type="date"
            filled
          />
          <q-checkbox
            v-model="payData.create_income"
            label="Create income entry"
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Confirm" @click="confirmPay" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Match Transaction Dialog -->
    <q-dialog v-model="showMatchDialog" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Find Matching Transaction</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="text-body2 text-grey-7 q-mb-md">
            Looking for transactions matching invoice {{ invoice?.invoice_number }} for R {{ formatMoney(invoice?.total) }}
          </div>

          <div v-if="potentialMatches.length === 0" class="text-center q-pa-xl text-grey-6">
            <q-icon name="search_off" size="64px" />
            <div class="q-mt-md">No potential matches found</div>
            <div class="text-caption q-mt-sm">
              Try importing more transactions or manually search for the payment
            </div>
          </div>

          <q-list v-else separator>
            <q-item
              v-for="match in potentialMatches"
              :key="match.transaction.id"
              clickable
              @click="selectMatch(match.transaction.id)"
              :class="{ 'bg-blue-1': match.score >= 70 }"
            >
              <q-item-section avatar>
                <q-circular-progress
                  :value="match.score"
                  size="50px"
                  :thickness="0.15"
                  :color="match.score >= 70 ? 'positive' : match.score >= 50 ? 'warning' : 'grey'"
                  track-color="grey-3"
                  show-value
                  class="text-weight-bold"
                />
              </q-item-section>

              <q-item-section>
                <q-item-label class="text-weight-medium">
                  R {{ formatMoney(match.transaction.amount) }}
                  <q-badge v-if="match.score >= 70" color="positive" class="q-ml-sm">High Match</q-badge>
                </q-item-label>
                <q-item-label caption>
                  {{ formatDate(match.transaction.transaction_date) }} - {{ match.transaction.description }}
                </q-item-label>
                <q-item-label caption class="q-mt-xs">
                  <q-chip
                    v-for="reason in match.reasons"
                    :key="reason"
                    dense
                    size="sm"
                    color="blue-2"
                    text-color="blue-8"
                    class="q-mr-xs"
                  >
                    {{ reason }}
                  </q-chip>
                </q-item-label>
              </q-item-section>

              <q-item-section side>
                <q-btn color="primary" label="Match" @click.stop="selectMatch(match.transaction.id)" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useInvoiceStore } from '@/stores/invoice.store';
import { useBusinessProfileStore } from '@/stores/business-profile.store';
import { invoiceApi, type TransactionMatch } from '@/services/api/invoice.api';
import { invoicePdfService } from '@/services/pdf/InvoicePdfService';

const $q = useQuasar();
const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const profileStore = useBusinessProfileStore();

// State
const showPayDialog = ref(false);
const showMatchDialog = ref(false);
const isLoadingMatches = ref(false);
const isGeneratingPdf = ref(false);
const potentialMatches = ref<TransactionMatch[]>([]);
const payData = ref({
  paid_date: new Date().toISOString().split('T')[0],
  create_income: true,
});

function openPayDialog() {
  // Default to invoice date, not today's date
  if (invoice.value?.invoice_date) {
    const invoiceDate = new Date(invoice.value.invoice_date);
    payData.value.paid_date = invoiceDate.toISOString().split('T')[0];
  }
  payData.value.create_income = true;
  showPayDialog.value = true;
}

// Computed
const invoice = computed(() => invoiceStore.currentInvoice);
const profile = computed(() => profileStore.profile);
const isLoading = computed(() => invoiceStore.isLoading);
const canMatch = computed(() => {
  return invoice.value && ['sent', 'overdue'].includes(invoice.value.status);
});

// Line items are rendered with custom zebra-striped template

// Methods
function goBack() {
  // Use router.back() to preserve filter state when returning to invoice list
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push({ name: 'invoices' });
  }
}

function formatMoney(amount: number | undefined | null): string {
  const num = amount ?? 0;
  return num.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('en-ZA');
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'draft': return 'grey';
    case 'sent': return 'blue';
    case 'paid': return 'positive';
    case 'overdue': return 'negative';
    case 'cancelled': return 'grey-6';
    default: return 'grey';
  }
}

async function handleSend() {
  if (!invoice.value) return;
  const result = await invoiceStore.markAsSent(invoice.value.id);
  if (result) {
    $q.notify({ type: 'positive', message: 'Invoice marked as sent' });
  }
}

async function downloadPdf() {
  if (!invoice.value || !profile.value) {
    $q.notify({ type: 'negative', message: 'Please set up your business profile first' });
    return;
  }

  isGeneratingPdf.value = true;
  try {
    invoicePdfService.download({
      invoice: invoice.value,
      profile: profile.value,
    });
    $q.notify({ type: 'positive', message: 'PDF downloaded' });
  } catch (error) {
    console.error('PDF generation error:', error);
    $q.notify({ type: 'negative', message: 'Failed to generate PDF' });
  } finally {
    isGeneratingPdf.value = false;
  }
}

async function confirmPay() {
  if (!invoice.value) return;

  try {
    const result = await invoiceStore.markAsPaid(invoice.value.id, payData.value);
    if (result) {
      $q.notify({ type: 'positive', message: 'Invoice marked as paid' });
      if (payData.value.create_income) {
        $q.notify({ type: 'info', message: 'Income entry created' });
      }
      showPayDialog.value = false;
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

async function findMatches() {
  if (!invoice.value) return;

  isLoadingMatches.value = true;
  try {
    const response = await invoiceApi.findMatches(invoice.value.id);
    potentialMatches.value = response.data;
    showMatchDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to find matches' });
  } finally {
    isLoadingMatches.value = false;
  }
}

async function selectMatch(transactionId: string) {
  if (!invoice.value) return;

  try {
    await invoiceApi.match(invoice.value.id, transactionId);
    $q.notify({ type: 'positive', message: 'Invoice matched to transaction and marked as paid' });
    showMatchDialog.value = false;
    // Reload the invoice to get updated data
    await invoiceStore.loadInvoice(invoice.value.id);
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to match invoice' });
  }
}

async function handleUnmatch() {
  if (!invoice.value) return;

  $q.dialog({
    title: 'Unmatch Transaction',
    message: 'Are you sure you want to remove the match between this invoice and transaction? The invoice status will remain as paid.',
    cancel: true,
  }).onOk(async () => {
    try {
      await invoiceApi.unmatch(invoice.value!.id);
      $q.notify({ type: 'info', message: 'Invoice unmatched from transaction' });
      await invoiceStore.loadInvoice(invoice.value!.id);
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to unmatch invoice' });
    }
  });
}

// Lifecycle
onMounted(async () => {
  const id = route.params.id as string;
  await invoiceStore.loadInvoice(id);
  await profileStore.loadProfile();
});
</script>

<style scoped>
/* ============================================
   Zebra-Striped Line Items (Deep Teal Theme)
   ============================================ */

.line-items-header {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: #004D40; /* Deep Teal */
  color: white;
  font-weight: 600;
  font-size: 13px;
}

.line-item-row {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  font-size: 14px;
  color: #37474F;
  border-bottom: 1px solid #ECEFF1;
}

.line-item-row:last-child {
  border-bottom: none;
}

/* Zebra striping - alternating rows */
.line-item-row.zebra-stripe {
  background: #E0F2F1; /* Teal-50 */
}

/* Column widths */
.col-desc {
  flex: 3;
  padding-right: 8px;
}

.col-qty {
  flex: 0.7;
  text-align: center;
}

.col-unit {
  flex: 0.7;
  text-align: center;
}

.col-rate {
  flex: 1;
  text-align: right;
  padding-right: 16px;
}

.col-amount {
  flex: 1;
  text-align: right;
  font-weight: 600;
}

/* Header specific adjustments */
.line-items-header .col-desc {
  color: white;
}

.line-items-header .col-qty,
.line-items-header .col-unit,
.line-items-header .col-rate,
.line-items-header .col-amount {
  color: rgba(255, 255, 255, 0.9);
}

/* Mobile responsive */
@media (max-width: 599px) {
  .line-items-header,
  .line-item-row {
    padding: 10px 12px;
    font-size: 12px;
  }

  .col-unit {
    display: none;
  }

  .col-qty {
    flex: 0.5;
  }

  .col-rate {
    flex: 1;
  }
}
</style>
