<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Import Bank Statement</div>

    <q-card>
      <q-card-section>
        <p class="text-body2 text-grey q-mb-md">
          Upload your Nedbank CSV or Excel statement to import transactions.
        </p>

        <q-file
          v-model="file"
          label="Select CSV or Excel file"
          accept=".csv,.xlsx,.xls"
          outlined
          use-chips
          @update:model-value="handleFileSelect"
        >
          <template v-slot:prepend>
            <q-icon name="attach_file" />
          </template>
        </q-file>
      </q-card-section>

      <!-- Processing -->
      <q-card-section v-if="isProcessing">
        <q-linear-progress indeterminate />
        <p class="text-center q-mt-sm">Processing file...</p>
      </q-card-section>

      <!-- Import Progress -->
      <q-card-section v-if="isImporting && importProgress > 0">
        <q-linear-progress
          :value="importProgress"
          color="primary"
          class="q-mb-sm"
        />
        <p class="text-center text-caption">
          Importing: {{ Math.round(importProgress * 100) }}%
          ({{ importProgressText }})
        </p>
      </q-card-section>

      <!-- Results -->
      <q-card-section v-if="parseResult">
        <q-banner
          :class="parseResult.success ? 'bg-positive' : 'bg-warning'"
          class="text-white"
          rounded
        >
          <template v-slot:avatar>
            <q-icon :name="parseResult.success ? 'check_circle' : 'warning'" />
          </template>
          <div>
            <strong>{{ parseResult.stats.parsedRows }}</strong> transactions found
            <span v-if="parseResult.stats.skippedRows > 0">
              ({{ parseResult.stats.skippedRows }} rows skipped)
            </span>
          </div>
          <div v-if="parseResult.stats.dateRange" class="text-caption">
            Date range: {{ formatDate(parseResult.stats.dateRange.start) }} to
            {{ formatDate(parseResult.stats.dateRange.end) }}
          </div>
        </q-banner>

        <!-- Errors -->
        <q-expansion-item
          v-if="parseResult.errors.length > 0"
          icon="error"
          label="Parsing Errors"
          :caption="`${parseResult.errors.length} errors`"
          class="q-mt-md"
        >
          <q-card>
            <q-card-section>
              <div v-for="error in parseResult.errors" :key="error.row" class="q-mb-sm text-body2">
                <strong>Row {{ error.row }}:</strong> {{ error.message }}
              </div>
            </q-card-section>
          </q-card>
        </q-expansion-item>

        <!-- Preview -->
        <div v-if="parseResult.transactions.length > 0" class="q-mt-md">
          <div class="text-subtitle2 q-mb-sm">Preview (first 5 transactions)</div>
          <q-list bordered separator>
            <q-item v-for="(tx, idx) in parseResult.transactions.slice(0, 5)" :key="idx">
              <q-item-section>
                <q-item-label>{{ tx.description }}</q-item-label>
                <q-item-label caption>{{ tx.transactionDate }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label :class="tx.amount >= 0 ? 'text-positive' : 'text-negative'">
                  R {{ formatAmount(tx.amount) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </div>

        <!-- Actions -->
        <div class="q-mt-md q-gutter-sm">
          <q-btn
            color="primary"
            label="Import Transactions"
            :disable="parseResult.transactions.length === 0"
            :loading="isImporting"
            @click="importTransactions"
          />
          <q-btn flat label="Cancel" @click="reset" />
        </div>
      </q-card-section>
    </q-card>

    <!-- Import Success -->
    <q-dialog v-model="showSuccess">
      <q-card style="min-width: 350px">
        <q-card-section class="row items-center">
          <q-avatar icon="check_circle" color="positive" text-color="white" />
          <span class="q-ml-sm text-h6">Import Successful!</span>
        </q-card-section>

        <q-card-section>
          <!-- Import Summary -->
          <div class="text-subtitle1 q-mb-sm">Import Summary</div>
          <div class="row q-col-gutter-sm q-mb-md">
            <div class="col-6">
              <q-card flat bordered class="text-center q-pa-sm">
                <div class="text-h5 text-positive">{{ importedCount }}</div>
                <div class="text-caption">Imported</div>
              </q-card>
            </div>
            <div class="col-6" v-if="duplicateCount > 0">
              <q-card flat bordered class="text-center q-pa-sm cursor-pointer" @click="showSkippedDialog = true">
                <div class="text-h5 text-warning">{{ duplicateCount }}</div>
                <div class="text-caption">Duplicates Skipped</div>
                <div class="text-caption text-primary">View Details</div>
              </q-card>
            </div>
          </div>

          <!-- Blueprint Matching Results -->
          <div v-if="autoMatchedCount > 0 || needsManualAuditCount > 0" class="q-mb-md">
            <div class="text-subtitle1 q-mb-sm">Blueprint Matching</div>
            <div class="row q-col-gutter-sm">
              <div class="col-4">
                <q-card flat bordered class="text-center q-pa-sm bg-positive-1">
                  <div class="text-h5 text-positive">{{ autoMatchedCount }}</div>
                  <div class="text-caption">Auto-Matched</div>
                </q-card>
              </div>
              <div class="col-4">
                <q-card flat bordered class="text-center q-pa-sm bg-warning-1">
                  <div class="text-h5 text-warning">{{ needsManualAuditCount }}</div>
                  <div class="text-caption">Needs Audit</div>
                </q-card>
              </div>
              <div class="col-4" v-if="leaksCount > 0">
                <q-card flat bordered class="text-center q-pa-sm bg-grey-2">
                  <div class="text-h5 text-grey-7">{{ leaksCount }}</div>
                  <div class="text-caption">Bank Fees</div>
                </q-card>
              </div>
            </div>
          </div>

          <!-- Categorization -->
          <div v-if="categorizedCount > 0" class="text-positive">
            <q-icon name="category" class="q-mr-xs" />
            {{ categorizedCount }} transactions auto-categorized
          </div>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right">
          <q-btn
            v-if="needsManualAuditCount > 0"
            flat
            color="warning"
            label="Go to Audit"
            to="/audit"
            v-close-popup
          />
          <q-btn flat label="View Transactions" to="/transactions" v-close-popup />
          <q-btn flat label="Import More" @click="reset" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Skipped Records Dialog -->
    <q-dialog v-model="showSkippedDialog" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card>
        <q-toolbar class="bg-warning text-white">
          <q-btn flat round dense icon="arrow_back" @click="showSkippedDialog = false" />
          <q-toolbar-title>Skipped Records ({{ duplicateCount }})</q-toolbar-title>
        </q-toolbar>

        <q-card-section>
          <div class="text-body2 q-mb-md">
            These records were not imported because they already exist in your data.
            This prevents double-counting when re-uploading statements.
          </div>

          <q-list separator>
            <template v-for="(records, reason) in groupSkippedByReason()" :key="reason">
              <q-item-label header class="text-weight-bold">
                {{ reason }} ({{ records.length }})
              </q-item-label>

              <q-item v-for="(record, idx) in records.slice(0, 50)" :key="idx" class="q-py-sm">
                <q-item-section avatar>
                  <q-icon name="block" color="warning" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ record.description }}</q-item-label>
                  <q-item-label caption>
                    {{ formatDate(record.transaction_date) }} |
                    R {{ formatAmount(record.amount) }}
                  </q-item-label>
                  <q-item-label caption class="text-grey-6 q-mt-xs">
                    <q-icon name="fingerprint" size="xs" class="q-mr-xs" />
                    {{ record.matched_key }}
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item v-if="records.length > 50">
                <q-item-section class="text-center text-caption text-grey">
                  ... and {{ records.length - 50 }} more
                </q-item-section>
              </q-item>
            </template>
          </q-list>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right">
          <q-btn flat label="Close" color="primary" @click="showSkippedDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { format } from 'date-fns';
import { useQuasar } from 'quasar';
import { UniversalStatementParser } from '@/services/parsers/UniversalStatementParser';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useAccountStore } from '@/stores/account.store';
import { auditApi } from '@/services/api/audit.api';
import type { ParseResult, Transaction } from '@/types';
import type { SkippedRecord } from '@/services/storage/LocalBaseService';

const $q = useQuasar();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();
const accountStore = useAccountStore();

const file = ref<File | null>(null);
const isProcessing = ref(false);
const isImporting = ref(false);
const parseResult = ref<ParseResult | null>(null);
const showSuccess = ref(false);
const importedCount = ref(0);
const duplicateCount = ref(0);
const categorizedCount = ref(0);
const skippedRecords = ref<SkippedRecord[]>([]);
const showSkippedDialog = ref(false);
const showOpeningBalancePrompt = ref(false);
const detectedOpeningBalance = ref<number>(0);
const isSavingBalance = ref(false);

// Progress tracking for large imports
const importProgress = ref(0);
const importProgressProcessed = ref(0);
const importProgressTotal = ref(0);
const importPhase = ref<'importing' | 'matching' | 'categorizing'>('importing');

// Blueprint matching stats
const autoMatchedCount = ref(0);
const needsManualAuditCount = ref(0);
const leaksCount = ref(0);

const importProgressText = computed(() => {
  const phase = importPhase.value === 'importing' ? 'Importing' :
                importPhase.value === 'matching' ? 'Matching to Blueprints' : 'Auto-categorizing';
  return `${phase}: ${importProgressProcessed.value} of ${importProgressTotal.value}`;
});

// Load account state on mount to check if balance is set
onMounted(async () => {
  await accountStore.loadAccount();
});

async function handleFileSelect(selectedFile: File | null) {
  if (!selectedFile) {
    reset();
    return;
  }

  isProcessing.value = true;
  parseResult.value = null;

  try {
    // Use UniversalStatementParser which auto-detects format (CSV, Excel, OFX, PDF)
    console.log('ImportPage: Starting file parse for:', selectedFile.name);
    const result = await UniversalStatementParser.parse(selectedFile);
    console.log('ImportPage: Parse result:', result);

    parseResult.value = result;
  } catch (error) {
    console.error('File processing error:', error);
    parseResult.value = {
      success: false,
      transactions: [],
      errors: [
        {
          row: 0,
          field: 'file',
          message: error instanceof Error ? error.message : 'Failed to process file',
          rawValue: null,
        },
      ],
      warnings: [],
      stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
    };
  } finally {
    isProcessing.value = false;
  }
}

async function importTransactions() {
  if (!parseResult.value || parseResult.value.transactions.length === 0) return;

  isImporting.value = true;
  importProgress.value = 0;
  importProgressProcessed.value = 0;
  importProgressTotal.value = parseResult.value.transactions.length;
  importPhase.value = 'importing';

  // Reset stats
  autoMatchedCount.value = 0;
  needsManualAuditCount.value = 0;
  leaksCount.value = 0;

  try {
    const transactions: Partial<Transaction>[] = parseResult.value.transactions.map((t) => ({
      transaction_date: t.transactionDate,
      description: t.description,
      amount: t.amount,
      balance_after: t.balanceAfter,
      bank_reference: t.bankReference,
      raw_description: t.rawDescription,
      import_source: 'csv' as const,
      is_categorized: false,
      tags: [],
    }));

    // Phase 1: Import transactions with progress tracking
    const result = await transactionsStore.importTransactionsWithStats(
      transactions as Transaction[],
      (processed, total) => {
        importProgressProcessed.value = processed;
        importProgressTotal.value = total;
        importProgress.value = total > 0 ? (processed / total) * 0.5 : 0; // 50% of progress bar
      }
    );

    importedCount.value = result.imported;
    duplicateCount.value = result.duplicates;
    skippedRecords.value = result.skipped;

    // Phase 2: Run Blueprint reconciliation to auto-match transactions
    if (result.imported > 0 && parseResult.value.stats.dateRange) {
      importPhase.value = 'matching';
      importProgress.value = 0.5;
      importProgressProcessed.value = 0;
      importProgressTotal.value = result.imported;

      try {
        const reconcileResult = await auditApi.reconcile({
          start_date: parseResult.value.stats.dateRange.start,
          end_date: parseResult.value.stats.dateRange.end,
        });

        autoMatchedCount.value = reconcileResult.matched;
        needsManualAuditCount.value = reconcileResult.unmatched;
        leaksCount.value = reconcileResult.leaks;

        importProgress.value = 0.75;
      } catch (reconcileError) {
        console.warn('Blueprint reconciliation failed (will need manual audit):', reconcileError);
        // Don't fail the import - just note that manual audit is needed
        needsManualAuditCount.value = result.imported;
      }
    }

    // Phase 3: Auto-categorize any remaining uncategorized transactions
    if (result.imported > 0) {
      importPhase.value = 'categorizing';
      importProgress.value = 0.75;

      const categories = categoriesStore.categories;
      const catResult = await transactionsStore.autoCategorize(categories, true);
      categorizedCount.value = catResult.categorized;

      importProgress.value = 1.0;
    }

    // Task 3: Missing Opening Balance Detection
    await checkMissingOpeningBalance();

    showSuccess.value = true;
  } catch (error) {
    console.error('Import error:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to import transactions',
    });
  } finally {
    isImporting.value = false;
    importProgress.value = 0;
    importPhase.value = 'importing';
  }
}

/**
 * Task 3: November Benchmark Logic
 *
 * Detects if the system has no opening balance set but the imported statement
 * shows a starting balance. Prompts the user to create an Opening Balance Adjustment.
 *
 * Uses BCMath-like precision via JavaScript's built-in number handling for display,
 * with backend BCMath for actual financial calculations.
 */
async function checkMissingOpeningBalance(): Promise<void> {
  // Reload account to get current state
  await accountStore.loadAccount();

  // Check if system balance is effectively 0 (no opening balance set)
  const systemBalance = accountStore.openingBalance;
  const hasExistingBalance = accountStore.hasSetBalance;

  // If user already has a balance set, don't prompt
  if (hasExistingBalance && systemBalance !== 0) {
    return;
  }

  // Calculate statement's opening balance from first transaction
  // Opening balance = first transaction's balance_after - first transaction's amount
  if (!parseResult.value || parseResult.value.transactions.length === 0) {
    return;
  }

  // Sort by date to get the earliest transaction
  const sortedTransactions = [...parseResult.value.transactions].sort(
    (a, b) => new Date(a.transactionDate).getTime() - new Date(b.transactionDate).getTime()
  );

  const firstTx = sortedTransactions[0];

  // Need balance_after to calculate opening balance
  if (firstTx.balanceAfter === undefined || firstTx.balanceAfter === null) {
    return;
  }

  // Calculate opening balance: balance_after - amount = balance before transaction
  // Use precise arithmetic
  const balanceAfter = Number(firstTx.balanceAfter);
  const amount = Number(firstTx.amount);
  const calculatedOpeningBalance = Number((balanceAfter - amount).toFixed(2));

  // Only prompt if there's a significant opening balance to align
  if (calculatedOpeningBalance <= 0) {
    return;
  }

  // Store the detected opening balance and show prompt
  detectedOpeningBalance.value = calculatedOpeningBalance;

  // Get the statement start date for display
  const startDate = format(new Date(firstTx.transactionDate), 'MMMM do');

  $q.dialog({
    title: 'Opening Balance Detected',
    message: `I see a starting balance of R ${formatAmount(calculatedOpeningBalance)} on ${startDate}. Should I set this as your opening balance to align your 50/30/20 budget targets?`,
    cancel: {
      label: 'Not Now',
      flat: true,
    },
    ok: {
      label: 'Yes, Set Balance',
      color: 'primary',
    },
    persistent: true,
  }).onOk(async () => {
    await setDetectedOpeningBalance();
  });
}

async function setDetectedOpeningBalance(): Promise<void> {
  isSavingBalance.value = true;

  try {
    // Calculate the "current balance" that would result in this opening balance
    // current_balance = opening_balance + transaction_sum
    // So we need to set current_balance = detected_opening_balance + transaction_sum

    const transactionSum = accountStore.transactionSum;
    const targetCurrentBalance = Number(
      (detectedOpeningBalance.value + transactionSum).toFixed(2)
    );

    const success = await accountStore.setBalance(targetCurrentBalance);

    if (success) {
      $q.notify({
        type: 'positive',
        message: `Opening balance set to R ${formatAmount(detectedOpeningBalance.value)}`,
        position: 'bottom',
        timeout: 3000,
      });
    } else {
      $q.notify({
        type: 'negative',
        message: 'Failed to set opening balance',
        position: 'bottom',
      });
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to set opening balance',
      position: 'bottom',
    });
  } finally {
    isSavingBalance.value = false;
  }
}

function reset() {
  file.value = null;
  parseResult.value = null;
  importedCount.value = 0;
  duplicateCount.value = 0;
  categorizedCount.value = 0;
  skippedRecords.value = [];
  showSkippedDialog.value = false;
  importProgress.value = 0;
  autoMatchedCount.value = 0;
  needsManualAuditCount.value = 0;
  leaksCount.value = 0;
}

// Helper to group skipped records by reason
function groupSkippedByReason() {
  return skippedRecords.value.reduce((acc, record) => {
    const label = record.reason === 'duplicate_bank_ref' ? 'Existing Records (Bank Reference Match)'
      : record.reason === 'duplicate_composite' ? 'Existing Records (Date/Amount/Description Match)'
      : 'Duplicates Within This File';
    if (!acc[label]) acc[label] = [];
    acc[label].push(record);
    return acc;
  }, {} as Record<string, typeof skippedRecords.value>);
}

function formatDate(dateStr: string): string {
  return format(new Date(dateStr), 'dd MMM yyyy');
}

function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>
