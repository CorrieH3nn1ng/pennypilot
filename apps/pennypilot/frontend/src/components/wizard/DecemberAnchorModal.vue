<template>
  <q-dialog
    v-model="showDialog"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="anchor-modal">
      <!-- Header -->
      <q-card-section class="header-section">
        <div class="header-content">
          <q-btn flat round icon="close" color="grey-7" @click="close" />
          <div class="header-title">
            <q-icon name="anchor" color="teal" size="24px" />
            <span>Your December Anchor</span>
          </div>
          <div style="width: 40px"></div>
        </div>
      </q-card-section>

      <q-scroll-area class="modal-scroll">
        <div class="modal-content">
          <!-- Anchor Icon -->
          <div class="anchor-icon-container">
            <q-icon name="anchor" color="teal" size="64px" />
          </div>

          <!-- Opening Balance Display -->
          <div class="balance-display">
            <div class="balance-label">December 1st Opening Balance</div>
            <div class="balance-value">
              {{ configStore.currencySymbol }} {{ formatAmount(anchorResult.dec1OpeningBalance) }}
            </div>
          </div>

          <!-- Calculation Breakdown -->
          <q-card flat bordered class="calculation-card">
            <q-card-section>
              <div class="calculation-title">
                <q-icon name="calculate" color="grey-7" size="18px" />
                How Penny calculated this:
              </div>

              <div class="calculation-rows">
                <div class="calc-row">
                  <span class="calc-label">Dec 30th balance you entered:</span>
                  <span class="calc-value">{{ configStore.currencySymbol }} {{ formatAmount(anchorResult.dec30Balance) }}</span>
                </div>
                <div class="calc-row subtract">
                  <span class="calc-label">December deposits (inflows):</span>
                  <span class="calc-value">- {{ configStore.currencySymbol }} {{ formatAmount(anchorResult.totalInflows) }}</span>
                </div>
                <div class="calc-row add">
                  <span class="calc-label">December spending (outflows):</span>
                  <span class="calc-value">+ {{ configStore.currencySymbol }} {{ formatAmount(anchorResult.totalOutflows) }}</span>
                </div>
                <q-separator class="q-my-sm" />
                <div class="calc-row result">
                  <span class="calc-label">Opening Balance:</span>
                  <span class="calc-value">{{ configStore.currencySymbol }} {{ formatAmount(anchorResult.dec1OpeningBalance) }}</span>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Transaction Summary -->
          <div class="summary-section">
            <q-icon name="receipt_long" color="teal" size="20px" />
            <span>Based on {{ anchorResult.transactionCount }} December {{ anchorResult.anchorYear }} transactions</span>
          </div>

          <!-- Success Message -->
          <div class="success-message">
            <q-icon name="check_circle" color="positive" size="24px" />
            <div>
              <div class="success-title">Your 50/30/20 budget now balances to zero</div>
              <div class="success-subtitle">Starting from December 1st, {{ anchorResult.anchorYear }}</div>
            </div>
          </div>

          <!-- Manual Adjustment Option -->
          <q-expansion-item
            v-model="showManualInput"
            icon="edit"
            label="Adjust manually"
            header-class="text-grey-7"
            class="manual-section"
          >
            <q-card flat>
              <q-card-section>
                <div class="text-caption text-grey-7 q-mb-sm">
                  If you know your exact Dec 1st balance, enter it here:
                </div>
                <q-input
                  v-model.number="manualBalance"
                  type="number"
                  outlined
                  dense
                  :prefix="configStore.currencySymbol"
                  placeholder="Enter opening balance"
                  :input-style="{ textAlign: 'right' }"
                />
                <q-btn
                  v-if="manualBalance && manualBalance !== anchorResult.dec1OpeningBalance"
                  color="grey-7"
                  label="Use this balance"
                  class="q-mt-md full-width"
                  unelevated
                  @click="useManualBalance"
                />
              </q-card-section>
            </q-card>
          </q-expansion-item>
        </div>
      </q-scroll-area>

      <!-- Bottom Action -->
      <q-card-section class="action-section">
        <q-btn
          color="teal"
          label="Lock In December Anchor"
          icon="lock"
          size="lg"
          unelevated
          class="full-width lock-btn"
          @click="lockAnchor"
        />
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useConfigStore } from '@/stores/config.store';
import type { DecemberAnchorResult } from '@/services/balancing/BalancingEngine';

const props = defineProps<{
  modelValue: boolean;
  anchorResult: DecemberAnchorResult;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'confirm', openingBalance: number, year: number): void;
  (e: 'cancel'): void;
}>();

const configStore = useConfigStore();

const showDialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const showManualInput = ref(false);
const manualBalance = ref<number | null>(null);

function formatAmount(amount: number): string {
  return Math.abs(amount).toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function useManualBalance(): void {
  if (manualBalance.value !== null) {
    emit('confirm', manualBalance.value, props.anchorResult.anchorYear);
    showDialog.value = false;
  }
}

function lockAnchor(): void {
  emit('confirm', props.anchorResult.dec1OpeningBalance, props.anchorResult.anchorYear);
  showDialog.value = false;
}

function close(): void {
  emit('cancel');
  showDialog.value = false;
}
</script>

<style scoped>
.anchor-modal {
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #E0F2F1 0%, #FFFFFF 20%);
}

.header-section {
  padding: 8px 8px 0;
  flex-shrink: 0;
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

.modal-scroll {
  flex: 1;
  height: 0;
}

.modal-content {
  padding: 24px 16px;
}

.anchor-icon-container {
  width: 100px;
  height: 100px;
  background: rgba(0, 77, 64, 0.1);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}

.balance-display {
  text-align: center;
  margin-bottom: 24px;
}

.balance-label {
  font-size: 14px;
  color: #78909C;
  margin-bottom: 8px;
}

.balance-value {
  font-size: 36px;
  font-weight: 700;
  color: #004D40;
}

.calculation-card {
  border-radius: 12px;
  border-color: #B2DFDB;
  margin-bottom: 20px;
}

.calculation-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #546E7A;
  margin-bottom: 16px;
}

.calculation-rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.calc-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.calc-label {
  color: #546E7A;
}

.calc-value {
  font-weight: 500;
  color: #37474F;
}

.calc-row.subtract .calc-value {
  color: #2E7D32;
}

.calc-row.add .calc-value {
  color: #C62828;
}

.calc-row.result {
  font-weight: 600;
}

.calc-row.result .calc-label,
.calc-row.result .calc-value {
  color: #004D40;
}

.summary-section {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 13px;
  color: #78909C;
  margin-bottom: 20px;
}

.success-message {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: #E8F5E9;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 20px;
}

.success-title {
  font-size: 15px;
  font-weight: 600;
  color: #2E7D32;
}

.success-subtitle {
  font-size: 13px;
  color: #4CAF50;
  margin-top: 2px;
}

.manual-section {
  border-radius: 8px;
  background: #FAFAFA;
}

.action-section {
  padding: 16px;
  background: white;
  border-top: 1px solid #E0E0E0;
  flex-shrink: 0;
}

.lock-btn {
  border-radius: 12px;
  min-height: 52px;
  font-size: 16px;
  font-weight: 600;
}
</style>
