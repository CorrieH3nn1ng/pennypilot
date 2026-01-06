<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Settings</div>

    <q-list>
      <q-item-label header>Account</q-item-label>
      <q-item>
        <q-item-section>
          <q-item-label>Profile</q-item-label>
          <q-item-label caption>Manage your account details</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="chevron_right" />
        </q-item-section>
      </q-item>

      <!-- Income Type Selector -->
      <q-item>
        <q-item-section>
          <q-item-label>Income Type</q-item-label>
          <q-item-label caption>How do you earn your income?</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-select
            v-model="selectedIncomeType"
            :options="incomeTypeOptions"
            dense
            outlined
            emit-value
            map-options
            style="min-width: 150px"
            @update:model-value="handleIncomeTypeChange"
          />
        </q-item-section>
      </q-item>

      <!-- Net Monthly Income (Salaried Only) -->
      <q-item v-if="selectedIncomeType === 'salaried'">
        <q-item-section>
          <q-item-label>Net Monthly Income</q-item-label>
          <q-item-label caption>Your take-home pay after tax</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-input
            v-model.number="netMonthlyIncome"
            type="number"
            dense
            outlined
            :prefix="configStore.currencySymbol"
            style="width: 140px"
            @blur="handleNetIncomeChange"
          />
        </q-item-section>
      </q-item>

      <!-- Admin-only Subscription Selector -->
      <q-item v-if="userStore.isAdmin">
        <q-item-section>
          <q-item-label>Subscription Tier</q-item-label>
          <q-item-label caption>Admin: Select your subscription level</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-select
            v-model="selectedTier"
            :options="tierOptions"
            dense
            outlined
            emit-value
            map-options
            style="min-width: 120px"
            @update:model-value="handleTierChange"
          />
        </q-item-section>
      </q-item>

      <q-separator />

      <q-item-label header>Data</q-item-label>
      <q-item clickable @click="exportData">
        <q-item-section>
          <q-item-label>Export Data</q-item-label>
          <q-item-label caption>Download all your transactions</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="download" />
        </q-item-section>
      </q-item>

      <q-item clickable @click="startFresh" :disable="isReloading">
        <q-item-section>
          <q-item-label class="text-primary">Start Fresh</q-item-label>
          <q-item-label caption>Clear local data and reload everything from server</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-spinner v-if="isReloading" color="primary" size="24px" />
          <q-icon v-else name="refresh" color="primary" />
        </q-item-section>
      </q-item>

      <q-item clickable @click="clearLocalData">
        <q-item-section>
          <q-item-label class="text-negative">Clear Local Data</q-item-label>
          <q-item-label caption>Remove offline cached data only</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="delete" color="negative" />
        </q-item-section>
      </q-item>

      <q-separator />

      <!-- Historical Data Section -->
      <q-item-label header>Historical Data</q-item-label>

      <!-- December Anchor Status -->
      <q-item>
        <q-item-section avatar>
          <q-icon name="anchor" :color="accountStore.hasDecemberAnchor ? 'teal' : 'grey-5'" />
        </q-item-section>
        <q-item-section>
          <q-item-label>December Anchor</q-item-label>
          <q-item-label caption v-if="accountStore.hasDecemberAnchor">
            Set for December {{ accountStore.anchorYear }} -
            {{ configStore.currencySymbol }} {{ formatAmount(accountStore.anchorOpeningBalance) }}
          </q-item-label>
          <q-item-label caption v-else class="text-grey-6">
            Not set - Complete the onboarding wizard to anchor your budget
          </q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-badge v-if="accountStore.hasDecemberAnchor" color="positive" label="Active" />
          <q-badge v-else color="grey-5" label="Not Set" />
        </q-item-section>
      </q-item>

      <!-- Historical Walk-Back Toggle -->
      <q-item :disable="!accountStore.hasDecemberAnchor || !isPremium">
        <q-item-section avatar>
          <q-icon name="history" :color="accountStore.canWalkBack ? 'teal' : 'grey-5'" />
        </q-item-section>
        <q-item-section>
          <q-item-label>Historical Walk-Back</q-item-label>
          <q-item-label caption v-if="!accountStore.hasDecemberAnchor">
            Requires December anchor to be set first
          </q-item-label>
          <q-item-label caption v-else-if="!isPremium">
            Premium feature - Upgrade to unlock
          </q-item-label>
          <q-item-label caption v-else-if="accountStore.canWalkBack">
            Walk back from December to February {{ accountStore.anchorYear }}
          </q-item-label>
          <q-item-label caption v-else>
            Enable to reconcile older transactions back to February
          </q-item-label>
        </q-item-section>
        <q-item-section side>
          <div class="row items-center q-gutter-sm">
            <q-badge v-if="!isPremium" color="amber-8" text-color="white" label="Premium" />
            <q-toggle
              :model-value="accountStore.isHistoryUnlocked"
              :disable="!accountStore.hasDecemberAnchor || !isPremium"
              color="teal"
              @update:model-value="handleHistoryToggle"
            />
          </div>
        </q-item-section>
      </q-item>

      <!-- Minimum Date Info -->
      <q-item v-if="accountStore.hasDecemberAnchor">
        <q-item-section avatar>
          <q-icon name="info" color="grey-5" />
        </q-item-section>
        <q-item-section>
          <q-item-label caption>
            <span v-if="accountStore.canWalkBack">
              Transactions allowed from {{ formatDate(accountStore.minimumAllowedDate) }} onwards
            </span>
            <span v-else>
              Free users are limited to current month only
            </span>
          </q-item-label>
        </q-item-section>
      </q-item>

      <q-separator />

      <q-item-label header>About</q-item-label>
      <q-item>
        <q-item-section>
          <q-item-label>Version</q-item-label>
          <q-item-label caption>0.1.0 (MVP)</q-item-label>
        </q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useRouter } from 'vue-router';
import { localBaseService } from '@/services/storage/LocalBaseService';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import { useAccountStore } from '@/stores/account.store';
import { useUserStore } from '@/stores/user.store';
import { useConfigStore } from '@/stores/config.store';
import { getResetPreview, executeReset } from '@/services/api/reset.api';

const $q = useQuasar();
const router = useRouter();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();
const accountStore = useAccountStore();
const userStore = useUserStore();
const configStore = useConfigStore();

const isReloading = ref(false);
const resetPreview = ref<{ transactions: number; invoices: number } | null>(null);

// Computed helpers
const isPremium = computed(() => userStore.subscriptionTier === 'premium');

// Income type selector
const selectedIncomeType = ref<'self_employed' | 'salaried'>('self_employed');
const netMonthlyIncome = ref<number | null>(null);
const incomeTypeOptions = [
  { label: 'Self-Employed / Freelancer', value: 'self_employed' },
  { label: 'Salaried Employee', value: 'salaried' },
];

// Subscription tier selector (admin only)
const selectedTier = ref<'free' | 'premium'>('free');
const tierOptions = [
  { label: 'Free', value: 'free' },
  { label: 'Premium', value: 'premium' },
];

onMounted(() => {
  selectedTier.value = userStore.subscriptionTier;
  selectedIncomeType.value = userStore.incomeType;
  netMonthlyIncome.value = userStore.netMonthlyIncome;
});

async function handleTierChange(tier: 'free' | 'premium') {
  const success = await userStore.updateSubscription(tier);
  if (success) {
    $q.notify({
      type: 'positive',
      message: `Subscription changed to ${tier}`,
    });
  } else {
    $q.notify({
      type: 'negative',
      message: 'Failed to update subscription',
    });
    // Revert selection
    selectedTier.value = userStore.subscriptionTier;
  }
}

async function handleIncomeTypeChange(incomeType: 'self_employed' | 'salaried') {
  const success = await userStore.updateIncomeSettings({
    income_type: incomeType,
    net_monthly_income: incomeType === 'salaried' ? netMonthlyIncome.value : null,
  });

  if (success) {
    $q.notify({
      type: 'positive',
      message: incomeType === 'salaried'
        ? 'Switched to Salaried mode - invoicing features hidden'
        : 'Switched to Self-Employed mode - full features enabled',
    });
  } else {
    $q.notify({
      type: 'negative',
      message: 'Failed to update income settings',
    });
    // Revert selection
    selectedIncomeType.value = userStore.incomeType;
  }
}

async function handleNetIncomeChange() {
  if (selectedIncomeType.value !== 'salaried') return;

  const success = await userStore.updateIncomeSettings({
    income_type: selectedIncomeType.value,
    net_monthly_income: netMonthlyIncome.value,
  });

  if (success) {
    $q.notify({
      type: 'positive',
      message: 'Net monthly income updated',
    });
  } else {
    $q.notify({
      type: 'negative',
      message: 'Failed to update income',
    });
    // Revert
    netMonthlyIncome.value = userStore.netMonthlyIncome;
  }
}

function exportData() {
  $q.notify({
    type: 'info',
    message: 'Export feature coming soon',
  });
}

// Formatting helpers
function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-ZA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

// Historical walk-back toggle handler
function handleHistoryToggle(value: boolean): void {
  if (value) {
    const success = accountStore.unlockHistory();
    if (success) {
      $q.notify({
        type: 'positive',
        message: `Historical walk-back enabled. You can now import transactions from February ${accountStore.anchorYear}.`,
      });
    }
  } else {
    accountStore.lockHistory();
    $q.notify({
      type: 'info',
      message: 'Historical walk-back disabled. Limited to current month only.',
    });
  }
}

async function startFresh() {
  // First, get preview of what will be deleted
  let previewMessage = 'This will permanently delete ALL your data from both the server and this device.';

  try {
    const preview = await getResetPreview();
    const totalItems =
      preview.counts.transactions +
      preview.counts.invoices +
      preview.counts.clients;

    if (totalItems > 0) {
      previewMessage = `This will permanently delete:
- ${preview.counts.transactions} transactions
- ${preview.counts.invoices} invoices
- ${preview.counts.clients} clients
- ${preview.counts.income_sources} income sources
- ${preview.counts.budget_periods} budget periods

This action cannot be undone.`;
    }
  } catch {
    // Preview failed, use generic message
  }

  $q.dialog({
    title: 'Start Fresh',
    message: previewMessage,
    cancel: true,
    persistent: true,
    ok: {
      label: 'Delete Everything',
      color: 'negative',
    },
  }).onOk(async () => {
    isReloading.value = true;

    try {
      // Step 1: Execute atomic server wipe
      $q.notify({
        type: 'info',
        message: 'Deleting server data...',
        timeout: 2000,
      });

      const result = await executeReset();

      if (!result.success) {
        throw new Error('Server reset failed');
      }

      $q.notify({
        type: 'info',
        message: 'Server data deleted. Clearing local storage...',
        timeout: 2000,
      });

      // Step 2: Clear all local storage (only after server confirms)
      await localBaseService.clearAll();

      // Clear localStorage
      localStorage.clear();

      // Delete all IndexedDB databases
      try {
        const databases = await indexedDB.databases();
        for (const db of databases) {
          if (db.name) {
            indexedDB.deleteDatabase(db.name);
          }
        }
      } catch (e) {
        console.log('IndexedDB clear fallback:', e);
      }

      $q.notify({
        type: 'positive',
        message: 'All data deleted. Redirecting to setup wizard...',
        timeout: 2000,
      });

      // Step 3: Redirect to wizard
      setTimeout(() => {
        router.push('/wizard');
      }, 1500);
    } catch (error) {
      console.error('Start Fresh error:', error);
      $q.notify({
        type: 'negative',
        message: `Failed: ${error instanceof Error ? error.message : 'Unknown error'}`,
        timeout: 10000,
      });
      isReloading.value = false;
    }
  });
}

async function clearLocalData() {
  $q.dialog({
    title: 'Clear Local Data',
    message: 'This will remove ALL local data including transactions, categories, and rules. You may need to re-sync from server.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    // Clear all IndexedDB data
    await localBaseService.clearAll();

    // Also delete the entire IndexedDB database for a full reset
    try {
      const databases = await indexedDB.databases();
      for (const db of databases) {
        if (db.name) {
          indexedDB.deleteDatabase(db.name);
        }
      }
    } catch (e) {
      console.log('IndexedDB clear fallback:', e);
    }

    $q.notify({
      type: 'positive',
      message: 'All local data cleared',
    });
    window.location.reload();
  });
}
</script>
