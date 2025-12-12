import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authApi } from '@/services/api/auth.api';
import { apiClient } from '@/services/api/client';
import type { User } from '@/types';

export const useUserStore = defineStore('user', () => {
  // State
  const user = ref<User | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const isAuthenticated = computed(() => apiClient.isAuthenticated());
  const userName = computed(() => user.value?.name || 'Guest');

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

  return {
    // State
    user,
    isLoading,
    error,
    // Getters
    isAuthenticated,
    userName,
    // Actions
    register,
    login,
    logout,
    fetchUser,
    updateProfile,
  };
});
