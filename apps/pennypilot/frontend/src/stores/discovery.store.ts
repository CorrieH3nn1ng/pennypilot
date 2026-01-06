import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  discoveryApi,
  type PatternGroup,
  type TransactionRule,
  type DiscoveryStats,
} from '@/services/api/discovery.api';
import type { Transaction, BudgetBucket } from '@/types';

export const useDiscoveryStore = defineStore('discovery', () => {
  // State
  const patterns = ref<PatternGroup[]>([]);
  const transactions = ref<Transaction[]>([]);
  const rules = ref<TransactionRule[]>([]);
  const stats = ref<DiscoveryStats | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const currentIndex = ref(0);

  // Getters
  const totalUnassigned = computed(() => transactions.value.length);
  const hasUnassigned = computed(() => patterns.value.length > 0);
  const currentPattern = computed(() => patterns.value[currentIndex.value] || null);
  const progress = computed(() => {
    if (patterns.value.length === 0) return 100;
    return Math.round((currentIndex.value / patterns.value.length) * 100);
  });
  const remainingCount = computed(() => patterns.value.length - currentIndex.value);

  // Actions
  async function loadUnassigned(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await discoveryApi.getUnassigned();
      patterns.value = response.patterns;
      transactions.value = response.transactions;
      currentIndex.value = 0;
    } catch (e) {
      error.value = 'Failed to load unassigned transactions';
      console.error('Discovery load error:', e);
    } finally {
      isLoading.value = false;
    }
  }

  async function assignBucket(
    pattern: string,
    bucket: BudgetBucket,
    categoryId?: string | null,
    transactionIds?: string[]
  ): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      await discoveryApi.assignBucket({
        pattern,
        bucket,
        category_id: categoryId,
        transaction_ids: transactionIds,
        create_budget_item: true,
      });

      // Move to next pattern
      if (currentIndex.value < patterns.value.length - 1) {
        currentIndex.value++;
      } else {
        // Refresh to get updated list
        await loadUnassigned();
      }

      return true;
    } catch (e) {
      error.value = 'Failed to assign bucket';
      console.error('Assign bucket error:', e);
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  async function skipPattern(): void {
    if (currentIndex.value < patterns.value.length - 1) {
      currentIndex.value++;
    }
  }

  async function loadRules(): Promise<void> {
    try {
      rules.value = await discoveryApi.getRules();
    } catch (e) {
      console.error('Failed to load rules:', e);
    }
  }

  async function deleteRule(ruleId: string): Promise<boolean> {
    try {
      await discoveryApi.deleteRule(ruleId);
      rules.value = rules.value.filter((r) => r.id !== ruleId);
      return true;
    } catch (e) {
      console.error('Failed to delete rule:', e);
      return false;
    }
  }

  async function applyRules(): Promise<number> {
    try {
      const result = await discoveryApi.applyRules();
      await loadUnassigned(); // Refresh
      return result.applied_count;
    } catch (e) {
      console.error('Failed to apply rules:', e);
      return 0;
    }
  }

  async function loadStats(): Promise<void> {
    try {
      stats.value = await discoveryApi.getStats();
    } catch (e) {
      console.error('Failed to load stats:', e);
    }
  }

  function reset(): void {
    patterns.value = [];
    transactions.value = [];
    currentIndex.value = 0;
    error.value = null;
  }

  return {
    // State
    patterns,
    transactions,
    rules,
    stats,
    isLoading,
    error,
    currentIndex,
    // Getters
    totalUnassigned,
    hasUnassigned,
    currentPattern,
    progress,
    remainingCount,
    // Actions
    loadUnassigned,
    assignBucket,
    skipPattern,
    loadRules,
    deleteRule,
    applyRules,
    loadStats,
    reset,
  };
});
