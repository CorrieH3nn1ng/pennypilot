import { apiClient } from './client';
import type {
  Liability,
  LiabilitiesResponse,
  LiabilityPaymentResult,
  LiabilityCelebration,
  LiabilityType,
  LiabilityStatus,
  BudgetBucket,
  Blueprint,
} from '@/types';

export interface CreateLiabilityData {
  name: string;
  liability_type: LiabilityType;
  original_balance: number;
  current_balance?: number;
  monthly_installment: number;
  interest_rate?: number;
  total_installments?: number;
  category_id?: string;
  creditor?: string;
  reference_number?: string;
  start_date?: string;
  target_payoff_date?: string;
  notes?: string;
}

export interface UpdateLiabilityData {
  name?: string;
  liability_type?: LiabilityType;
  current_balance?: number;
  monthly_installment?: number;
  interest_rate?: number;
  total_installments?: number;
  category_id?: string;
  creditor?: string;
  reference_number?: string;
  target_payoff_date?: string;
  notes?: string;
  status?: Exclude<LiabilityStatus, 'paid_off'>; // Cannot manually set to paid_off
}

export interface RecordPaymentResponse {
  liability: Liability;
  result: LiabilityPaymentResult;
  deactivated_blueprints?: number;
  celebration?: LiabilityCelebration;
}

export interface CreateBlueprintFromLiabilityData {
  bucket: BudgetBucket;
  due_day?: number;
  match_pattern?: string;
}

export const liabilityApi = {
  /**
   * List all liabilities with summary stats
   */
  async list(): Promise<LiabilitiesResponse> {
    return apiClient.getRaw<LiabilitiesResponse>('/liabilities');
  },

  /**
   * Get a single liability with full details
   */
  async get(id: string): Promise<Liability> {
    return apiClient.get<Liability>(`/liabilities/${id}`);
  },

  /**
   * Create a new liability
   */
  async create(data: CreateLiabilityData): Promise<Liability> {
    return apiClient.post<Liability>('/liabilities', data);
  },

  /**
   * Update a liability
   */
  async update(id: string, data: UpdateLiabilityData): Promise<Liability> {
    return apiClient.put<Liability>(`/liabilities/${id}`, data);
  },

  /**
   * Delete a liability
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/liabilities/${id}`);
  },

  /**
   * Record a payment against a liability
   * Returns celebration data if liability is paid off
   */
  async recordPayment(id: string, amount: number, notes?: string): Promise<RecordPaymentResponse> {
    return apiClient.postRaw<RecordPaymentResponse>(`/liabilities/${id}/payment`, {
      amount,
      notes,
    });
  },

  /**
   * Link an existing blueprint to a liability
   */
  async linkBlueprint(id: string, blueprintId: string): Promise<void> {
    await apiClient.post(`/liabilities/${id}/link-blueprint`, {
      blueprint_id: blueprintId,
    });
  },

  /**
   * Create a new blueprint from a liability
   */
  async createBlueprint(id: string, data: CreateBlueprintFromLiabilityData): Promise<Blueprint> {
    return apiClient.post<Blueprint>(`/liabilities/${id}/create-blueprint`, data);
  },

  /**
   * Get recently paid off liabilities (for celebration display)
   */
  async recentlyPaidOff(): Promise<Liability[]> {
    return apiClient.get<Liability[]>('/liabilities/recently-paid-off');
  },
};
