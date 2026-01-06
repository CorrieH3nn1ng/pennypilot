import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  businessProfileApi,
  type UpdateBusinessProfileData,
} from '@/services/api/business-profile.api';
import type { BusinessProfile } from '@/types';

export const useBusinessProfileStore = defineStore('businessProfile', () => {
  // State
  const profile = ref<BusinessProfile | null>(null);
  const hasBankingDetails = ref(false);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const displayName = computed(() => {
    if (!profile.value) return '';
    return profile.value.trading_name || profile.value.business_name || '';
  });

  const hasProfile = computed(() => profile.value !== null);

  const isVatRegistered = computed(() => !!profile.value?.vat_number);

  const bankingComplete = computed(() => {
    if (!profile.value) return false;
    return !!(
      profile.value.bank_name &&
      profile.value.account_holder &&
      profile.value.account_number
    );
  });

  // Actions
  async function loadProfile(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await businessProfileApi.get();
      profile.value = response.data;
      hasBankingDetails.value = response.has_banking_details;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load business profile';
    } finally {
      isLoading.value = false;
    }
  }

  async function updateProfile(data: UpdateBusinessProfileData): Promise<BusinessProfile | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const updated = await businessProfileApi.update(data);
      profile.value = updated;
      // Update banking details flag
      hasBankingDetails.value = !!(
        updated.bank_name &&
        updated.account_holder &&
        updated.account_number
      );
      return updated;
    } catch (e: unknown) {
      // Extract validation errors from API response
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const responseData = axiosError.response?.data;
        if (responseData?.errors) {
          // Get first validation error
          const firstField = Object.keys(responseData.errors)[0];
          error.value = responseData.errors[firstField]?.[0] || responseData.message || 'Validation failed';
        } else {
          error.value = responseData?.message || 'Failed to update business profile';
        }
      } else {
        error.value = e instanceof Error ? e.message : 'Failed to update business profile';
      }
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function uploadLogo(file: File): Promise<string | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await businessProfileApi.uploadLogo(file);
      profile.value = response.data;
      return response.logo_url;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to upload logo';
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function removeLogo(): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      const updated = await businessProfileApi.removeLogo();
      profile.value = updated;
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to remove logo';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    // State
    profile,
    hasBankingDetails,
    isLoading,
    error,
    // Getters
    displayName,
    hasProfile,
    isVatRegistered,
    bankingComplete,
    // Actions
    loadProfile,
    updateProfile,
    uploadLogo,
    removeLogo,
  };
});
