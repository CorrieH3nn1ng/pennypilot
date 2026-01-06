<template>
  <q-dialog
    v-model="isOpen"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="balance-confirmation-card">
      <q-card-section class="header-section">
        <div class="header-icon">
          <q-icon name="account_balance" size="48px" color="teal-8" />
        </div>
        <div class="text-h5 text-center q-mt-md">Opening Balance Detected</div>
        <div class="text-caption text-grey-6 text-center q-mt-xs">
          Penny calculated your starting point
        </div>
      </q-card-section>

      <q-card-section class="content-section">
        <!-- Derived Balance Display -->
        <div class="balance-display">
          <div class="text-caption text-grey-6">Calculated Opening Balance</div>
          <div class="derived-amount">
            {{ configStore.currencySymbol }} {{ formatAmount(derivedBalance) }}
          </div>
        </div>

        <!-- Explanation -->
        <q-card flat bordered class="explanation-card q-mt-lg">
          <q-card-section>
            <div class="text-subtitle2 text-grey-8 q-mb-sm">
              <q-icon name="lightbulb" color="amber-8" class="q-mr-xs" />
              How we calculated this
            </div>
            <div class="calculation-breakdown">
              <div class="calc-row">
                <span>Your current balance</span>
                <span class="text-weight-medium">
                  {{ configStore.currencySymbol }} {{ formatAmount(currentBalance) }}
                </span>
              </div>
              <div class="calc-row text-positive">
                <span>Money received ({{ transactionCount }} transactions)</span>
                <span>- {{ configStore.currencySymbol }} {{ formatAmount(totalInflows) }}</span>
              </div>
              <div class="calc-row text-negative">
                <span>Money spent</span>
                <span>+ {{ configStore.currencySymbol }} {{ formatAmount(totalOutflows) }}</span>
              </div>
              <q-separator class="q-my-sm" />
              <div class="calc-row result-row">
                <span class="text-weight-bold">Opening Balance</span>
                <span class="text-weight-bold text-teal-8">
                  {{ configStore.currencySymbol }} {{ formatAmount(derivedBalance) }}
                </span>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- Date Range Info -->
        <div class="date-info q-mt-md text-center">
          <q-icon name="date_range" size="16px" color="grey-6" />
          <span class="text-caption text-grey-6 q-ml-xs">
            Based on transactions from {{ formatDateRange() }}
          </span>
        </div>

        <!-- Free User Notice -->
        <q-banner v-if="isFreeUser" class="q-mt-lg bg-amber-1" rounded>
          <template v-slot:avatar>
            <q-icon name="info" color="amber-8" />
          </template>
          <div class="text-body2">
            <strong>Free Account:</strong> Your budget starts from today.
            Upgrade to go back in time and reconcile older transactions.
          </div>
        </q-banner>

        <!-- Discrepancy Warning -->
        <q-banner
          v-if="hasUserProvidedBalance && discrepancy > 0"
          class="q-mt-lg bg-orange-1"
          rounded
        >
          <template v-slot:avatar>
            <q-icon name="warning" color="orange-8" />
          </template>
          <div class="text-body2">
            <strong>Note:</strong> This differs from your entered balance by
            {{ configStore.currencySymbol }} {{ formatAmount(discrepancy) }}.
            This could be due to unrecorded transactions or bank fees.
          </div>
        </q-banner>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div class="confirmation-text text-center text-body2 text-grey-7 q-mb-md">
          To make your 50/30/20 budget balance to zero, we've calculated an opening balance of
          <strong class="text-teal-8">
            {{ configStore.currencySymbol }} {{ formatAmount(derivedBalance) }}
          </strong>.
          Is this correct?
        </div>
      </q-card-section>

      <q-card-actions class="action-section" align="center">
        <div class="action-buttons">
          <q-btn
            flat
            color="grey-7"
            label="Enter Manually"
            class="action-btn"
            @click="onManualEntry"
          />
          <q-btn
            unelevated
            color="teal-8"
            label="Yes, Use This"
            class="action-btn primary"
            @click="onConfirm"
          />
        </div>
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useConfigStore } from '@/stores/config.store';

const props = defineProps<{
  modelValue: boolean;
  derivedBalance: number;
  currentBalance: number;
  totalInflows: number;
  totalOutflows: number;
  transactionCount: number;
  dateRange: {
    earliest: string | null;
    latest: string | null;
  };
  userProvidedBalance?: number | null;
  isFreeUser?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'confirm', balance: number): void;
  (e: 'manual-entry'): void;
}>();

const configStore = useConfigStore();

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const hasUserProvidedBalance = computed(() => {
  return props.userProvidedBalance !== null && props.userProvidedBalance !== undefined;
});

const discrepancy = computed(() => {
  if (!hasUserProvidedBalance.value) return 0;
  return Math.abs(props.derivedBalance - (props.userProvidedBalance ?? 0));
});

function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateRange(): string {
  if (!props.dateRange.earliest || !props.dateRange.latest) {
    return 'this month';
  }

  const earliest = new Date(props.dateRange.earliest);
  const latest = new Date(props.dateRange.latest);

  const formatDate = (d: Date) => d.toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'short',
  });

  if (props.dateRange.earliest === props.dateRange.latest) {
    return formatDate(earliest);
  }

  return `${formatDate(earliest)} to ${formatDate(latest)}`;
}

function onConfirm() {
  emit('confirm', props.derivedBalance);
  isOpen.value = false;
}

function onManualEntry() {
  emit('manual-entry');
  isOpen.value = false;
}
</script>

<style scoped>
.balance-confirmation-card {
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #E0F2F1 0%, #FFFFFF 15%);
}

.header-section {
  padding-top: 48px;
  text-align: center;
}

.header-icon {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 80px;
  height: 80px;
  margin: 0 auto;
  background: white;
  border-radius: 50%;
  box-shadow: 0 4px 12px rgba(0, 77, 64, 0.15);
}

.content-section {
  flex: 1;
  padding: 16px 24px;
}

.balance-display {
  text-align: center;
  padding: 24px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.derived-amount {
  font-size: 36px;
  font-weight: 700;
  color: #004D40;
  letter-spacing: -1px;
}

.explanation-card {
  border-radius: 12px;
  background: #FAFAFA;
}

.calculation-breakdown {
  font-size: 14px;
}

.calc-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
}

.result-row {
  padding-top: 8px;
  font-size: 15px;
}

.date-info {
  display: flex;
  align-items: center;
  justify-content: center;
}

.confirmation-text {
  max-width: 320px;
  margin: 0 auto;
}

.action-section {
  padding: 16px 24px 32px;
}

.action-buttons {
  display: flex;
  gap: 12px;
  width: 100%;
  max-width: 360px;
  margin: 0 auto;
}

.action-btn {
  flex: 1;
  min-height: 48px;
  border-radius: 12px;
  font-weight: 600;
}

.action-btn.primary {
  box-shadow: 0 4px 12px rgba(0, 77, 64, 0.25);
}

/* Warning/Info banners */
.bg-amber-1 {
  background: #FFF8E1 !important;
}

.bg-orange-1 {
  background: #FFF3E0 !important;
}
</style>
