import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { accountsApi, type Account, type AccountWithBalance } from '@/services/api/accounts.api';
import { localBaseService } from '@/services/storage/LocalBaseService';

export const useAccountStore = defineStore('account', () => {
  // State
  const account = ref<Account | null>(null);
  const transactionSum = ref<number>(0);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const openingBalance = computed(() => account.value?.opening_balance ?? 0);
  const currentBalance = computed(() => account.value?.current_balance ?? null);
  const calculatedBalance = computed(() => openingBalance.value + transactionSum.value);
  const hasSetBalance = computed(() => account.value?.balance_updated_at !== null);

  // Actions
  async function loadAccount(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const result = await accountsApi.getDefault();
      account.value = result.account;
      transactionSum.value = result.transaction_sum;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load account';

      // Fallback to local calculation
      const transactions = await localBaseService.getAllTransactions();
      transactionSum.value = transactions.reduce((sum, t) => sum + t.amount, 0);
    } finally {
      isLoading.value = false;
    }
  }

  async function setBalance(currentBankBalance: number): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      const result = await accountsApi.setBalance(currentBankBalance);
      account.value = result.account;
      transactionSum.value = result.transaction_sum;
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to set balance';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  // Calculate local balance (offline mode)
  async function calculateLocalBalance(): Promise<number> {
    const transactions = await localBaseService.getAllTransactions();
    transactionSum.value = transactions.reduce((sum, t) => sum + t.amount, 0);
    return openingBalance.value + transactionSum.value;
  }

  return {
    // State
    account,
    transactionSum,
    isLoading,
    error,
    // Getters
    openingBalance,
    currentBalance,
    calculatedBalance,
    hasSetBalance,
    // Actions
    loadAccount,
    setBalance,
    calculateLocalBalance,
  };
});
