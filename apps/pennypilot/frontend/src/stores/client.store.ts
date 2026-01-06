import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { clientApi, type CreateClientData, type UpdateClientData } from '@/services/api/clients.api';
import type { Client } from '@/types';

export const useClientStore = defineStore('client', () => {
  // State
  const clients = ref<Client[]>([]);
  const currentClient = ref<Client | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const summary = ref<{ total_count: number; active_count: number } | null>(null);

  // Getters
  const activeClients = computed(() => clients.value.filter((c) => c.is_active));

  const clientOptions = computed(() =>
    activeClients.value.map((c) => ({
      label: c.name,
      value: c.id,
      code: c.client_code,
    }))
  );

  // Actions
  async function loadClients(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await clientApi.list();
      clients.value = response.data || [];
      summary.value = response.summary || null;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load clients';
      clients.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  async function loadClient(id: string): Promise<Client | null> {
    try {
      const response = await clientApi.get(id);
      currentClient.value = response.data;
      return response.data;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load client';
      return null;
    }
  }

  async function createClient(data: CreateClientData): Promise<Client | null> {
    try {
      const created = await clientApi.create(data);
      clients.value.push(created);
      // Refresh to get updated summary
      await loadClients();
      return created;
    } catch (e: unknown) {
      // Extract validation errors from API response
      if (e && typeof e === 'object' && 'response' in e) {
        const axiosError = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const responseData = axiosError.response?.data;
        if (responseData?.errors) {
          const firstField = Object.keys(responseData.errors)[0];
          error.value = responseData.errors[firstField]?.[0] || responseData.message || 'Validation failed';
        } else {
          error.value = responseData?.message || 'Failed to create client';
        }
      } else {
        error.value = e instanceof Error ? e.message : 'Failed to create client';
      }
      return null;
    }
  }

  async function updateClient(id: string, data: UpdateClientData): Promise<Client | null> {
    try {
      const updated = await clientApi.update(id, data);
      const index = clients.value.findIndex((c) => c.id === id);
      if (index !== -1) {
        clients.value[index] = updated;
      }
      if (currentClient.value?.id === id) {
        currentClient.value = updated;
      }
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update client';
      return null;
    }
  }

  async function deleteClient(id: string): Promise<boolean> {
    try {
      await clientApi.delete(id);
      clients.value = clients.value.filter((c) => c.id !== id);
      if (currentClient.value?.id === id) {
        currentClient.value = null;
      }
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete client';
      return false;
    }
  }

  function getClientById(id: string): Client | undefined {
    return clients.value.find((c) => c.id === id);
  }

  return {
    // State
    clients,
    currentClient,
    isLoading,
    error,
    summary,
    // Getters
    activeClients,
    clientOptions,
    // Actions
    loadClients,
    loadClient,
    createClient,
    updateClient,
    deleteClient,
    getClientById,
  };
});
