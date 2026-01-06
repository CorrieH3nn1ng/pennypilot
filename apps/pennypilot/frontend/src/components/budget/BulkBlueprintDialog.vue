<template>
  <q-dialog
    v-model="isOpen"
    persistent
    :maximized="$q.screen.lt.md"
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card :style="$q.screen.gt.sm ? 'min-width: 900px; max-width: 95vw' : ''">
      <!-- Header -->
      <q-card-section class="row items-center bg-primary text-white">
        <q-icon name="grid_on" size="24px" class="q-mr-sm" />
        <div>
          <div class="text-h6">Bulk Blueprint Entry</div>
          <div class="text-caption">Add multiple items at once for 2025 reconstruction</div>
        </div>
        <q-space />
        <q-btn icon="close" flat round dense @click="closeDialog" />
      </q-card-section>

      <!-- Tabs for Income vs Expense -->
      <q-tabs v-model="activeTab" class="bg-grey-2" active-color="primary" indicator-color="primary">
        <q-tab name="expense" icon="trending_down" label="Expenses" />
        <q-tab name="income" icon="trending_up" label="Income" />
      </q-tabs>

      <q-card-section class="q-pa-none">
        <!-- Column Headers -->
        <div class="row q-pa-sm bg-grey-3 text-weight-medium text-caption items-center">
          <div class="col-3">Name *</div>
          <div class="col-2">Amount *</div>
          <div class="col-1 text-center">Due Day</div>
          <div v-if="activeTab === 'expense'" class="col-2 text-center">Nature</div>
          <div v-if="activeTab === 'expense'" class="col-2 text-center">Bucket</div>
          <div v-if="activeTab === 'expense'" class="col-2 text-center">Liability</div>
          <div v-else class="col-4"></div>
        </div>

        <!-- Scrollable Grid Area -->
        <q-scroll-area style="height: 400px">
          <div v-for="(row, index) in rows" :key="index" class="row q-pa-xs items-center row-item">
            <!-- Name -->
            <div class="col-3 q-pr-xs">
              <q-input
                v-model="row.name"
                dense
                outlined
                placeholder="Item name"
                :rules="[(v) => !v || v.length <= 100 || 'Max 100 chars']"
              />
            </div>

            <!-- Amount -->
            <div class="col-2 q-pr-xs">
              <q-input
                v-model.number="row.expected_amount"
                dense
                outlined
                type="number"
                prefix="R"
                placeholder="0.00"
                :rules="[(v) => !row.name || v > 0 || 'Required']"
              />
            </div>

            <!-- Due Day -->
            <div class="col-1 q-pr-xs">
              <q-input
                v-model.number="row.due_day"
                dense
                outlined
                type="number"
                placeholder="Any"
                hint="Leave blank for variable dates"
                :rules="[(v) => !v || (v >= 1 && v <= 31) || '1-31']"
              >
                <q-tooltip>Day of month (1-31). Leave blank for expenses that vary.</q-tooltip>
              </q-input>
            </div>

            <!-- Nature (Expense only) -->
            <div v-if="activeTab === 'expense'" class="col-2 q-pr-xs">
              <q-select
                v-model="row.nature"
                :options="natureOptions"
                dense
                outlined
                emit-value
                map-options
                options-dense
              />
            </div>

            <!-- Bucket (Expense only) -->
            <div v-if="activeTab === 'expense'" class="col-2 q-pr-xs">
              <q-select
                v-model="row.bucket"
                :options="bucketOptions"
                dense
                outlined
                emit-value
                map-options
                options-dense
              />
            </div>

            <!-- Liability (Expense only) -->
            <div v-if="activeTab === 'expense'" class="col-2">
              <q-select
                v-model="row.liability_id"
                :options="liabilityOptions"
                dense
                outlined
                emit-value
                map-options
                options-dense
                clearable
              />
            </div>

            <!-- Spacer for income -->
            <div v-else class="col-4"></div>
          </div>
        </q-scroll-area>

        <!-- Action Buttons -->
        <div class="row q-pa-sm justify-center q-gutter-sm">
          <q-btn flat color="primary" icon="add" label="Add 5 Rows" @click="addRows(5)" />
          <q-btn flat color="secondary" icon="content_paste" label="Paste from Clipboard" @click="pasteFromClipboard">
            <q-tooltip>Paste tab-separated data: Name, Amount, Day, Nature, Bucket</q-tooltip>
          </q-btn>
        </div>
      </q-card-section>

      <!-- Summary -->
      <q-separator />
      <q-card-section class="bg-grey-1">
        <div class="row items-center q-gutter-md">
          <!-- Current tab stats -->
          <div>
            <span class="text-weight-medium">This Tab:</span>
            <q-badge color="primary" class="q-ml-xs">{{ validRowCount }}</q-badge>
          </div>
          <div v-if="activeTab === 'expense'">
            <span class="text-weight-medium">Business:</span>
            <q-badge color="blue" class="q-ml-xs">{{ businessCount }}</q-badge>
          </div>
          <div v-if="activeTab === 'expense'">
            <span class="text-weight-medium">Private:</span>
            <q-badge color="purple" class="q-ml-xs">{{ privateCount }}</q-badge>
          </div>
          <div>
            <span class="text-weight-medium">Tab Total:</span>
            <q-badge :color="activeTab === 'income' ? 'positive' : 'negative'" class="q-ml-xs">
              R {{ formatMoney(totalAmount) }}
            </q-badge>
          </div>
          <q-space />
          <!-- Both tabs totals -->
          <div class="text-caption">
            <q-badge color="negative" class="q-mr-xs">{{ validExpenseCount }} expenses</q-badge>
            <q-badge color="positive">{{ validIncomeCount }} income</q-badge>
          </div>
        </div>
      </q-card-section>

      <!-- Actions -->
      <q-card-actions align="right" class="q-pa-md">
        <q-btn flat label="Clear All" color="negative" @click="clearAll" :disable="isSaving" />
        <q-space />
        <q-btn flat label="Cancel" @click="closeDialog" :disable="isSaving" />
        <q-btn
          color="primary"
          :label="`Save All ${totalValidCount} Items`"
          :loading="isSaving"
          :disable="totalValidCount === 0"
          @click="saveItems"
        >
          <q-tooltip v-if="totalValidCount > 0">
            {{ validExpenseCount }} expenses + {{ validIncomeCount }} income items
          </q-tooltip>
        </q-btn>
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useBlueprintStore } from '@/stores/blueprint.store';
import { useLiabilityStore } from '@/stores/liability.store';
import type { BlueprintItemType, BlueprintNature, BudgetBucket, BulkBlueprintItem } from '@/types';
import { formatNumber } from '@/utils/currency';

const $q = useQuasar();
const blueprintStore = useBlueprintStore();
const liabilityStore = useLiabilityStore();

// Props/Emit
const isOpen = defineModel<boolean>('modelValue', { required: true });
const emit = defineEmits<{
  saved: [result: { created: number; skipped: number }];
}>();

// State
const activeTab = ref<BlueprintItemType>('expense');
const isSaving = ref(false);

interface BulkRow {
  name: string;
  expected_amount: number | null;
  due_day: number | null;
  nature: BlueprintNature;
  bucket: BudgetBucket;
  liability_id: string | null;
}

const createEmptyRow = (): BulkRow => ({
  name: '',
  expected_amount: null,
  due_day: null,
  nature: 'private',
  bucket: 'needs',
  liability_id: null,
});

// Separate rows for expenses and income
const expenseRows = ref<BulkRow[]>([]);
const incomeRows = ref<BulkRow[]>([]);

// Computed property that returns the current tab's rows
const rows = computed({
  get: () => activeTab.value === 'expense' ? expenseRows.value : incomeRows.value,
  set: (val) => {
    if (activeTab.value === 'expense') {
      expenseRows.value = val;
    } else {
      incomeRows.value = val;
    }
  },
});

// Initialize with 10 empty rows for each tab
const initializeRows = () => {
  expenseRows.value = Array.from({ length: 10 }, () => createEmptyRow());
  incomeRows.value = Array.from({ length: 10 }, () => createEmptyRow());
};

// Options
const natureOptions = [
  { label: 'Business', value: 'business' },
  { label: 'Private', value: 'private' },
];

const bucketOptions = [
  { label: 'Needs', value: 'needs' },
  { label: 'Wants', value: 'wants' },
  { label: 'Savings', value: 'savings' },
];

const liabilityOptions = computed(() => [
  { label: '(None)', value: null },
  ...liabilityStore.activeLiabilities.map((l) => ({
    label: l.name,
    value: l.id,
  })),
]);

// Computed - current tab
const validRows = computed(() =>
  rows.value.filter((row) => row.name.trim() && row.expected_amount && row.expected_amount > 0)
);

const validRowCount = computed(() => validRows.value.length);

const businessCount = computed(() =>
  validRows.value.filter((row) => row.nature === 'business').length
);

const privateCount = computed(() =>
  validRows.value.filter((row) => row.nature === 'private').length
);

const totalAmount = computed(() =>
  validRows.value.reduce((sum, row) => sum + (row.expected_amount || 0), 0)
);

// Computed - totals from BOTH tabs (for save button)
const validExpenseCount = computed(() =>
  expenseRows.value.filter((row) => row.name.trim() && row.expected_amount && row.expected_amount > 0).length
);

const validIncomeCount = computed(() =>
  incomeRows.value.filter((row) => row.name.trim() && row.expected_amount && row.expected_amount > 0).length
);

const totalValidCount = computed(() => validExpenseCount.value + validIncomeCount.value);

// Helpers
function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function addRows(count: number) {
  const targetRows = activeTab.value === 'expense' ? expenseRows : incomeRows;
  for (let i = 0; i < count; i++) {
    targetRows.value.push(createEmptyRow());
  }
}

async function pasteFromClipboard() {
  try {
    const text = await navigator.clipboard.readText();
    if (!text.trim()) {
      $q.notify({ type: 'warning', message: 'Clipboard is empty' });
      return;
    }

    const lines = text.trim().split('\n');
    const targetRows = activeTab.value === 'expense' ? expenseRows : incomeRows;
    let pastedCount = 0;

    for (const line of lines) {
      // Split by tab first, then comma if no tabs
      let parts = line.includes('\t') ? line.split('\t') : line.split(',');
      parts = parts.map(p => p.trim());

      if (parts.length < 2 || !parts[0]) continue; // Need at least name and amount

      const name = parts[0];
      const amountStr = parts[1]?.replace(/[R\s,]/g, '') || '0';
      const amount = parseFloat(amountStr) || 0;

      if (!name || amount <= 0) continue;

      // Parse day - handle "blank", empty, or number
      let dueDay: number | null = null;
      if (parts[2] && parts[2] !== '' && parts[2].toLowerCase() !== 'blank' && parts[2] !== '-') {
        const dayNum = parseInt(parts[2]);
        if (dayNum >= 1 && dayNum <= 31) {
          dueDay = dayNum;
        }
      }

      // Parse nature
      let nature: BlueprintNature = 'private';
      if (parts[3]?.toLowerCase().includes('business') || parts[3]?.toLowerCase() === 'biz') {
        nature = 'business';
      }

      // Parse bucket
      let bucket: BudgetBucket = 'needs';
      if (parts[4]?.toLowerCase().includes('want')) {
        bucket = 'wants';
      } else if (parts[4]?.toLowerCase().includes('saving')) {
        bucket = 'savings';
      }

      // Find empty row or add new one
      let emptyRowIndex = targetRows.value.findIndex(r => !r.name.trim());
      if (emptyRowIndex === -1) {
        targetRows.value.push(createEmptyRow());
        emptyRowIndex = targetRows.value.length - 1;
      }

      targetRows.value[emptyRowIndex] = {
        name,
        expected_amount: amount,
        due_day: dueDay,
        nature,
        bucket,
        liability_id: null,
      };

      pastedCount++;
    }

    if (pastedCount > 0) {
      $q.notify({
        type: 'positive',
        message: `Pasted ${pastedCount} items`,
        icon: 'content_paste',
      });
    } else {
      $q.notify({
        type: 'warning',
        message: 'No valid data found in clipboard',
        caption: 'Format: Name, Amount, Day, Nature, Bucket',
      });
    }
  } catch (err) {
    $q.notify({
      type: 'negative',
      message: 'Could not read clipboard',
      caption: 'Please allow clipboard access',
    });
  }
}

function clearAll() {
  $q.dialog({
    title: 'Clear All Rows',
    message: `Are you sure you want to clear all ${activeTab.value} rows?`,
    cancel: true,
  }).onOk(() => {
    if (activeTab.value === 'expense') {
      expenseRows.value = Array.from({ length: 10 }, () => createEmptyRow());
    } else {
      incomeRows.value = Array.from({ length: 10 }, () => createEmptyRow());
    }
  });
}

function closeDialog() {
  // Check both tabs for unsaved items
  const expenseCount = expenseRows.value.filter(
    (r) => r.name.trim() && r.expected_amount && r.expected_amount > 0
  ).length;
  const incomeCount = incomeRows.value.filter(
    (r) => r.name.trim() && r.expected_amount && r.expected_amount > 0
  ).length;
  const totalUnsaved = expenseCount + incomeCount;

  if (totalUnsaved > 0) {
    $q.dialog({
      title: 'Unsaved Changes',
      message: `You have ${totalUnsaved} unsaved items. Discard them?`,
      cancel: true,
      ok: { label: 'Discard', color: 'negative' },
    }).onOk(() => {
      isOpen.value = false;
    });
  } else {
    isOpen.value = false;
  }
}

async function saveItems() {
  // Get valid items from BOTH tabs
  const validExpenses = expenseRows.value.filter(
    (row) => row.name.trim() && row.expected_amount && row.expected_amount > 0
  );
  const validIncomes = incomeRows.value.filter(
    (row) => row.name.trim() && row.expected_amount && row.expected_amount > 0
  );

  const totalValid = validExpenses.length + validIncomes.length;
  if (totalValid === 0) return;

  isSaving.value = true;

  try {
    // Convert expense rows to BulkBlueprintItem format
    const expenseItems: BulkBlueprintItem[] = validExpenses.map((row) => ({
      name: row.name.trim(),
      expected_amount: row.expected_amount!,
      item_type: 'expense' as const,
      bucket: row.bucket,
      nature: row.nature,
      due_day: row.due_day,
      liability_id: row.liability_id,
    }));

    // Convert income rows to BulkBlueprintItem format
    const incomeItems: BulkBlueprintItem[] = validIncomes.map((row) => ({
      name: row.name.trim(),
      expected_amount: row.expected_amount!,
      item_type: 'income' as const,
      bucket: 'needs',
      nature: 'private',
      due_day: row.due_day,
      liability_id: null,
    }));

    // Combine all items and save
    const items = [...expenseItems, ...incomeItems];
    const result = await blueprintStore.bulkCreate(items);

    $q.notify({
      type: 'positive',
      message: `Created ${result.created_count} blueprint items`,
      caption: result.skipped_count > 0 ? `${result.skipped_count} duplicates skipped` : undefined,
    });

    emit('saved', { created: result.created_count, skipped: result.skipped_count });
    isOpen.value = false;
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Failed to save items';
    $q.notify({ type: 'negative', message: msg });
  } finally {
    isSaving.value = false;
  }
}

// Watch for dialog open to reset state
watch(isOpen, (val) => {
  if (val) {
    initializeRows();
    liabilityStore.loadLiabilities();
  }
});
</script>

<style scoped>
.row-item:nth-child(odd) {
  background-color: rgba(0, 0, 0, 0.02);
}

.row-item:hover {
  background-color: rgba(0, 77, 64, 0.05);
}
</style>
