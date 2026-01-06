<template>
  <div v-if="isActive" class="survival-mode-container q-mb-md">
    <!-- Main Banner -->
    <q-banner class="survival-banner bg-red-10 text-white" rounded>
      <template v-slot:avatar>
        <q-icon name="warning" size="md" />
      </template>

      <div class="row items-center justify-between full-width">
        <div>
          <div class="text-weight-bold text-h6">SURVIVAL MODE</div>
          <div class="text-caption">{{ daysRemaining }} days until {{ targetDateFormatted }}</div>
        </div>
        <q-btn flat dense icon="close" @click="dismiss" />
      </div>
    </q-banner>

    <!-- Stats Cards -->
    <div class="row q-col-gutter-sm q-mt-sm">
      <div class="col-4">
        <q-card class="bg-red-1 stat-card">
          <q-card-section class="q-pa-sm text-center">
            <div class="text-caption text-red-9">BALANCE</div>
            <div class="text-h6 text-red-10">R {{ formatAmount(balance) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-4">
        <q-card class="bg-orange-1 stat-card">
          <q-card-section class="q-pa-sm text-center">
            <div class="text-caption text-orange-9">DAILY LIMIT</div>
            <div class="text-h6 text-orange-10">R {{ formatAmount(dailyBudget) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-4">
        <q-card class="bg-deep-orange-1 stat-card">
          <q-card-section class="q-pa-sm text-center">
            <div class="text-caption text-deep-orange-9">FAILURE IF</div>
            <div class="text-h6 text-deep-orange-10">> R {{ formatAmount(failureThreshold) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Today's Status -->
    <q-card class="q-mt-sm" :class="todayStatusClass">
      <q-card-section class="q-pa-sm">
        <div class="row items-center justify-between">
          <div>
            <div class="text-caption">Today's Spending</div>
            <div class="text-subtitle1 text-weight-medium">
              R {{ formatAmount(todaySpent) }} / R {{ formatAmount(dailyBudget) }}
            </div>
          </div>
          <q-circular-progress
            :value="todayProgress"
            size="50px"
            :thickness="0.2"
            :color="todayProgressColor"
            track-color="grey-3"
          >
            <span class="text-caption">{{ Math.round(todayProgress) }}%</span>
          </q-circular-progress>
        </div>
        <q-linear-progress
          :value="todayProgress / 100"
          :color="todayProgressColor"
          class="q-mt-sm"
          rounded
        />
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { format, differenceInDays, parseISO, isToday } from 'date-fns';
import { useTransactionsStore } from '@/stores/transactions.store';

// Survival Mode Configuration
// These values come from the "Brass Knuckles" reality check
const SURVIVAL_CONFIG = {
  balance: 1045.82,           // Actual spendable balance
  targetDate: '2026-01-26',   // Expected invoice date
  dailyBudget: 49.80,         // Max daily spend
  failureThreshold: 50.00,    // Any transaction > this = SYSTEM FAILURE
  startDate: '2026-01-05',    // When survival mode started
};

const transactionsStore = useTransactionsStore();

const isDismissed = ref(false);

// Check if survival mode should be active
const isActive = computed(() => {
  if (isDismissed.value) return false;

  const today = new Date();
  const target = parseISO(SURVIVAL_CONFIG.targetDate);
  const start = parseISO(SURVIVAL_CONFIG.startDate);

  // Active if we're between start and target date
  return today >= start && today <= target;
});

const balance = computed(() => SURVIVAL_CONFIG.balance);
const dailyBudget = computed(() => SURVIVAL_CONFIG.dailyBudget);
const failureThreshold = computed(() => SURVIVAL_CONFIG.failureThreshold);

const daysRemaining = computed(() => {
  const today = new Date();
  const target = parseISO(SURVIVAL_CONFIG.targetDate);
  return Math.max(0, differenceInDays(target, today));
});

const targetDateFormatted = computed(() => {
  return format(parseISO(SURVIVAL_CONFIG.targetDate), 'MMM d, yyyy');
});

// Calculate today's spending
const todaySpent = computed(() => {
  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const todayTransactions = transactionsStore.transactions.filter(tx => {
    const txDate = tx.transaction_date.split('T')[0];
    return txDate === todayStr && Number(tx.amount) < 0 && !tx.is_transfer;
  });

  return todayTransactions.reduce((sum, tx) => sum + Math.abs(Number(tx.amount)), 0);
});

const todayProgress = computed(() => {
  if (dailyBudget.value === 0) return 0;
  return Math.min(100, (todaySpent.value / dailyBudget.value) * 100);
});

const todayProgressColor = computed(() => {
  if (todayProgress.value >= 100) return 'red';
  if (todayProgress.value >= 80) return 'orange';
  if (todayProgress.value >= 50) return 'amber';
  return 'teal';
});

const todayStatusClass = computed(() => {
  if (todayProgress.value >= 100) return 'bg-red-1';
  if (todayProgress.value >= 80) return 'bg-orange-1';
  return 'bg-grey-1';
});

function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function dismiss() {
  isDismissed.value = true;
  // Store in localStorage so it stays dismissed for the session
  localStorage.setItem('survival_mode_dismissed', 'true');
}

onMounted(() => {
  // Check if was dismissed this session
  const dismissed = localStorage.getItem('survival_mode_dismissed');
  if (dismissed === 'true') {
    isDismissed.value = true;
  }
});
</script>

<style scoped>
.survival-mode-container {
  animation: pulse-border 2s ease-in-out infinite;
}

.survival-banner {
  border: 2px solid rgba(244, 67, 54, 0.5);
}

.stat-card {
  border-radius: 8px;
}

@keyframes pulse-border {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.9;
  }
}
</style>
