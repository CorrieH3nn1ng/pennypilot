<template>
  <q-page class="dev-page">
    <div class="page-header">
      <div class="header-title">Clear the Slate</div>
      <div class="header-subtitle">Dev utility for testing fresh UI state</div>
    </div>

    <!-- Warning Banner -->
    <q-banner class="bg-warning text-dark q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="warning" color="dark" />
      </template>
      <strong>Warning:</strong> This will permanently delete all local and server data.
      Only use this for testing purposes.
    </q-banner>

    <!-- Status Cards -->
    <div class="status-grid q-mb-md">
      <q-card flat bordered class="status-card">
        <q-card-section>
          <div class="status-label">Local Transactions</div>
          <div class="status-value">{{ localTxCount }}</div>
        </q-card-section>
      </q-card>

      <q-card flat bordered class="status-card">
        <q-card-section>
          <div class="status-label">Pending Sync</div>
          <div class="status-value" :class="pendingCount > 0 ? 'text-warning' : 'text-positive'">
            {{ pendingCount }}
          </div>
        </q-card-section>
      </q-card>

      <q-card flat bordered class="status-card">
        <q-card-section>
          <div class="status-label">Categories</div>
          <div class="status-value">{{ categoryCount }}</div>
        </q-card-section>
      </q-card>
    </div>

    <!-- Action Buttons -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Step 1: Wipe Local Data</div>
        <div class="text-caption text-grey q-mb-md">
          Clears IndexedDB, localStorage, and resets Pinia stores
        </div>
        <q-btn
          color="negative"
          label="Clear Local Data"
          icon="delete_sweep"
          :loading="isWipingLocal"
          @click="wipeLocalData"
        />
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Step 2: Wipe Server Data</div>
        <div class="text-caption text-grey q-mb-md">
          Truncates transactions, budgets, tax tables on backend
        </div>
        <q-btn
          color="negative"
          label="Clear Server Data"
          icon="cloud_off"
          :loading="isWipingServer"
          @click="wipeServerData"
        />
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Step 3: Seed Test Data</div>
        <div class="text-caption text-grey q-mb-md">
          Creates 9 test transactions for Tax Gap validation
        </div>
        <div class="seed-preview q-mb-md">
          <div class="seed-item text-positive">+ R30,000.00 - Salary (Income)</div>
          <div class="seed-item text-positive">+ R45,000.00 - Bonus December (Income)</div>
          <div class="seed-item text-positive">+ R3,500.00 - Extra Work Bonus (Income)</div>
          <div class="seed-item text-negative">- R1,000.00 - Medical Aid (Deductible)</div>
          <div class="seed-item text-negative">- R500.00 - Charity Donation (S18A)</div>
          <div class="seed-item text-negative">- R2,500.00 - Pick n Pay (Groceries)</div>
          <div class="seed-item text-negative">- R800.00 - Sasol Fuel (Transport)</div>
          <div class="seed-item text-negative">- R1,200.00 - Uber Trips (Transport)</div>
          <div class="seed-item text-negative">- R650.00 - Woolworths (Groceries)</div>
        </div>
        <q-btn
          color="primary"
          label="Seed Test Data"
          icon="add_circle"
          :loading="isSeeding"
          @click="seedTestData"
        />
      </q-card-section>
    </q-card>

    <!-- Quick Actions -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Quick Action: Full Reset + Seed</div>
        <div class="text-caption text-grey q-mb-md">
          Runs all three steps in sequence
        </div>
        <q-btn
          color="deep-purple"
          label="Clear Slate + Seed"
          icon="refresh"
          :loading="isFullReset"
          @click="fullResetAndSeed"
        />
      </q-card-section>
    </q-card>

    <!-- Log Output -->
    <q-card v-if="logs.length > 0" flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Log</div>
        <div class="log-output">
          <div v-for="(log, i) in logs" :key="i" class="log-line" :class="log.type">
            <span class="log-time">{{ log.time }}</span>
            <span class="log-msg">{{ log.message }}</span>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { localBaseService } from '@/services/storage/LocalBaseService';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useAccountStore } from '@/stores/account.store';
import { useBudgetStore } from '@/stores/budget.store';
import { useIncomeStore } from '@/stores/income.store';
import { useTaxStore } from '@/stores/tax.store';
import { useQuestStore } from '@/modules/gamification/stores/quest.store';
import { apiClient } from '@/services/api/client';
import { v4 as uuidv4 } from 'uuid';

const $q = useQuasar();

// Stores
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();
const accountStore = useAccountStore();
const budgetStore = useBudgetStore();
const incomeStore = useIncomeStore();
const taxStore = useTaxStore();
const questStore = useQuestStore();

// State
const isWipingLocal = ref(false);
const isWipingServer = ref(false);
const isSeeding = ref(false);
const isFullReset = ref(false);
const localTxCount = ref(0);
const pendingCount = ref(0);
const categoryCount = ref(0);

interface LogEntry {
  time: string;
  message: string;
  type: 'info' | 'success' | 'error';
}
const logs = ref<LogEntry[]>([]);

// Log helper
function log(message: string, type: 'info' | 'success' | 'error' = 'info') {
  const time = new Date().toLocaleTimeString();
  logs.value.push({ time, message, type });
}

// Refresh counts
async function refreshCounts() {
  try {
    const txs = await localBaseService.getAllTransactions();
    localTxCount.value = txs.length;
    pendingCount.value = txs.filter(t => t.sync_status === 'pending').length;

    const cats = await localBaseService.getAllCategories();
    categoryCount.value = cats.length;
  } catch {
    // Ignore errors during count refresh
  }
}

// Step 1: Wipe Local Data
async function wipeLocalData() {
  isWipingLocal.value = true;
  log('Starting local data wipe...');

  try {
    // Clear IndexedDB collections
    log('Clearing IndexedDB transactions...');
    await localBaseService.clearTransactions();

    log('Clearing IndexedDB categories...');
    try {
      const db = (localBaseService as unknown as { db: { collection: (name: string) => { delete: () => Promise<void> } } }).db;
      await db.collection('categories').delete();
    } catch { /* ignore */ }

    log('Clearing IndexedDB category rules...');
    try {
      const db = (localBaseService as unknown as { db: { collection: (name: string) => { delete: () => Promise<void> } } }).db;
      await db.collection('categoryRules').delete();
    } catch { /* ignore */ }

    log('Clearing IndexedDB oplog...');
    try {
      const db = (localBaseService as unknown as { db: { collection: (name: string) => { delete: () => Promise<void> } } }).db;
      await db.collection('oplog').delete();
    } catch { /* ignore */ }

    log('Clearing IndexedDB persona...');
    await localBaseService.clearPersona();

    log('Clearing IndexedDB sync metadata...');
    try {
      const db = (localBaseService as unknown as { db: { collection: (name: string) => { delete: () => Promise<void> } } }).db;
      await db.collection('_meta').delete();
    } catch { /* ignore */ }

    // Clear localStorage
    log('Clearing localStorage...');
    localStorage.clear();

    // Reset Pinia stores
    log('Resetting Pinia stores...');
    transactionsStore.$reset();
    categoriesStore.$reset();
    accountStore.$reset();
    budgetStore.$reset();
    incomeStore.$reset();
    taxStore.$reset();
    questStore.$reset();

    log('Local data wipe complete!', 'success');
    await refreshCounts();

    $q.notify({
      type: 'positive',
      message: 'Local data cleared successfully',
      position: 'bottom',
    });
  } catch (err) {
    log(`Error: ${err}`, 'error');
    $q.notify({
      type: 'negative',
      message: 'Failed to clear local data',
      position: 'bottom',
    });
  } finally {
    isWipingLocal.value = false;
  }
}

// Step 2: Wipe Server Data
async function wipeServerData() {
  isWipingServer.value = true;
  log('Starting server data wipe...');

  try {
    log('Calling /api/dev/clear-slate endpoint...');
    await apiClient.post('/dev/clear-slate');
    log('Server data wipe complete!', 'success');

    $q.notify({
      type: 'positive',
      message: 'Server data cleared successfully',
      position: 'bottom',
    });
  } catch (err) {
    log(`Error: ${err}`, 'error');
    $q.notify({
      type: 'negative',
      message: 'Failed to clear server data (endpoint may not exist yet)',
      position: 'bottom',
    });
  } finally {
    isWipingServer.value = false;
  }
}

// Step 3: Seed Test Data
async function seedTestData() {
  isSeeding.value = true;
  log('Starting test data seed...');

  try {
    // First reload categories from server
    log('Loading categories from server...');
    await categoriesStore.loadFromServer();

    // Find category IDs
    const categories = categoriesStore.categories;
    const incomeCategory = categories.find(c => c.name.toLowerCase() === 'income' || c.name.toLowerCase() === 'salary');
    const medicalCategory = categories.find(c => c.name.toLowerCase().includes('medical') || c.name.toLowerCase().includes('health'));
    const charityCategory = categories.find(c => c.name.toLowerCase().includes('charity') || c.name.toLowerCase().includes('donation'));
    const groceriesCategory = categories.find(c => c.name.toLowerCase().includes('groceries') || c.name.toLowerCase().includes('food'));
    const transportCategory = categories.find(c => c.name.toLowerCase().includes('transport') || c.name.toLowerCase().includes('fuel'));

    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];

    // Seed 9 transactions with explicit income (positive) and expense (negative) amounts
    const seedTransactions = [
      // INCOME - Explicit positive amounts (MUST show green with + prefix)
      {
        local_id: uuidv4(),
        description: 'SALARY - ACME CORPORATION',
        amount: 30000.00,  // Positive = Income
        transaction_date: dateStr,
        category_id: incomeCategory?.id || null,
        bank_reference: `SEED-SALARY-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'BONUS DECEMBER - ACME CORPORATION',
        amount: 45000.00,  // Positive = Income
        transaction_date: dateStr,
        category_id: incomeCategory?.id || null,
        bank_reference: `SEED-BONUS-DEC-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'EXTRA WORK BONUS - FREELANCE',
        amount: 3500.00,  // Positive = Income
        transaction_date: dateStr,
        category_id: incomeCategory?.id || null,
        bank_reference: `SEED-BONUS-EXTRA-${Date.now()}`,
        is_transfer: false,
      },
      // EXPENSES - Explicit negative amounts (MUST show red)
      {
        local_id: uuidv4(),
        description: 'DISCOVERY MEDICAL AID',
        amount: -1000.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: medicalCategory?.id || null,
        bank_reference: `SEED-MEDICAL-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'GIFT OF THE GIVERS DONATION',
        amount: -500.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: charityCategory?.id || null,
        bank_reference: `SEED-CHARITY-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'PICK N PAY ROSEBANK',
        amount: -2500.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: groceriesCategory?.id || null,
        bank_reference: `SEED-GROCERIES-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'SASOL SANDTON',
        amount: -800.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: transportCategory?.id || null,
        bank_reference: `SEED-FUEL-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'UBER TRIPS - DECEMBER',
        amount: -1200.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: transportCategory?.id || null,
        bank_reference: `SEED-UBER-${Date.now()}`,
        is_transfer: false,
      },
      {
        local_id: uuidv4(),
        description: 'WOOLWORTHS SANDTON CITY',
        amount: -650.00,  // Negative = Expense
        transaction_date: dateStr,
        category_id: groceriesCategory?.id || null,
        bank_reference: `SEED-WOOLIES-${Date.now()}`,
        is_transfer: false,
      },
    ];

    log(`Seeding ${seedTransactions.length} transactions...`);
    for (const tx of seedTransactions) {
      await localBaseService.addTransaction(tx);
      log(`Added: ${tx.description} (R${Math.abs(tx.amount).toLocaleString()})`, 'success');
    }

    // Reload transactions store
    log('Reloading transactions store...');
    await transactionsStore.loadFromLocal();

    log('Test data seed complete!', 'success');
    await refreshCounts();

    $q.notify({
      type: 'positive',
      message: '9 test transactions seeded successfully',
      position: 'bottom',
    });
  } catch (err) {
    log(`Error: ${err}`, 'error');
    $q.notify({
      type: 'negative',
      message: 'Failed to seed test data',
      position: 'bottom',
    });
  } finally {
    isSeeding.value = false;
  }
}

// Full Reset + Seed
async function fullResetAndSeed() {
  isFullReset.value = true;
  logs.value = [];

  try {
    await wipeLocalData();
    await wipeServerData();
    await seedTestData();

    log('Full reset and seed complete!', 'success');
  } finally {
    isFullReset.value = false;
  }
}

onMounted(async () => {
  await refreshCounts();
});
</script>

<style scoped>
.dev-page {
  padding: 16px;
  padding-bottom: 80px;
  background: #FAFAFA;
}

.page-header {
  margin-bottom: 16px;
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #C62828;
}

.header-subtitle {
  font-size: 14px;
  color: #78909C;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.status-card {
  border-radius: 12px;
  text-align: center;
}

.status-label {
  font-size: 11px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-value {
  font-size: 24px;
  font-weight: 700;
  color: #263238;
}

.seed-preview {
  background: #ECEFF1;
  border-radius: 8px;
  padding: 12px;
  font-family: monospace;
  font-size: 12px;
}

.seed-item {
  padding: 4px 0;
}

.log-output {
  background: #263238;
  border-radius: 8px;
  padding: 12px;
  max-height: 300px;
  overflow-y: auto;
  font-family: monospace;
  font-size: 11px;
}

.log-line {
  padding: 2px 0;
}

.log-line.info {
  color: #90A4AE;
}

.log-line.success {
  color: #4CAF50;
}

.log-line.error {
  color: #EF5350;
}

.log-time {
  color: #546E7A;
  margin-right: 8px;
}

.log-msg {
  color: inherit;
}

.text-positive { color: #2E7D32; }
.text-negative { color: #C62828; }
.text-warning { color: #F57C00; }
</style>
