<template>
  <div class="transaction-list">
    <q-list separator padding>
      <q-slide-item
        v-for="tx in transactions"
        :key="tx.local_id"
        @left="onConfirm(tx)"
        @right="onEdit(tx)"
        left-color="teal-9"
        right-color="amber-8"
      >
        <!-- Swipe Right: Confirm -->
        <template v-slot:left>
          <q-icon name="check" color="white" />
        </template>

        <!-- Swipe Left: Edit -->
        <template v-slot:right>
          <q-icon name="edit" color="white" />
        </template>

        <!-- Transaction Item -->
        <q-item class="q-py-md" clickable @click="onEdit(tx)">
          <!-- Category Icon -->
          <q-item-section side>
            <q-avatar
              :style="{ backgroundColor: getCategoryColor(tx.category_id) }"
              size="44px"
            >
              <q-icon :name="getCategoryIcon(tx.category_id)" color="white" />
            </q-avatar>
          </q-item-section>

          <!-- Transaction Details -->
          <q-item-section>
            <q-item-label class="text-weight-bold ellipsis-2-lines">
              {{ tx.description }}
            </q-item-label>
            <q-item-label caption class="q-mt-xs">
              {{ formatDate(tx.transaction_date) }}
              <q-chip
                v-if="isSystemFailure(tx)"
                size="sm"
                color="red-10"
                text-color="white"
                dense
                icon="warning"
                class="q-ml-xs system-failure-chip"
              >
                SYSTEM FAILURE
              </q-chip>
              <q-chip
                v-if="!tx.is_categorized"
                size="sm"
                color="warning"
                text-color="white"
                dense
                class="q-ml-xs"
              >
                Uncategorized
              </q-chip>
              <q-chip
                v-if="tx.is_transfer"
                size="sm"
                color="orange"
                text-color="white"
                dense
                icon="swap_horiz"
                class="q-ml-xs"
              >
                Transfer
              </q-chip>
            </q-item-label>
          </q-item-section>

          <!-- Amount -->
          <q-item-section side>
            <q-item-label
              class="text-weight-medium"
              :class="getAmountClass(tx.amount)"
            >
              {{ formatAmount(tx.amount) }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-slide-item>

      <!-- Empty State -->
      <q-item v-if="transactions.length === 0" class="q-py-xl">
        <q-item-section class="text-center text-grey">
          <q-icon name="receipt_long" size="48px" class="q-mb-md" />
          <div>No transactions found</div>
          <q-btn flat color="primary" label="Import CSV" to="/import" class="q-mt-md" />
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Category Selection Bottom Sheet -->
    <q-dialog
      v-model="showCategorySheet"
      position="bottom"
      :maximized="$q.screen.lt.sm"
    >
      <q-card class="category-sheet" :style="sheetStyle">
        <!-- Header -->
        <q-toolbar class="bg-grey-2">
          <q-btn flat round dense icon="close" @click="showCategorySheet = false" />
          <q-toolbar-title class="text-body1">
            Select Category
          </q-toolbar-title>
          <q-btn
            flat
            color="primary"
            label="Save"
            :loading="isSaving"
            :disable="!selectedCategoryId"
            @click="saveCategory"
          />
        </q-toolbar>

        <!-- Selected Transaction Info -->
        <q-item v-if="editingTransaction" class="bg-grey-1">
          <q-item-section>
            <q-item-label class="text-weight-medium ellipsis">
              {{ editingTransaction.description }}
            </q-item-label>
            <q-item-label caption>
              {{ formatDate(editingTransaction.transaction_date) }}
              <span :class="getAmountClass(editingTransaction.amount)">
                {{ formatAmount(editingTransaction.amount) }}
              </span>
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-separator />

        <!-- Category List -->
        <q-scroll-area style="height: 50vh">
          <q-list separator>
            <!-- Create New Category -->
            <q-item clickable @click="$emit('create-category', editingTransaction)">
              <q-item-section avatar>
                <q-avatar color="primary" text-color="white" size="40px">
                  <q-icon name="add" />
                </q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-primary text-weight-medium">
                  Create New Category
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-separator />

            <!-- Expense Categories -->
            <q-item-label header class="text-grey-7">Expenses</q-item-label>
            <q-item
              v-for="cat in expenseCategories"
              :key="cat.id"
              clickable
              :active="selectedCategoryId === cat.id"
              active-class="bg-teal-1"
              @click="selectedCategoryId = cat.id"
              class="q-py-md"
            >
              <q-item-section avatar>
                <q-avatar :style="{ backgroundColor: cat.color }" size="40px">
                  <q-icon :name="cat.icon" color="white" />
                </q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ cat.name }}</q-item-label>
              </q-item-section>
              <q-item-section side v-if="selectedCategoryId === cat.id">
                <q-icon name="check" color="teal" />
              </q-item-section>
            </q-item>

            <!-- Income Categories -->
            <q-item-label header class="text-grey-7">Income</q-item-label>
            <q-item
              v-for="cat in incomeCategories"
              :key="cat.id"
              clickable
              :active="selectedCategoryId === cat.id"
              active-class="bg-teal-1"
              @click="selectedCategoryId = cat.id"
              class="q-py-md"
            >
              <q-item-section avatar>
                <q-avatar :style="{ backgroundColor: cat.color }" size="40px">
                  <q-icon :name="cat.icon" color="white" />
                </q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ cat.name }}</q-item-label>
              </q-item-section>
              <q-item-section side v-if="selectedCategoryId === cat.id">
                <q-icon name="check" color="teal" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-scroll-area>

        <!-- Transfer Toggle -->
        <q-separator />
        <q-item class="q-py-md">
          <q-item-section>
            <q-item-label>Mark as transfer</q-item-label>
            <q-item-label caption>Excluded from income/expense totals</q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-toggle v-model="isTransfer" color="orange" />
          </q-item-section>
        </q-item>

        <!-- Flag for Accountant (expense only) -->
        <template v-if="editingTransaction && Number(editingTransaction.amount) < 0">
          <q-separator />
          <q-item clickable class="q-py-md bg-deep-orange-1" @click="flagForAccountant">
            <q-item-section avatar>
              <q-icon name="gavel" color="deep-orange" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-deep-orange text-weight-medium">Flag for Accountant</q-item-label>
              <q-item-label caption>Ask your accountant if this is tax-deductible</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-icon name="chevron_right" color="deep-orange" />
            </q-item-section>
          </q-item>
        </template>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { format, parseISO, isWithinInterval } from 'date-fns';
import { useQuasar } from 'quasar';
import { useCategoriesStore } from '@/stores/categories.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import type { Transaction, Category } from '@/types';

// Survival Mode Configuration - matches SurvivalModeBanner.vue
const SURVIVAL_CONFIG = {
  failureThreshold: 50.00,    // Any transaction > this = SYSTEM FAILURE
  startDate: '2026-01-05',    // When survival mode started
  endDate: '2026-01-26',      // Expected invoice date
};

const props = defineProps<{
  transactions: Transaction[];
}>();

const emit = defineEmits<{
  (e: 'confirm', tx: Transaction): void;
  (e: 'edit', tx: Transaction): void;
  (e: 'create-category', tx: Transaction | null): void;
  (e: 'category-saved', tx: Transaction, categoryId: string, isTransfer: boolean): void;
  (e: 'flag-for-accountant', tx: Transaction): void;
}>();

const $q = useQuasar();
const categoriesStore = useCategoriesStore();
const transactionsStore = useTransactionsStore();

// State
const showCategorySheet = ref(false);
const editingTransaction = ref<Transaction | null>(null);
const selectedCategoryId = ref<string | null>(null);
const isTransfer = ref(false);
const isSaving = ref(false);

// Computed
const expenseCategories = computed(() => categoriesStore.expenseCategories);
const incomeCategories = computed(() => categoriesStore.incomeCategories);

const sheetStyle = computed(() => ({
  borderRadius: $q.screen.lt.sm ? '0' : '16px 16px 0 0',
  maxHeight: $q.screen.lt.sm ? '100vh' : '70vh',
}));

// Methods
function getCategoryColor(id: string | null): string {
  return categoriesStore.getCategoryColor(id);
}

function getCategoryIcon(id: string | null): string {
  return categoriesStore.getCategoryIcon(id);
}

// Check if transaction triggers SYSTEM FAILURE (expense > threshold during survival period)
function isSystemFailure(tx: Transaction): boolean {
  const amount = Number(tx.amount);

  // Only check expenses (negative amounts) that are not transfers
  if (amount >= 0 || tx.is_transfer) return false;

  // Check if expense exceeds threshold
  if (Math.abs(amount) <= SURVIVAL_CONFIG.failureThreshold) return false;

  // Check if transaction is within survival period
  try {
    const txDate = parseISO(tx.transaction_date);
    const startDate = parseISO(SURVIVAL_CONFIG.startDate);
    const endDate = parseISO(SURVIVAL_CONFIG.endDate);

    return isWithinInterval(txDate, { start: startDate, end: endDate });
  } catch {
    return false;
  }
}

function getAmountClass(amount: number | string): string {
  const num = Number(amount);
  return num >= 0 ? 'text-positive' : 'text-negative';
}

function formatAmount(amount: number | string): string {
  const num = Number(amount) || 0;
  const prefix = num >= 0 ? '+' : '';
  const formatted = Math.abs(num).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `${prefix}R ${formatted}`;
}

function formatDate(dateStr: string): string {
  return format(new Date(dateStr), 'dd MMM yyyy');
}

function onConfirm(tx: Transaction): void {
  // Confirm keeps current category (or marks as reviewed)
  emit('confirm', tx);

  $q.notify({
    type: 'positive',
    message: 'Category confirmed',
    icon: 'check',
    timeout: 1500,
  });
}

function onEdit(tx: Transaction): void {
  editingTransaction.value = tx;
  selectedCategoryId.value = tx.category_id;
  isTransfer.value = tx.is_transfer || false;
  showCategorySheet.value = true;
  emit('edit', tx);
}

async function saveCategory(): Promise<void> {
  if (!editingTransaction.value || !selectedCategoryId.value) return;

  isSaving.value = true;

  try {
    const categoryName = categoriesStore.getCategoryName(selectedCategoryId.value);

    await transactionsStore.applyCategoryWithRule(
      editingTransaction.value.local_id,
      selectedCategoryId.value,
      categoryName,
      null,
      false,
      isTransfer.value
    );

    emit('category-saved', editingTransaction.value, selectedCategoryId.value, isTransfer.value);

    $q.notify({
      type: 'positive',
      message: 'Category updated',
      icon: 'check',
      timeout: 1500,
    });

    showCategorySheet.value = false;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to update category',
    });
  } finally {
    isSaving.value = false;
  }
}

function flagForAccountant(): void {
  if (!editingTransaction.value) return;
  showCategorySheet.value = false;
  emit('flag-for-accountant', editingTransaction.value);
}
</script>

<style scoped>
.transaction-list {
  /* Ensure touch-friendly spacing */
}

.category-sheet {
  width: 100%;
}

.ellipsis-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Ensure 44px+ touch targets */
:deep(.q-item) {
  min-height: 56px;
}

:deep(.q-slide-item__left),
:deep(.q-slide-item__right) {
  padding: 0 24px;
}

/* System failure chip animation */
.system-failure-chip {
  animation: failure-pulse 1.5s ease-in-out infinite;
}

@keyframes failure-pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}
</style>
