import { apiClient } from './client';
import type {
  TaxSetting,
  TaxProvision,
  TaxBracket,
  TaxYearSummary,
  AnnualTaxCalculation,
  MonthlyProvisionCalculation,
  ProvisionalPaymentsInfo,
  EmploymentType,
  BudgetBucket,
} from '@/types';

export interface UpdateTaxSettingsData {
  employment_type?: EmploymentType;
  tax_number?: string;
  vat_registered?: boolean;
  vat_number?: string;
  estimated_annual_income?: number;
  provisional_tax_enabled?: boolean;
}

export interface CreateProvisionData {
  year: number;
  month: number;
  income_amount: number;
  provision_amount: number;
  provision_rate?: number;
  notes?: string;
}

export interface TaxCalculateRequest {
  amount: number;
  type: 'annual' | 'monthly';
  age?: number;
}

export interface AnnualCalculationResponse extends AnnualTaxCalculation {
  provisional_payments: ProvisionalPaymentsInfo;
}

export interface TaxBracketsResponse {
  data: TaxBracket[];
  tax_year: string;
  effective_date: string;
}

/**
 * Effective Rate Response - SINGLE SOURCE OF TRUTH for tax calculations
 * This must be used by both Wizard and Dashboard to ensure consistency.
 */
export interface EffectiveRateResponse {
  monthly_amount: string;
  persona: 'self_employed' | 'salaried';
  effective_rate: string;
  tax_set_aside: string;
  net_after_tax: string;
  budget_preview: {
    needs: string;
    wants: string;
    savings: string;
  };
  calculation_method: string;
  description: string | null;
  currency_symbol: string; // From CountryConfig (e.g., 'R', '$', '£')
  annual_rebate?: string;  // Primary rebate amount
}

export interface EffectiveRateRequest {
  amount: number;
  persona?: 'self_employed' | 'salaried';
  age?: number;
}

export const taxApi = {
  async getSettings(): Promise<TaxSetting> {
    return apiClient.get<TaxSetting>('/tax/settings');
  },

  async updateSettings(data: UpdateTaxSettingsData): Promise<TaxSetting> {
    return apiClient.put<TaxSetting>('/tax/settings', data);
  },

  async getProvisions(taxYear?: number): Promise<TaxProvision[]> {
    const params = taxYear ? { tax_year: taxYear } : undefined;
    return apiClient.get<TaxProvision[]>('/tax/provisions', params);
  },

  async getCurrentSummary(): Promise<TaxYearSummary> {
    return apiClient.get<TaxYearSummary>('/tax/provisions/current');
  },

  async createProvision(data: CreateProvisionData): Promise<TaxProvision> {
    return apiClient.post<TaxProvision>('/tax/provisions', data);
  },

  async markProvisionPaid(
    id: string,
    data: { payment_date: string; notes?: string }
  ): Promise<TaxProvision> {
    return apiClient.put<TaxProvision>(`/tax/provisions/${id}/paid`, data);
  },

  async calculate(
    request: TaxCalculateRequest
  ): Promise<AnnualCalculationResponse | MonthlyProvisionCalculation> {
    return apiClient.post<AnnualCalculationResponse | MonthlyProvisionCalculation>(
      '/tax/calculate',
      request
    );
  },

  async getBrackets(): Promise<TaxBracketsResponse> {
    return apiClient.getRaw<TaxBracketsResponse>('/tax/brackets');
  },

  async suggestBucket(categoryName: string): Promise<{ category_name: string; suggested_bucket: BudgetBucket }> {
    return apiClient.post<{ category_name: string; suggested_bucket: BudgetBucket }>(
      '/tax/suggest-bucket',
      { category_name: categoryName }
    );
  },

  /**
   * Get effective tax rate for a given amount and persona.
   *
   * THIS IS THE SINGLE SOURCE OF TRUTH for all tax calculations.
   * Both Wizard and Dashboard must use this method.
   *
   * Benchmark: R74,900 + self_employed → 39.1% → R29,286.90 set-aside
   */
  async getEffectiveRate(request: EffectiveRateRequest): Promise<EffectiveRateResponse> {
    const response = await apiClient.getRaw<{ success: boolean; data: EffectiveRateResponse }>(
      '/tax/effective-rate',
      {
        amount: request.amount,
        persona: request.persona || 'self_employed',
        age: request.age,
      }
    );
    return response.data;
  },

  /**
   * Verify the nucleus benchmark calculation.
   * Used for testing to ensure tax calculations are correct.
   */
  async verifyBenchmark(): Promise<{
    input: number;
    result: EffectiveRateResponse;
    benchmark: { expected_rate: string; expected_set_aside: string; expected_net: string };
    verified: boolean;
  }> {
    const response = await apiClient.getRaw<{ success: boolean; data: unknown }>('/tax/verify-benchmark');
    return response.data as {
      input: number;
      result: EffectiveRateResponse;
      benchmark: { expected_rate: string; expected_set_aside: string; expected_net: string };
      verified: boolean;
    };
  },
};
