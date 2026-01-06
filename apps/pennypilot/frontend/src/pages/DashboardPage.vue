<template>
  <q-page class="dashboard-page">
    <!-- Premium Loading Experience -->
    <PennyLoading :showing="isLoading" />

    <!-- SURVIVAL MODE Banner (Critical Alert - Jan 5-26, 2026) -->
    <SurvivalModeBanner />

    <!-- Financial Snapshot (Single Consolidated Card) -->
    <FinancialSnapshot
      class="q-mb-sm"
      @set-balance="showBalanceDialog = true"
    />

    <!-- Milestone Alerts (Budget Review Reminders) -->
    <MilestoneAlert />

    <!-- Methodology-Based Budget Display -->
    <MethodologyDisplay class="q-mb-sm" />

    <!-- Penny's Insight (Dismissible) -->
    <PennyInsightCard class="q-mb-sm" />

    <!-- Quest Hub (Unified Quest + Alerts) -->
    <QuestHub
      :is-auto-categorizing="isAutoCategorizing"
      class="q-mb-sm"
      @auto-categorize="runAutoCategorize"
      @open-quest="showBronzePilotQuest = true"
      @open-interrogator="showPennyInterrogator = true"
    />

    <!-- Chart Carousel (Swipeable Data Visuals) -->
    <ChartCarousel class="q-mb-sm" />

    <!-- Recent Transactions (Compact) -->
    <q-card
      v-if="recentTransactions.length > 0"
      class="transactions-card"
      flat
      bordered
    >
      <div class="transactions-header">
        <span class="transactions-title">Recent</span>
        <q-btn
          flat
          dense
          size="xs"
          color="teal"
          label="All"
          icon-right="chevron_right"
          to="/transactions"
        />
      </div>

      <q-list dense class="transactions-list">
        <q-item
          v-for="tx in recentTransactions"
          :key="tx.local_id"
          class="transaction-item"
          clickable
          :to="`/transactions?id=${tx.local_id}`"
        >
          <q-item-section avatar>
            <q-avatar
              size="28px"
              :style="{ backgroundColor: getCategoryColor(tx.category_id) }"
            >
              <q-icon
                :name="getCategoryIcon(tx.category_id)"
                color="white"
                size="14px"
              />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="tx-desc">{{ tx.description }}</q-item-label>
          </q-item-section>

          <q-item-section side>
            <q-item-label
              class="tx-amount"
              :class="Number(tx.amount) >= 0 ? 'text-positive' : 'text-negative'"
            >
              {{ Number(tx.amount) >= 0 ? '+' : '' }}{{ configStore.currencySymbol }} {{ formatCompact(tx.amount) }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-card>

    <!-- Primary Action FAB (QPageSticky) -->
    <q-page-sticky position="bottom-right" :offset="[16, 72]">
      <q-btn
        fab
        color="primary"
        icon="add"
        class="action-fab"
      >
        <q-menu anchor="top right" self="bottom right">
          <q-list dense style="min-width: 180px">
            <q-item clickable v-close-popup to="/import">
              <q-item-section avatar>
                <q-icon name="upload_file" color="teal" />
              </q-item-section>
              <q-item-section>Import CSV</q-item-section>
            </q-item>
            <q-item clickable v-close-popup to="/transactions?add=true">
              <q-item-section avatar>
                <q-icon name="edit" color="teal" />
              </q-item-section>
              <q-item-section>Add Transaction</q-item-section>
            </q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="showPennyInterrogator = true">
              <q-item-section avatar>
                <q-icon name="psychology" color="teal" />
              </q-item-section>
              <q-item-section>Assign Buckets</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </q-page-sticky>

    <!-- Bronze Pilot Quest Modal -->
    <BronzePilotQuest v-model="showBronzePilotQuest" />

    <!-- Penny Interrogator (Bucket Assignment) -->
    <PennyInterrogator v-model="showPennyInterrogator" />

    <!-- Set Balance Dialog -->
    <q-dialog v-model="showBalanceDialog" persistent>
      <q-card class="balance-dialog">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Set Current Balance</div>
          <div class="text-caption text-grey">
            Enter your bank balance to calculate opening balance.
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model.number="newBalance"
            type="number"
            label="Current Bank Balance"
            :prefix="configStore.currencySymbol"
            outlined
            dense
            autofocus
            :rules="[(v) => v !== null && v !== '' || 'Required']"
          />

          <div v-if="newBalance" class="q-mt-sm text-caption">
            <div class="row justify-between">
              <span>Bank balance:</span>
              <span>{{ configStore.currencySymbol }} {{ formatAmount(newBalance || 0) }}</span>
            </div>
            <div class="row justify-between">
              <span>Transaction sum:</span>
              <span>{{ configStore.currencySymbol }} {{ formatAmount(transactionSum) }}</span>
            </div>
            <q-separator class="q-my-xs" />
            <div class="row justify-between text-weight-bold">
              <span>Opening balance:</span>
              <span>{{ configStore.currencySymbol }} {{ formatAmount((newBalance || 0) - transactionSum) }}</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-none">
          <q-btn flat label="Cancel" size="sm" v-close-popup />
          <q-btn
            color="primary"
            label="Save"
            size="sm"
            :loading="isSaving"
            :disable="!newBalance"
            @click="saveBalance"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Balance Confirmation Modal (Balancing Engine) -->
    <BalanceConfirmationModal
      v-model="showBalanceConfirmation"
      :derived-balance="derivedBalance"
      :current-balance="newBalance || 0"
      :total-inflows="totalInflows"
      :total-outflows="totalOutflows"
      :transaction-count="transactionCount"
      :date-range="dateRange"
      :user-provided-balance="accountStore.openingBalance"
      :is-free-user="userStore.isFreeUser"
      @confirm="onBalanceConfirmed"
      @manual-entry="onManualBalanceEntry"
    />
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useAccountStore } from '@/stores/account.store';
import { useBudgetStore } from '@/stores/budget.store';
import { useIncomeStore } from '@/stores/income.store';
import { useUserStore } from '@/stores/user.store';
import { useConfigStore } from '@/stores/config.store';
import { useQuestStore } from '@/modules/gamification/stores/quest.store';
import { getDerivedOpeningBalance } from '@/services/balancing/BalancingEngine';

// Components
import PennyLoading from '@/components/PennyLoading.vue';
import FinancialSnapshot from '@/components/dashboard/FinancialSnapshot.vue';
import MethodologyDisplay from '@/components/dashboard/MethodologyDisplay.vue';
import PennyInsightCard from '@/components/PennyInsightCard.vue';
import QuestHub from '@/components/dashboard/QuestHub.vue';
import ChartCarousel from '@/components/dashboard/ChartCarousel.vue';
import BronzePilotQuest from '@/modules/gamification/components/BronzePilotQuest.vue';
import PennyInterrogator from '@/components/PennyInterrogator.vue';
import BalanceConfirmationModal from '@/components/BalanceConfirmationModal.vue';
import MilestoneAlert from '@/components/dashboard/MilestoneAlert.vue';
import SurvivalModeBanner from '@/components/dashboard/SurvivalModeBanner.vue';

const $q = useQuasar();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();
const accountStore = useAccountStore();
const budgetStore = useBudgetStore();
const incomeStore = useIncomeStore();
const userStore = useUserStore();
const configStore = useConfigStore();
const questStore = useQuestStore();

// Loading state
const isLoading = ref(true);

// Dialog state
const showBalanceDialog = ref(false);
const showBalanceConfirmation = ref(false);
const showBronzePilotQuest = ref(false);
const showPennyInterrogator = ref(false);
const newBalance = ref<number | null>(null);
const isSaving = ref(false);
const isAutoCategorizing = ref(false);

// Balancing engine computed values
const balancingResult = computed(() => {
  if (!newBalance.value) return null;
  return getDerivedOpeningBalance(newBalance.value, transactionsStore.transactions);
});

const derivedBalance = computed(() => balancingResult.value?.derivedOpeningBalance ?? 0);
const totalInflows = computed(() => balancingResult.value?.totalInflows ?? 0);
const totalOutflows = computed(() => balancingResult.value?.totalOutflows ?? 0);
const transactionCount = computed(() => balancingResult.value?.transactionCount ?? 0);
const dateRange = computed(() => balancingResult.value?.dateRange ?? { earliest: null, latest: null });

// Computed
const transactionSum = computed(() => accountStore.transactionSum);

const recentTransactions = computed(() => {
  return transactionsStore.filteredTransactions.slice(0, 7);
});

// Methods
function formatAmount(amount: number | string): string {
  const num = Number(amount) || 0;
  return num.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatCompact(amount: number | string): string {
  // Full precision for institutional trust - no abbreviations
  const num = Number(amount) || 0;
  return Math.abs(num).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function getCategoryColor(id: string | null): string {
  return categoriesStore.getCategoryColor(id);
}

function getCategoryIcon(id: string | null): string {
  return categoriesStore.getCategoryIcon(id);
}

async function runAutoCategorize() {
  isAutoCategorizing.value = true;

  try {
    const result = await transactionsStore.autoCategorize(
      categoriesStore.categories,
      true
    );

    if (result.categorized > 0) {
      $q.notify({
        type: 'positive',
        message: `Auto-categorized ${result.categorized} transactions`,
        position: 'bottom',
        timeout: 2000,
      });
    } else {
      $q.notify({
        type: 'info',
        message: 'No transactions could be auto-categorized',
        position: 'bottom',
        timeout: 2000,
      });
    }
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Auto-categorization failed',
      position: 'bottom',
    });
  } finally {
    isAutoCategorizing.value = false;
  }
}

async function saveBalance() {
  if (!newBalance.value) return;

  // If we have transactions, show the confirmation modal with derived balance
  if (transactionsStore.transactions.length > 0) {
    showBalanceDialog.value = false;
    showBalanceConfirmation.value = true;
    return;
  }

  // No transactions - just save directly
  isSaving.value = true;

  const success = await accountStore.setBalance(newBalance.value);

  if (success) {
    $q.notify({
      type: 'positive',
      message: 'Balance updated',
      position: 'bottom',
      timeout: 2000,
    });
    showBalanceDialog.value = false;
    newBalance.value = null;
  } else {
    $q.notify({
      type: 'negative',
      message: accountStore.error || 'Failed to update',
      position: 'bottom',
    });
  }

  isSaving.value = false;
}

// Balance confirmation modal handlers
async function onBalanceConfirmed(balance: number) {
  isSaving.value = true;

  const success = await accountStore.setBalance(balance);

  if (success) {
    $q.notify({
      type: 'positive',
      message: 'Opening balance set successfully',
      position: 'bottom',
      timeout: 2000,
    });
    showBalanceConfirmation.value = false;
    newBalance.value = null;
  } else {
    $q.notify({
      type: 'negative',
      message: accountStore.error || 'Failed to update balance',
      position: 'bottom',
    });
  }

  isSaving.value = false;
}

function onManualBalanceEntry() {
  // Go back to manual entry dialog
  showBalanceConfirmation.value = false;
  showBalanceDialog.value = true;
}

onMounted(async () => {
  try {
    await Promise.all([
      accountStore.loadAccount(),
      budgetStore.loadCurrentBudget(),
      incomeStore.loadIncomeSources(),
      questStore.loadPersona(),
    ]);
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
.dashboard-page {
  padding: 12px;
  padding-bottom: 80px;
  background: #FAFAFA;
}

/* Transactions Card */
.transactions-card {
  border-radius: 12px;
  border-color: #B2DFDB;
}

.transactions-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #FAFAFA;
  border-bottom: 1px solid #ECEFF1;
}

.transactions-title {
  font-size: 12px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.transactions-list {
  padding: 0;
}

.transaction-item {
  min-height: 40px;
  padding: 6px 12px;
  border-bottom: 1px solid #F5F5F5;
}

.transaction-item:last-child {
  border-bottom: none;
}

.tx-desc {
  font-size: 13px;
  color: #37474F;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 180px;
}

.tx-amount {
  font-size: 13px;
  font-weight: 600;
}

/* Action FAB */
.action-fab {
  box-shadow: 0 4px 16px rgba(0, 77, 64, 0.35);
}

/* Balance Dialog */
.balance-dialog {
  min-width: 300px;
  border-radius: 12px;
}

/* Text colors */
.text-positive {
  color: #2E7D32;
}

.text-negative {
  color: #C62828;
}
</style>
