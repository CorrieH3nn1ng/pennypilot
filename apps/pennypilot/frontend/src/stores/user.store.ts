import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authApi, type UpdateIncomeSettingsData } from '@/services/api/auth.api';
import { apiClient } from '@/services/api/client';
import type { User, IncomeType } from '@/types';

export const useUserStore = defineStore('user', () => {
  // State
  const user = ref<User | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const isAuthenticated = computed(() => apiClient.isAuthenticated());
  const userName = computed(() => user.value?.name || 'Guest');
  const isPremium = computed(() => user.value?.is_premium || false);
  const isAdmin = computed(() => user.value?.is_admin || false);
  const subscriptionTier = computed(() => user.value?.subscription_tier || 'free');
  const isFreeUser = computed(() => subscriptionTier.value === 'free');
  const onboardingCompleted = computed(() => user.value?.onboarding_completed || false);

  // Feature checks based on tier
  const canSync = computed(() => {
    const tier = subscriptionTier.value;
    return isAdmin.value || ['cruiser', 'captain', 'premium'].includes(tier);
  });

  const canUseOcr = computed(() => {
    const tier = subscriptionTier.value;
    return isAdmin.value || tier === 'captain';
  });

  // =========================================
  // Income Type / Persona Getters
  // =========================================

  const incomeType = computed<IncomeType>(() => user.value?.income_type || 'self_employed');
  const isSelfEmployed = computed(() => incomeType.value === 'self_employed');
  const isSalaried = computed(() => incomeType.value === 'salaried');
  const netMonthlyIncome = computed(() => user.value?.net_monthly_income || null);

  // Persona-based feature visibility
  const persona = computed(() => user.value?.persona || {
    income_type: 'self_employed' as IncomeType,
    show_invoicing: true,
    show_clients: true,
    show_vat_threshold: true,
    show_tax_provisions: true,
    budget_source: 'invoiced_income' as const,
    net_monthly_income: null,
  });

  // Convenience getters for UI visibility
  const showInvoicing = computed(() => persona.value.show_invoicing);
  const showClients = computed(() => persona.value.show_clients);
  const showVatThreshold = computed(() => persona.value.show_vat_threshold);
  const showTaxProvisions = computed(() => persona.value.show_tax_provisions);
  const budgetSource = computed(() => persona.value.budget_source);

  // Actions
  async function register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await authApi.register({
        ...data,
        consent_given: true,
      });
      user.value = response.user;
      return true;
    } catch (e: unknown) {
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Registration failed';
      } else {
        error.value = 'Registration failed';
      }
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  async function login(email: string, password: string): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await authApi.login(email, password);
      user.value = response.user;
      return true;
    } catch (e: unknown) {
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Login failed';
      } else {
        error.value = 'Login failed';
      }
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout();
    } catch {
      // Ignore logout errors
    } finally {
      user.value = null;
      apiClient.clearToken();
    }
  }

  async function fetchUser(): Promise<void> {
    if (!apiClient.isAuthenticated()) {
      return;
    }

    isLoading.value = true;

    try {
      user.value = await authApi.getUser();
    } catch {
      // Token might be invalid
      user.value = null;
      apiClient.clearToken();
    } finally {
      isLoading.value = false;
    }
  }

  async function updateProfile(data: { name?: string; email?: string }): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      user.value = await authApi.updateProfile(data);
      return true;
    } catch (e: unknown) {
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Update failed';
      } else {
        error.value = 'Update failed';
      }
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateSubscription(tier: 'free' | 'premium'): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      user.value = await authApi.updateSubscription(tier);
      return true;
    } catch (e: unknown) {
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Update failed';
      } else {
        error.value = 'Update failed';
      }
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateIncomeSettings(data: UpdateIncomeSettingsData): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      user.value = await authApi.updateIncomeSettings(data);
      return true;
    } catch (e: unknown) {
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Update failed';
      } else {
        error.value = 'Update failed';
      }
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    // State
    user,
    isLoading,
    error,
    // Getters
    isAuthenticated,
    userName,
    isPremium,
    isAdmin,
    subscriptionTier,
    isFreeUser,
    onboardingCompleted,
    canSync,
    canUseOcr,
    // Income Type / Persona Getters
    incomeType,
    isSelfEmployed,
    isSalaried,
    netMonthlyIncome,
    persona,
    showInvoicing,
    showClients,
    showVatThreshold,
    showTaxProvisions,
    budgetSource,
    // Actions
    register,
    login,
    logout,
    fetchUser,
    updateProfile,
    updateSubscription,
    updateIncomeSettings,
  };
});
