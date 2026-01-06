import { apiClient } from './client';
import type {
  BudgetPeriod,
  BudgetItem,
  BudgetResponse,
  BudgetSummary,
  BudgetMethodology,
  BudgetBucket,
  RolloverOption,
} from '@/types';

export interface CreateBudgetData {
  year: number;
  month: number;
  methodology: BudgetMethodology;
  rollover_option?: RolloverOption;
}

export interface UpdateBudgetData {
  methodology?: BudgetMethodology;
  total_income?: number;
  tax_provision?: number;
  status?: 'draft' | 'active' | 'closed';
  rollover_option?: RolloverOption;
}

export interface BudgetItemData {
  id?: string;
  category_id?: string;
  name?: string;
  planned_amount: number;
  bucket: BudgetBucket;
  is_fixed?: boolean;
}

export interface BudgetItemsResponse {
  data: BudgetItem[];
  by_bucket: {
    needs: BudgetItem[];
    wants: BudgetItem[];
    savings: BudgetItem[];
  };
}

export const budgetApi = {
  async list(year?: number): Promise<BudgetPeriod[]> {
    const params = year ? { year } : undefined;
    return apiClient.get<BudgetPeriod[]>('/budgets', params);
  },

  async getCurrent(): Promise<BudgetResponse | null> {
    try {
      const response = await apiClient.getRaw<BudgetResponse>('/budgets/current');
      // Handle case where no budget exists (data is null)
      if (!response.data) return null;
      return response;
    } catch {
      return null;
    }
  },

  async get(id: string): Promise<BudgetResponse> {
    return apiClient.getRaw<BudgetResponse>(`/budgets/${id}`);
  },

  async create(data: CreateBudgetData): Promise<BudgetPeriod> {
    return apiClient.post<BudgetPeriod>('/budgets', data);
  },

  async update(id: string, data: UpdateBudgetData): Promise<BudgetPeriod> {
    return apiClient.put<BudgetPeriod>(`/budgets/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/budgets/${id}`);
  },

  async getItems(budgetId: string): Promise<BudgetItemsResponse> {
    return apiClient.getRaw<BudgetItemsResponse>(`/budgets/${budgetId}/items`);
  },

  async updateItems(budgetId: string, items: BudgetItemData[]): Promise<BudgetItem[]> {
    return apiClient.put<BudgetItem[]>(`/budgets/${budgetId}/items`, { items });
  },

  async getSummary(budgetId: string): Promise<BudgetSummary> {
    return apiClient.getRaw<{ data: BudgetSummary }>(`/budgets/${budgetId}/summary`).then(r => r.data);
  },
};
