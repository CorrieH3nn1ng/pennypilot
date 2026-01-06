import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { localBaseService, type CategoryRule, type SkippedRecord } from '@/services/storage/LocalBaseService';
import { transactionsApi } from '@/services/api/transactions.api';
import { categorizationService } from '@/services/categorization/CategorizationService';
import { useCategoriesStore } from '@/stores/categories.store';
import type { Transaction, TransactionFilters, TransactionSummary } from '@/types';

export const useTransactionsStore = defineStore('transactions', () => {
  // State
  const transactions = ref<Transaction[]>([]);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const filters = ref<TransactionFilters>({
    startDate: null,
    endDate: null,
    categoryId: null,
    isCategorized: null,
    searchQuery: '',
  });
  const summary = ref<TransactionSummary | null>(null);
  const customFilter = ref<((t: Transaction) => boolean) | null>(null);

  // Helper to check if a transaction is a transfer
  function isTransfer(t: Transaction): boolean {
    // Only check the explicit is_transfer flag
    // Handle different types from server (boolean, number, string)
    const flag = t.is_transfer;
    return flag === true || flag === 1 || flag === '1';
  }

  // Getters
  const filteredTransactions = computed(() => {
    let result = [...transactions.value];

    // Apply custom filter first (for Income/Transfer tabs)
    if (customFilter.value) {
      result = result.filter(customFilter.value);
    }

    if (filters.value.startDate) {
      result = result.filter((t) => t.transaction_date >= filters.value.startDate!);
    }

    if (filters.value.endDate) {
      result = result.filter((t) => t.transaction_date <= filters.value.endDate!);
    }

    if (filters.value.categoryId) {
      result = result.filter((t) => t.category_id === filters.value.categoryId);
    }

    if (filters.value.isCategorized !== null) {
      result = result.filter((t) => t.is_categorized === filters.value.isCategorized);
    }

    if (filters.value.searchQuery) {
      const query = filters.value.searchQuery.toLowerCase();
      result = result.filter(
        (t) =>
          t.description.toLowerCase().includes(query) ||
          t.raw_description?.toLowerCase().includes(query)
      );
    }

    return result.sort(
      (a, b) => new Date(b.transaction_date).getTime() - new Date(a.transaction_date).getTime()
    );
  });

  // Totals from ALL transactions (for Dashboard)
  const totalExpenses = computed(() => {
    return transactions.value
      .filter((t) => Number(t.amount) < 0 && !isTransfer(t))
      .reduce((sum, t) => sum + Math.abs(Number(t.amount)), 0);
  });

  const totalIncome = computed(() => {
    return transactions.value
      .filter((t) => Number(t.amount) > 0 && !isTransfer(t))
      .reduce((sum, t) => sum + Number(t.amount), 0);
  });

  // Totals from FILTERED transactions (for reports/analysis)
  const filteredTotalExpenses = computed(() => {
    return filteredTransactions.value
      .filter((t) => Number(t.amount) < 0 && !isTransfer(t))
      .reduce((sum, t) => sum + Math.abs(Number(t.amount)), 0);
  });

  const filteredTotalIncome = computed(() => {
    return filteredTransactions.value
      .filter((t) => Number(t.amount) > 0 && !isTransfer(t))
      .reduce((sum, t) => sum + Number(t.amount), 0);
  });

  const uncategorizedCount = computed(() => {
    return transactions.value.filter((t) => !t.is_categorized).length;
  });

  const pendingSyncCount = computed(() => {
    return transactions.value.filter((t) => t.sync_status === 'pending').length;
  });

  // Actions
  async function loadFromLocal(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      transactions.value = await localBaseService.getAllTransactions();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load transactions';
    } finally {
      isLoading.value = false;
    }
  }

  async function loadFromServer(): Promise<number> {
    isLoading.value = true;
    error.value = null;

    try {
      // Fetch all transactions from server (paginated)
      let allTransactions: Transaction[] = [];
      let offset = 0;
      const limit = 100;
      let hasMore = true;

      console.log('[loadFromServer] Starting to fetch transactions...');

      while (hasMore) {
        // Note: apiClient.get unwraps the response, so we get the array directly
        const txBatch = await transactionsApi.list({ limit, offset }) as unknown as Transaction[];
        console.log(`[loadFromServer] Fetched batch: offset=${offset}, count=${txBatch?.length ?? 0}`);
        if (!txBatch || !Array.isArray(txBatch)) {
          console.log('[loadFromServer] No more transactions or invalid response');
          break;
        }
        allTransactions = [...allTransactions, ...txBatch];
        offset += limit;
        hasMore = txBatch.length === limit;
      }

      console.log(`[loadFromServer] Total fetched from server: ${allTransactions.length}`);

      // Save to local storage with synced status
      const added = await localBaseService.addTransactionsFromServer(allTransactions);
      console.log(`[loadFromServer] Added to local storage: ${added}`);

      // Update in-memory state
      transactions.value = await localBaseService.getAllTransactions();
      console.log(`[loadFromServer] In-memory transactions: ${transactions.value.length}`);

      // Debug: Calculate totals
      const income = transactions.value
        .filter(t => Number(t.amount) > 0)
        .reduce((sum, t) => sum + Number(t.amount), 0);
      const expenses = transactions.value
        .filter(t => Number(t.amount) < 0)
        .reduce((sum, t) => sum + Math.abs(Number(t.amount)), 0);
      console.log(`[loadFromServer] Calculated income: R ${income.toFixed(2)}, expenses: R ${expenses.toFixed(2)}`);

      return added;
    } catch (e) {
      console.error('[loadFromServer] Error:', e);
      error.value = e instanceof Error ? e.message : 'Failed to load from server';
      return 0;
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Import result with deduplication stats and skipped record details
   */
  interface ImportResult {
    imported: number;
    duplicates: number;
    total: number;
    skipped: SkippedRecord[];  // Detailed list of skipped records for audit
  }

  async function importTransactions(
    parsed: Transaction[],
    onProgress?: (processed: number, total: number) => void
  ): Promise<number> {
    const result = await importTransactionsWithStats(parsed, onProgress);
    return result.imported;
  }

  /**
   * Import transactions with full stats (imported, duplicates, total)
   * Now includes detailed list of skipped records for audit trail
   */
  async function importTransactionsWithStats(
    parsed: Transaction[],
    onProgress?: (processed: number, total: number) => void
  ): Promise<ImportResult> {
    isLoading.value = true;
    error.value = null;

    try {
      const result = await localBaseService.addTransactionsBulkWithStats(parsed, onProgress);
      transactions.value = [...transactions.value, ...result.added];

      return {
        imported: result.added.length,
        duplicates: result.duplicateCount,
        total: result.totalProcessed,
        skipped: result.skipped,
      };
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to import transactions';
      return { imported: 0, duplicates: 0, total: parsed.length, skipped: [] };
    } finally {
      isLoading.value = false;
    }
  }

  async function updateCategory(
    localId: string,
    categoryId: string | null,
    isTransferFlag?: boolean
  ): Promise<void> {
    const updates: Partial<Transaction> = {
      category_id: categoryId,
      is_categorized: !!categoryId,
      categorized_by: categoryId ? 'manual' : null,
    };

    // Include is_transfer if provided
    if (isTransferFlag !== undefined) {
      updates.is_transfer = isTransferFlag;
    }

    await localBaseService.updateTransaction(localId, updates);

    // Update local state
    const index = transactions.value.findIndex((t) => t.local_id === localId);
    const transaction = index !== -1 ? transactions.value[index] : null;

    if (index !== -1) {
      transactions.value[index] = {
        ...transactions.value[index],
        ...updates,
        sync_status: 'pending',
      };
    }

    // Auto-sync to server if online and transaction has server ID
    if (navigator.onLine && transaction?.id) {
      try {
        await transactionsApi.bulkUpdate([{
          id: transaction.id,
          category_id: categoryId,
          is_transfer: isTransferFlag,
          is_categorized: !!categoryId,
          categorized_by: categoryId ? 'manual' : null,
        }]);

        // Mark as synced
        await localBaseService.markTransactionsSynced([localId], new Map());
        if (index !== -1) {
          transactions.value[index] = {
            ...transactions.value[index],
            sync_status: 'synced',
          };
        }
        console.log('[updateCategory] Auto-synced to server');
      } catch (e) {
        console.warn('[updateCategory] Auto-sync failed, will sync later:', e);
        // Keep as pending, will sync later
      }
    }
  }

  async function deleteTransaction(localId: string): Promise<void> {
    await localBaseService.deleteTransaction(localId);
    transactions.value = transactions.value.filter((t) => t.local_id !== localId);
  }

  async function autoCategorize(
    categories: { id: string; name: string }[],
    onlyUncategorized = true
  ): Promise<{ categorized: number; total: number }> {
    // Set categories in the service
    categorizationService.setCategories(categories as any);

    // Load and set user rules
    const userRules = await localBaseService.getAllCategoryRules();
    categorizationService.setUserRules(userRules);

    // Get transactions to categorize
    const toProcess = onlyUncategorized
      ? transactions.value.filter((t) => !t.is_categorized)
      : transactions.value;

    let categorized = 0;

    for (const tx of toProcess) {
      const result = categorizationService.categorize(tx);

      if (result.categoryId) {
        // Update in local storage
        await localBaseService.updateTransaction(tx.local_id, {
          category_id: result.categoryId,
          is_categorized: true,
          categorized_by: 'auto',
        });

        // Update in memory
        const index = transactions.value.findIndex((t) => t.local_id === tx.local_id);
        if (index !== -1) {
          transactions.value[index] = {
            ...transactions.value[index],
            category_id: result.categoryId,
            is_categorized: true,
            categorized_by: 'auto',
          };
        }

        // Update rule hit count if user rule was used
        if (result.ruleId) {
          await localBaseService.updateCategoryRuleHitCount(result.ruleId);
        }

        categorized++;
      }
    }

    return { categorized, total: toProcess.length };
  }

  /**
   * Find similar transactions based on a pattern
   */
  function findSimilarTransactions(pattern: string, excludeLocalId?: string): Transaction[] {
    return categorizationService.findSimilarTransactions(
      transactions.value,
      pattern,
      excludeLocalId
    );
  }

  /**
   * Extract a pattern from a transaction description
   */
  function extractPattern(description: string): string {
    return categorizationService.extractPattern(description);
  }

  /**
   * Apply category to transaction and optionally create a rule for similar transactions
   */
  async function applyCategoryWithRule(
    localId: string,
    categoryId: string,
    categoryName: string,
    pattern: string | null,
    applyToSimilar: boolean,
    isTransferFlag?: boolean
  ): Promise<{ updated: number; ruleCreated: boolean }> {
    let updated = 0;
    let ruleCreated = false;

    // Update the original transaction
    await updateCategory(localId, categoryId, isTransferFlag);
    updated++;

    // If pattern provided and should apply to similar
    if (pattern && applyToSimilar) {
      // Create a user rule
      await localBaseService.addCategoryRule({
        pattern,
        category_id: categoryId,
        category_name: categoryName,
        match_type: 'contains',
        is_user_defined: true,
      });
      ruleCreated = true;

      // Find and update similar transactions
      const similar = findSimilarTransactions(pattern, localId);
      const uncategorizedSimilar = similar.filter((t) => !t.is_categorized);

      for (const tx of uncategorizedSimilar) {
        await localBaseService.updateTransaction(tx.local_id, {
          category_id: categoryId,
          is_categorized: true,
          categorized_by: 'auto',
        });

        // Update in memory
        const index = transactions.value.findIndex((t) => t.local_id === tx.local_id);
        if (index !== -1) {
          transactions.value[index] = {
            ...transactions.value[index],
            category_id: categoryId,
            is_categorized: true,
            categorized_by: 'auto',
          };
        }

        updated++;
      }
    }

    return { updated, ruleCreated };
  }

  /**
   * Get all user-defined category rules
   */
  async function getCategoryRules(): Promise<CategoryRule[]> {
    return localBaseService.getAllCategoryRules();
  }

  /**
   * Delete a user-defined category rule
   */
  async function deleteCategoryRule(ruleId: string): Promise<void> {
    await localBaseService.deleteCategoryRule(ruleId);
  }

  async function syncToServer(): Promise<{ pushed: number; updated: number; errors: number }> {
    const pending = await localBaseService.getPendingSyncTransactions();

    if (pending.length === 0) {
      return { pushed: 0, updated: 0, errors: 0 };
    }

    // Separate new transactions from updates
    // New transactions don't have a server id (id field)
    const newTransactions = pending.filter((t) => !t.id);
    const updatedTransactions = pending.filter((t) => t.id);

    const BATCH_SIZE = 500;
    let totalPushed = 0;
    let totalUpdated = 0;
    let totalErrors = 0;

    try {
      // Handle new transactions with bulkCreate
      for (let i = 0; i < newTransactions.length; i += BATCH_SIZE) {
        const batch = newTransactions.slice(i, i + BATCH_SIZE);

        const result = await transactionsApi.bulkCreate(
          batch.map((t) => ({
            transaction_date: t.transaction_date,
            description: t.description,
            amount: t.amount,
            balance_after: t.balance_after,
            bank_reference: t.bank_reference,
            raw_description: t.raw_description || t.description,
            local_id: t.local_id,
          }))
        );

        // Mark synced transactions
        const serverIdMap = new Map<string, string>();
        result.created.forEach((item) => {
          if (item.local_id) {
            serverIdMap.set(item.local_id, item.id);
          }
        });

        await localBaseService.markTransactionsSynced(
          batch.map((t) => t.local_id),
          serverIdMap
        );

        // Update in-memory sync status
        batch.forEach((t) => {
          const index = transactions.value.findIndex((tx) => tx.local_id === t.local_id);
          if (index !== -1) {
            transactions.value[index] = {
              ...transactions.value[index],
              sync_status: 'synced',
            };
          }
        });

        totalPushed += result.created_count;
        totalErrors += result.skipped_count;
      }

      // Handle updates with bulkUpdate
      for (let i = 0; i < updatedTransactions.length; i += BATCH_SIZE) {
        const batch = updatedTransactions.slice(i, i + BATCH_SIZE);

        const result = await transactionsApi.bulkUpdate(
          batch.map((t) => ({
            id: t.id!,
            category_id: t.category_id,
            is_transfer: t.is_transfer,
            is_categorized: t.is_categorized,
            categorized_by: t.categorized_by,
          }))
        );

        // Mark synced transactions
        await localBaseService.markTransactionsSynced(
          batch.map((t) => t.local_id),
          new Map() // No new IDs for updates
        );

        // Update in-memory sync status
        batch.forEach((t) => {
          const index = transactions.value.findIndex((tx) => tx.local_id === t.local_id);
          if (index !== -1) {
            transactions.value[index] = {
              ...transactions.value[index],
              sync_status: 'synced',
            };
          }
        });

        totalUpdated += result.updated_count;
        totalErrors += result.error_count;
      }

      return {
        pushed: totalPushed,
        updated: totalUpdated,
        errors: totalErrors,
      };
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Sync failed';
      return { pushed: totalPushed, updated: totalUpdated, errors: pending.length - totalPushed - totalUpdated };
    }
  }

  async function loadSummary(startDate: string, endDate: string): Promise<void> {
    try {
      summary.value = await transactionsApi.getSummary(startDate, endDate);
    } catch (e) {
      console.error('Failed to load summary:', e);
    }
  }

  function setFilters(newFilters: Partial<TransactionFilters>): void {
    filters.value = { ...filters.value, ...newFilters };
  }

  function clearFilters(): void {
    filters.value = {
      startDate: null,
      endDate: null,
      categoryId: null,
      isCategorized: null,
      searchQuery: '',
    };
    customFilter.value = null;
  }

  function setCustomFilter(filterFn: (t: Transaction) => boolean): void {
    customFilter.value = filterFn;
  }

  function clearCustomFilter(): void {
    customFilter.value = null;
  }

  return {
    // State
    transactions,
    isLoading,
    error,
    filters,
    summary,
    // Getters
    filteredTransactions,
    totalExpenses,
    totalIncome,
    filteredTotalExpenses,
    filteredTotalIncome,
    uncategorizedCount,
    pendingSyncCount,
    // Helpers
    isTransfer,
    // Actions
    loadFromLocal,
    loadFromServer,
    importTransactions,
    importTransactionsWithStats,
    updateCategory,
    deleteTransaction,
    autoCategorize,
    findSimilarTransactions,
    extractPattern,
    applyCategoryWithRule,
    getCategoryRules,
    deleteCategoryRule,
    syncToServer,
    loadSummary,
    setFilters,
    clearFilters,
    setCustomFilter,
    clearCustomFilter,
  };
});
