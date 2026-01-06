<template>
  <q-page class="q-pa-md">
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5">Blueprint</div>
        <div class="text-caption text-grey">Your strategic plan for recurring income & expenses</div>
      </div>
      <q-space />
      <q-btn
        flat
        color="grey"
        icon="download"
        label="Import from Fixed"
        @click="importFromFixed"
        class="q-mr-sm"
      />
      <q-btn
        outline
        color="primary"
        icon="grid_on"
        :label="$q.screen.gt.xs ? 'Bulk Add' : ''"
        @click="showBulkDialog = true"
        class="q-mr-sm"
      />
      <q-btn color="primary" icon="add" label="Add Item" @click="openAddDialog" />
    </div>

    <!-- Summary Cards -->
    <div class="row q-gutter-md q-mb-lg" v-if="totals.total_count > 0">
      <!-- Row 1: Income, Expenses, Tax Reserve -->
      <q-card class="col-12 col-sm-4">
        <q-card-section>
          <div class="text-caption text-grey">Expected Income</div>
          <div class="text-h5 text-positive">R {{ formatMoney(totals.income) }}</div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-4">
        <q-card-section>
          <div class="text-caption text-grey">Expected Expenses</div>
          <div class="text-h5 text-negative">R {{ formatMoney(totals.expense) }}</div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-4">
        <q-card-section>
          <div class="text-caption text-grey">Tax Reserve (39.1%)</div>
          <div class="text-h5 text-deep-purple">R {{ formatMoney(totals.tax_reserve || 0) }}</div>
          <div class="text-caption text-grey-6">
            On R{{ formatMoney(totals.taxable_income || 0) }} taxable
            <q-icon name="info" size="12px" class="cursor-pointer">
              <q-tooltip>
                Business Income: R{{ formatMoney(totals.business_income || 0) }}<br/>
                - Business Expenses: R{{ formatMoney(totals.business_expenses || 0) }}<br/>
                = Taxable Income: R{{ formatMoney(totals.taxable_income || 0) }}
              </q-tooltip>
            </q-icon>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <!-- Row 2: The Real Numbers -->
    <div class="row q-gutter-md q-mb-lg" v-if="totals.total_count > 0">
      <q-card class="col-12 col-sm-6" :class="actualSpendable >= 0 ? 'bg-positive' : 'bg-negative'">
        <q-card-section>
          <div class="text-caption text-white-7">ACTUAL SPENDABLE CASH</div>
          <div class="text-h4 text-white">R {{ formatMoney(actualSpendable) }}</div>
          <div class="text-caption text-white-7">
            R{{ formatMoney(totals.income) }} - R{{ formatMoney(totals.tax_reserve || 0) }} - R{{ formatMoney(totals.expense) }}
          </div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">50/30/20 Check</div>
          <div class="text-body2">
            <span class="text-blue">Needs: R{{ formatMoney(totals.needs) }}</span><br/>
            <span class="text-pink">Wants: R{{ formatMoney(totals.wants) }}</span><br/>
            <span class="text-green">Savings: R{{ formatMoney(totals.savings) }}</span>
          </div>
        </q-card-section>
      </q-card>
      <q-card class="col-12 col-sm-3">
        <q-card-section>
          <div class="text-caption text-grey">Active Items</div>
          <div class="text-h5 text-primary">{{ totals.active_count }} / {{ totals.total_count }}</div>
        </q-card-section>
      </q-card>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <!-- Empty State -->
    <div v-else-if="blueprints.length === 0" class="text-center q-pa-xl">
      <q-icon name="account_balance" size="80px" color="grey-4" />
      <div class="text-h6 text-grey q-mt-md">No blueprint items yet</div>
      <div class="text-body2 text-grey q-mb-md">
        Create your strategic financial plan by adding expected income and recurring expenses
      </div>
      <div class="q-gutter-sm">
        <q-btn color="primary" icon="add" label="Add First Item" @click="openAddDialog" />
        <q-btn outline color="grey" icon="download" label="Import from Fixed Expenses" @click="importFromFixed" />
      </div>
    </div>

    <!-- Blueprint Content -->
    <template v-else>
      <q-list bordered class="rounded-borders">
        <!-- Income Section (Expandable) -->
        <q-expansion-item
          v-model="expandedSections.income"
          icon="trending_up"
          header-class="bg-positive text-white text-h6"
          expand-icon-class="text-white"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-icon name="trending_up" color="white" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-white text-weight-bold">Income</q-item-label>
              <q-item-label caption class="text-white-7">{{ grouped.income.length }} items</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label class="text-white text-h6">R {{ formatMoney(totals.income) }}/mo</q-item-label>
            </q-item-section>
          </template>

          <q-list separator v-if="grouped.income.length > 0">
            <BlueprintItem
              v-for="item in grouped.income"
              :key="item.id"
              :item="item"
              @edit="editBlueprint"
              @toggle="toggleBlueprint"
              @delete="confirmDelete"
            />
          </q-list>
          <q-card-section v-else class="text-center text-grey">
            No income items. Add your expected income sources.
          </q-card-section>
        </q-expansion-item>

        <q-separator />

        <!-- Needs Section (Expandable) -->
        <q-expansion-item
          v-model="expandedSections.needs"
          icon="home"
          header-class="bg-blue text-white text-h6"
          expand-icon-class="text-white"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-icon name="home" color="white" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-white text-weight-bold">Needs (Essentials)</q-item-label>
              <q-item-label caption class="text-white-7">{{ grouped.expense.needs.length }} items · 50% budget</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label class="text-white text-h6">R {{ formatMoney(totals.needs) }}/mo</q-item-label>
            </q-item-section>
          </template>

          <q-list separator v-if="grouped.expense.needs.length > 0">
            <BlueprintItem
              v-for="item in grouped.expense.needs"
              :key="item.id"
              :item="item"
              @edit="editBlueprint"
              @toggle="toggleBlueprint"
              @delete="confirmDelete"
            />
          </q-list>
          <q-card-section v-else class="text-center text-grey">
            No needs items yet.
          </q-card-section>
        </q-expansion-item>

        <q-separator />

        <!-- Wants Section (Expandable) -->
        <q-expansion-item
          v-model="expandedSections.wants"
          icon="favorite"
          header-class="bg-pink text-white text-h6"
          expand-icon-class="text-white"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-icon name="favorite" color="white" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-white text-weight-bold">Wants (Discretionary)</q-item-label>
              <q-item-label caption class="text-white-7">{{ grouped.expense.wants.length }} items · 30% budget</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label class="text-white text-h6">R {{ formatMoney(totals.wants) }}/mo</q-item-label>
            </q-item-section>
          </template>

          <q-list separator v-if="grouped.expense.wants.length > 0">
            <BlueprintItem
              v-for="item in grouped.expense.wants"
              :key="item.id"
              :item="item"
              @edit="editBlueprint"
              @toggle="toggleBlueprint"
              @delete="confirmDelete"
            />
          </q-list>
          <q-card-section v-else class="text-center text-grey">
            No wants items yet.
          </q-card-section>
        </q-expansion-item>

        <q-separator />

        <!-- Savings Section (Expandable) -->
        <q-expansion-item
          v-model="expandedSections.savings"
          icon="savings"
          header-class="bg-green text-white text-h6"
          expand-icon-class="text-white"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-icon name="savings" color="white" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-white text-weight-bold">Savings & Debt</q-item-label>
              <q-item-label caption class="text-white-7">{{ grouped.expense.savings.length }} items · 20% budget</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label class="text-white text-h6">R {{ formatMoney(totals.savings) }}/mo</q-item-label>
            </q-item-section>
          </template>

          <q-list separator v-if="grouped.expense.savings.length > 0">
            <BlueprintItem
              v-for="item in grouped.expense.savings"
              :key="item.id"
              :item="item"
              @edit="editBlueprint"
              @toggle="toggleBlueprint"
              @delete="confirmDelete"
            />
          </q-list>
          <q-card-section v-else class="text-center text-grey">
            No savings items yet.
          </q-card-section>
        </q-expansion-item>
      </q-list>

      <!-- Preview Next Month Button -->
      <div class="row justify-center q-mt-lg">
        <q-btn
          color="primary"
          icon="visibility"
          label="Preview Next Month's Budget Card"
          @click="showPreview"
        />
      </div>
    </template>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent :maximized="$q.screen.lt.sm">
      <q-card :style="$q.screen.gt.xs ? 'min-width: 450px' : ''">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ isEditing ? 'Edit Blueprint Item' : 'Add Blueprint Item' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <!-- Item Type Toggle -->
          <q-btn-toggle
            v-model="formData.item_type"
            spread
            no-caps
            toggle-color="primary"
            :options="[
              { label: 'Income', value: 'income' },
              { label: 'Expense', value: 'expense' },
            ]"
          />

          <q-input
            v-model="formData.name"
            label="Name *"
            outlined
            placeholder="e.g., Salary, Rent, Netflix"
            :rules="[(v) => !!v || 'Name is required']"
          />

          <CurrencyInput
            v-model="formData.expected_amount"
            label="Expected Amount *"
            outlined
            :rules="[(v: number) => v > 0 || 'Amount must be greater than 0']"
          />

          <q-select
            v-if="formData.item_type === 'expense'"
            v-model="formData.bucket"
            :options="bucketOptions"
            label="Category (50/30/20) *"
            outlined
            emit-value
            map-options
          />

          <q-select
            v-if="formData.item_type === 'expense'"
            v-model="formData.nature"
            :options="natureOptions"
            label="Nature (Tax Classification)"
            outlined
            emit-value
            map-options
          >
            <template v-slot:hint>
              <span v-if="formData.nature === 'business'" class="text-blue">
                Business expenses reduce taxable income (39.1% + 15% VAT)
              </span>
              <span v-else class="text-purple">
                Private expenses are categorized into 50/30/20 framework
              </span>
            </template>
          </q-select>

          <q-input
            v-model.number="formData.due_day"
            label="Expected Day of Month"
            outlined
            type="number"
            hint="When do you expect this to come in/go out?"
            :rules="[(v) => !v || (v >= 1 && v <= 31) || 'Must be 1-31']"
          />

          <q-input
            v-model="formData.match_pattern"
            label="Match Pattern"
            outlined
            hint="Text to match in bank descriptions (auto-generated from name if empty)"
          />

          <q-select
            v-model="formData.match_type"
            :options="matchTypeOptions"
            label="Match Type"
            outlined
            emit-value
            map-options
          />

          <div class="row q-gutter-sm">
            <q-input
              v-model.number="formData.amount_tolerance"
              label="Amount Tolerance %"
              outlined
              type="number"
              class="col"
              hint="How much can actual vary from expected?"
              :rules="[(v) => v >= 0 && v <= 50 || 'Must be 0-50']"
            />
            <q-input
              v-model.number="formData.due_day_tolerance"
              label="Day Tolerance"
              outlined
              type="number"
              class="col"
              hint="Days before/after expected"
              :rules="[(v) => v >= 0 && v <= 15 || 'Must be 0-15']"
            />
          </div>

          <q-select
            v-model="formData.category_id"
            :options="categoryOptions"
            label="Transaction Category"
            outlined
            emit-value
            map-options
            clearable
          />

          <!-- Liability Linking (for expense items only) -->
          <q-select
            v-if="formData.item_type === 'expense' && liabilityOptions.length > 1"
            v-model="formData.liability_id"
            :options="liabilityOptions"
            label="Link to Debt/Liability"
            outlined
            emit-value
            map-options
            hint="Link to a liability to track repayments"
          >
            <template v-slot:prepend>
              <q-icon name="account_balance_wallet" color="warning" />
            </template>
          </q-select>

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
            @click="saveBlueprint"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Preview Dialog -->
    <q-dialog v-model="showPreviewDialog" :maximized="$q.screen.lt.sm">
      <q-card :style="$q.screen.gt.xs ? 'min-width: 500px' : ''">
        <q-card-section class="row items-center bg-primary text-white">
          <q-icon name="visibility" size="24px" class="q-mr-sm" />
          <div class="text-h6">Next Month's Budget Card Preview</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="preview">
          <div class="row q-gutter-md q-mb-md">
            <div class="col-12 col-sm-6">
              <div class="text-caption text-grey">Total Income</div>
              <div class="text-h5 text-positive">R {{ formatMoney(preview.summary.total_income) }}</div>
            </div>
            <div class="col-12 col-sm-6">
              <div class="text-caption text-grey">Total Expenses</div>
              <div class="text-h5 text-negative">R {{ formatMoney(preview.summary.total_expense) }}</div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 q-mb-sm">Budget Allocation</div>
          <div class="row q-gutter-sm q-mb-md">
            <q-chip color="blue" text-color="white">
              Needs: R {{ formatMoney(preview.summary.needs) }}
            </q-chip>
            <q-chip color="pink" text-color="white">
              Wants: R {{ formatMoney(preview.summary.wants) }}
            </q-chip>
            <q-chip color="green" text-color="white">
              Savings: R {{ formatMoney(preview.summary.savings) }}
            </q-chip>
          </div>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 q-mb-sm">Tax Reserves (39.1% Rule)</div>
          <div class="row q-gutter-sm">
            <div class="col">
              <div class="text-caption text-grey">VAT Reserve (15%)</div>
              <div class="text-body1">R {{ formatMoney(preview.tax.vat_reserve) }}</div>
            </div>
            <div class="col">
              <div class="text-caption text-grey">Income Tax Reserve</div>
              <div class="text-body1">R {{ formatMoney(preview.tax.tax_reserve) }}</div>
            </div>
            <div class="col">
              <div class="text-caption text-grey">Net After Tax</div>
              <div class="text-body1 text-weight-bold">R {{ formatMoney(preview.tax.net_after_tax) }}</div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <div class="text-center">
            <div class="text-h4" :class="preview.summary.net_cash_flow >= 0 ? 'text-positive' : 'text-negative'">
              R {{ formatMoney(preview.summary.net_cash_flow) }}
            </div>
            <div class="text-caption text-grey">Expected Net Cash Flow</div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Close" v-close-popup />
          <q-btn
            color="primary"
            icon="play_arrow"
            label="Generate Budget Card"
            @click="generateBudgetCard"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Bulk Entry Dialog -->
    <BulkBlueprintDialog v-model="showBulkDialog" @saved="onBulkSaved" />
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useRouter } from 'vue-router';
import { useBlueprintStore } from '@/stores/blueprint.store';
import { useBudgetCardStore } from '@/stores/budget-card.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useLiabilityStore } from '@/stores/liability.store';
import type { Blueprint, BudgetBucket, BlueprintItemType, BlueprintMatchType, BlueprintNature } from '@/types';
import { formatNumber } from '@/utils/currency';
import CurrencyInput from '@/components/CurrencyInput.vue';
import BlueprintItem from '@/components/budget/BlueprintItem.vue';
import BulkBlueprintDialog from '@/components/budget/BulkBlueprintDialog.vue';

const $q = useQuasar();
const router = useRouter();
const blueprintStore = useBlueprintStore();
const budgetCardStore = useBudgetCardStore();
const categoriesStore = useCategoriesStore();
const liabilityStore = useLiabilityStore();

// State
const showDialog = ref(false);
const showPreviewDialog = ref(false);
const showBulkDialog = ref(false);
const isEditing = ref(false);
const isSaving = ref(false);
const editingId = ref<string | null>(null);

// Expandable sections state (all collapsed by default for cleaner view)
const expandedSections = ref({
  income: false,
  needs: false,
  wants: false,
  savings: false,
});

const formData = ref({
  name: '',
  expected_amount: 0,
  item_type: 'expense' as BlueprintItemType,
  bucket: 'needs' as BudgetBucket,
  nature: 'private' as BlueprintNature,
  due_day: undefined as number | undefined,
  due_day_tolerance: 3,
  amount_tolerance: 5,
  category_id: undefined as string | undefined,
  liability_id: undefined as string | undefined,
  match_pattern: '',
  match_type: 'contains' as BlueprintMatchType,
  is_active: true,
  notes: '',
});

// Computed
const isLoading = computed(() => blueprintStore.isLoading);
const blueprints = computed(() => blueprintStore.blueprints);
const grouped = computed(() => blueprintStore.grouped);
const totals = computed(() => blueprintStore.totals);
const preview = computed(() => blueprintStore.preview);

// THE REAL NUMBERS - after 39.1% tax deduction
const actualSpendable = computed(() => totals.value.actual_spendable || 0);

// Goal comparison (R77,570 monthly goal)
const MONTHLY_GOAL = 77570;
const goalGap = computed(() => MONTHLY_GOAL - actualSpendable.value);

const categoryOptions = computed(() => [
  ...categoriesStore.incomeCategories.map((c) => ({ label: `${c.icon} ${c.name}`, value: c.id })),
  ...categoriesStore.expenseCategories.map((c) => ({ label: `${c.icon} ${c.name}`, value: c.id })),
]);

const liabilityOptions = computed(() => [
  { label: 'None (Regular Item)', value: undefined },
  ...liabilityStore.activeLiabilities.map((l) => ({
    label: `${l.name} (R${formatNumber(l.current_balance)} remaining)`,
    value: l.id,
  })),
]);

// Options
const bucketOptions = [
  { label: 'Needs (Essentials - 50%)', value: 'needs' },
  { label: 'Wants (Discretionary - 30%)', value: 'wants' },
  { label: 'Savings/Debt (20%)', value: 'savings' },
];

const matchTypeOptions = [
  { label: 'Contains', value: 'contains' },
  { label: 'Starts With', value: 'starts_with' },
  { label: 'Exact Match', value: 'exact' },
];

const natureOptions = [
  { label: 'Business (Tax-deductible)', value: 'business' },
  { label: 'Private (50/30/20)', value: 'private' },
];

const expenseBuckets = {
  needs: { label: 'Needs (Essentials)', color: 'blue', icon: 'home' },
  wants: { label: 'Wants (Discretionary)', color: 'pink', icon: 'favorite' },
  savings: { label: 'Savings & Debt', color: 'green', icon: 'savings' },
};

// Helpers
function formatMoney(amount: number): string {
  return formatNumber(amount);
}

// Actions
function openAddDialog() {
  isEditing.value = false;
  editingId.value = null;
  formData.value = {
    name: '',
    expected_amount: 0,
    item_type: 'expense',
    bucket: 'needs',
    nature: 'private',
    due_day: undefined,
    due_day_tolerance: 3,
    amount_tolerance: 5,
    category_id: undefined,
    liability_id: undefined,
    match_pattern: '',
    match_type: 'contains',
    is_active: true,
    notes: '',
  };
  showDialog.value = true;
}

function editBlueprint(item: Blueprint) {
  isEditing.value = true;
  editingId.value = item.id;
  formData.value = {
    name: item.name,
    expected_amount: item.expected_amount,
    item_type: item.item_type,
    bucket: item.bucket,
    nature: item.nature || 'private',
    due_day: item.due_day || undefined,
    due_day_tolerance: item.due_day_tolerance,
    amount_tolerance: item.amount_tolerance,
    category_id: item.category_id || undefined,
    liability_id: item.liability_id || undefined,
    match_pattern: item.match_pattern || '',
    match_type: item.match_type,
    is_active: item.is_active,
    notes: item.notes || '',
  };
  showDialog.value = true;
}

async function saveBlueprint() {
  if (!formData.value.name || formData.value.expected_amount <= 0) {
    $q.notify({ type: 'warning', message: 'Please fill in required fields' });
    return;
  }

  isSaving.value = true;

  try {
    const data = {
      name: formData.value.name,
      expected_amount: formData.value.expected_amount,
      item_type: formData.value.item_type,
      bucket: formData.value.item_type === 'income' ? 'needs' : formData.value.bucket,
      nature: formData.value.item_type === 'expense' ? formData.value.nature : 'private',
      due_day: formData.value.due_day,
      due_day_tolerance: formData.value.due_day_tolerance,
      amount_tolerance: formData.value.amount_tolerance,
      category_id: formData.value.category_id,
      liability_id: formData.value.item_type === 'expense' ? formData.value.liability_id : undefined,
      match_pattern: formData.value.match_pattern || undefined,
      match_type: formData.value.match_type,
      is_active: formData.value.is_active,
      notes: formData.value.notes || undefined,
    };

    if (isEditing.value && editingId.value) {
      await blueprintStore.updateBlueprint(editingId.value, data);
      $q.notify({ type: 'positive', message: 'Blueprint item updated' });
    } else {
      await blueprintStore.createBlueprint(data);
      $q.notify({ type: 'positive', message: 'Blueprint item added' });
    }
    showDialog.value = false;
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to save blueprint item' });
  } finally {
    isSaving.value = false;
  }
}

async function toggleBlueprint(item: Blueprint) {
  await blueprintStore.toggleBlueprint(item.id);
  $q.notify({
    type: 'info',
    message: item.is_active ? 'Item deactivated' : 'Item activated',
  });
}

function confirmDelete(item: Blueprint) {
  $q.dialog({
    title: 'Delete Blueprint Item',
    message: `Are you sure you want to delete "${item.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await blueprintStore.deleteBlueprint(item.id);
      $q.notify({ type: 'positive', message: 'Blueprint item deleted' });
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to delete item' });
    }
  });
}

async function importFromFixed() {
  $q.dialog({
    title: 'Import from Fixed Expenses',
    message: 'This will import your existing fixed expenses as blueprint items. Continue?',
    cancel: true,
  }).onOk(async () => {
    try {
      const result = await blueprintStore.importFromFixed();
      $q.notify({
        type: 'positive',
        message: `Imported ${result.imported} items, skipped ${result.skipped} duplicates`,
      });
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to import from fixed expenses' });
    }
  });
}

function onBulkSaved(result: { created: number; skipped: number }) {
  // Refresh is handled by the store, just show summary if needed
  if (result.skipped > 0) {
    $q.notify({
      type: 'info',
      message: `${result.skipped} duplicate items were skipped`,
    });
  }
}

async function showPreview() {
  try {
    await blueprintStore.fetchPreview();
    showPreviewDialog.value = true;
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to load preview' });
  }
}

async function generateBudgetCard() {
  const now = new Date();
  try {
    await budgetCardStore.generateCard({
      year: now.getFullYear(),
      month: now.getMonth() + 1,
    });
    showPreviewDialog.value = false;
    $q.notify({ type: 'positive', message: 'Budget card generated!' });
    router.push('/budget-card');
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Failed to generate budget card';
    $q.notify({ type: 'negative', message: msg });
  }
}

// Lifecycle
onMounted(() => {
  blueprintStore.fetchBlueprints();
  liabilityStore.loadLiabilities();
  if (categoriesStore.categories.length === 0) {
    categoriesStore.loadFromServer();
  }
});
</script>
