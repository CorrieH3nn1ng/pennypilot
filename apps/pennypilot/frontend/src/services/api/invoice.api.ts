import { apiClient } from './client';
import type { Invoice, InvoicesResponse, InvoiceItem, Transaction } from '@/types';

export interface CreateInvoiceItemData {
  description: string;
  quantity: number;
  unit?: string;
  unit_price: number;
}

export interface CreateInvoiceData {
  client_id: string;
  invoice_date: string;
  due_date: string;
  tax_rate?: number;
  title?: string;
  notes?: string;
  items: CreateInvoiceItemData[];
}

export interface UpdateInvoiceData {
  invoice_date?: string;
  due_date?: string;
  tax_rate?: number;
  title?: string;
  notes?: string;
  items?: (CreateInvoiceItemData & { id?: string })[];
}

export interface MarkPaidData {
  paid_date: string;
  create_income?: boolean;
}

export interface UploadHistoricalData {
  client_id: string;
  invoice_number: string;
  invoice_date: string;
  due_date: string;
  paid_date?: string;
  total: number;
  title?: string;
  notes?: string;
  file: File;
}

export interface InvoiceFilters {
  status?: string;
  client_id?: string;
  from_date?: string;
  to_date?: string;
}

export interface InvoiceNumberPreview {
  invoice_number: string;
  sequence_number: number;
}

export interface TransactionMatch {
  transaction: Transaction;
  score: number;
  reasons: string[];
}

export interface FindMatchesResponse {
  data: TransactionMatch[];
  invoice: Invoice;
}

export interface AutoMatchResult {
  invoice_id: string;
  invoice_number: string;
  transaction_id: string;
  score: number;
}

export interface AutoMatchAllResponse {
  matched: AutoMatchResult[];
  unmatched_count: number;
  message: string;
}

export interface MatchStats {
  total_invoices: number;
  matched: number;
  auto_matched: number;
  manual_matched: number;
  unmatched_pending: number;
}

export const invoiceApi = {
  async list(filters?: InvoiceFilters): Promise<InvoicesResponse> {
    return apiClient.getRaw<InvoicesResponse>('/invoices', filters as Record<string, unknown>);
  },

  async get(id: string): Promise<{ data: Invoice }> {
    return apiClient.getRaw<{ data: Invoice }>(`/invoices/${id}`);
  },

  async create(data: CreateInvoiceData): Promise<Invoice> {
    return apiClient.post<Invoice>('/invoices', data);
  },

  async update(id: string, data: UpdateInvoiceData): Promise<Invoice> {
    return apiClient.put<Invoice>(`/invoices/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/invoices/${id}`);
  },

  async markSent(id: string): Promise<Invoice> {
    return apiClient.put<Invoice>(`/invoices/${id}/send`, {});
  },

  async markPaid(id: string, data: MarkPaidData): Promise<Invoice> {
    return apiClient.put<Invoice>(`/invoices/${id}/pay`, data);
  },

  async cancel(id: string): Promise<Invoice> {
    return apiClient.put<Invoice>(`/invoices/${id}/cancel`, {});
  },

  async previewNumber(clientId: string, dueDate: string): Promise<InvoiceNumberPreview> {
    return apiClient.getRaw<InvoiceNumberPreview>('/invoices/preview-number', {
      client_id: clientId,
      due_date: dueDate,
    });
  },

  async updateOverdueStatus(): Promise<{ updated_count: number }> {
    return apiClient.post<{ updated_count: number }>('/invoices/update-overdue', {});
  },

  async uploadHistorical(data: UploadHistoricalData): Promise<Invoice> {
    const formData = new FormData();
    formData.append('client_id', data.client_id);
    formData.append('invoice_number', data.invoice_number);
    formData.append('invoice_date', data.invoice_date);
    formData.append('due_date', data.due_date);
    if (data.paid_date) formData.append('paid_date', data.paid_date);
    formData.append('total', data.total.toString());
    if (data.title) formData.append('title', data.title);
    if (data.notes) formData.append('notes', data.notes);
    formData.append('file', data.file);
    if (data.transaction_id) formData.append('transaction_id', data.transaction_id);

    return apiClient.postFormData<Invoice>('/invoices/upload-historical', formData);
  },

  getDownloadUrl(id: string): string {
    return `${apiClient.getBaseUrl()}/invoices/${id}/download`;
  },

  // Transaction matching
  async findMatches(id: string): Promise<FindMatchesResponse> {
    return apiClient.getRaw<FindMatchesResponse>(`/invoices/${id}/find-matches`);
  },

  async match(id: string, transactionId: string): Promise<Invoice> {
    return apiClient.post<Invoice>(`/invoices/${id}/match`, { transaction_id: transactionId });
  },

  async unmatch(id: string): Promise<Invoice> {
    return apiClient.post<Invoice>(`/invoices/${id}/unmatch`, {});
  },

  async autoMatchAll(minConfidence = 70): Promise<AutoMatchAllResponse> {
    return apiClient.postRaw<AutoMatchAllResponse>('/invoices/auto-match-all', {
      min_confidence: minConfidence,
    });
  },

  async unmatchAll(): Promise<{ unmatched_count: number; message: string }> {
    return apiClient.post<{ unmatched_count: number; message: string }>('/invoices/unmatch-all', {});
  },

  async getMatchStats(): Promise<MatchStats> {
    return apiClient.get<MatchStats>('/invoices/match-stats');
  },

  async bulkDeleteBefore(beforeDate: string): Promise<{ deleted_count: number; message: string }> {
    return apiClient.postRaw<{ deleted_count: number; message: string }>('/invoices/bulk-delete-before', {
      before_date: beforeDate,
    });
  },
};

// Staged Invoice types (from n8n/Gemini pipeline)
export interface StagedInvoice {
  id: string;
  user_id: string;
  status: 'pending' | 'approved' | 'rejected';
  extracted_data: {
    invoice_number?: string;
    invoice_date?: string;
    due_date?: string;
    total?: number;
    subtotal?: number;
    tax_amount?: number;
    vendor_name?: string;
    line_items?: Array<{
      description: string;
      quantity: number;
      unit_price: number;
      amount: number;
    }>;
  };
  matched_client?: {
    id: string;
    name: string;
    confidence: number;
  };
  original_filename?: string;
  created_at: string;
}

export interface StagedInvoicesResponse {
  data: StagedInvoice[];
  count: number;
}

export interface ApproveStagedData {
  client_id: string;
}

// Invoice Ingestion API (n8n/Gemini pipeline)
export const invoiceIngestionApi = {
  async getStaged(): Promise<StagedInvoicesResponse> {
    return apiClient.getRaw<StagedInvoicesResponse>('/invoice-ingestion/staged');
  },

  async approveStaged(id: string, data: ApproveStagedData): Promise<Invoice> {
    return apiClient.post<Invoice>(`/invoice-ingestion/staged/${id}/approve`, data);
  },

  async rejectStaged(id: string): Promise<void> {
    await apiClient.post(`/invoice-ingestion/staged/${id}/reject`, {});
  },
};
