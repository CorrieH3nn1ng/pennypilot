<template>
  <q-dialog
    v-model="showDialog"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="interrogator-card">
      <!-- Header -->
      <q-card-section class="header-section">
        <div class="header-content">
          <q-btn flat round icon="close" color="grey-7" @click="close" />
          <div class="header-title">
            <q-icon name="psychology" color="teal" size="24px" />
            <span>Penny's Discovery</span>
          </div>
          <div class="header-progress">
            {{ remainingCount }} left
          </div>
        </div>
        <q-linear-progress
          :value="progress / 100"
          color="teal"
          track-color="teal-2"
          size="4px"
          class="progress-bar"
        />
      </q-card-section>

      <!-- Loading State -->
      <div v-if="isLoading" class="loading-container">
        <q-spinner-dots color="teal" size="48px" />
        <div class="loading-text">Finding transactions...</div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!currentPattern" class="empty-container">
        <div class="celebration-icon">
          <q-icon name="celebration" color="amber" size="64px" />
        </div>
        <div class="empty-title">All Caught Up!</div>
        <div class="empty-subtitle">
          Every transaction has been assigned to a budget bucket.
        </div>
        <q-btn
          color="teal"
          label="Back to Dashboard"
          unelevated
          class="q-mt-lg"
          @click="close"
        />
      </div>

      <!-- Transaction Card -->
      <div v-else class="card-container">
        <q-card class="transaction-card" flat bordered>
          <!-- Pattern Badge -->
          <div class="pattern-badge">
            <q-icon name="store" size="16px" />
            <span>{{ currentPattern.pattern }}</span>
          </div>

          <!-- Amount Display -->
          <div class="amount-display">
            <div class="amount-label">Total This Month</div>
            <div class="amount-value">
              R {{ formatAmount(currentPattern.total_amount) }}
            </div>
            <div class="amount-count">
              {{ currentPattern.count }} transaction{{ currentPattern.count > 1 ? 's' : '' }}
            </div>
          </div>

          <!-- Sample Description -->
          <div class="sample-section">
            <div class="sample-label">Example:</div>
            <div class="sample-text">{{ currentPattern.sample_description }}</div>
          </div>

          <!-- Category Info -->
          <div v-if="currentPattern.category" class="category-info">
            <q-chip
              :style="{ backgroundColor: currentPattern.category.color }"
              text-color="white"
              size="sm"
            >
              <q-icon :name="currentPattern.category.icon" size="14px" class="q-mr-xs" />
              {{ currentPattern.category.name }}
            </q-chip>
          </div>
        </q-card>

        <!-- Bucket Assignment Question -->
        <div class="question-section">
          <div class="question-text">
            What kind of expense is this?
          </div>
          <div class="question-hint">
            This helps Penny track your {{ methodologyName }} budget
          </div>
        </div>

        <!-- Bucket Buttons -->
        <div class="bucket-buttons">
          <q-btn
            class="bucket-btn needs-btn"
            unelevated
            @click="assignBucket('needs')"
            :loading="isAssigning"
          >
            <div class="bucket-btn-content">
              <q-icon name="home" size="28px" />
              <span class="bucket-label">Essential Need</span>
              <span class="bucket-hint">Must pay - rent, food, utilities</span>
            </div>
          </q-btn>

          <q-btn
            class="bucket-btn wants-btn"
            unelevated
            @click="assignBucket('wants')"
            :loading="isAssigning"
          >
            <div class="bucket-btn-content">
              <q-icon name="favorite" size="28px" />
              <span class="bucket-label">Lifestyle Want</span>
              <span class="bucket-hint">Nice to have - dining, entertainment</span>
            </div>
          </q-btn>

          <q-btn
            class="bucket-btn savings-btn"
            unelevated
            @click="assignBucket('savings')"
            :loading="isAssigning"
          >
            <div class="bucket-btn-content">
              <q-icon name="savings" size="28px" />
              <span class="bucket-label">Savings / Debt</span>
              <span class="bucket-hint">Future you - savings, investments, debt</span>
            </div>
          </q-btn>
        </div>

        <!-- Skip Button -->
        <div class="skip-section">
          <q-btn
            flat
            color="grey-6"
            label="Skip for now"
            size="sm"
            @click="skip"
          />
        </div>
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useDiscoveryStore } from '@/stores/discovery.store';
import { useBudgetStore } from '@/stores/budget.store';
import type { BudgetBucket } from '@/types';

const props = defineProps<{
  modelValue: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'complete'): void;
}>();

const $q = useQuasar();
const discoveryStore = useDiscoveryStore();
const budgetStore = useBudgetStore();

const showDialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const isAssigning = ref(false);

// Computed
const isLoading = computed(() => discoveryStore.isLoading);
const currentPattern = computed(() => discoveryStore.currentPattern);
const progress = computed(() => discoveryStore.progress);
const remainingCount = computed(() => discoveryStore.remainingCount);

const methodologyName = computed(() => {
  const methodology = budgetStore.currentBudget?.methodology;
  switch (methodology) {
    case '50-30-20':
      return '50/30/20';
    case 'zero-based':
      return 'Zero-Based';
    case 'profit-first':
      return 'Profit First';
    default:
      return 'budget';
  }
});

// Load data when dialog opens
watch(showDialog, async (isOpen) => {
  if (isOpen) {
    await discoveryStore.loadUnassigned();
  }
});

// Methods
function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

async function assignBucket(bucket: BudgetBucket): Promise<void> {
  if (!currentPattern.value || isAssigning.value) return;

  isAssigning.value = true;

  const success = await discoveryStore.assignBucket(
    currentPattern.value.pattern,
    bucket,
    currentPattern.value.category?.id,
    currentPattern.value.transactions.map((t) => t.id).filter(Boolean) as string[]
  );

  if (success) {
    const bucketName = bucket === 'needs' ? 'Essential Need' : bucket === 'wants' ? 'Lifestyle Want' : 'Savings/Debt';
    $q.notify({
      type: 'positive',
      message: `Assigned to ${bucketName}`,
      position: 'bottom',
      timeout: 1500,
    });

    // Check if all done
    if (!discoveryStore.hasUnassigned) {
      emit('complete');
    }
  }

  isAssigning.value = false;
}

function skip(): void {
  discoveryStore.skipPattern();
}

function close(): void {
  showDialog.value = false;
}
</script>

<style scoped>
.interrogator-card {
  background: linear-gradient(180deg, #E0F2F1 0%, #FFFFFF 30%);
  display: flex;
  flex-direction: column;
}

.header-section {
  padding: 8px 8px 0;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #004D40;
}

.header-progress {
  font-size: 13px;
  color: #78909C;
  padding-right: 8px;
}

.progress-bar {
  margin-top: 8px;
}

.loading-container,
.empty-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px;
}

.loading-text {
  margin-top: 16px;
  color: #78909C;
}

.celebration-icon {
  width: 96px;
  height: 96px;
  background: #FFF8E1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}

.empty-title {
  font-size: 24px;
  font-weight: 700;
  color: #004D40;
}

.empty-subtitle {
  font-size: 14px;
  color: #78909C;
  margin-top: 8px;
  text-align: center;
}

.card-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 16px;
  overflow-y: auto;
}

.transaction-card {
  border-radius: 16px;
  border-color: #B2DFDB;
  padding: 20px;
  margin-bottom: 24px;
}

.pattern-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #E0F2F1;
  color: #004D40;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 16px;
}

.amount-display {
  text-align: center;
  margin-bottom: 20px;
}

.amount-label {
  font-size: 12px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.amount-value {
  font-size: 36px;
  font-weight: 700;
  color: #C62828;
  line-height: 1.2;
}

.amount-count {
  font-size: 13px;
  color: #90A4AE;
  margin-top: 4px;
}

.sample-section {
  background: #FAFAFA;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
}

.sample-label {
  font-size: 11px;
  color: #90A4AE;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.sample-text {
  font-size: 13px;
  color: #37474F;
  font-family: monospace;
  word-break: break-all;
}

.category-info {
  display: flex;
  justify-content: center;
}

.question-section {
  text-align: center;
  margin-bottom: 20px;
}

.question-text {
  font-size: 18px;
  font-weight: 600;
  color: #37474F;
}

.question-hint {
  font-size: 13px;
  color: #90A4AE;
  margin-top: 4px;
}

.bucket-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bucket-btn {
  min-height: 80px;
  border-radius: 12px;
  text-transform: none;
  padding: 16px;
}

.bucket-btn-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 100%;
}

.bucket-label {
  font-size: 16px;
  font-weight: 600;
}

.bucket-hint {
  font-size: 11px;
  opacity: 0.8;
}

.needs-btn {
  background: linear-gradient(135deg, #004D40 0%, #00796B 100%) !important;
  color: white !important;
}

.wants-btn {
  background: linear-gradient(135deg, #FF8F00 0%, #FFA726 100%) !important;
  color: white !important;
}

.savings-btn {
  background: linear-gradient(135deg, #1565C0 0%, #42A5F5 100%) !important;
  color: white !important;
}

.skip-section {
  text-align: center;
  margin-top: 16px;
  padding-bottom: 24px;
}
</style>
