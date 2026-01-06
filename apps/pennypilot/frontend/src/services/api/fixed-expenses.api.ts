import { apiClient } from './client';
import type { FixedExpense, FixedExpensesResponse, BudgetBucket } from '@/types';

export interface CreateFixedExpenseData {
  category_id?: string;
  name: string;
  amount: number;
  due_day?: number;
  bucket: BudgetBucket;
  is_active?: boolean;
  notes?: string;
}

export const fixedExpensesApi = {
  async list(): Promise<FixedExpensesResponse> {
    return apiClient.getRaw<FixedExpensesResponse>('/fixed-expenses');
  },

  async get(id: string): Promise<FixedExpense> {
    return apiClient.get<FixedExpense>(`/fixed-expenses/${id}`);
  },

  async create(data: CreateFixedExpenseData): Promise<FixedExpense> {
    return apiClient.post<FixedExpense>('/fixed-expenses', data);
  },

  async update(id: string, data: Partial<CreateFixedExpenseData>): Promise<FixedExpense> {
    return apiClient.put<FixedExpense>(`/fixed-expenses/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/fixed-expenses/${id}`);
  },

  async getDueSoon(): Promise<{ data: FixedExpense[]; count: number }> {
    return apiClient.getRaw<{ data: FixedExpense[]; count: number }>('/fixed-expenses/due-soon');
  },
};
