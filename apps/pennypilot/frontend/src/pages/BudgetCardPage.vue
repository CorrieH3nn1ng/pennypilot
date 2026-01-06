<template>
  <q-page class="q-pa-md">
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5">
          {{ currentCard?.card.period_label || 'Budget Card' }}
          <q-badge v-if="currentCard" :color="statusColor" class="q-ml-sm">
            {{ currentCard.card.status }}
          </q-badge>
          <q-badge v-if="currentCard?.card.letter_grade" color="amber" class="q-ml-xs">
            {{ currentCard.card.letter_grade }}
          </q-badge>
        </div>
        <div class="text-caption text-grey">Strategic audit of income & expenses</div>
      </div>
      <q-space />
      <q-btn-group v-if="hasCurrentCard && !isFinalized">
        <q-btn
          flat
          color="primary"
          icon="refresh"
          label="Run Audit"
          :loading="isAuditing"
          @click="runAudit"
        />
        <q-btn
          flat
          color="positive"
          icon="check_circle"
          label="Finalize"
          @click="showFinalizeDialog = true"
        />
      </q-btn-group>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="50px" color="primary" />
    </div>

    <!-- No Card State -->
    <div v-else-if="!hasCurrentCard" class="text-center q-pa-xl">
      <q-icon name="credit_card_off" size="80px" color="grey-4" />
      <div class="text-h6 text-grey q-mt-md">No Budget Card for this month</div>
      <div class="text-body2 text-grey q-mb-md">
        Generate a budget card from your blueprint to start tracking
      </div>
      <q-btn color="primary" icon="add" label="Generate Budget Card" @click="generateCard" />
    </div>

    <!-- Budget Card Content -->
    <template v-else>
      <!-- Stats Summary -->
      <div class="row q-gutter-md q-mb-lg">
        <q-card class="col-12 col-sm-3">
          <q-card-section>
            <div class="text-caption text-grey">Match Rate</div>
            <div class="text-h4 text-primary">{{ matchRate }}%</div>
            <q-linear-progress
              :value="matchRate / 100"
              color="primary"
              class="q-mt-sm"
              rounded
            />
          </q-card-section>
        </q-card>
        <q-card class="col-12 col-sm-3">
          <q-card-section>
            <div class="text-caption text-grey">Expected Income</div>
            <div class="text-h5 text-positive">R {{ formatMoney(stats?.total_expected_income || 0) }}</div>
            <div class="text-caption">
              Matched: R {{ formatMoney(stats?.total_matched_income || 0) }}
            </div>
          </q-card-section>
        </q-card>
        <q-card class="col-12 col-sm-3">
          <q-card-section>
            <div class="text-caption text-grey">Expected Expenses</div>
            <div class="text-h5 text-negative">R {{ formatMoney(stats?.total_expected_expense || 0) }}</div>
            <div class="text-caption">
              Matched: R {{ formatMoney(stats?.total_matched_expense || 0) }}
            </div>
          </q-card-section>
        </q-card>
        <q-card class="col-12 col-sm-3">
          <q-card-section>
            <div class="text-caption text-grey">Leaks</div>
            <div class="text-h5 text-warning">R {{ formatMoney(stats?.total_leaked || 0) }}</div>
            <div class="text-caption">
              {{ stats?.leak_count || 0 }} unplanned expenses
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Tax Reserve Card -->
      <q-card class="q-mb-md bg-deep-purple-1">
        <q-card-section class="q-py-sm">
          <div class="row items-center">
            <q-icon name="account_balance" color="deep-purple" size="sm" class="q-mr-sm" />
            <div class="text-subtitle2 text-deep-purple">Tax Reserves (39.1% Rule)</div>
            <q-space />
            <div class="text-body2 q-mr-md">
              VAT: <strong>R {{ formatMoney(stats?.tax_reserve?.vat || 0) }}</strong>
            </div>
            <div class="text-body2 q-mr-md">
              Tax: <strong>R {{ formatMoney(stats?.tax_reserve?.income_tax || 0) }}</strong>
            </div>
            <div class="text-body2 text-weight-bold text-deep-purple">
              Total: R {{ formatMoney(stats?.tax_reserve?.total || 0) }}
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Tabs -->
      <q-tabs
        v-model="activeTab"
        class="text-grey"
        active-color="primary"
        indicator-color="primary"
        align="justify"
      >
        <q-tab name="planned" label="Planned" :badge="pendingCount || undefined" />
        <q-tab name="matched" label="Matched" :badge="matchedCount || undefined" badge-color="positive" />
        <q-tab name="leaks" label="Leaks" :badge="leakCount || undefined" badge-color="warning" />
        <q-tab name="windfalls" label="Windfalls" :badge="windfallCount || undefined" badge-color="info" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="activeTab" animated>
        <!-- Planned Items Tab -->
        <q-tab-panel name="planned" class="q-pa-none">
          <q-list separator v-if="pendingItems.length > 0">
            <BudgetCardItemRow
              v-for="item in pendingItems"
              :key="item.id"
              :item="item"
              @match="showMatchDialog"
            />
          </q-list>
          <div v-else class="text-center q-pa-lg text-grey">
            All planned items have been matched or marked as missing.
          </div>
        </q-tab-panel>

        <!-- Matched Items Tab -->
        <q-tab-panel name="matched" class="q-pa-none">
          <q-list separator v-if="matchedItems.length > 0">
            <BudgetCardItemRow
              v-for="item in matchedItems"
              :key="item.id"
              :item="item"
              :show-matched="true"
              @unmatch="unmatchItem"
            />
          </q-list>
          <div v-else class="text-center q-pa-lg text-grey">
            No matched items yet. Run the audit to match transactions.
          </div>
        </q-tab-panel>

        <!-- Leaks Tab -->
        <q-tab-panel name="leaks" class="q-pa-none">
          <q-list separator v-if="leaks.length > 0">
            <LeakItem
              v-for="leak in leaks"
              :key="leak.id"
              :audit="leak"
              @silence="silenceLeak"
              @add-to-blueprint="addLeakToBlueprint"
            />
          </q-list>
          <div v-else class="text-center q-pa-lg text-grey">
            No unplanned expenses detected. Great job!
          </div>
        </q-tab-panel>

        <!-- Windfalls Tab -->
        <q-tab-panel name="windfalls" class="q-pa-none">
          <q-list separator v-if="windfalls.length > 0">
            <WindfallItem
              v-for="windfall in windfalls"
              :key="windfall.id"
              :audit="windfall"
              @add-to-blueprint="addWindfallToBlueprint"
            />
          </q-list>
          <div v-else class="text-center q-pa-lg text-grey">
            No unplanned income detected.
          </div>
        </q-tab-panel>
      </q-tab-panels>

      <!-- Add One-Off FAB -->
      <q-page-sticky position="bottom-right" :offset="[18, 18]" v-if="!isFinalized">
        <q-btn fab icon="add" color="primary" @click="showAddItemDialog = true">
          <q-tooltip>Add One-Off Item</q-tooltip>
        </q-btn>
      </q-page-sticky>
    </template>

    <!-- Finalize Dialog -->
    <q-dialog v-model="showFinalizeDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">Finalize Budget Card</div>
        </q-card-section>

        <q-card-section>
          <div class="text-subtitle1 q-mb-md">Month-End CFO Review</div>

          <div class="q-mb-md" v-if="gradePreview">
            <div class="text-center">
              <div class="text-h2" :class="gradeColor">{{ gradePreview.letter_grade }}</div>
              <div class="text-caption text-grey">Success Grade: {{ gradePreview.success_grade.toFixed(1) }}%</div>
            </div>
            <div class="row q-gutter-sm q-mt-md">
              <div class="col text-center">
                <div class="text-caption">Income</div>
                <div class="text-weight-bold">{{ gradePreview.income_score.toFixed(0) }}%</div>
              </div>
              <div class="col text-center">
                <div class="text-caption">Discipline</div>
                <div class="text-weight-bold">{{ gradePreview.discipline_score.toFixed(0) }}%</div>
              </div>
              <div class="col text-center">
                <div class="text-caption">Leak Penalty</div>
                <div class="text-weight-bold text-negative">-{{ gradePreview.leak_penalty.toFixed(0) }}%</div>
              </div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 q-mb-sm">What should we do with any surplus?</div>
          <q-option-group
            v-model="surplusAction"
            :options="[
              { label: 'Bank to Savings', value: 'savings' },
              { label: 'Leave as Buffer', value: 'buffer' },
            ]"
            color="primary"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            icon="check"
            label="Finalize Month"
            :loading="isFinalizing"
            @click="finalizeCard"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add One-Off Item Dialog -->
    <q-dialog v-model="showAddItemDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Add One-Off Item</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-btn-toggle
            v-model="addItemForm.item_type"
            spread
            no-caps
            toggle-color="primary"
            :options="[
              { label: 'Income', value: 'income' },
              { label: 'Expense', value: 'expense' },
            ]"
          />

          <q-input
            v-model="addItemForm.name"
            label="Name *"
            outlined
            placeholder="e.g., Bonus, Gift, Emergency repair"
          />

          <CurrencyInput
            v-model="addItemForm.expected_amount"
            label="Expected Amount *"
            outlined
          />

          <q-select
            v-if="addItemForm.item_type === 'expense'"
            v-model="addItemForm.bucket"
            :options="bucketOptions"
            label="Category (50/30/20)"
            outlined
            emit-value
            map-options
          />

          <q-input
            v-model.number="addItemForm.expected_day"
            label="Expected Day"
            outlined
            type="number"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Add"
            :loading="isAddingItem"
            @click="addOneOffItem"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Manual Match Dialog -->
    <q-dialog v-model="showMatchDialogVisible" persistent>
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">Match Transaction</div>
          <div class="text-caption text-grey" v-if="matchingItem">
            Finding matches for: {{ matchingItem.name }}
          </div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="matchSearch"
            label="Search transactions"
            outlined
            dense
            debounce="300"
          />
          <q-list separator class="q-mt-md" style="max-height: 300px; overflow-y: auto">
            <q-item
              v-for="tx in matchableTransactions"
              :key="tx.id"
              clickable
              @click="matchTransaction(tx.id!)"
            >
              <q-item-section>
                <q-item-label>{{ tx.description }}</q-item-label>
                <q-item-label caption>{{ tx.transaction_date }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label :class="Number(tx.amount) >= 0 ? 'text-positive' : 'text-negative'">
                  R {{ formatMoney(Math.abs(Number(tx.amount))) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Liability Pay-Off Celebration Dialog -->
    <LiabilityCelebrationDialog
      v-model="showCelebration"
      :celebration="pendingCelebration"
      @close="handleCelebrationClose"
    />
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useBudgetCardStore } from '@/stores/budget-card.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useBlueprintStore } from '@/stores/blueprint.store';
import { useLiabilityStore } from '@/stores/liability.store';
import type { BudgetCardItem, TransactionAudit, SurplusAction, SuccessGrade, BlueprintItemType, BudgetBucket, LiabilityCelebration } from '@/types';
import { formatNumber } from '@/utils/currency';
import CurrencyInput from '@/components/CurrencyInput.vue';
import BudgetCardItemRow from '@/components/budget/BudgetCardItemRow.vue';
import LeakItem from '@/components/budget/LeakItem.vue';
import WindfallItem from '@/components/budget/WindfallItem.vue';
import LiabilityCelebrationDialog from '@/components/budget/LiabilityCelebrationDialog.vue';

const $q = useQuasar();
const budgetCardStore = useBudgetCardStore();
const transactionsStore = useTransactionsStore();
const blueprintStore = useBlueprintStore();
const liabilityStore = useLiabilityStore();

// State
const activeTab = ref('planned');
const showCelebration = ref(false);
const pendingCelebration = ref<LiabilityCelebration | null>(null);
const showFinalizeDialog = ref(false);
const showAddItemDialog = ref(false);
const showMatchDialogVisible = ref(false);
const matchingItem = ref<BudgetCardItem | null>(null);
const matchSearch = ref('');
const surplusAction = ref<SurplusAction>('savings');
const isFinalizing = ref(false);
const isAddingItem = ref(false);
const gradePreview = ref<SuccessGrade | null>(null);

const addItemForm = ref({
  name: '',
  expected_amount: 0,
  item_type: 'expense' as BlueprintItemType,
  bucket: 'needs' as BudgetBucket,
  expected_day: undefined as number | undefined,
});

const bucketOptions = [
  { label: 'Needs (Essentials)', value: 'needs' },
  { label: 'Wants (Discretionary)', value: 'wants' },
  { label: 'Savings/Debt', value: 'savings' },
];

// Computed
const isLoading = computed(() => budgetCardStore.isLoading);
const isAuditing = computed(() => budgetCardStore.isAuditing);
const hasCurrentCard = computed(() => budgetCardStore.hasCurrentCard);
const currentCard = computed(() => budgetCardStore.currentCard);
const stats = computed(() => budgetCardStore.currentStats);
const pendingItems = computed(() => budgetCardStore.pendingItems);
const matchedItems = computed(() => budgetCardStore.matchedItems);
const leaks = computed(() => budgetCardStore.leaks);
const windfalls = computed(() => budgetCardStore.windfalls);
const matchRate = computed(() => budgetCardStore.matchRate);
const isFinalized = computed(() => currentCard.value?.card.is_finalized ?? false);

const pendingCount = computed(() => pendingItems.value.length);
const matchedCount = computed(() => matchedItems.value.length);
const leakCount = computed(() => leaks.value.filter(l => !l.is_silenced).length);
const windfallCount = computed(() => windfalls.value.length);

const statusColor = computed(() => {
  switch (currentCard.value?.card.status) {
    case 'draft': return 'grey';
    case 'active': return 'primary';
    case 'finalized': return 'positive';
    default: return 'grey';
  }
});

const gradeColor = computed(() => {
  const letter = gradePreview.value?.letter_grade || '';
  if (letter === 'A') return 'text-positive';
  if (letter === 'B') return 'text-info';
  if (letter === 'C') return 'text-warning';
  return 'text-negative';
});

const matchableTransactions = computed(() => {
  const search = matchSearch.value.toLowerCase();
  return transactionsStore.transactions
    .filter(tx => {
      if (!tx.id) return false;
      if (search && !tx.description.toLowerCase().includes(search)) return false;
      return true;
    })
    .slice(0, 20);
});

// Helpers
function formatMoney(amount: number): string {
  return formatNumber(amount);
}

// Actions
async function generateCard() {
  const now = new Date();
  try {
    await budgetCardStore.generateCard({
      year: now.getFullYear(),
      month: now.getMonth() + 1,
    });
    $q.notify({ type: 'positive', message: 'Budget card generated!' });
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Failed to generate budget card';
    $q.notify({ type: 'negative', message: msg });
  }
}

async function runAudit() {
  try {
    const result = await budgetCardStore.runAudit();
    $q.notify({
      type: 'positive',
      message: `Audit complete: ${result.matched} matched, ${result.leaks} leaks`,
    });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to run audit' });
  }
}

async function loadGradePreview() {
  if (!currentCard.value?.card.id) return;
  try {
    gradePreview.value = await budgetCardStore.getGrade(currentCard.value.card.id);
  } catch {
    // Silently fail
  }
}

async function finalizeCard() {
  if (!currentCard.value?.card.id) return;

  isFinalizing.value = true;
  try {
    const result = await budgetCardStore.finalizeCard(currentCard.value.card.id, {
      surplus_action: surplusAction.value,
    });
    showFinalizeDialog.value = false;
    $q.notify({
      type: 'positive',
      message: `Budget card finalized with grade ${result.grade.letter_grade}!`,
    });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to finalize budget card' });
  } finally {
    isFinalizing.value = false;
  }
}

async function addOneOffItem() {
  if (!currentCard.value?.card.id || !addItemForm.value.name || addItemForm.value.expected_amount <= 0) {
    $q.notify({ type: 'warning', message: 'Please fill in required fields' });
    return;
  }

  isAddingItem.value = true;
  try {
    await budgetCardStore.addItem(currentCard.value.card.id, addItemForm.value);
    showAddItemDialog.value = false;
    addItemForm.value = {
      name: '',
      expected_amount: 0,
      item_type: 'expense',
      bucket: 'needs',
      expected_day: undefined,
    };
    $q.notify({ type: 'positive', message: 'One-off item added' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to add item' });
  } finally {
    isAddingItem.value = false;
  }
}

function showMatchDialog(item: BudgetCardItem) {
  matchingItem.value = item;
  matchSearch.value = '';
  showMatchDialogVisible.value = true;
}

async function matchTransaction(transactionId: string) {
  if (!matchingItem.value) return;

  try {
    await budgetCardStore.matchTransaction({
      transaction_id: transactionId,
      budget_card_item_id: matchingItem.value.id,
    });
    showMatchDialogVisible.value = false;
    matchingItem.value = null;
    $q.notify({ type: 'positive', message: 'Transaction matched' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to match transaction' });
  }
}

async function unmatchItem(item: BudgetCardItem) {
  try {
    await budgetCardStore.unmatchTransaction(item.id);
    $q.notify({ type: 'info', message: 'Transaction unmatched' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to unmatch transaction' });
  }
}

async function silenceLeak(audit: TransactionAudit) {
  try {
    await budgetCardStore.silenceLeak(audit.id);
    $q.notify({ type: 'info', message: 'Leak silenced' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to silence leak' });
  }
}

async function addLeakToBlueprint(audit: TransactionAudit, bucket: BudgetBucket) {
  try {
    await budgetCardStore.convertLeakToBlueprint({
      transaction_audit_id: audit.id,
      bucket,
    });
    $q.notify({ type: 'positive', message: 'Added to blueprint for future months' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to add to blueprint' });
  }
}

async function addWindfallToBlueprint(audit: TransactionAudit) {
  // Convert windfall to income blueprint
  const tx = audit.transaction;
  if (!tx) return;

  try {
    await blueprintStore.createBlueprint({
      name: tx.description.substring(0, 50),
      expected_amount: Math.abs(Number(tx.amount)),
      item_type: 'income',
    });
    $q.notify({ type: 'positive', message: 'Added as recurring income source' });
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to add to blueprint' });
  }
}

// Celebration handling
function handleCelebrationClose() {
  pendingCelebration.value = null;
  liabilityStore.clearCelebration();
  // Refresh liabilities to update balances
  liabilityStore.loadLiabilities();
}

// Watch for finalize dialog to load grade
watch(showFinalizeDialog, async (val) => {
  if (val) {
    await loadGradePreview();
  }
});

// Watch for liability celebrations from store
watch(
  () => liabilityStore.pendingCelebration,
  (celebration) => {
    if (celebration) {
      pendingCelebration.value = celebration;
      showCelebration.value = true;
    }
  },
  { immediate: true }
);

// Lifecycle
onMounted(async () => {
  await budgetCardStore.fetchCurrentCard();
  await transactionsStore.fetchTransactions();
  // Load liabilities for pay-off celebration checks
  await liabilityStore.loadRecentlyPaidOff();
});
</script>
