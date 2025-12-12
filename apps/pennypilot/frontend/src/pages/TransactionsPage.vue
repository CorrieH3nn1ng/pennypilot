<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5">Transactions</div>
      <q-space />
      <q-btn flat round icon="filter_list" @click="showFilters = !showFilters">
        <q-badge v-if="hasActiveFilters" color="primary" floating />
      </q-btn>
    </div>

    <!-- Filters -->
    <q-slide-transition>
      <q-card v-if="showFilters" class="q-mb-md">
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-4">
              <q-input v-model="searchQuery" label="Search" outlined dense clearable>
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
            </div>
            <div class="col-12 col-sm-4">
              <q-select
                v-model="categoryFilter"
                :options="categoryOptions"
                label="Category"
                outlined
                dense
                clearable
                emit-value
                map-options
              />
            </div>
            <div class="col-12 col-sm-4">
              <q-select
                v-model="categorizedFilter"
                :options="[
                  { label: 'All', value: null },
                  { label: 'Categorized', value: true },
                  { label: 'Uncategorized', value: false },
                ]"
                label="Status"
                outlined
                dense
                emit-value
                map-options
              />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-slide-transition>

    <!-- Transaction List -->
    <q-list separator>
      <q-item
        v-for="tx in transactions"
        :key="tx.local_id"
        clickable
        @click="selectTransaction(tx)"
      >
        <q-item-section avatar>
          <q-avatar :style="{ backgroundColor: getCategoryColor(tx.category_id) }">
            <q-icon :name="getCategoryIcon(tx.category_id)" color="white" />
          </q-avatar>
        </q-item-section>

        <q-item-section>
          <q-item-label>{{ tx.description }}</q-item-label>
          <q-item-label caption>
            {{ formatDate(tx.transaction_date) }}
            <q-chip
              v-if="!tx.is_categorized"
              size="sm"
              color="warning"
              text-color="white"
              dense
            >
              Uncategorized
            </q-chip>
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <q-item-label :class="tx.amount >= 0 ? 'text-positive' : 'text-negative'">
            {{ tx.amount >= 0 ? '+' : '' }}R {{ formatAmount(Math.abs(tx.amount)) }}
          </q-item-label>
        </q-item-section>
      </q-item>

      <q-item v-if="transactions.length === 0">
        <q-item-section class="text-center text-grey q-py-xl">
          <q-icon name="receipt_long" size="48px" class="q-mb-md" />
          <div>No transactions found</div>
          <q-btn flat color="primary" label="Import CSV" to="/import" class="q-mt-md" />
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Category Edit Dialog -->
    <q-dialog v-model="showCategoryDialog">
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Assign Category</div>
          <div class="text-caption text-grey">{{ selectedTransaction?.description }}</div>
        </q-card-section>

        <q-card-section>
          <q-select
            v-model="selectedCategoryId"
            :options="allCategoryOptions"
            label="Category"
            outlined
            emit-value
            map-options
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Save" @click="saveCategory" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { format } from 'date-fns';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import type { Transaction } from '@/types';

const route = useRoute();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

const showFilters = ref(false);
const searchQuery = ref('');
const categoryFilter = ref<string | null>(null);
const categorizedFilter = ref<boolean | null>(null);

const showCategoryDialog = ref(false);
const selectedTransaction = ref<Transaction | null>(null);
const selectedCategoryId = ref<string | null>(null);

const transactions = computed(() => transactionsStore.filteredTransactions);

const hasActiveFilters = computed(() => {
  return searchQuery.value || categoryFilter.value || categorizedFilter.value !== null;
});

const categoryOptions = computed(() => {
  return [
    { label: 'All Categories', value: null },
    ...categoriesStore.categories.map((c) => ({
      label: c.name,
      value: c.id,
    })),
  ];
});

const allCategoryOptions = computed(() => {
  return [
    { label: 'Uncategorized', value: null },
    ...categoriesStore.expenseCategories.map((c) => ({
      label: c.name,
      value: c.id,
    })),
    ...categoriesStore.incomeCategories.map((c) => ({
      label: `${c.name} (Income)`,
      value: c.id,
    })),
  ];
});

// Apply filters
watch([searchQuery, categoryFilter, categorizedFilter], () => {
  transactionsStore.setFilters({
    searchQuery: searchQuery.value,
    categoryId: categoryFilter.value,
    isCategorized: categorizedFilter.value,
  });
});

// Handle URL filter parameter
onMounted(() => {
  if (route.query.filter === 'uncategorized') {
    categorizedFilter.value = false;
    showFilters.value = true;
  }
});

function selectTransaction(tx: Transaction) {
  selectedTransaction.value = tx;
  selectedCategoryId.value = tx.category_id;
  showCategoryDialog.value = true;
}

async function saveCategory() {
  if (selectedTransaction.value) {
    await transactionsStore.updateCategory(
      selectedTransaction.value.local_id,
      selectedCategoryId.value
    );
    showCategoryDialog.value = false;
  }
}

function formatDate(dateStr: string): string {
  return format(new Date(dateStr), 'dd MMM yyyy');
}

function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
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
</script>
