import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  taxApi,
  type UpdateTaxSettingsData,
  type CreateProvisionData,
  type TaxBracketsResponse,
} from '@/services/api/tax.api';
import type {
  TaxSetting,
  TaxProvision,
  TaxYearSummary,
  TaxBracket,
  AnnualTaxCalculation,
  MonthlyProvisionCalculation,
} from '@/types';

export const useTaxStore = defineStore('tax', () => {
  // State
  const settings = ref<TaxSetting | null>(null);
  const provisions = ref<TaxProvision[]>([]);
  const currentYearSummary = ref<TaxYearSummary | null>(null);
  const taxBrackets = ref<TaxBracket[]>([]);
  const taxYearInfo = ref<{ tax_year: string; effective_date: string } | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const isFreelancer = computed(() =>
    settings.value?.employment_type === 'self_employed' ||
    settings.value?.employment_type === 'mixed'
  );

  const provisionalTaxEnabled = computed(() =>
    settings.value?.provisional_tax_enabled || false
  );

  const totalProvisionThisYear = computed(() =>
    currentYearSummary.value?.total_provision || 0
  );

  const estimatedAnnualTax = computed(() =>
    currentYearSummary.value?.estimated_annual_tax || 0
  );

  const effectiveRate = computed(() =>
    currentYearSummary.value?.effective_rate || 0
  );

  const nextProvisionalPayment = computed(() => {
    if (!currentYearSummary.value?.provisional_payments) return null;
    const { first_payment, second_payment } = currentYearSummary.value.provisional_payments;
    const now = new Date();
    const firstDue = new Date(first_payment.due_date);
    const secondDue = new Date(second_payment.due_date);

    if (now < firstDue) return first_payment;
    if (now < secondDue) return second_payment;
    return null;
  });

  const unpaidProvisions = computed(() =>
    provisions.value.filter((p) => !p.is_paid)
  );

  // Actions
  async function loadSettings(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      settings.value = await taxApi.getSettings();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load tax settings';
    } finally {
      isLoading.value = false;
    }
  }

  async function updateSettings(data: UpdateTaxSettingsData): Promise<TaxSetting | null> {
    try {
      const updated = await taxApi.updateSettings(data);
      settings.value = updated;
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update tax settings';
      return null;
    }
  }

  async function loadProvisions(taxYear?: number): Promise<void> {
    try {
      provisions.value = await taxApi.getProvisions(taxYear);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load provisions';
    }
  }

  async function loadCurrentYearSummary(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      currentYearSummary.value = await taxApi.getCurrentSummary();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load tax year summary';
    } finally {
      isLoading.value = false;
    }
  }

  async function createProvision(data: CreateProvisionData): Promise<TaxProvision | null> {
    try {
      const created = await taxApi.createProvision(data);
      provisions.value.push(created);
      // Refresh summary
      await loadCurrentYearSummary();
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create provision';
      return null;
    }
  }

  async function markProvisionPaid(
    id: string,
    paymentDate: string,
    notes?: string
  ): Promise<TaxProvision | null> {
    try {
      const updated = await taxApi.markProvisionPaid(id, { payment_date: paymentDate, notes });
      const index = provisions.value.findIndex((p) => p.id === id);
      if (index !== -1) {
        provisions.value[index] = updated;
      }
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to mark provision as paid';
      return null;
    }
  }

  async function loadTaxBrackets(): Promise<void> {
    try {
      const response = await taxApi.getBrackets();
      taxBrackets.value = response.data;
      taxYearInfo.value = {
        tax_year: response.tax_year,
        effective_date: response.effective_date,
      };
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load tax brackets';
    }
  }

  async function calculateTax(
    amount: number,
    type: 'annual' | 'monthly',
    age?: number
  ): Promise<AnnualTaxCalculation | MonthlyProvisionCalculation | null> {
    try {
      const result = await taxApi.calculate({ amount, type, age });
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to calculate tax';
      return null;
    }
  }

  async function loadAll(): Promise<void> {
    await Promise.all([
      loadSettings(),
      loadCurrentYearSummary(),
      loadTaxBrackets(),
    ]);
  }

  return {
    // State
    settings,
    provisions,
    currentYearSummary,
    taxBrackets,
    taxYearInfo,
    isLoading,
    error,
    // Getters
    isFreelancer,
    provisionalTaxEnabled,
    totalProvisionThisYear,
    estimatedAnnualTax,
    effectiveRate,
    nextProvisionalPayment,
    unpaidProvisions,
    // Actions
    loadSettings,
    updateSettings,
    loadProvisions,
    loadCurrentYearSummary,
    createProvision,
    markProvisionPaid,
    loadTaxBrackets,
    calculateTax,
    loadAll,
  };
});
