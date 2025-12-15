<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5">Transactions</div>
      <q-space />
      <q-btn flat round icon="filter_list" @click="showFilters = !showFilters">
        <q-badge v-if="hasActiveFilters" color="primary" floating />
      </q-btn>
    </div>

    <!-- Quick Filter Tabs -->
    <q-tabs v-model="quickTab" class="q-mb-md" align="left" dense>
      <q-tab name="all" label="All" />
      <q-tab name="uncategorized">
        <span>Uncategorized</span>
        <q-badge v-if="uncategorizedCount > 0" color="warning" class="q-ml-sm">
          {{ uncategorizedCount }}
        </q-badge>
      </q-tab>
      <q-tab name="categorized" label="Categorized" />
    </q-tabs>

    <!-- Monthly Summary Cards -->
    <div v-if="quickTab !== 'uncategorized' && !selectedMonth" class="row q-col-gutter-sm q-mb-md">
      <div v-for="month in monthlyTotals" :key="month.key" class="col-6 col-sm-4 col-md-3">
        <q-card
          class="cursor-pointer monthly-card"
          :class="{ 'selected-month': selectedMonth === month.key }"
          @click="selectMonth(month.key)"
        >
          <q-card-section class="q-pa-sm">
            <div class="text-subtitle2 text-grey-8">{{ month.label }}</div>
            <div class="row justify-between q-mt-xs">
              <div>
                <div class="text-caption text-grey">Income</div>
                <div class="text-positive text-weight-medium">+R {{ formatCompact(month.income) }}</div>
              </div>
              <div class="text-right">
                <div class="text-caption text-grey">Expenses</div>
                <div class="text-negative text-weight-medium">-R {{ formatCompact(month.expenses) }}</div>
              </div>
            </div>
            <div class="text-caption text-grey q-mt-xs">{{ month.count }} transactions</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Selected Month Header -->
    <q-banner v-if="selectedMonth" class="bg-primary text-white q-mb-md" rounded>
      <template v-slot:avatar>
        <q-icon name="calendar_month" />
      </template>
      {{ selectedMonthLabel }}
      <template v-slot:action>
        <q-btn flat label="Show All Months" @click="clearMonthFilter" />
      </template>
    </q-banner>

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
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Assign Category</div>
          <div class="text-caption text-grey">{{ selectedTransaction?.description }}</div>
        </q-card-section>

        <q-card-section>
          <q-select
            v-model="selectedCategoryId"
            :options="allCategoryOptionsWithCreate"
            label="Category"
            outlined
            emit-value
            map-options
            @update:model-value="onCategorySelected"
          >
            <template v-slot:option="scope">
              <q-item v-bind="scope.itemProps">
                <q-item-section avatar v-if="scope.opt.value === '__create__'">
                  <q-icon name="add_circle" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label :class="scope.opt.value === '__create__' ? 'text-primary text-weight-medium' : ''">
                    {{ scope.opt.label }}
                  </q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <!-- Apply to similar transactions -->
          <div v-if="selectedCategoryId && similarCount > 0" class="q-mt-md">
            <q-separator class="q-mb-md" />
            <q-toggle
              v-model="applyToSimilar"
              :label="`Apply to ${similarCount} similar transaction${similarCount > 1 ? 's' : ''}`"
            />
            <div v-if="applyToSimilar" class="q-mt-sm">
              <div class="text-caption text-grey q-mb-xs">Pattern to match:</div>
              <q-input
                v-model="matchPattern"
                outlined
                dense
                hint="Transactions containing this text will be categorized"
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            :label="applyToSimilar ? `Save (${similarCount + 1} items)` : 'Save'"
            :loading="isSaving"
            @click="saveCategory"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Quick Create Category Dialog -->
    <q-dialog v-model="showQuickCreateDialog" persistent>
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">Quick Add Category</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="quickCategory.name"
            label="Category Name"
            outlined
            autofocus
          />

          <q-select
            v-model="quickCategory.is_income"
            :options="[
              { label: 'Expense', value: false },
              { label: 'Income', value: true },
            ]"
            label="Type"
            outlined
            emit-value
            map-options
          />

          <div>
            <div class="text-caption text-grey q-mb-xs">Color</div>
            <div class="row q-gutter-xs">
              <q-avatar
                v-for="color in quickColorOptions"
                :key="color"
                :style="{ backgroundColor: color, cursor: 'pointer' }"
                size="28px"
                @click="quickCategory.color = color"
              >
                <q-icon v-if="quickCategory.color === color" name="check" color="white" size="16px" />
              </q-avatar>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="cancelQuickCreate" />
          <q-btn
            color="primary"
            label="Create & Use"
            :loading="isCreatingCategory"
            :disable="!quickCategory.name"
            @click="createAndUseCategory"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { format, parseISO, startOfMonth, endOfMonth } from 'date-fns';
import { useQuasar } from 'quasar';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import type { Transaction } from '@/types';

const $q = useQuasar();
const route = useRoute();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

const showFilters = ref(false);
const searchQuery = ref('');
const categoryFilter = ref<string | null>(null);
const categorizedFilter = ref<boolean | null>(null);
const quickTab = ref('all');
const selectedMonth = ref<string | null>(null);

const showCategoryDialog = ref(false);
const selectedTransaction = ref<Transaction | null>(null);
const selectedCategoryId = ref<string | null>(null);
const applyToSimilar = ref(false);
const matchPattern = ref('');
const similarCount = ref(0);
const isSaving = ref(false);

// Quick create category
const showQuickCreateDialog = ref(false);
const isCreatingCategory = ref(false);
const quickCategory = ref({
  name: '',
  color: '#1976D2',
  is_income: false,
});
const quickColorOptions = [
  '#F44336', '#E91E63', '#9C27B0', '#3F51B5',
  '#1976D2', '#00BCD4', '#4CAF50', '#FF9800',
];

const transactions = computed(() => transactionsStore.filteredTransactions);
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);

const hasActiveFilters = computed(() => {
  return searchQuery.value || categoryFilter.value;
});

// Monthly totals for cards
interface MonthlyTotal {
  key: string;
  label: string;
  income: number;
  expenses: number;
  count: number;
}

const monthlyTotals = computed<MonthlyTotal[]>(() => {
  // Get base transactions based on current tab (all or categorized only)
  const baseTransactions = quickTab.value === 'categorized'
    ? transactionsStore.transactions.filter(t => t.is_categorized)
    : transactionsStore.transactions;

  // Group by month
  const grouped = new Map<string, { income: number; expenses: number; count: number }>();

  baseTransactions.forEach((t) => {
    const date = parseISO(t.transaction_date);
    const monthKey = format(startOfMonth(date), 'yyyy-MM');

    if (!grouped.has(monthKey)) {
      grouped.set(monthKey, { income: 0, expenses: 0, count: 0 });
    }

    const data = grouped.get(monthKey)!;
    if (t.amount >= 0) {
      data.income += t.amount;
    } else {
      data.expenses += Math.abs(t.amount);
    }
    data.count++;
  });

  // Convert to array and sort by month descending
  const result: MonthlyTotal[] = [];
  grouped.forEach((data, monthKey) => {
    result.push({
      key: monthKey,
      label: format(parseISO(monthKey + '-01'), 'MMM yyyy'),
      income: data.income,
      expenses: data.expenses,
      count: data.count,
    });
  });

  return result.sort((a, b) => b.key.localeCompare(a.key));
});

const selectedMonthLabel = computed(() => {
  if (!selectedMonth.value) return '';
  const month = monthlyTotals.value.find(m => m.key === selectedMonth.value);
  return month ? month.label : '';
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

const allCategoryOptionsWithCreate = computed(() => {
  return [
    { label: '+ Create New Category', value: '__create__' },
    ...allCategoryOptions.value,
  ];
});

// Apply filters when search or category changes
watch([searchQuery, categoryFilter], () => {
  transactionsStore.setFilters({
    searchQuery: searchQuery.value,
    categoryId: categoryFilter.value,
    isCategorized: categorizedFilter.value,
  });
});

// Handle quick tab changes
watch(quickTab, (newTab) => {
  // Clear month filter when switching tabs
  selectedMonth.value = null;

  switch (newTab) {
    case 'uncategorized':
      categorizedFilter.value = false;
      break;
    case 'categorized':
      categorizedFilter.value = true;
      break;
    default:
      categorizedFilter.value = null;
  }
  transactionsStore.setFilters({
    searchQuery: searchQuery.value,
    categoryId: categoryFilter.value,
    isCategorized: categorizedFilter.value,
    startDate: null,
    endDate: null,
  });
});

// Handle URL filter parameter
onMounted(() => {
  if (route.query.filter === 'uncategorized') {
    quickTab.value = 'uncategorized';
  }
});

// Month selection
function selectMonth(monthKey: string) {
  selectedMonth.value = monthKey;
  const date = parseISO(monthKey + '-01');
  const start = format(startOfMonth(date), 'yyyy-MM-dd');
  const end = format(endOfMonth(date), 'yyyy-MM-dd');

  transactionsStore.setFilters({
    searchQuery: searchQuery.value,
    categoryId: categoryFilter.value,
    isCategorized: categorizedFilter.value,
    startDate: start,
    endDate: end,
  });
}

function clearMonthFilter() {
  selectedMonth.value = null;
  transactionsStore.setFilters({
    searchQuery: searchQuery.value,
    categoryId: categoryFilter.value,
    isCategorized: categorizedFilter.value,
    startDate: null,
    endDate: null,
  });
}

function formatCompact(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(1) + 'K';
  }
  return amount.toFixed(0);
}

function selectTransaction(tx: Transaction) {
  selectedTransaction.value = tx;
  selectedCategoryId.value = tx.category_id;
  applyToSimilar.value = false;
  matchPattern.value = transactionsStore.extractPattern(tx.description);
  updateSimilarCount();
  showCategoryDialog.value = true;
}

function onCategorySelected() {
  if (selectedCategoryId.value === '__create__') {
    // Open quick create dialog
    quickCategory.value = {
      name: '',
      color: '#1976D2',
      is_income: selectedTransaction.value ? selectedTransaction.value.amount > 0 : false,
    };
    showQuickCreateDialog.value = true;
    selectedCategoryId.value = null; // Reset selection
    return;
  }
  updateSimilarCount();
}

function cancelQuickCreate() {
  showQuickCreateDialog.value = false;
}

async function createAndUseCategory() {
  if (!quickCategory.value.name) return;

  isCreatingCategory.value = true;

  try {
    const created = await categoriesStore.createCategory({
      name: quickCategory.value.name,
      icon: 'label',
      color: quickCategory.value.color,
      is_income: quickCategory.value.is_income,
    });

    if (created) {
      $q.notify({
        type: 'positive',
        message: `Category "${created.name}" created`,
      });
      showQuickCreateDialog.value = false;
      selectedCategoryId.value = created.id;
      updateSimilarCount();
    } else {
      $q.notify({
        type: 'negative',
        message: categoriesStore.error || 'Failed to create category',
      });
    }
  } finally {
    isCreatingCategory.value = false;
  }
}

function updateSimilarCount() {
  if (!selectedTransaction.value || !matchPattern.value) {
    similarCount.value = 0;
    return;
  }

  const similar = transactionsStore.findSimilarTransactions(
    matchPattern.value,
    selectedTransaction.value.local_id
  );
  // Only count uncategorized ones
  similarCount.value = similar.filter((t) => !t.is_categorized).length;
}

// Update count when pattern changes
watch(matchPattern, () => {
  updateSimilarCount();
});

async function saveCategory() {
  if (!selectedTransaction.value) return;

  isSaving.value = true;

  try {
    const categoryName = selectedCategoryId.value
      ? categoriesStore.getCategoryName(selectedCategoryId.value)
      : 'Uncategorized';

    const result = await transactionsStore.applyCategoryWithRule(
      selectedTransaction.value.local_id,
      selectedCategoryId.value!,
      categoryName,
      applyToSimilar.value ? matchPattern.value : null,
      applyToSimilar.value
    );

    if (result.updated > 1) {
      $q.notify({
        type: 'positive',
        message: `Categorized ${result.updated} transactions${result.ruleCreated ? ' and saved rule' : ''}`,
      });
    } else {
      $q.notify({
        type: 'positive',
        message: 'Category updated',
      });
    }

    showCategoryDialog.value = false;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to update category',
    });
  } finally {
    isSaving.value = false;
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

<style scoped>
.monthly-card {
  transition: all 0.2s ease;
  border: 2px solid transparent;
}

.monthly-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.monthly-card.selected-month {
  border-color: var(--q-primary);
}
</style>
