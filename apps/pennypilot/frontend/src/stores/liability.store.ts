import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  liabilityApi,
  type CreateLiabilityData,
  type UpdateLiabilityData,
  type RecordPaymentResponse,
  type CreateBlueprintFromLiabilityData,
} from '@/services/api/liability.api';
import type {
  Liability,
  LiabilitySummary,
  LiabilityCelebration,
  Blueprint,
} from '@/types';

export const useLiabilityStore = defineStore('liability', () => {
  // State
  const liabilities = ref<Liability[]>([]);
  const summary = ref<LiabilitySummary | null>(null);
  const selectedLiability = ref<Liability | null>(null);
  const recentlyPaidOff = ref<Liability[]>([]);

  // Celebration state (for pay-off dialog)
  const pendingCelebration = ref<LiabilityCelebration | null>(null);

  // Loading/Error state
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const activeLiabilities = computed(() =>
    liabilities.value.filter((l) => l.status === 'active')
  );

  const paidOffLiabilities = computed(() =>
    liabilities.value.filter((l) => l.status === 'paid_off')
  );

  const pausedLiabilities = computed(() =>
    liabilities.value.filter((l) => l.status === 'paused')
  );

  const totalRemainingDebt = computed(() =>
    activeLiabilities.value.reduce((sum, l) => sum + l.current_balance, 0)
  );

  const totalMonthlyPayments = computed(() =>
    activeLiabilities.value.reduce((sum, l) => sum + l.monthly_installment, 0)
  );

  const totalAmountFreed = computed(() =>
    paidOffLiabilities.value.reduce((sum, l) => sum + (l.amount_freed || 0), 0)
  );

  const hasActiveLiabilities = computed(() => activeLiabilities.value.length > 0);

  const hasCelebration = computed(() => pendingCelebration.value !== null);

  // Actions
  async function loadLiabilities(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await liabilityApi.list();
      liabilities.value = response.liabilities || [];
      summary.value = response.summary || null;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load liabilities';
      liabilities.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  async function loadLiability(id: string): Promise<Liability | null> {
    try {
      const liability = await liabilityApi.get(id);
      selectedLiability.value = liability;

      // Update in list if present
      const index = liabilities.value.findIndex((l) => l.id === id);
      if (index !== -1) {
        liabilities.value[index] = liability;
      }

      return liability;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load liability';
      return null;
    }
  }

  async function createLiability(data: CreateLiabilityData): Promise<Liability | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const created = await liabilityApi.create(data);
      liabilities.value.push(created);
      await loadLiabilities(); // Refresh summary
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create liability';
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateLiability(
    id: string,
    data: UpdateLiabilityData
  ): Promise<Liability | null> {
    try {
      const updated = await liabilityApi.update(id, data);
      const index = liabilities.value.findIndex((l) => l.id === id);
      if (index !== -1) {
        liabilities.value[index] = updated;
      }
      if (selectedLiability.value?.id === id) {
        selectedLiability.value = updated;
      }
      await loadLiabilities(); // Refresh summary
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update liability';
      return null;
    }
  }

  async function deleteLiability(id: string): Promise<boolean> {
    try {
      await liabilityApi.delete(id);
      liabilities.value = liabilities.value.filter((l) => l.id !== id);
      if (selectedLiability.value?.id === id) {
        selectedLiability.value = null;
      }
      await loadLiabilities(); // Refresh summary
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete liability';
      return false;
    }
  }

  /**
   * Record a payment against a liability
   * If the liability is paid off, sets pendingCelebration for display
   */
  async function recordPayment(
    id: string,
    amount: number,
    notes?: string
  ): Promise<RecordPaymentResponse | null> {
    try {
      const response = await liabilityApi.recordPayment(id, amount, notes);

      // Update liability in list
      const index = liabilities.value.findIndex((l) => l.id === id);
      if (index !== -1) {
        liabilities.value[index] = response.liability;
      }
      if (selectedLiability.value?.id === id) {
        selectedLiability.value = response.liability;
      }

      // If paid off, set celebration for display
      if (response.celebration) {
        pendingCelebration.value = response.celebration;
      }

      await loadLiabilities(); // Refresh summary
      return response;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to record payment';
      return null;
    }
  }

  /**
   * Link a liability to an existing blueprint
   */
  async function linkBlueprint(id: string, blueprintId: string): Promise<boolean> {
    try {
      await liabilityApi.linkBlueprint(id, blueprintId);
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to link blueprint';
      return false;
    }
  }

  /**
   * Create a new blueprint from a liability
   */
  async function createBlueprintFromLiability(
    id: string,
    data: CreateBlueprintFromLiabilityData
  ): Promise<Blueprint | null> {
    try {
      const blueprint = await liabilityApi.createBlueprint(id, data);
      return blueprint;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create blueprint';
      return null;
    }
  }

  /**
   * Load recently paid off liabilities for celebration
   */
  async function loadRecentlyPaidOff(): Promise<void> {
    try {
      recentlyPaidOff.value = await liabilityApi.recentlyPaidOff();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load recently paid off';
    }
  }

  /**
   * Clear the pending celebration (after displaying dialog)
   */
  function clearCelebration(): void {
    pendingCelebration.value = null;
  }

  /**
   * Get a liability by ID from the local list
   */
  function getLiabilityById(id: string): Liability | undefined {
    return liabilities.value.find((l) => l.id === id);
  }

  return {
    // State
    liabilities,
    summary,
    selectedLiability,
    recentlyPaidOff,
    pendingCelebration,
    isLoading,
    error,
    // Getters
    activeLiabilities,
    paidOffLiabilities,
    pausedLiabilities,
    totalRemainingDebt,
    totalMonthlyPayments,
    totalAmountFreed,
    hasActiveLiabilities,
    hasCelebration,
    // Actions
    loadLiabilities,
    loadLiability,
    createLiability,
    updateLiability,
    deleteLiability,
    recordPayment,
    linkBlueprint,
    createBlueprintFromLiability,
    loadRecentlyPaidOff,
    clearCelebration,
    getLiabilityById,
  };
});
