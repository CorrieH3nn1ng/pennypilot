import { apiClient } from './client';
import type { Transaction, PaginatedResponse, TransactionSummary, ParsedTransaction } from '@/types';

export interface TransactionListParams {
  start_date?: string;
  end_date?: string;
  category_id?: string;
  is_categorized?: boolean;
  limit?: number;
  offset?: number;
}

export const transactionsApi = {
  async list(params?: TransactionListParams): Promise<PaginatedResponse<Transaction>> {
    const response = await apiClient.get<PaginatedResponse<Transaction>>('/transactions', params);
    return response;
  },

  async get(id: string): Promise<Transaction> {
    return apiClient.get<Transaction>(`/transactions/${id}`);
  },

  async create(data: Partial<Transaction>): Promise<Transaction> {
    return apiClient.post<Transaction>('/transactions', data);
  },

  async update(id: string, data: Partial<Transaction>): Promise<Transaction> {
    return apiClient.put<Transaction>(`/transactions/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/transactions/${id}`);
  },

  async bulkCreate(transactions: ParsedTransaction[]): Promise<{
    created_count: number;
    skipped_count: number;
    created: { id: string; local_id: string | null }[];
    skipped: string[];
  }> {
    return apiClient.post('/transactions/bulk', { transactions });
  },

  async getSummary(startDate: string, endDate: string): Promise<TransactionSummary> {
    return apiClient.get<TransactionSummary>('/transactions/summary', {
      start_date: startDate,
      end_date: endDate,
    });
  },
};
