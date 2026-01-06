<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5">Fixed Expenses</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Add Expense" @click="openAddDialog" />
    </div>

    <!-- Summary Cards -->
    <div class="row q-gutter-md q-mb-lg" v-if="summary">
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">Monthly Total</div>
          <div class="text-h5 text-primary">R {{ formatMoney(summary.total_monthly) }}</div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">Needs</div>
          <div class="text-h6 text-blue">R {{ formatMoney(summary.by_bucket.needs) }}</div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">Wants</div>
          <div class="text-h6 text-pink">R {{ formatMoney(summary.by_bucket.wants) }}</div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">Savings</div>
          <div class="text-h6 text-green">R {{ formatMoney(summary.by_bucket.savings) }}</div>
        </q-card-section>
      </q-card>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <!-- Empty State -->
    <div v-else-if="fixedExpenses.length === 0" class="text-center q-pa-xl">
      <q-icon name="repeat" size="80px" color="grey-4" />
      <div class="text-h6 text-grey q-mt-md">No fixed expenses yet</div>
      <div class="text-body2 text-grey q-mb-md">
        Add your recurring monthly expenses like rent, insurance, and subscriptions
      </div>
      <q-btn color="primary" icon="add" label="Add Your First Expense" @click="openAddDialog" />
    </div>

    <!-- Expenses List -->
    <q-list separator v-else>
      <q-item v-for="expense in fixedExpenses" :key="expense.id" class="q-py-md">
        <q-item-section avatar>
          <q-avatar :color="expense.is_active ? getBucketColor(expense.bucket) : 'grey'" text-color="white">
            <q-icon :name="expense.category?.icon || 'repeat'" />
          </q-avatar>
        </q-item-section>

        <q-item-section>
          <q-item-label class="text-weight-medium">{{ expense.name }}</q-item-label>
          <q-item-label caption>
            <q-badge :color="getBucketColor(expense.bucket)" class="q-mr-xs">
              {{ expense.bucket }}
            </q-badge>
            <span v-if="expense.due_day">Due on {{ getOrdinal(expense.due_day) }}</span>
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <q-item-label class="text-weight-bold text-primary">
            R {{ formatMoney(expense.amount) }}/mo
          </q-item-label>
          <q-item-label caption>
            R {{ formatMoney(expense.amount * 12) }}/yr
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <q-btn flat round icon="more_vert">
            <q-menu>
              <q-list>
                <q-item clickable v-close-popup @click="editExpense(expense)">
                  <q-item-section>Edit</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="toggleActive(expense)">
                  <q-item-section>{{ expense.is_active ? 'Deactivate' : 'Activate' }}</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="confirmDelete(expense)">
                  <q-item-section class="text-negative">Delete</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ isEditing ? 'Edit Fixed Expense' : 'Add Fixed Expense' }}</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="formData.name"
            label="Name *"
            outlined
            placeholder="e.g., Rent, Netflix, Gym"
            :rules="[(v) => !!v || 'Name is required']"
          />

          <CurrencyInput
            v-model="formData.amount"
            label="Monthly Amount *"
            outlined
            :rules="[(v: number) => v > 0 || 'Amount must be greater than 0']"
          />

          <q-select
            v-model="formData.bucket"
            :options="bucketOptions"
            label="Category (50/30/20) *"
            outlined
            emit-value
            map-options
          />

          <q-input
            v-model.number="formData.due_day"
            label="Due Day of Month"
            outlined
            type="number"
            hint="e.g., 1 for 1st of month, 15 for 15th"
            :rules="[(v) => !v || (v >= 1 && v <= 31) || 'Must be 1-31']"
          />

          <q-select
            v-model="formData.category_id"
            :options="categoryOptions"
            label="Transaction Category"
            outlined
            emit-value
            map-options
            clearable
            hint="Optional - for linking to transactions"
          />

          <q-input
            v-model="formData.notes"
            label="Notes"
            outlined
            type="textarea"
            rows="2"
          />

          <q-toggle v-model="formData.is_active" label="Active" />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            :label="isEditing ? 'Update' : 'Add'"
            :loading="isSaving"
            @click="saveExpense"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useBudgetStore } from '@/stores/budget.store';
import { useCategoriesStore } from '@/stores/categories.store';
import type { FixedExpense, BudgetBucket } from '@/types';
import { formatNumber } from '@/utils/currency';
import CurrencyInput from '@/components/CurrencyInput.vue';

const $q = useQuasar();
const budgetStore = useBudgetStore();
const categoriesStore = useCategoriesStore();

// State
const showDialog = ref(false);
const isEditing = ref(false);
const isSaving = ref(false);
const editingId = ref<string | null>(null);

const formData = ref({
  name: '',
  amount: 0,
  bucket: 'needs' as BudgetBucket,
  due_day: undefined as number | undefined,
  category_id: undefined as string | undefined,
  is_active: true,
  notes: '',
});

// Computed
const isLoading = computed(() => budgetStore.isLoading);
const fixedExpenses = computed(() => budgetStore.fixedExpenses);
const summary = computed(() => budgetStore.fixedExpenseSummary);

const categoryOptions = computed(() =>
  categoriesStore.expenseCategories.map((c) => ({
    label: c.name,
    value: c.id,
  }))
);

// Options
const bucketOptions = [
  { label: 'Needs (Essentials)', value: 'needs' },
  { label: 'Wants (Discretionary)', value: 'wants' },
  { label: 'Savings/Debt', value: 'savings' },
];

// Helpers - use centralized currency utilities
function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function getBucketColor(bucket: BudgetBucket): string {
  switch (bucket) {
    case 'needs': return 'blue';
    case 'wants': return 'pink';
    case 'savings': return 'green';
    default: return 'grey';
  }
}

function getOrdinal(n: number): string {
  const s = ['th', 'st', 'nd', 'rd'];
  const v = n % 100;
  return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

// Actions
function openAddDialog() {
  isEditing.value = false;
  editingId.value = null;
  formData.value = {
    name: '',
    amount: 0,
    bucket: 'needs',
    due_day: undefined,
    category_id: undefined,
    is_active: true,
    notes: '',
  };
  showDialog.value = true;
}

function editExpense(expense: FixedExpense) {
  isEditing.value = true;
  editingId.value = expense.id;
  formData.value = {
    name: expense.name,
    amount: expense.amount,
    bucket: expense.bucket,
    due_day: expense.due_day || undefined,
    category_id: expense.category_id || undefined,
    is_active: expense.is_active,
    notes: expense.notes || '',
  };
  showDialog.value = true;
}

async function saveExpense() {
  if (!formData.value.name || formData.value.amount <= 0) {
    $q.notify({ type: 'warning', message: 'Please fill in required fields' });
    return;
  }

  isSaving.value = true;

  try {
    const data = {
      name: formData.value.name,
      amount: formData.value.amount,
      bucket: formData.value.bucket,
      due_day: formData.value.due_day,
      category_id: formData.value.category_id,
      is_active: formData.value.is_active,
      notes: formData.value.notes || undefined,
    };

    if (isEditing.value && editingId.value) {
      await budgetStore.updateFixedExpense(editingId.value, data);
      $q.notify({ type: 'positive', message: 'Expense updated' });
    } else {
      await budgetStore.createFixedExpense(data);
      $q.notify({ type: 'positive', message: 'Expense added' });
    }
    showDialog.value = false;
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to save expense' });
  } finally {
    isSaving.value = false;
  }
}

async function toggleActive(expense: FixedExpense) {
  await budgetStore.updateFixedExpense(expense.id, { is_active: !expense.is_active });
  $q.notify({
    type: 'info',
    message: expense.is_active ? 'Expense deactivated' : 'Expense activated',
  });
}

function confirmDelete(expense: FixedExpense) {
  $q.dialog({
    title: 'Delete Fixed Expense',
    message: `Are you sure you want to delete "${expense.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    const success = await budgetStore.deleteFixedExpense(expense.id);
    if (success) {
      $q.notify({ type: 'positive', message: 'Expense deleted' });
    } else {
      $q.notify({ type: 'negative', message: 'Failed to delete expense' });
    }
  });
}

// Lifecycle
onMounted(() => {
  budgetStore.loadFixedExpenses();
  if (categoriesStore.categories.length === 0) {
    categoriesStore.loadFromServer();
  }
});
</script>
