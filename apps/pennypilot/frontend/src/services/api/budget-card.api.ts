import { apiClient } from './client';
import type {
  BudgetCard,
  BudgetCardItem,
  BudgetCardDetails,
  SuccessGrade,
  BlueprintItemType,
  BudgetBucket,
  SurplusAction,
} from '@/types';

export interface GenerateCardParams {
  year: number;
  month: number;
}

export interface AddItemData {
  name: string;
  expected_amount: number;
  item_type: BlueprintItemType;
  bucket?: BudgetBucket;
  expected_day?: number | null;
  category_id?: string | null;
  match_pattern?: string | null;
  notes?: string | null;
}

export interface UpdateItemData {
  name?: string;
  expected_amount?: number;
  expected_day?: number | null;
  bucket?: BudgetBucket;
  match_pattern?: string | null;
  notes?: string | null;
}

export interface FinalizeData {
  surplus_action: SurplusAction;
}

export interface CardListItem {
  id: string;
  year: number;
  month: number;
  period_label: string;
  status: string;
  success_grade: number | null;
  letter_grade: string | null;
  total_expected_income: number;
  total_expected_expense: number;
  total_matched: number;
  total_leaked: number;
  surplus_amount: number | null;
  is_current_month: boolean;
  finalized_at: string | null;
}

export interface GenerateResult {
  id: string;
  period_label: string;
  status: string;
  item_count: number;
  total_expected_income: number;
  total_expected_expense: number;
  rollover_from_previous: number;
}

export interface FinalizeResult {
  card_id: string;
  status: string;
  finalized_at: string;
  grade: SuccessGrade;
  stats: Record<string, unknown>;
  missing_count: number;
  surplus_action: SurplusAction;
}

export const budgetCardApi = {
  /**
   * List all budget cards for the user
   */
  async list(): Promise<CardListItem[]> {
    const response = await apiClient.get<{ success: boolean; data: CardListItem[] }>('/budget-cards');
    return response.data;
  },

  /**
   * Get current month's budget card (or null if none exists)
   */
  async current(): Promise<BudgetCardDetails | null> {
    const response = await apiClient.get<{ success: boolean; data: BudgetCardDetails | null }>('/budget-cards/current');
    return response.data;
  },

  /**
   * Get a specific budget card with full details
   */
  async get(id: string): Promise<BudgetCardDetails> {
    const response = await apiClient.get<{ success: boolean; data: BudgetCardDetails }>(`/budget-cards/${id}`);
    return response.data;
  },

  /**
   * Generate a new budget card from blueprints
   */
  async generate(params: GenerateCardParams): Promise<GenerateResult> {
    const response = await apiClient.post<{ success: boolean; data: GenerateResult }>('/budget-cards/generate', params);
    return response.data;
  },

  /**
   * Renew card for next month
   */
  async renew(): Promise<GenerateResult> {
    const response = await apiClient.post<{ success: boolean; data: GenerateResult }>('/budget-cards/renew');
    return response.data;
  },

  /**
   * Add a one-off item to a budget card
   */
  async addItem(cardId: string, data: AddItemData): Promise<BudgetCardItem> {
    const response = await apiClient.post<{ success: boolean; data: BudgetCardItem }>(
      `/budget-cards/${cardId}/items`,
      data
    );
    return response.data;
  },

  /**
   * Update a budget card item
   */
  async updateItem(cardId: string, itemId: string, data: UpdateItemData): Promise<BudgetCardItem> {
    const response = await apiClient.put<{ success: boolean; data: BudgetCardItem }>(
      `/budget-cards/${cardId}/items/${itemId}`,
      data
    );
    return response.data;
  },

  /**
   * Remove a one-off item from a budget card
   */
  async removeItem(cardId: string, itemId: string): Promise<void> {
    await apiClient.delete(`/budget-cards/${cardId}/items/${itemId}`);
  },

  /**
   * Activate a draft budget card
   */
  async activate(id: string): Promise<{ id: string; status: string }> {
    const response = await apiClient.post<{ success: boolean; data: { id: string; status: string } }>(
      `/budget-cards/${id}/activate`
    );
    return response.data;
  },

  /**
   * Finalize a budget card (month-end CFO review)
   */
  async finalize(id: string, data: FinalizeData): Promise<FinalizeResult> {
    const response = await apiClient.post<{ success: boolean; data: FinalizeResult }>(
      `/budget-cards/${id}/finalize`,
      data
    );
    return response.data;
  },

  /**
   * Get grade breakdown for a budget card
   */
  async getGrade(id: string): Promise<SuccessGrade> {
    const response = await apiClient.get<{ success: boolean; data: SuccessGrade }>(`/budget-cards/${id}/grade`);
    return response.data;
  },
};
