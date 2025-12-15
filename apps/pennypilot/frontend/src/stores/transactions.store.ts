import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { localBaseService, type CategoryRule } from '@/services/storage/LocalBaseService';
import { transactionsApi } from '@/services/api/transactions.api';
import { categorizationService } from '@/services/categorization/CategorizationService';
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

  // Getters
  const filteredTransactions = computed(() => {
    let result = [...transactions.value];

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

  const totalExpenses = computed(() => {
    return filteredTransactions.value
      .filter((t) => t.amount < 0)
      .reduce((sum, t) => sum + Math.abs(t.amount), 0);
  });

  const totalIncome = computed(() => {
    return filteredTransactions.value
      .filter((t) => t.amount > 0)
      .reduce((sum, t) => sum + t.amount, 0);
  });

  const uncategorizedCount = computed(() => {
    return transactions.value.filter((t) => !t.is_categorized).length;
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

  async function importTransactions(parsed: Transaction[]): Promise<number> {
    isLoading.value = true;
    error.value = null;

    try {
      const imported = await localBaseService.addTransactionsBulk(parsed);
      transactions.value = [...transactions.value, ...imported];
      return imported.length;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to import transactions';
      return 0;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateCategory(localId: string, categoryId: string | null): Promise<void> {
    await localBaseService.updateTransaction(localId, {
      category_id: categoryId,
      is_categorized: !!categoryId,
      categorized_by: categoryId ? 'manual' : null,
    });

    // Update local state
    const index = transactions.value.findIndex((t) => t.local_id === localId);
    if (index !== -1) {
      transactions.value[index] = {
        ...transactions.value[index],
        category_id: categoryId,
        is_categorized: !!categoryId,
        categorized_by: categoryId ? 'manual' : null,
      };
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
    applyToSimilar: boolean
  ): Promise<{ updated: number; ruleCreated: boolean }> {
    let updated = 0;
    let ruleCreated = false;

    // Update the original transaction
    await updateCategory(localId, categoryId);
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

  async function syncToServer(): Promise<{ pushed: number; errors: number }> {
    const pending = await localBaseService.getPendingSyncTransactions();

    if (pending.length === 0) {
      return { pushed: 0, errors: 0 };
    }

    try {
      const result = await transactionsApi.bulkCreate(
        pending.map((t) => ({
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
        pending.map((t) => t.local_id),
        serverIdMap
      );

      return {
        pushed: result.created_count,
        errors: result.skipped_count,
      };
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Sync failed';
      return { pushed: 0, errors: pending.length };
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
    uncategorizedCount,
    // Actions
    loadFromLocal,
    importTransactions,
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
  };
});
