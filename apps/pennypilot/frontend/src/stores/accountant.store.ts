import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  accountantApi,
  type FlaggedTransaction,
  type FlaggedSummary,
  type AccountantSummary,
  type AccountantStatus,
  type ApprovalResponse,
} from '@/services/api/accountant.api';

export const useAccountantStore = defineStore('accountant', () => {
  // State
  const flaggedTransactions = ref<FlaggedTransaction[]>([]);
  const summary = ref<FlaggedSummary | null>(null);
  const accountantSummary = ref<AccountantSummary | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const lastTaxImpact = ref<ApprovalResponse['tax_impact'] | null>(null);

  // Filters
  const statusFilter = ref<AccountantStatus | 'all'>('pending');
  const fromDateFilter = ref<string | null>(null);
  const toDateFilter = ref<string | null>(null);

  // Getters
  const pendingCount = computed(() => summary.value?.pending_review ?? 0);
  const pendingAmount = computed(() => summary.value?.total_pending_amount ?? 0);
  const approvedDeductions = computed(() => summary.value?.total_approved_deductions ?? 0);

  const pendingTransactions = computed(() =>
    flaggedTransactions.value.filter((t) => t.accountant_status === 'pending')
  );

  const approvedTransactions = computed(() =>
    flaggedTransactions.value.filter((t) => t.accountant_status === 'approved_business')
  );

  const rejectedTransactions = computed(() =>
    flaggedTransactions.value.filter((t) => t.accountant_status === 'rejected_private')
  );

  const potentialTaxSavings = computed(() => {
    // 39.1% of pending amount
    return pendingAmount.value * 0.391;
  });

  const taxYearDeductions = computed(() =>
    accountantSummary.value?.approved.total_deductions ?? 0
  );

  const taxYearSavings = computed(() =>
    accountantSummary.value?.approved.tax_savings ?? 0
  );

  // Actions
  async function loadFlagged(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const filters = {
        status: statusFilter.value,
        from_date: fromDateFilter.value || undefined,
        to_date: toDateFilter.value || undefined,
      };
      const response = await accountantApi.getFlagged(filters);
      flaggedTransactions.value = response.transactions;
      summary.value = response.summary;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load flagged transactions';
    } finally {
      isLoading.value = false;
    }
  }

  async function loadSummary(): Promise<void> {
    try {
      accountantSummary.value = await accountantApi.getSummary();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load accountant summary';
    }
  }

  async function flagTransaction(transactionId: string, question?: string): Promise<FlaggedTransaction | null> {
    try {
      const flagged = await accountantApi.flag(transactionId, question);
      // Refresh the list
      await loadFlagged();
      await loadSummary();
      return flagged;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to flag transaction';
      return null;
    }
  }

  async function unflagTransaction(transactionId: string): Promise<FlaggedTransaction | null> {
    try {
      const unflagged = await accountantApi.unflag(transactionId);
      // Remove from local state
      flaggedTransactions.value = flaggedTransactions.value.filter(
        (t) => t.id !== transactionId
      );
      await loadSummary();
      return unflagged;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to unflag transaction';
      return null;
    }
  }

  async function approveAsBusiness(transactionId: string, notes?: string): Promise<ApprovalResponse | null> {
    try {
      const response = await accountantApi.approve(transactionId, notes);
      // Update local state
      const index = flaggedTransactions.value.findIndex((t) => t.id === transactionId);
      if (index !== -1) {
        flaggedTransactions.value[index] = response.transaction;
      }
      // Store tax impact for display
      lastTaxImpact.value = response.tax_impact;
      // Refresh summary
      await loadSummary();
      return response;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to approve transaction';
      return null;
    }
  }

  async function rejectToPrivate(transactionId: string, notes?: string): Promise<FlaggedTransaction | null> {
    try {
      const rejected = await accountantApi.reject(transactionId, notes);
      // Update local state
      const index = flaggedTransactions.value.findIndex((t) => t.id === transactionId);
      if (index !== -1) {
        flaggedTransactions.value[index] = rejected;
      }
      // Refresh summary
      await loadSummary();
      return rejected;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to reject transaction';
      return null;
    }
  }

  async function updateQuestion(transactionId: string, question: string): Promise<FlaggedTransaction | null> {
    try {
      const updated = await accountantApi.updateQuestion(transactionId, question);
      // Update local state
      const index = flaggedTransactions.value.findIndex((t) => t.id === transactionId);
      if (index !== -1) {
        flaggedTransactions.value[index] = updated;
      }
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update question';
      return null;
    }
  }

  async function downloadReport(): Promise<void> {
    try {
      const filters = {
        status: statusFilter.value,
        from_date: fromDateFilter.value || undefined,
        to_date: toDateFilter.value || undefined,
      };
      const blob = await accountantApi.downloadReport(filters);

      // Create download link
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `accountant-review-${new Date().toISOString().split('T')[0]}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to download report';
    }
  }

  function setStatusFilter(status: AccountantStatus | 'all'): void {
    statusFilter.value = status;
  }

  function setDateFilter(from: string | null, to: string | null): void {
    fromDateFilter.value = from;
    toDateFilter.value = to;
  }

  function clearFilters(): void {
    statusFilter.value = 'pending';
    fromDateFilter.value = null;
    toDateFilter.value = null;
  }

  async function loadAll(): Promise<void> {
    await Promise.all([loadFlagged(), loadSummary()]);
  }

  return {
    // State
    flaggedTransactions,
    summary,
    accountantSummary,
    isLoading,
    error,
    lastTaxImpact,
    statusFilter,
    fromDateFilter,
    toDateFilter,
    // Getters
    pendingCount,
    pendingAmount,
    approvedDeductions,
    pendingTransactions,
    approvedTransactions,
    rejectedTransactions,
    potentialTaxSavings,
    taxYearDeductions,
    taxYearSavings,
    // Actions
    loadFlagged,
    loadSummary,
    flagTransaction,
    unflagTransaction,
    approveAsBusiness,
    rejectToPrivate,
    updateQuestion,
    downloadReport,
    setStatusFilter,
    setDateFilter,
    clearFilters,
    loadAll,
  };
});
