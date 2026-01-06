<template>
  <div class="wizard-interrogator">
    <!-- Loading State -->
    <div v-if="isProcessing" class="loading-container">
      <q-spinner-dots color="teal" size="48px" />
      <div class="loading-text">Grouping transactions by merchant...</div>
    </div>

    <!-- Empty State - All Done -->
    <div v-else-if="!currentPattern" class="complete-container">
      <div class="celebration-icon">
        <q-icon name="celebration" color="amber" size="64px" />
      </div>
      <div class="complete-title">All Patterns Assigned!</div>
      <div class="complete-subtitle">
        {{ assignedCount }} patterns categorized into budget buckets.
      </div>
      <div class="summary-chips q-mt-md">
        <q-chip color="teal" text-color="white" icon="home">
          {{ bucketSummary.needs }} Needs
        </q-chip>
        <q-chip color="amber" text-color="white" icon="favorite">
          {{ bucketSummary.wants }} Wants
        </q-chip>
        <q-chip color="blue" text-color="white" icon="savings">
          {{ bucketSummary.savings }} Savings
        </q-chip>
        <q-chip v-if="bucketSummary.business > 0" color="purple" text-color="white" icon="work">
          {{ bucketSummary.business }} Business
        </q-chip>
        <q-chip v-if="bucketSummary.transfer > 0" color="grey-7" text-color="white" icon="swap_horiz">
          {{ bucketSummary.transfer }} Transfers
        </q-chip>
        <q-chip v-if="bucketSummary.tax_paid > 0" color="red-8" text-color="white" icon="account_balance">
          {{ bucketSummary.tax_paid }} Tax Paid
        </q-chip>
      </div>
    </div>

    <!-- Pattern Card -->
    <div v-else class="pattern-container">
      <!-- Progress Header -->
      <div class="progress-header">
        <div class="progress-text">
          Pattern {{ currentIndex + 1 }} of {{ totalPatterns }}
        </div>
        <q-linear-progress
          :value="progress"
          color="teal"
          track-color="teal-2"
          size="6px"
          rounded
          class="q-mt-sm"
        />
      </div>

      <!-- Pattern Card -->
      <q-card class="pattern-card" flat bordered>
        <!-- Pattern Badge -->
        <div class="pattern-badge">
          <q-icon name="store" size="16px" />
          <span>{{ currentPattern.pattern }}</span>
        </div>

        <!-- Amount Display -->
        <div class="amount-display">
          <div class="amount-label">December Total</div>
          <div class="amount-value" :class="{ 'text-positive': currentPattern.totalAmount > 0 }">
            {{ configStore.currencySymbol }} {{ formatAmount(currentPattern.totalAmount) }}
          </div>
          <div class="amount-count">
            {{ currentPattern.count }} transaction{{ currentPattern.count > 1 ? 's' : '' }}
          </div>
        </div>

        <!-- Sample Description -->
        <div class="sample-section">
          <div class="sample-label">Example from statement:</div>
          <div class="sample-text">{{ currentPattern.sampleDescription }}</div>
        </div>
      </q-card>

      <!-- Question -->
      <div class="question-section">
        <div class="question-text">What kind of expense is this?</div>
        <div class="question-hint">Assign to your 50/30/20 budget bucket</div>
      </div>

      <!-- Bucket Buttons -->
      <div class="bucket-buttons">
        <!-- Business (Pending Accountant Review) -->
        <q-btn
          class="bucket-btn business-btn"
          unelevated
          @click="assignBucket('business')"
        >
          <div class="bucket-btn-content">
            <q-icon name="work" size="28px" />
            <span class="bucket-label">Business Expense</span>
            <span class="bucket-hint">Pending Accountant - Tax stays at 39.1%</span>
          </div>
        </q-btn>

        <q-btn
          class="bucket-btn needs-btn"
          unelevated
          @click="assignBucket('needs')"
        >
          <div class="bucket-btn-content">
            <q-icon name="home" size="28px" />
            <span class="bucket-label">Essential Need</span>
            <span class="bucket-hint">50% - Rent, food, utilities</span>
          </div>
        </q-btn>

        <q-btn
          class="bucket-btn wants-btn"
          unelevated
          @click="assignBucket('wants')"
        >
          <div class="bucket-btn-content">
            <q-icon name="favorite" size="28px" />
            <span class="bucket-label">Lifestyle Want</span>
            <span class="bucket-hint">30% - Dining, entertainment</span>
          </div>
        </q-btn>

        <q-btn
          class="bucket-btn savings-btn"
          unelevated
          @click="assignBucket('savings')"
        >
          <div class="bucket-btn-content">
            <q-icon name="savings" size="28px" />
            <span class="bucket-label">Savings / Debt</span>
            <span class="bucket-hint">20% - Future you, investments</span>
          </div>
        </q-btn>

        <!-- Transfer (Neutral - Exclude from expenses & tax) -->
        <q-btn
          class="bucket-btn transfer-btn"
          unelevated
          @click="assignBucket('transfer')"
        >
          <div class="bucket-btn-content">
            <q-icon name="swap_horiz" size="28px" />
            <span class="bucket-label">Transfer / Neutral</span>
            <span class="bucket-hint">Excluded from expenses & taxable income</span>
          </div>
        </q-btn>

        <!-- Tax Paid (SARS) - Statutory debt settlement -->
        <q-btn
          class="bucket-btn tax-paid-btn"
          unelevated
          @click="assignBucket('tax_paid')"
        >
          <div class="bucket-btn-content">
            <q-icon name="account_balance" size="28px" />
            <span class="bucket-label">Tax Paid (SARS)</span>
            <span class="bucket-hint">Statutory debt - reduces Remaining Tax Due</span>
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
          @click="skipPattern"
        />
      </div>

      <!-- Manual Override - Review Previous Assignments -->
      <div v-if="assignments.length > 0" class="override-section">
        <q-expansion-item
          icon="edit"
          label="Review Previous Assignments"
          caption="Change category for any pattern"
          class="override-expansion"
        >
          <div class="override-list">
            <div
              v-for="(assignment, idx) in assignments"
              :key="assignment.pattern"
              class="override-item"
            >
              <div class="override-pattern">{{ assignment.pattern }}</div>
              <q-select
                v-model="assignments[idx].bucket"
                :options="bucketOptions"
                emit-value
                map-options
                dense
                outlined
                class="override-select"
                @update:model-value="onOverrideChange(idx)"
              />
            </div>
          </div>
        </q-expansion-item>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config.store';
import type { Transaction, BudgetBucket } from '@/types';

export interface MerchantPattern {
  pattern: string;
  transactions: Transaction[];
  totalAmount: number;
  count: number;
  sampleDescription: string;
  assignedBucket?: BudgetBucket;
}

export interface PatternAssignment {
  pattern: string;
  bucket: BudgetBucket;
  transactionIds: string[];
}

const props = defineProps<{
  transactions: Transaction[];
}>();

const emit = defineEmits<{
  (e: 'complete', assignments: PatternAssignment[]): void;
  (e: 'progress', current: number, total: number): void;
}>();

const configStore = useConfigStore();

// State
const isProcessing = ref(true);
const patterns = ref<MerchantPattern[]>([]);
const currentIndex = ref(0);
const assignments = ref<PatternAssignment[]>([]);
const skippedPatterns = ref<Set<string>>(new Set());

// Bucket options for dropdown
const bucketOptions = [
  { value: 'needs', label: 'Essential Need (50%)' },
  { value: 'wants', label: 'Lifestyle Want (30%)' },
  { value: 'savings', label: 'Savings / Debt (20%)' },
  { value: 'business', label: 'Business Expense (Pending Accountant)' },
  { value: 'transfer', label: 'Transfer / Neutral (Excluded)' },
  { value: 'tax_paid', label: 'Tax Paid (SARS)' },
];

// Handle manual override
function onOverrideChange(idx: number): void {
  // The v-model already updates assignments[idx].bucket
  // Emit progress to update parent if needed
  emit('progress', currentIndex.value, totalPatterns.value);
}

// Computed
const totalPatterns = computed(() => patterns.value.length);

const currentPattern = computed(() => {
  if (currentIndex.value >= patterns.value.length) return null;
  return patterns.value[currentIndex.value];
});

const progress = computed(() => {
  if (totalPatterns.value === 0) return 0;
  return currentIndex.value / totalPatterns.value;
});

const assignedCount = computed(() => assignments.value.length);

const bucketSummary = computed(() => {
  const summary = { needs: 0, wants: 0, savings: 0, business: 0, transfer: 0, tax_paid: 0 };
  for (const assignment of assignments.value) {
    if (assignment.bucket in summary) {
      summary[assignment.bucket as keyof typeof summary]++;
    }
  }
  return summary;
});

// Group transactions by merchant pattern
function groupByMerchantPattern(transactions: Transaction[]): MerchantPattern[] {
  const patternMap = new Map<string, Transaction[]>();

  for (const tx of transactions) {
    // Extract merchant pattern from description
    const pattern = extractMerchantPattern(tx.description);

    if (!patternMap.has(pattern)) {
      patternMap.set(pattern, []);
    }
    patternMap.get(pattern)!.push(tx);
  }

  // Convert to array and sort by total amount (biggest first)
  const result: MerchantPattern[] = [];

  for (const [pattern, txs] of patternMap) {
    const totalAmount = txs.reduce((sum, tx) => sum + tx.amount, 0);

    result.push({
      pattern,
      transactions: txs,
      totalAmount,
      count: txs.length,
      sampleDescription: txs[0]?.description || pattern,
    });
  }

  // Sort by absolute amount (largest expenses first)
  return result.sort((a, b) => Math.abs(b.totalAmount) - Math.abs(a.totalAmount));
}

// Extract a normalized merchant pattern from transaction description
function extractMerchantPattern(description: string): string {
  if (!description) return 'Unknown';

  // Common SA bank description patterns
  const patterns = [
    // Card transactions: "CARD PURCHASE - MERCHANT NAME"
    /CARD\s+PURCHASE\s*[-:]\s*(.+?)(?:\s+\d|$)/i,
    // Debit orders: "DEBIT ORDER - COMPANY"
    /DEBIT\s+ORDER\s*[-:]\s*(.+?)(?:\s+\d|$)/i,
    // EFT: "EFT - RECIPIENT"
    /EFT\s*[-:]\s*(.+?)(?:\s+\d|$)/i,
    // POS: "POS PURCHASE - MERCHANT"
    /POS\s+(?:PURCHASE\s*)?[-:]\s*(.+?)(?:\s+\d|$)/i,
    // General pattern - first significant words
    /^([A-Z][A-Z0-9\s]{2,30}?)(?:\s+\d|\s+[A-Z]{2}\d|$)/i,
  ];

  for (const regex of patterns) {
    const match = description.match(regex);
    if (match && match[1]) {
      return match[1].trim().toUpperCase().substring(0, 30);
    }
  }

  // Fallback: First 25 chars, uppercase
  return description.substring(0, 25).trim().toUpperCase();
}

function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function assignBucket(bucket: BudgetBucket): void {
  if (!currentPattern.value) return;

  // Record the assignment
  assignments.value.push({
    pattern: currentPattern.value.pattern,
    bucket,
    transactionIds: currentPattern.value.transactions
      .map(tx => tx.id)
      .filter((id): id is string => !!id),
  });

  // Move to next pattern
  moveToNext();
}

function skipPattern(): void {
  if (!currentPattern.value) return;

  skippedPatterns.value.add(currentPattern.value.pattern);
  moveToNext();
}

function moveToNext(): void {
  currentIndex.value++;
  emit('progress', currentIndex.value, totalPatterns.value);

  // Check if we're done
  if (currentIndex.value >= patterns.value.length) {
    emit('complete', assignments.value);
  }
}

// Process transactions when they change
watch(() => props.transactions, (txs) => {
  if (txs && txs.length > 0) {
    processTransactions(txs);
  }
}, { immediate: true });

function processTransactions(txs: Transaction[]): void {
  isProcessing.value = true;

  // Small delay for UX feedback
  setTimeout(() => {
    // Only process expenses (negative amounts) for bucket assignment
    const expenses = txs.filter(tx => tx.amount < 0);
    patterns.value = groupByMerchantPattern(expenses);
    currentIndex.value = 0;
    assignments.value = [];
    skippedPatterns.value.clear();
    isProcessing.value = false;

    emit('progress', 0, patterns.value.length);
  }, 500);
}

onMounted(() => {
  if (props.transactions && props.transactions.length > 0) {
    processTransactions(props.transactions);
  }
});
</script>

<style scoped>
.wizard-interrogator {
  padding: 16px;
}

.loading-container,
.complete-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  text-align: center;
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

.complete-title {
  font-size: 24px;
  font-weight: 700;
  color: #004D40;
}

.complete-subtitle {
  font-size: 14px;
  color: #78909C;
  margin-top: 8px;
}

.summary-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
}

.pattern-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.progress-header {
  margin-bottom: 8px;
}

.progress-text {
  font-size: 13px;
  color: #78909C;
  text-align: center;
}

.pattern-card {
  border-radius: 16px;
  border-color: #B2DFDB;
  padding: 20px;
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
  font-size: 32px;
  font-weight: 700;
  color: #C62828;
  line-height: 1.2;
}

.amount-value.text-positive {
  color: #2E7D32;
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

.question-section {
  text-align: center;
  padding: 8px 0;
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
  min-height: 72px;
  border-radius: 12px;
  text-transform: none;
  padding: 12px 16px;
}

.bucket-btn-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  width: 100%;
}

.bucket-label {
  font-size: 15px;
  font-weight: 600;
}

.bucket-hint {
  font-size: 11px;
  opacity: 0.85;
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

.business-btn {
  background: linear-gradient(135deg, #6A1B9A 0%, #9C27B0 100%) !important;
  color: white !important;
}

.transfer-btn {
  background: linear-gradient(135deg, #455A64 0%, #78909C 100%) !important;
  color: white !important;
}

.tax-paid-btn {
  background: linear-gradient(135deg, #B71C1C 0%, #E53935 100%) !important;
  color: white !important;
}

.skip-section {
  text-align: center;
  padding: 8px 0;
}

.override-section {
  margin-top: 16px;
  border-top: 1px solid #E0E0E0;
  padding-top: 8px;
}

.override-expansion {
  background: #FAFAFA;
  border-radius: 8px;
}

.override-list {
  padding: 8px 16px;
}

.override-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid #EEEEEE;
}

.override-item:last-child {
  border-bottom: none;
}

.override-pattern {
  font-size: 13px;
  font-weight: 500;
  color: #37474F;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.override-select {
  min-width: 180px;
  max-width: 200px;
}
</style>
