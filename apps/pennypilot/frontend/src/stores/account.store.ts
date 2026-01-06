import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { accountsApi, type Account, type AccountWithBalance } from '@/services/api/accounts.api';
import { localBaseService } from '@/services/storage/LocalBaseService';
import {
  getDerivedOpeningBalance,
  checkBalanceDiscrepancy,
  filterToCurrentMonth,
  getDay1Balance,
  isBeforeCurrentMonth,
  type BalancingResult,
  type BalanceDiscrepancy,
  type DecemberAnchorResult,
} from '@/services/balancing/BalancingEngine';
import type { Transaction } from '@/types';

// Local storage keys for December anchor persistence
const ANCHOR_STORAGE_KEY = 'pennypilot_december_anchor';
const HISTORY_UNLOCK_KEY = 'pennypilot_history_unlocked';

interface DecemberAnchorData {
  openingBalance: number;
  year: number;
  setAt: string;
}


export const useAccountStore = defineStore('account', () => {
  // State
  const account = ref<Account | null>(null);
  const transactionSum = ref<number>(0);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Balancing Engine State
  const balancingResult = ref<BalancingResult | null>(null);
  const balanceDiscrepancy = ref<BalanceDiscrepancy | null>(null);
  const showBalanceConfirmation = ref(false);

  // December Anchor State
  const decemberAnchor = ref<DecemberAnchorData | null>(null);
  const isHistoryUnlocked = ref(false);

  // Load persisted anchor data on init
  function loadPersistedAnchor(): void {
    try {
      const anchorJson = localStorage.getItem(ANCHOR_STORAGE_KEY);
      if (anchorJson) {
        decemberAnchor.value = JSON.parse(anchorJson);
      }
      isHistoryUnlocked.value = localStorage.getItem(HISTORY_UNLOCK_KEY) === 'true';
    } catch (e) {
      console.warn('Failed to load persisted anchor data:', e);
    }
  }

  // Initialize on store creation
  loadPersistedAnchor();

  // Getters
  const openingBalance = computed(() => account.value?.opening_balance ?? 0);
  const currentBalance = computed(() => account.value?.current_balance ?? null);
  const calculatedBalance = computed(() => openingBalance.value + transactionSum.value);
  const hasSetBalance = computed(() => account.value?.balance_updated_at !== null);

  // Balancing getters
  const derivedOpeningBalance = computed(() => balancingResult.value?.derivedOpeningBalance ?? 0);
  const hasBalanceDiscrepancy = computed(() => balanceDiscrepancy.value?.hasDiscrepancy ?? false);
  const needsBalanceConfirmation = computed(() => {
    // Show confirmation if:
    // 1. User hasn't set a balance yet, OR
    // 2. There's a significant discrepancy
    return !hasSetBalance.value || hasBalanceDiscrepancy.value;
  });

  // December Anchor Getters
  const hasDecemberAnchor = computed(() => decemberAnchor.value !== null);
  const anchorYear = computed(() => decemberAnchor.value?.year ?? null);
  const anchorOpeningBalance = computed(() => decemberAnchor.value?.openingBalance ?? 0);

  // Historical walk-back is allowed if:
  // 1. User has a December anchor set, AND
  // 2. User is Premium (or has explicitly unlocked history)
  const canWalkBack = computed(() => hasDecemberAnchor.value && isHistoryUnlocked.value);

  // Minimum allowed date for historical transactions
  // Free users: current month only
  // Premium users with anchor: February of anchor year (walk back from December)
  const minimumAllowedDate = computed(() => {
    if (canWalkBack.value && anchorYear.value) {
      // Allow February onwards of the anchor year
      return `${anchorYear.value}-02-01`;
    }
    // Default: current month start
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
  });

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
      transactionSum.value = transactions.reduce((sum, t) => sum + Number(t.amount), 0);
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
    transactionSum.value = transactions.reduce((sum, t) => sum + Number(t.amount), 0);
    return openingBalance.value + transactionSum.value;
  }

  // =========================================
  // Balancing Engine Actions
  // =========================================

  /**
   * Calculate the derived opening balance based on current balance and transactions.
   * This is the core of the Automatic Balancing Engine.
   *
   * @param bankBalance Current bank balance (from user input or statement)
   * @param transactions Transaction history
   * @param isFreeUser Whether user is on free tier (affects date range)
   */
  function calculateDerivedBalance(
    bankBalance: number,
    transactions: Transaction[],
    isFreeUser: boolean = false
  ): BalancingResult {
    // Free users can only use current month's transactions
    const effectiveTransactions = isFreeUser
      ? filterToCurrentMonth(transactions)
      : transactions;

    const result = getDerivedOpeningBalance(bankBalance, effectiveTransactions);
    balancingResult.value = result;

    // Check discrepancy against user's stored opening balance
    balanceDiscrepancy.value = checkBalanceDiscrepancy(
      result.derivedOpeningBalance,
      openingBalance.value || null
    );

    return result;
  }

  /**
   * Trigger the balance confirmation modal.
   * Called after transactions are imported or balance is entered.
   */
  function promptBalanceConfirmation(
    bankBalance: number,
    transactions: Transaction[],
    isFreeUser: boolean = false
  ): void {
    calculateDerivedBalance(bankBalance, transactions, isFreeUser);

    if (needsBalanceConfirmation.value) {
      showBalanceConfirmation.value = true;
    }
  }

  /**
   * Accept the derived opening balance.
   * Called when user confirms in the modal.
   */
  async function acceptDerivedBalance(): Promise<boolean> {
    if (!balancingResult.value) return false;

    const success = await setBalance(balancingResult.value.derivedOpeningBalance);

    if (success) {
      showBalanceConfirmation.value = false;
      balanceDiscrepancy.value = null;
    }

    return success;
  }

  /**
   * Dismiss the confirmation modal without accepting.
   */
  function dismissBalanceConfirmation(): void {
    showBalanceConfirmation.value = false;
  }

  /**
   * Check if a transaction date is allowed for this user.
   * Free users cannot add transactions before current month.
   */
  function isDateAllowed(date: string, isFreeUser: boolean): boolean {
    if (!isFreeUser) return true;
    return !isBeforeCurrentMonth(date);
  }

  /**
   * Get Day 1 balance for Free users.
   * This is their forced starting point.
   */
  async function getDay1BalanceForFreeUser(bankBalance: number): Promise<number> {
    const transactions = await localBaseService.getAllTransactions();
    const currentMonthTx = filterToCurrentMonth(transactions);
    return getDay1Balance(bankBalance, currentMonthTx);
  }

  // =========================================
  // December Anchor Actions
  // =========================================

  /**
   * Set the December anchor balance.
   * This establishes the starting point for the 50/30/20 budget.
   *
   * @param openingBalance The derived December 1st opening balance
   * @param year The anchor year (e.g., 2024)
   */
  async function setDecemberAnchor(openingBalance: number, year: number): Promise<boolean> {
    try {
      const anchorData: DecemberAnchorData = {
        openingBalance,
        year,
        setAt: new Date().toISOString(),
      };

      // Persist to localStorage
      localStorage.setItem(ANCHOR_STORAGE_KEY, JSON.stringify(anchorData));
      decemberAnchor.value = anchorData;

      // Also set as the account opening balance
      await setBalance(openingBalance);

      return true;
    } catch (e) {
      console.error('Failed to set December anchor:', e);
      return false;
    }
  }

  /**
   * Clear the December anchor.
   * Used for testing or resetting the setup.
   */
  function clearDecemberAnchor(): void {
    localStorage.removeItem(ANCHOR_STORAGE_KEY);
    decemberAnchor.value = null;
  }

  /**
   * Unlock historical walk-back capability.
   * This allows Premium users to import transactions back to February.
   */
  function unlockHistory(): boolean {
    try {
      if (!hasDecemberAnchor.value) {
        console.warn('Cannot unlock history without December anchor');
        return false;
      }

      localStorage.setItem(HISTORY_UNLOCK_KEY, 'true');
      isHistoryUnlocked.value = true;
      return true;
    } catch (e) {
      console.error('Failed to unlock history:', e);
      return false;
    }
  }

  /**
   * Lock historical walk-back.
   * Reverts to current-month-only mode.
   */
  function lockHistory(): void {
    localStorage.removeItem(HISTORY_UNLOCK_KEY);
    isHistoryUnlocked.value = false;
  }

  /**
   * Check if a date is allowed for transaction entry.
   * Takes into account historical unlock status.
   *
   * @param date Transaction date string (YYYY-MM-DD)
   * @param isFreeUser Whether the user is on the free tier
   */
  function isDateAllowedWithHistory(date: string, isFreeUser: boolean): boolean {
    // Premium users with history unlocked can go back to February of anchor year
    if (!isFreeUser && canWalkBack.value) {
      return date >= minimumAllowedDate.value;
    }

    // Free users or Premium without unlock: current month only
    return !isBeforeCurrentMonth(date);
  }

  return {
    // State
    account,
    transactionSum,
    isLoading,
    error,
    // Balancing State
    balancingResult,
    balanceDiscrepancy,
    showBalanceConfirmation,
    // December Anchor State
    decemberAnchor,
    isHistoryUnlocked,
    // Getters
    openingBalance,
    currentBalance,
    calculatedBalance,
    hasSetBalance,
    // Balancing Getters
    derivedOpeningBalance,
    hasBalanceDiscrepancy,
    needsBalanceConfirmation,
    // December Anchor Getters
    hasDecemberAnchor,
    anchorYear,
    anchorOpeningBalance,
    canWalkBack,
    minimumAllowedDate,
    // Actions
    loadAccount,
    setBalance,
    calculateLocalBalance,
    // Balancing Actions
    calculateDerivedBalance,
    promptBalanceConfirmation,
    acceptDerivedBalance,
    dismissBalanceConfirmation,
    isDateAllowed,
    getDay1BalanceForFreeUser,
    // December Anchor Actions
    setDecemberAnchor,
    clearDecemberAnchor,
    unlockHistory,
    lockHistory,
    isDateAllowedWithHistory,
  };
});
