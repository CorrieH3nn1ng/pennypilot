<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Dashboard</div>

    <!-- Balance Setup Banner -->
    <q-banner v-if="!hasSetBalance" class="bg-info text-white q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="account_balance" />
      </template>
      Set your current bank balance to see accurate totals.
      <template v-slot:action>
        <q-btn flat label="Set Balance" @click="showBalanceDialog = true" />
      </template>
    </q-banner>

    <!-- Summary Cards -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Current Balance</div>
            <div class="text-h5" :class="calculatedBalance >= 0 ? 'text-positive' : 'text-negative'">
              R {{ formatAmount(calculatedBalance) }}
            </div>
            <q-btn
              v-if="hasSetBalance"
              flat
              dense
              size="sm"
              icon="edit"
              class="q-mt-xs"
              @click="showBalanceDialog = true"
            >
              Update
            </q-btn>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Total Income</div>
            <div class="text-h5 text-positive">
              R {{ formatAmount(totalIncome) }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Total Expenses</div>
            <div class="text-h5 text-negative">
              R {{ formatAmount(totalExpenses) }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Opening Balance</div>
            <div class="text-h5 text-grey-8">
              R {{ formatAmount(openingBalance) }}
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Alerts -->
    <q-banner v-if="uncategorizedCount > 0" class="bg-warning text-white q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="warning" />
      </template>
      You have {{ uncategorizedCount }} uncategorized transactions.
      <template v-slot:action>
        <q-btn flat label="Categorize" to="/transactions?filter=uncategorized" />
      </template>
    </q-banner>

    <!-- Charts -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-6">
        <SpendingByCategory />
      </div>
      <div class="col-12 col-md-6">
        <MonthlyTrend />
      </div>
    </div>

    <!-- Recent Transactions -->
    <q-card class="q-mb-md">
      <q-card-section>
        <div class="text-h6">Recent Transactions</div>
      </q-card-section>

      <q-list separator>
        <q-item v-for="tx in recentTransactions" :key="tx.local_id" class="transaction-item">
          <q-item-section avatar>
            <q-avatar :style="{ backgroundColor: getCategoryColor(tx.category_id) }">
              <q-icon :name="getCategoryIcon(tx.category_id)" color="white" />
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label>{{ tx.description }}</q-item-label>
            <q-item-label caption>
              {{ formatDate(tx.transaction_date) }} - {{ getCategoryName(tx.category_id) }}
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <q-item-label :class="tx.amount >= 0 ? 'text-positive' : 'text-negative'">
              {{ tx.amount >= 0 ? '+' : '' }}R {{ formatAmount(Math.abs(tx.amount)) }}
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item v-if="recentTransactions.length === 0">
          <q-item-section>
            <q-item-label class="text-grey text-center">
              No transactions yet. Import a CSV to get started.
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>

      <q-card-actions v-if="recentTransactions.length > 0">
        <q-btn flat color="primary" to="/transactions">View All Transactions</q-btn>
      </q-card-actions>
    </q-card>

    <!-- Quick Actions -->
    <div class="row q-col-gutter-md">
      <div class="col-6">
        <q-btn color="primary" class="full-width" to="/import" icon="upload_file" label="Import CSV" />
      </div>
      <div class="col-6">
        <q-btn outline color="primary" class="full-width" to="/transactions" icon="add" label="Add Transaction" />
      </div>
    </div>

    <!-- Set Balance Dialog -->
    <q-dialog v-model="showBalanceDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Set Current Balance</div>
          <div class="text-caption text-grey">
            Enter your current bank balance to calculate the correct opening balance.
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model.number="newBalance"
            type="number"
            label="Current Bank Balance"
            prefix="R"
            outlined
            autofocus
            :rules="[(v) => v !== null && v !== '' || 'Balance is required']"
          />

          <div v-if="newBalance" class="q-mt-md text-body2">
            <div class="row justify-between">
              <span>Your bank balance:</span>
              <span class="text-weight-medium">R {{ formatAmount(newBalance || 0) }}</span>
            </div>
            <div class="row justify-between">
              <span>Sum of transactions:</span>
              <span>R {{ formatAmount(transactionSum) }}</span>
            </div>
            <q-separator class="q-my-sm" />
            <div class="row justify-between text-weight-bold">
              <span>Calculated opening balance:</span>
              <span>R {{ formatAmount((newBalance || 0) - transactionSum) }}</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Save Balance"
            :loading="isSaving"
            :disable="!newBalance"
            @click="saveBalance"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { format } from 'date-fns';
import { useQuasar } from 'quasar';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useAccountStore } from '@/stores/account.store';
import SpendingByCategory from '@/components/charts/SpendingByCategory.vue';
import MonthlyTrend from '@/components/charts/MonthlyTrend.vue';

const $q = useQuasar();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();
const accountStore = useAccountStore();

// Balance dialog state
const showBalanceDialog = ref(false);
const newBalance = ref<number | null>(null);
const isSaving = ref(false);

// Computed
const totalIncome = computed(() => transactionsStore.totalIncome);
const totalExpenses = computed(() => transactionsStore.totalExpenses);
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);
const openingBalance = computed(() => accountStore.openingBalance);
const calculatedBalance = computed(() => accountStore.calculatedBalance);
const hasSetBalance = computed(() => accountStore.hasSetBalance);
const transactionSum = computed(() => accountStore.transactionSum);

const recentTransactions = computed(() => {
  return transactionsStore.filteredTransactions.slice(0, 10);
});

// Methods
function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(dateStr: string): string {
  return format(new Date(dateStr), 'dd MMM yyyy');
}

function getCategoryColor(id: string | null): string {
  return categoriesStore.getCategoryColor(id);
}

function getCategoryIcon(id: string | null): string {
  return categoriesStore.getCategoryIcon(id);
}

function getCategoryName(id: string | null): string {
  return categoriesStore.getCategoryName(id);
}

async function saveBalance() {
  if (!newBalance.value) return;

  isSaving.value = true;

  const success = await accountStore.setBalance(newBalance.value);

  if (success) {
    $q.notify({
      type: 'positive',
      message: 'Balance updated successfully',
    });
    showBalanceDialog.value = false;
    newBalance.value = null;
  } else {
    $q.notify({
      type: 'negative',
      message: accountStore.error || 'Failed to update balance',
    });
  }

  isSaving.value = false;
}

onMounted(async () => {
  await accountStore.loadAccount();
});
</script>

<style scoped>
.dashboard-card {
  border-radius: 12px;
}
</style>
