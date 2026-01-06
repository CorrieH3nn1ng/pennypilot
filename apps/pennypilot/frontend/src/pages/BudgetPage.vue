<template>
  <q-page class="q-pa-md">
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5">Budget</div>
        <div class="text-caption text-grey" v-if="currentBudget">
          {{ currentBudget.period_name }} - {{ getMethodologyLabel(currentBudget.methodology) }}
        </div>
      </div>
      <q-space />
      <q-btn
        v-if="!hasBudget"
        color="primary"
        icon="add"
        label="Create Budget"
        @click="openCreateDialog"
      />
      <q-btn v-else flat icon="history" @click="showHistory = true">
        <q-tooltip>Budget History</q-tooltip>
      </q-btn>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <!-- No Budget State -->
    <div v-else-if="!hasBudget" class="text-center q-pa-xl">
      <q-icon name="account_balance_wallet" size="80px" color="grey-4" />
      <div class="text-h6 text-grey q-mt-md">No budget for this month</div>
      <div class="text-body2 text-grey q-mb-md">
        Create a budget to start planning your finances
      </div>
      <q-btn color="primary" icon="add" label="Create Budget" @click="openCreateDialog" />
    </div>

    <!-- Budget View -->
    <template v-else>
      <!-- Income Summary Card -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="row items-center">
            <div class="col">
              <div class="text-caption text-grey">Gross Income</div>
              <div class="text-h6">R {{ formatMoney(currentBudget.total_income) }}</div>
            </div>
            <div class="col text-center">
              <div class="text-caption text-grey">Tax Provision</div>
              <div class="text-h6 text-negative">- R {{ formatMoney(currentBudget.tax_provision) }}</div>
            </div>
            <div class="col text-right">
              <div class="text-caption text-grey">Net Available</div>
              <div class="text-h6 text-primary">R {{ formatMoney(currentBudget.net_available) }}</div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- 50/30/20 Buckets -->
      <div class="row q-gutter-md q-mb-md">
        <!-- Needs (50%) -->
        <q-card class="col">
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <q-icon name="home" color="blue" size="sm" class="q-mr-sm" />
              <span class="text-weight-medium">Needs</span>
              <q-space />
              <q-badge color="blue">50%</q-badge>
            </div>
            <q-linear-progress
              :value="getNeedsProgress"
              color="blue"
              track-color="blue-2"
              size="10px"
              rounded
              class="q-mb-sm"
            />
            <div class="row text-caption">
              <div class="col">R {{ formatMoney(bucketTotals?.needs.actual || 0) }}</div>
              <div class="col text-right">
                of R {{ formatMoney(targetAllocations?.needs || 0) }}
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- Wants (30%) -->
        <q-card class="col">
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <q-icon name="favorite" color="pink" size="sm" class="q-mr-sm" />
              <span class="text-weight-medium">Wants</span>
              <q-space />
              <q-badge color="pink">30%</q-badge>
            </div>
            <q-linear-progress
              :value="getWantsProgress"
              color="pink"
              track-color="pink-2"
              size="10px"
              rounded
              class="q-mb-sm"
            />
            <div class="row text-caption">
              <div class="col">R {{ formatMoney(bucketTotals?.wants.actual || 0) }}</div>
              <div class="col text-right">
                of R {{ formatMoney(targetAllocations?.wants || 0) }}
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- Savings (20%) -->
        <q-card class="col">
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <q-icon name="savings" color="green" size="sm" class="q-mr-sm" />
              <span class="text-weight-medium">Savings</span>
              <q-space />
              <q-badge color="green">20%</q-badge>
            </div>
            <q-linear-progress
              :value="getSavingsProgress"
              color="green"
              track-color="green-2"
              size="10px"
              rounded
              class="q-mb-sm"
            />
            <div class="row text-caption">
              <div class="col">R {{ formatMoney(bucketTotals?.savings.actual || 0) }}</div>
              <div class="col text-right">
                of R {{ formatMoney(targetAllocations?.savings || 0) }}
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Budget Items by Bucket -->
      <q-card>
        <q-tabs v-model="activeTab" class="text-grey" active-color="primary" indicator-color="primary">
          <q-tab name="needs" label="Needs" />
          <q-tab name="wants" label="Wants" />
          <q-tab name="savings" label="Savings" />
        </q-tabs>

        <q-separator />

        <q-tab-panels v-model="activeTab" animated>
          <q-tab-panel name="needs">
            <BudgetItemsList
              :items="needsItems"
              bucket="needs"
              @add="addBudgetItem('needs')"
            />
          </q-tab-panel>

          <q-tab-panel name="wants">
            <BudgetItemsList
              :items="wantsItems"
              bucket="wants"
              @add="addBudgetItem('wants')"
            />
          </q-tab-panel>

          <q-tab-panel name="savings">
            <BudgetItemsList
              :items="savingsItems"
              bucket="savings"
              @add="addBudgetItem('savings')"
            />
          </q-tab-panel>
        </q-tab-panels>
      </q-card>

      <!-- Remaining Budget -->
      <q-card class="q-mt-md">
        <q-card-section>
          <div class="row items-center">
            <div class="col">
              <div class="text-caption text-grey">Total Planned</div>
              <div class="text-h6">R {{ formatMoney(totalPlanned) }}</div>
            </div>
            <div class="col text-center">
              <div class="text-caption text-grey">Total Spent</div>
              <div class="text-h6 text-negative">R {{ formatMoney(totalActual) }}</div>
            </div>
            <div class="col text-right">
              <div class="text-caption text-grey">Remaining</div>
              <div class="text-h6" :class="remaining >= 0 ? 'text-positive' : 'text-negative'">
                R {{ formatMoney(remaining) }}
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </template>

    <!-- Create Budget Dialog -->
    <q-dialog v-model="showCreateDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Create Monthly Budget</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <div class="row q-gutter-md">
            <q-select
              v-model="createForm.year"
              :options="yearOptions"
              label="Year"
              outlined
              class="col"
            />
            <q-select
              v-model="createForm.month"
              :options="monthOptions"
              label="Month"
              outlined
              emit-value
              map-options
              class="col"
            />
          </div>

          <q-select
            v-model="createForm.methodology"
            :options="methodologyOptions"
            label="Budget Method"
            outlined
            emit-value
            map-options
          />

          <q-select
            v-model="createForm.rollover_option"
            :options="rolloverOptions"
            label="Unspent Budget"
            outlined
            emit-value
            map-options
            hint="What happens to unspent money at month end"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Create" :loading="isCreating" @click="createBudget" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Budget History Dialog -->
    <q-dialog v-model="showHistory" full-width>
      <q-card>
        <q-card-section class="row items-center">
          <div class="text-h6">Budget History</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-list separator>
            <q-item v-for="budget in budgetHistory" :key="budget.id" clickable @click="viewBudget(budget.id)">
              <q-item-section>
                <q-item-label>{{ budget.period_name }}</q-item-label>
                <q-item-label caption>{{ getMethodologyLabel(budget.methodology) }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge :color="getStatusColor(budget.status)">{{ budget.status }}</q-badge>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useBudgetStore } from '@/stores/budget.store';
import type { BudgetMethodology, BudgetBucket } from '@/types';
import BudgetItemsList from '@/components/budget/BudgetItemsList.vue';

const $q = useQuasar();
const budgetStore = useBudgetStore();

// State
const activeTab = ref<BudgetBucket>('needs');
const showCreateDialog = ref(false);
const showHistory = ref(false);
const isCreating = ref(false);

const createForm = ref({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  methodology: '50-30-20' as BudgetMethodology,
  rollover_option: 'reset' as 'savings' | 'rollover' | 'reset',
});

// Computed
const isLoading = computed(() => budgetStore.isLoading);
const hasBudget = computed(() => budgetStore.hasBudget);
const currentBudget = computed(() => budgetStore.currentBudget);
const bucketTotals = computed(() => budgetStore.bucketTotals);
const targetAllocations = computed(() => budgetStore.targetAllocations);
const budgetHistory = computed(() => budgetStore.budgetHistory);
const needsItems = computed(() => budgetStore.needsItems);
const wantsItems = computed(() => budgetStore.wantsItems);
const savingsItems = computed(() => budgetStore.savingsItems);
const totalPlanned = computed(() => budgetStore.totalPlanned);
const totalActual = computed(() => budgetStore.totalActual);
const remaining = computed(() => budgetStore.remaining);

const getNeedsProgress = computed(() => {
  if (!targetAllocations.value?.needs) return 0;
  return Math.min(1, (bucketTotals.value?.needs.actual || 0) / targetAllocations.value.needs);
});

const getWantsProgress = computed(() => {
  if (!targetAllocations.value?.wants) return 0;
  return Math.min(1, (bucketTotals.value?.wants.actual || 0) / targetAllocations.value.wants);
});

const getSavingsProgress = computed(() => {
  if (!targetAllocations.value?.savings) return 0;
  return Math.min(1, (bucketTotals.value?.savings.actual || 0) / targetAllocations.value.savings);
});

// Options
const yearOptions = [2024, 2025, 2026];
const monthOptions = [
  { label: 'January', value: 1 },
  { label: 'February', value: 2 },
  { label: 'March', value: 3 },
  { label: 'April', value: 4 },
  { label: 'May', value: 5 },
  { label: 'June', value: 6 },
  { label: 'July', value: 7 },
  { label: 'August', value: 8 },
  { label: 'September', value: 9 },
  { label: 'October', value: 10 },
  { label: 'November', value: 11 },
  { label: 'December', value: 12 },
];

const methodologyOptions = [
  { label: '50/30/20 Rule', value: '50-30-20' },
  { label: 'Zero-Based', value: 'zero-based' },
  { label: 'Envelope System', value: 'envelope' },
  { label: 'Pay Yourself First', value: 'pay-yourself-first' },
];

const rolloverOptions = [
  { label: 'Reset each month', value: 'reset' },
  { label: 'Roll over to next month', value: 'rollover' },
  { label: 'Transfer to savings', value: 'savings' },
];

// Helpers
function formatMoney(amount: number): string {
  return amount.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getMethodologyLabel(methodology: BudgetMethodology): string {
  return methodologyOptions.find((o) => o.value === methodology)?.label || methodology;
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'active': return 'positive';
    case 'draft': return 'warning';
    case 'closed': return 'grey';
    default: return 'grey';
  }
}

// Actions
function openCreateDialog() {
  createForm.value = {
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    methodology: '50-30-20',
    rollover_option: 'reset',
  };
  showCreateDialog.value = true;
}

async function createBudget() {
  isCreating.value = true;

  try {
    await budgetStore.createBudget(createForm.value);
    $q.notify({ type: 'positive', message: 'Budget created successfully' });
    showCreateDialog.value = false;
    await budgetStore.loadCurrentBudget();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to create budget' });
  } finally {
    isCreating.value = false;
  }
}

function addBudgetItem(bucket: BudgetBucket) {
  // TODO: Open add item dialog
  $q.notify({ type: 'info', message: `Add ${bucket} item - Coming soon!` });
}

function viewBudget(id: string) {
  // TODO: Load specific budget
  showHistory.value = false;
}

// Lifecycle
onMounted(async () => {
  await budgetStore.loadCurrentBudget();
  await budgetStore.loadBudgetHistory();
});
</script>
