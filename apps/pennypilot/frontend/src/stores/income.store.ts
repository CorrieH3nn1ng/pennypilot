import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { incomeApi, type CreateIncomeSourceData, type IncomeSummaryResponse } from '@/services/api/income.api';
import type { IncomeSource, IncomeSummary } from '@/types';

export const useIncomeStore = defineStore('income', () => {
  // State
  const incomeSources = ref<IncomeSource[]>([]);
  const summary = ref<IncomeSummary | null>(null);
  const fullSummary = ref<IncomeSummaryResponse | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const activeIncomeSources = computed(() =>
    incomeSources.value.filter((i) => i.is_active)
  );

  const totalMonthlyGross = computed(() =>
    summary.value?.total_monthly_gross || 0
  );

  const totalAnnualGross = computed(() =>
    summary.value?.total_annual_gross || 0
  );

  const hasIncome = computed(() => incomeSources.value.length > 0);

  // Actions
  async function loadIncomeSources(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await incomeApi.list();
      incomeSources.value = response.data || [];
      summary.value = response.summary || null;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load income sources';
      incomeSources.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  async function loadSummary(): Promise<void> {
    try {
      fullSummary.value = await incomeApi.getSummary();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load income summary';
    }
  }

  async function createIncomeSource(data: CreateIncomeSourceData): Promise<IncomeSource | null> {
    try {
      const created = await incomeApi.create(data);
      incomeSources.value.push(created);
      // Refresh summary
      await loadIncomeSources();
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create income source';
      return null;
    }
  }

  async function updateIncomeSource(
    id: string,
    data: Partial<CreateIncomeSourceData>
  ): Promise<IncomeSource | null> {
    try {
      const updated = await incomeApi.update(id, data);
      const index = incomeSources.value.findIndex((i) => i.id === id);
      if (index !== -1) {
        incomeSources.value[index] = updated;
      }
      // Refresh summary
      await loadIncomeSources();
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update income source';
      return null;
    }
  }

  async function deleteIncomeSource(id: string): Promise<boolean> {
    try {
      await incomeApi.delete(id);
      incomeSources.value = incomeSources.value.filter((i) => i.id !== id);
      // Refresh summary
      await loadIncomeSources();
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete income source';
      return false;
    }
  }

  function getIncomeSourceById(id: string): IncomeSource | undefined {
    return incomeSources.value.find((i) => i.id === id);
  }

  return {
    // State
    incomeSources,
    summary,
    fullSummary,
    isLoading,
    error,
    // Getters
    activeIncomeSources,
    totalMonthlyGross,
    totalAnnualGross,
    hasIncome,
    // Actions
    loadIncomeSources,
    loadSummary,
    createIncomeSource,
    updateIncomeSource,
    deleteIncomeSource,
    getIncomeSourceById,
  };
});
