import { apiClient } from './client';
import type {
  IncomeSource,
  IncomeSourcesResponse,
  IncomeSourceType,
  IncomeFrequency,
  MonthlyProvisionCalculation,
  AnnualTaxCalculation,
} from '@/types';

export interface CreateIncomeSourceData {
  name: string;
  type: IncomeSourceType;
  gross_amount: number;
  net_amount?: number;
  tax_amount?: number;
  frequency: IncomeFrequency;
  is_active?: boolean;
  notes?: string;
}

export interface IncomeSummaryResponse {
  monthly_gross: number;
  annual_gross: number;
  tax_estimate: AnnualTaxCalculation;
  monthly_provision: MonthlyProvisionCalculation;
  net_monthly: number;
}

export const incomeApi = {
  async list(): Promise<IncomeSourcesResponse> {
    return apiClient.getRaw<IncomeSourcesResponse>('/income-sources');
  },

  async get(id: string): Promise<IncomeSource> {
    return apiClient.get<IncomeSource>(`/income-sources/${id}`);
  },

  async create(data: CreateIncomeSourceData): Promise<IncomeSource> {
    return apiClient.post<IncomeSource>('/income-sources', data);
  },

  async update(id: string, data: Partial<CreateIncomeSourceData>): Promise<IncomeSource> {
    return apiClient.put<IncomeSource>(`/income-sources/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/income-sources/${id}`);
  },

  async getSummary(): Promise<IncomeSummaryResponse> {
    return apiClient.get<IncomeSummaryResponse>('/income-sources/summary');
  },
};
