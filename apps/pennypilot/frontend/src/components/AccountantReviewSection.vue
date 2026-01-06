<template>
  <q-card class="accountant-section q-mb-md">
    <q-card-section>
      <div class="row items-center q-mb-md">
        <div class="text-subtitle1 text-weight-medium">
          <q-icon name="gavel" color="deep-orange" class="q-mr-sm" />
          Accountant Intelligence
        </div>
        <q-space />
        <q-badge color="deep-orange" class="q-pa-xs">PRO</q-badge>
      </div>

      <!-- Summary Stats -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <div class="stat-value text-deep-orange">{{ accountantStore.pendingCount }}</div>
            <div class="stat-label">Pending Review</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <div class="stat-value">R {{ formatMoney(accountantStore.pendingAmount) }}</div>
            <div class="stat-label">Flagged Amount</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <div class="stat-value text-positive">R {{ formatMoney(accountantStore.potentialTaxSavings) }}</div>
            <div class="stat-label">Potential Tax Savings</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <div class="stat-value text-teal">R {{ formatMoney(accountantStore.taxYearSavings) }}</div>
            <div class="stat-label">Year-to-Date Savings</div>
          </div>
        </div>
      </div>

      <!-- Pending Review List -->
      <div v-if="accountantStore.pendingTransactions.length > 0" class="pending-list">
        <div class="row items-center q-mb-sm">
          <div class="text-caption text-grey-7 text-weight-medium">PENDING REVIEW</div>
          <q-space />
          <q-btn
            flat
            dense
            size="sm"
            color="deep-orange"
            label="View All"
            icon-right="chevron_right"
            @click="showReviewDialog = true"
          />
        </div>

        <q-list separator class="rounded-borders bg-white">
          <q-item
            v-for="tx in pendingPreview"
            :key="tx.id"
            class="q-py-md"
            clickable
            @click="openReviewItem(tx)"
          >
            <q-item-section avatar>
              <q-avatar color="deep-orange-1" text-color="deep-orange">
                <q-icon name="help_outline" />
              </q-avatar>
            </q-item-section>

            <q-item-section>
              <q-item-label class="text-weight-medium">{{ tx.description }}</q-item-label>
              <q-item-label caption>
                {{ formatDate(tx.transaction_date) }}
                <span v-if="tx.accountant_question" class="text-deep-orange q-ml-sm">
                  <q-icon name="comment" size="xs" /> Has question
                </span>
              </q-item-label>
            </q-item-section>

            <q-item-section side>
              <q-item-label class="text-weight-bold text-negative">
                R {{ formatMoney(Math.abs(tx.amount)) }}
              </q-item-label>
              <q-item-label caption class="text-positive">
                ~R {{ formatMoney(Math.abs(tx.amount) * 0.391) }} savings
              </q-item-label>
            </q-item-section>

            <q-item-section side>
              <q-icon name="chevron_right" color="grey" />
            </q-item-section>
          </q-item>
        </q-list>

        <div v-if="accountantStore.pendingTransactions.length > 3" class="text-center q-mt-sm">
          <q-btn
            flat
            color="deep-orange"
            :label="`+ ${accountantStore.pendingTransactions.length - 3} more items`"
            @click="showReviewDialog = true"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center q-pa-lg bg-grey-1 rounded-borders">
        <q-icon name="check_circle" color="positive" size="48px" class="q-mb-md" />
        <div class="text-subtitle1 text-grey-8">No items pending review</div>
        <div class="text-caption text-grey-6">
          Flag transactions from the Transactions page to ask your accountant about them.
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="row q-gutter-sm q-mt-md">
        <q-btn
          outline
          color="deep-orange"
          icon="picture_as_pdf"
          label="Export PDF Report"
          :loading="isDownloading"
          :disable="accountantStore.pendingCount === 0"
          @click="downloadReport"
        />
        <q-btn
          v-if="accountantStore.pendingCount > 0"
          flat
          color="grey-7"
          label="View Full Review"
          icon-right="open_in_new"
          @click="showReviewDialog = true"
        />
      </div>
    </q-card-section>

    <!-- Full Review Dialog -->
    <q-dialog
      v-model="showReviewDialog"
      maximized
      transition-show="slide-up"
      transition-hide="slide-down"
    >
      <q-card class="column no-wrap">
        <q-toolbar class="bg-deep-orange text-white">
          <q-btn flat round dense icon="close" v-close-popup />
          <q-toolbar-title>Accountant Review</q-toolbar-title>
          <q-btn
            flat
            icon="picture_as_pdf"
            label="Export PDF"
            :loading="isDownloading"
            @click="downloadReport"
          />
        </q-toolbar>

        <!-- Filter Tabs -->
        <q-tabs v-model="statusFilter" class="bg-white text-grey-7" active-color="deep-orange">
          <q-tab name="pending" label="Pending" :badge="accountantStore.summary?.pending_review" />
          <q-tab name="approved_business" label="Approved" :badge="accountantStore.summary?.approved_business" badge-color="positive" />
          <q-tab name="rejected_private" label="Rejected" :badge="accountantStore.summary?.rejected_private" badge-color="grey" />
          <q-tab name="all" label="All" />
        </q-tabs>

        <q-separator />

        <!-- Transaction List -->
        <q-scroll-area class="col">
          <q-list separator>
            <q-item
              v-for="tx in filteredTransactions"
              :key="tx.id"
              class="q-py-md"
            >
              <q-item-section avatar>
                <q-avatar
                  :color="getStatusColor(tx.accountant_status)"
                  text-color="white"
                >
                  <q-icon :name="getStatusIcon(tx.accountant_status)" />
                </q-avatar>
              </q-item-section>

              <q-item-section>
                <q-item-label class="text-weight-medium">{{ tx.description }}</q-item-label>
                <q-item-label caption>{{ formatDate(tx.transaction_date) }}</q-item-label>
                <q-item-label v-if="tx.accountant_question" caption class="q-mt-xs">
                  <div class="question-box">
                    <q-icon name="help" size="xs" color="amber" class="q-mr-xs" />
                    {{ tx.accountant_question }}
                  </div>
                </q-item-label>
                <q-item-label v-if="tx.accountant_notes" caption class="q-mt-xs">
                  <q-icon name="notes" size="xs" color="grey" class="q-mr-xs" />
                  {{ tx.accountant_notes }}
                </q-item-label>
              </q-item-section>

              <q-item-section side class="text-right">
                <q-item-label class="text-weight-bold text-negative">
                  R {{ formatMoney(Math.abs(tx.amount)) }}
                </q-item-label>
                <q-item-label v-if="tx.tax_deduction_amount" caption class="text-positive">
                  R {{ formatMoney(tx.tax_deduction_amount) }} deduction
                </q-item-label>
              </q-item-section>

              <q-item-section v-if="tx.accountant_status === 'pending'" side>
                <div class="row q-gutter-xs">
                  <q-btn
                    round
                    flat
                    size="sm"
                    color="positive"
                    icon="check"
                    @click="approveItem(tx)"
                  >
                    <q-tooltip>Approve as Business</q-tooltip>
                  </q-btn>
                  <q-btn
                    round
                    flat
                    size="sm"
                    color="negative"
                    icon="close"
                    @click="rejectItem(tx)"
                  >
                    <q-tooltip>Reject to Private</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>
          </q-list>

          <div v-if="filteredTransactions.length === 0" class="text-center q-pa-xl">
            <q-icon name="inbox" size="48px" color="grey-4" />
            <div class="text-grey-6 q-mt-md">No transactions in this category</div>
          </div>
        </q-scroll-area>

        <!-- Bottom Summary -->
        <q-separator />
        <q-card-section class="bg-grey-1">
          <div class="row text-center">
            <div class="col">
              <div class="text-caption text-grey-7">Pending</div>
              <div class="text-weight-bold text-deep-orange">{{ accountantStore.summary?.pending_review ?? 0 }}</div>
            </div>
            <div class="col">
              <div class="text-caption text-grey-7">Total Deductions</div>
              <div class="text-weight-bold text-positive">R {{ formatMoney(accountantStore.taxYearDeductions) }}</div>
            </div>
            <div class="col">
              <div class="text-caption text-grey-7">Tax Savings</div>
              <div class="text-weight-bold text-teal">R {{ formatMoney(accountantStore.taxYearSavings) }}</div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Approval Notes Dialog -->
    <q-dialog v-model="showApprovalDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Approve as Business Expense</div>
          <div class="text-caption text-grey-7 q-mt-sm">
            This will mark the expense as tax-deductible and recalculate your tax reserves.
          </div>
        </q-card-section>

        <q-card-section>
          <div class="bg-positive-1 q-pa-md rounded-borders q-mb-md">
            <div class="row items-center">
              <q-icon name="savings" color="positive" size="md" class="q-mr-md" />
              <div>
                <div class="text-positive text-weight-bold">
                  R {{ formatMoney(selectedForAction ? Math.abs(selectedForAction.amount) * 0.391 : 0) }}
                </div>
                <div class="text-caption">Estimated tax savings</div>
              </div>
            </div>
          </div>

          <q-input
            v-model="approvalNotes"
            label="Notes (optional)"
            outlined
            type="textarea"
            rows="2"
            placeholder="e.g., Verified with accountant on 15 Jan 2025"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="positive"
            label="Approve"
            icon="check"
            :loading="isProcessing"
            @click="confirmApproval"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Rejection Notes Dialog -->
    <q-dialog v-model="showRejectDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Reject to Private</div>
          <div class="text-caption text-grey-7 q-mt-sm">
            This will mark the expense as personal/private and not tax-deductible.
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="rejectionNotes"
            label="Reason (optional)"
            outlined
            type="textarea"
            rows="2"
            placeholder="e.g., Personal expense, not business-related"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="negative"
            label="Reject to Private"
            icon="close"
            :loading="isProcessing"
            @click="confirmRejection"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useAccountantStore } from '@/stores/accountant.store';
import type { FlaggedTransaction, AccountantStatus } from '@/services/api/accountant.api';
import { formatNumber } from '@/utils/currency';

const $q = useQuasar();
const accountantStore = useAccountantStore();

// State
const showReviewDialog = ref(false);
const showApprovalDialog = ref(false);
const showRejectDialog = ref(false);
const statusFilter = ref<AccountantStatus | 'all'>('pending');
const isDownloading = ref(false);
const isProcessing = ref(false);
const selectedForAction = ref<FlaggedTransaction | null>(null);
const approvalNotes = ref('');
const rejectionNotes = ref('');

// Computed
const pendingPreview = computed(() =>
  accountantStore.pendingTransactions.slice(0, 3)
);

const filteredTransactions = computed(() => {
  if (statusFilter.value === 'all') {
    return accountantStore.flaggedTransactions;
  }
  return accountantStore.flaggedTransactions.filter(
    (t) => t.accountant_status === statusFilter.value
  );
});

// Helpers
function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function getStatusColor(status: AccountantStatus | null): string {
  switch (status) {
    case 'pending': return 'deep-orange';
    case 'approved_business': return 'positive';
    case 'rejected_private': return 'grey';
    default: return 'grey';
  }
}

function getStatusIcon(status: AccountantStatus | null): string {
  switch (status) {
    case 'pending': return 'help_outline';
    case 'approved_business': return 'check';
    case 'rejected_private': return 'close';
    default: return 'help_outline';
  }
}

// Actions
async function downloadReport(): Promise<void> {
  isDownloading.value = true;
  try {
    await accountantStore.downloadReport();
    $q.notify({
      type: 'positive',
      message: 'PDF report downloaded successfully',
    });
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Failed to download report',
    });
  } finally {
    isDownloading.value = false;
  }
}

function openReviewItem(tx: FlaggedTransaction): void {
  showReviewDialog.value = true;
  statusFilter.value = 'pending';
}

function approveItem(tx: FlaggedTransaction): void {
  selectedForAction.value = tx;
  approvalNotes.value = '';
  showApprovalDialog.value = true;
}

function rejectItem(tx: FlaggedTransaction): void {
  selectedForAction.value = tx;
  rejectionNotes.value = '';
  showRejectDialog.value = true;
}

async function confirmApproval(): Promise<void> {
  if (!selectedForAction.value?.id) return;

  isProcessing.value = true;
  try {
    const result = await accountantStore.approveAsBusiness(
      selectedForAction.value.id,
      approvalNotes.value.trim() || undefined
    );
    if (result) {
      $q.notify({
        type: 'positive',
        message: `Approved! Tax savings: R ${formatMoney(result.tax_impact.tax_savings)}`,
        icon: 'savings',
      });
      showApprovalDialog.value = false;
    }
  } finally {
    isProcessing.value = false;
  }
}

async function confirmRejection(): Promise<void> {
  if (!selectedForAction.value?.id) return;

  isProcessing.value = true;
  try {
    const result = await accountantStore.rejectToPrivate(
      selectedForAction.value.id,
      rejectionNotes.value.trim() || undefined
    );
    if (result) {
      $q.notify({
        type: 'info',
        message: 'Transaction marked as private expense',
      });
      showRejectDialog.value = false;
    }
  } finally {
    isProcessing.value = false;
  }
}

// Sync filter with store
watch(statusFilter, (newVal) => {
  accountantStore.setStatusFilter(newVal);
});

// Load data
onMounted(async () => {
  await accountantStore.loadAll();
});
</script>

<style scoped>
.accountant-section {
  border-radius: 12px;
  border: 1px solid #E0E0E0;
}

.stat-card {
  background: #FAFAFA;
  border-radius: 8px;
  padding: 12px;
  text-align: center;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #263238;
}

.stat-label {
  font-size: 11px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.pending-list {
  background: #FFF3E0;
  border-radius: 8px;
  padding: 16px;
}

.question-box {
  background: #FFF8E1;
  border-left: 3px solid #FFC107;
  padding: 8px;
  border-radius: 4px;
  font-style: italic;
}

.text-deep-orange {
  color: #FF5722 !important;
}

.bg-positive-1 {
  background: #E8F5E9;
}
</style>
