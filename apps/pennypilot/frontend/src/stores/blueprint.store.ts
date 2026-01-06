import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { blueprintApi, type CreateBlueprintData, type UpdateBlueprintData } from '@/services/api/blueprint.api';
import type {
  Blueprint,
  BlueprintGrouped,
  BlueprintTotals,
  BlueprintPreview,
  BulkBlueprintItem,
  BulkBlueprintResult,
} from '@/types';

export const useBlueprintStore = defineStore('blueprint', () => {
  // State
  const blueprints = ref<Blueprint[]>([]);
  const grouped = ref<BlueprintGrouped>({
    income: [],
    expense: { needs: [], wants: [], savings: [] },
  });
  const totals = ref<BlueprintTotals>({
    income: 0,
    expense: 0,
    needs: 0,
    wants: 0,
    savings: 0,
    active_count: 0,
    total_count: 0,
  });
  const preview = ref<BlueprintPreview | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const activeBlueprints = computed(() => blueprints.value.filter((b) => b.is_active));

  const incomeBlueprints = computed(() => blueprints.value.filter((b) => b.item_type === 'income'));

  const expenseBlueprints = computed(() => blueprints.value.filter((b) => b.item_type === 'expense'));

  const netCashFlow = computed(() => totals.value.income - totals.value.expense);

  const bucketPercentages = computed(() => {
    const total = totals.value.expense;
    if (total === 0) return { needs: 0, wants: 0, savings: 0 };
    return {
      needs: Math.round((totals.value.needs / total) * 100),
      wants: Math.round((totals.value.wants / total) * 100),
      savings: Math.round((totals.value.savings / total) * 100),
    };
  });

  // Actions
  async function fetchBlueprints() {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await blueprintApi.list();
      blueprints.value = response.blueprints;
      grouped.value = response.grouped;
      totals.value = response.totals;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch blueprints';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function createBlueprint(data: CreateBlueprintData): Promise<Blueprint> {
    isLoading.value = true;
    error.value = null;
    try {
      const blueprint = await blueprintApi.create(data);
      await fetchBlueprints(); // Refresh to get updated totals
      return blueprint;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create blueprint';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateBlueprint(id: string, data: UpdateBlueprintData): Promise<Blueprint> {
    isLoading.value = true;
    error.value = null;
    try {
      const blueprint = await blueprintApi.update(id, data);
      await fetchBlueprints(); // Refresh to get updated totals
      return blueprint;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update blueprint';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function deleteBlueprint(id: string): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
      await blueprintApi.delete(id);
      await fetchBlueprints(); // Refresh to get updated totals
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete blueprint';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function toggleBlueprint(id: string): Promise<void> {
    error.value = null;
    try {
      await blueprintApi.toggle(id);
      await fetchBlueprints(); // Refresh to get updated totals
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to toggle blueprint';
      throw e;
    }
  }

  async function importFromFixed(): Promise<{ imported: number; skipped: number }> {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await blueprintApi.importFromFixed();
      await fetchBlueprints();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to import from fixed expenses';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function bulkCreate(items: BulkBlueprintItem[]): Promise<BulkBlueprintResult> {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await blueprintApi.bulkCreate(items);
      await fetchBlueprints(); // Refresh to get updated totals
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to bulk create blueprints';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchPreview(): Promise<BlueprintPreview> {
    isLoading.value = true;
    error.value = null;
    try {
      preview.value = await blueprintApi.preview();
      return preview.value;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch preview';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  function getBlueprintById(id: string): Blueprint | undefined {
    return blueprints.value.find((b) => b.id === id);
  }

  function clearError() {
    error.value = null;
  }

  return {
    // State
    blueprints,
    grouped,
    totals,
    preview,
    isLoading,
    error,
    // Getters
    activeBlueprints,
    incomeBlueprints,
    expenseBlueprints,
    netCashFlow,
    bucketPercentages,
    // Actions
    fetchBlueprints,
    createBlueprint,
    updateBlueprint,
    deleteBlueprint,
    toggleBlueprint,
    importFromFixed,
    bulkCreate,
    fetchPreview,
    getBlueprintById,
    clearError,
  };
});
