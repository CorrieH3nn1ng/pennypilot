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
      <q-card>
        <q-card-section class="row items-center">
          <q-avatar icon="check_circle" color="positive" text-color="white" />
          <span class="q-ml-sm text-h6">Import Successful!</span>
        </q-card-section>
        <q-card-section>
          {{ importedCount }} transactions have been imported.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="View Transactions" to="/transactions" v-close-popup />
          <q-btn flat label="Import More" @click="reset" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Papa from 'papaparse';
import * as XLSX from 'xlsx';
import { format } from 'date-fns';
import { useQuasar } from 'quasar';
import { ParserFactory } from '@/services/parsers/ParserFactory';
import { useTransactionsStore } from '@/stores/transactions.store';
import type { ParseResult, BankFormat, Transaction } from '@/types';

const $q = useQuasar();
const transactionsStore = useTransactionsStore();

const file = ref<File | null>(null);
const isProcessing = ref(false);
const isImporting = ref(false);
const parseResult = ref<ParseResult | null>(null);
const detectedFormat = ref<BankFormat>('nedbank');
const showSuccess = ref(false);
const importedCount = ref(0);

async function handleFileSelect(selectedFile: File | null) {
  if (!selectedFile) {
    reset();
    return;
  }

  isProcessing.value = true;
  parseResult.value = null;

  try {
    let rows: Record<string, string>[];

    if (selectedFile.name.endsWith('.csv')) {
      rows = await parseCsv(selectedFile);
    } else {
      rows = await parseExcel(selectedFile);
    }

    if (rows.length === 0) {
      throw new Error('No data found in file');
    }

    // Detect bank format from headers
    const headers = Object.keys(rows[0]);
    detectedFormat.value = ParserFactory.detectFormat(headers);

    const parser = ParserFactory.create(detectedFormat.value);
    parseResult.value = parser.parse(rows);
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

function parseCsv(file: File): Promise<Record<string, string>[]> {
  return new Promise((resolve, reject) => {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: (results) => {
        resolve(results.data as Record<string, string>[]);
      },
      error: (error) => {
        reject(error);
      },
    });
  });
}

async function parseExcel(file: File): Promise<Record<string, string>[]> {
  const arrayBuffer = await file.arrayBuffer();
  const workbook = XLSX.read(arrayBuffer, { type: 'array' });
  const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
  return XLSX.utils.sheet_to_json(firstSheet);
}

async function importTransactions() {
  if (!parseResult.value || parseResult.value.transactions.length === 0) return;

  isImporting.value = true;

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

    const count = await transactionsStore.importTransactions(transactions as Transaction[]);
    importedCount.value = count;
    showSuccess.value = true;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to import transactions',
    });
  } finally {
    isImporting.value = false;
  }
}

function reset() {
  file.value = null;
  parseResult.value = null;
  detectedFormat.value = 'nedbank';
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
