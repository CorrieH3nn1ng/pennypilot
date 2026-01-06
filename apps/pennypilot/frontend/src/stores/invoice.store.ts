import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  invoiceApi,
  type CreateInvoiceData,
  type UpdateInvoiceData,
  type MarkPaidData,
  type InvoiceFilters,
  type UploadHistoricalData,
} from '@/services/api/invoice.api';
import type { Invoice, InvoiceSummary } from '@/types';

export const useInvoiceStore = defineStore('invoice', () => {
  // State
  const invoices = ref<Invoice[]>([]);
  const currentInvoice = ref<Invoice | null>(null);
  const summary = ref<InvoiceSummary | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const filters = ref<InvoiceFilters>({});

  // Getters
  const draftInvoices = computed(() =>
    invoices.value.filter((i) => i.status === 'draft')
  );

  const sentInvoices = computed(() =>
    invoices.value.filter((i) => i.status === 'sent')
  );

  const paidInvoices = computed(() =>
    invoices.value.filter((i) => i.status === 'paid')
  );

  const overdueInvoices = computed(() =>
    invoices.value.filter((i) => i.status === 'overdue')
  );

  const totalOutstanding = computed(() =>
    invoices.value
      .filter((i) => ['sent', 'overdue'].includes(i.status))
      .reduce((sum, i) => sum + Number(i.total), 0)
  );

  // Actions
  async function loadInvoices(newFilters?: InvoiceFilters): Promise<void> {
    isLoading.value = true;
    error.value = null;

    if (newFilters) {
      filters.value = newFilters;
    }

    try {
      const response = await invoiceApi.list(filters.value);
      invoices.value = response.data || [];
      summary.value = response.summary || null;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load invoices';
      invoices.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  async function loadInvoice(id: string): Promise<Invoice | null> {
    try {
      const response = await invoiceApi.get(id);
      currentInvoice.value = response.data;
      return response.data;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load invoice';
      return null;
    }
  }

  async function createInvoice(data: CreateInvoiceData): Promise<Invoice | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const created = await invoiceApi.create(data);
      invoices.value.unshift(created);
      await loadInvoices(); // Refresh to get updated summary
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create invoice';
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateInvoice(id: string, data: UpdateInvoiceData): Promise<Invoice | null> {
    try {
      const updated = await invoiceApi.update(id, data);
      const index = invoices.value.findIndex((i) => i.id === id);
      if (index !== -1) {
        invoices.value[index] = updated;
      }
      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updated;
      }
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update invoice';
      return null;
    }
  }

  async function deleteInvoice(id: string): Promise<boolean> {
    try {
      await invoiceApi.delete(id);
      invoices.value = invoices.value.filter((i) => i.id !== id);
      if (currentInvoice.value?.id === id) {
        currentInvoice.value = null;
      }
      await loadInvoices(); // Refresh summary
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to delete invoice';
      return false;
    }
  }

  async function markAsSent(id: string): Promise<Invoice | null> {
    try {
      const updated = await invoiceApi.markSent(id);
      const index = invoices.value.findIndex((i) => i.id === id);
      if (index !== -1) {
        invoices.value[index] = updated;
      }
      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updated;
      }
      await loadInvoices(); // Refresh summary
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to mark invoice as sent';
      return null;
    }
  }

  async function markAsPaid(id: string, data: MarkPaidData): Promise<Invoice | null> {
    try {
      const updated = await invoiceApi.markPaid(id, data);
      const index = invoices.value.findIndex((i) => i.id === id);
      if (index !== -1) {
        invoices.value[index] = updated;
      }
      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updated;
      }
      await loadInvoices(); // Refresh summary
      return updated;
    } catch (e: unknown) {
      // Extract error message from API response
      const axiosError = e as { response?: { data?: { message?: string } } };
      const message = axiosError.response?.data?.message
        || (e instanceof Error ? e.message : 'Failed to mark invoice as paid');
      error.value = message;
      throw new Error(message);
    }
  }

  async function cancelInvoice(id: string): Promise<Invoice | null> {
    try {
      const updated = await invoiceApi.cancel(id);
      const index = invoices.value.findIndex((i) => i.id === id);
      if (index !== -1) {
        invoices.value[index] = updated;
      }
      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updated;
      }
      await loadInvoices(); // Refresh summary
      return updated;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to cancel invoice';
      return null;
    }
  }

  async function previewInvoiceNumber(
    clientId: string,
    dueDate: string
  ): Promise<{ invoice_number: string; sequence_number: number } | null> {
    try {
      return await invoiceApi.previewNumber(clientId, dueDate);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to preview invoice number';
      return null;
    }
  }

  async function uploadHistoricalInvoice(data: UploadHistoricalData): Promise<Invoice | null> {
    isLoading.value = true;
    error.value = null;

    try {
      const created = await invoiceApi.uploadHistorical(data);
      invoices.value.unshift(created);
      await loadInvoices(); // Refresh to get updated summary
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to upload historical invoice';
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateOverdueStatus(): Promise<number> {
    try {
      const result = await invoiceApi.updateOverdueStatus();
      if (result.updated_count > 0) {
        await loadInvoices(); // Refresh list
      }
      return result.updated_count;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update overdue status';
      return 0;
    }
  }

  function getInvoiceById(id: string): Invoice | undefined {
    return invoices.value.find((i) => i.id === id);
  }

  function getDownloadUrl(id: string): string {
    return invoiceApi.getDownloadUrl(id);
  }

  return {
    // State
    invoices,
    currentInvoice,
    summary,
    isLoading,
    error,
    filters,
    // Getters
    draftInvoices,
    sentInvoices,
    paidInvoices,
    overdueInvoices,
    totalOutstanding,
    // Actions
    loadInvoices,
    loadInvoice,
    createInvoice,
    updateInvoice,
    deleteInvoice,
    markAsSent,
    markAsPaid,
    cancelInvoice,
    previewInvoiceNumber,
    uploadHistoricalInvoice,
    updateOverdueStatus,
    getInvoiceById,
    getDownloadUrl,
  };
});
