<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Dashboard</div>

    <!-- Summary Cards -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-4">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Total Income</div>
            <div class="text-h5 text-positive">
              R {{ formatAmount(totalIncome) }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-4">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Total Expenses</div>
            <div class="text-h5 text-negative">
              R {{ formatAmount(totalExpenses) }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-4">
        <q-card class="dashboard-card">
          <q-card-section>
            <div class="text-caption text-grey">Net Balance</div>
            <div class="text-h5" :class="netBalance >= 0 ? 'text-positive' : 'text-negative'">
              R {{ formatAmount(netBalance) }}
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
  </q-page>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { format } from 'date-fns';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';

const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

const totalIncome = computed(() => transactionsStore.totalIncome);
const totalExpenses = computed(() => transactionsStore.totalExpenses);
const netBalance = computed(() => totalIncome.value - totalExpenses.value);
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);

const recentTransactions = computed(() => {
  return transactionsStore.filteredTransactions.slice(0, 10);
});

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
</script>

<style scoped>
.dashboard-card {
  border-radius: 12px;
}
</style>
