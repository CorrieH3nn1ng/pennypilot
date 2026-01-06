import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  budgetApi,
  type CreateBudgetData,
  type UpdateBudgetData,
  type BudgetItemData,
} from '@/services/api/budget.api';
import { fixedExpensesApi, type CreateFixedExpenseData } from '@/services/api/fixed-expenses.api';
import type {
  BudgetPeriod,
  BudgetItem,
  BucketTotals,
  TargetAllocations,
  BudgetSummary,
  FixedExpense,
  FixedExpenseSummary,
} from '@/types';

export const useBudgetStore = defineStore('budget', () => {
  // State - Budget
  const currentBudget = ref<BudgetPeriod | null>(null);
  const budgetHistory = ref<BudgetPeriod[]>([]);
  const budgetItems = ref<BudgetItem[]>([]);
  const bucketTotals = ref<BucketTotals | null>(null);
  const targetAllocations = ref<TargetAllocations | null>(null);
  const budgetSummary = ref<BudgetSummary | null>(null);

  // State - Fixed Expenses
  const fixedExpenses = ref<FixedExpense[]>([]);
  const fixedExpenseSummary = ref<FixedExpenseSummary | null>(null);
  const expensesDueSoon = ref<FixedExpense[]>([]);

  // State - Loading
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const hasBudget = computed(() => currentBudget.value !== null);

  const needsItems = computed(() =>
    budgetItems.value.filter((i) => i.bucket === 'needs')
  );

  const wantsItems = computed(() =>
    budgetItems.value.filter((i) => i.bucket === 'wants')
  );

  const savingsItems = computed(() =>
    budgetItems.value.filter((i) => i.bucket === 'savings')
  );

  const totalPlanned = computed(() =>
    budgetItems.value.reduce((sum, i) => sum + i.planned_amount, 0)
  );

  const totalActual = computed(() =>
    budgetItems.value.reduce((sum, i) => sum + i.actual_amount, 0)
  );

  const remaining = computed(() => {
    if (!currentBudget.value) return 0;
    return currentBudget.value.net_available - totalActual.value;
  });

  const activeFixedExpenses = computed(() =>
    fixedExpenses.value.filter((e) => e.is_active)
  );

  // Budget Actions
  async function loadCurrentBudget(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await budgetApi.getCurrent();
      if (response) {
        currentBudget.value = response.data;
        bucketTotals.value = response.bucket_totals;
        targetAllocations.value = response.target_allocations;
        budgetItems.value = response.data.items || [];
      } else {
        currentBudget.value = null;
        bucketTotals.value = null;
        targetAllocations.value = null;
        budgetItems.value = [];
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load budget';
    } finally {
      isLoading.value = false;
    }
  }

  async function loadBudgetHistory(year?: number): Promise<void> {
    try {
      budgetHistory.value = await budgetApi.list(year);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load budget history';
    }
  }

  async function createBudget(data: CreateBudgetData): Promise<BudgetPeriod | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const created = await budgetApi.create(data);
      currentBudget.value = created;
      budgetItems.value = created.items || [];
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create budget';
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateBudget(data: UpdateBudgetData): Promise<BudgetPeriod | null> {
    if (!currentBudget.value) return null;

    try {
      const updated = await budgetApi.update(currentBudget.value.id, data);
      currentBudget.value = updated;
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update budget';
      return null;
    }
  }

  async function deleteBudget(id: string): Promise<boolean> {
    try {
      await budgetApi.delete(id);
      if (currentBudget.value?.id === id) {
        currentBudget.value = null;
        budgetItems.value = [];
      }
      budgetHistory.value = budgetHistory.value.filter((b) => b.id !== id);
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete budget';
      return false;
    }
  }

  async function updateBudgetItems(items: BudgetItemData[]): Promise<boolean> {
    if (!currentBudget.value) return false;

    try {
      const updated = await budgetApi.updateItems(currentBudget.value.id, items);
      // Reload the current budget to get updated totals
      await loadCurrentBudget();
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update budget items';
      return false;
    }
  }

  async function loadBudgetSummary(): Promise<void> {
    if (!currentBudget.value) return;

    try {
      budgetSummary.value = await budgetApi.getSummary(currentBudget.value.id);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load budget summary';
    }
  }

  // Fixed Expense Actions
  async function loadFixedExpenses(): Promise<void> {
    try {
      const response = await fixedExpensesApi.list();
      fixedExpenses.value = response.data || [];
      fixedExpenseSummary.value = response.summary || null;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load fixed expenses';
      fixedExpenses.value = [];
    }
  }

  async function loadExpensesDueSoon(): Promise<void> {
    try {
      const response = await fixedExpensesApi.getDueSoon();
      expensesDueSoon.value = response.data;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load due soon expenses';
    }
  }

  async function createFixedExpense(data: CreateFixedExpenseData): Promise<FixedExpense | null> {
    try {
      const created = await fixedExpensesApi.create(data);
      fixedExpenses.value.push(created);
      await loadFixedExpenses(); // Refresh summary
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create fixed expense';
      return null;
    }
  }

  async function updateFixedExpense(
    id: string,
    data: Partial<CreateFixedExpenseData>
  ): Promise<FixedExpense | null> {
    try {
      const updated = await fixedExpensesApi.update(id, data);
      const index = fixedExpenses.value.findIndex((e) => e.id === id);
      if (index !== -1) {
        fixedExpenses.value[index] = updated;
      }
      await loadFixedExpenses(); // Refresh summary
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update fixed expense';
      return null;
    }
  }

  async function deleteFixedExpense(id: string): Promise<boolean> {
    try {
      await fixedExpensesApi.delete(id);
      fixedExpenses.value = fixedExpenses.value.filter((e) => e.id !== id);
      await loadFixedExpenses(); // Refresh summary
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete fixed expense';
      return false;
    }
  }

  return {
    // State - Budget
    currentBudget,
    budgetHistory,
    budgetItems,
    bucketTotals,
    targetAllocations,
    budgetSummary,
    // State - Fixed Expenses
    fixedExpenses,
    fixedExpenseSummary,
    expensesDueSoon,
    // State - Loading
    isLoading,
    error,
    // Getters
    hasBudget,
    needsItems,
    wantsItems,
    savingsItems,
    totalPlanned,
    totalActual,
    remaining,
    activeFixedExpenses,
    // Budget Actions
    loadCurrentBudget,
    loadBudgetHistory,
    createBudget,
    updateBudget,
    deleteBudget,
    updateBudgetItems,
    loadBudgetSummary,
    // Fixed Expense Actions
    loadFixedExpenses,
    loadExpensesDueSoon,
    createFixedExpense,
    updateFixedExpense,
    deleteFixedExpense,
  };
});
