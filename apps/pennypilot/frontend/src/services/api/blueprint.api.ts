import { apiClient } from './client';
import type {
  Blueprint,
  BlueprintsResponse,
  BlueprintPreview,
  BlueprintItemType,
  BlueprintMatchType,
  BlueprintFrequency,
  BlueprintNature,
  BudgetBucket,
  BulkBlueprintItem,
  BulkBlueprintResult,
} from '@/types';

export interface CreateBlueprintData {
  name: string;
  expected_amount: number;
  item_type: BlueprintItemType;
  bucket?: BudgetBucket;
  nature?: BlueprintNature;
  due_day?: number | null;
  due_day_tolerance?: number;
  amount_tolerance?: number;
  category_id?: string | null;
  liability_id?: string | null;
  match_pattern?: string | null;
  match_type?: BlueprintMatchType;
  frequency?: BlueprintFrequency;
  priority?: number;
  notes?: string | null;
}

export interface UpdateBlueprintData {
  name?: string;
  expected_amount?: number;
  item_type?: BlueprintItemType;
  bucket?: BudgetBucket;
  nature?: BlueprintNature;
  due_day?: number | null;
  due_day_tolerance?: number;
  amount_tolerance?: number;
  category_id?: string | null;
  liability_id?: string | null;
  match_pattern?: string | null;
  match_type?: BlueprintMatchType;
  frequency?: BlueprintFrequency;
  priority?: number;
  is_active?: boolean;
  notes?: string | null;
}

export const blueprintApi = {
  /**
   * Get all blueprints with grouping and totals
   */
  async list(): Promise<BlueprintsResponse> {
    // apiClient.get already extracts response.data.data
    return apiClient.get<BlueprintsResponse>('/blueprints');
  },

  /**
   * Get a single blueprint
   */
  async get(id: string): Promise<Blueprint> {
    return apiClient.get<Blueprint>(`/blueprints/${id}`);
  },

  /**
   * Create a new blueprint
   */
  async create(data: CreateBlueprintData): Promise<Blueprint> {
    return apiClient.post<Blueprint>('/blueprints', data);
  },

  /**
   * Update a blueprint
   */
  async update(id: string, data: UpdateBlueprintData): Promise<Blueprint> {
    return apiClient.put<Blueprint>(`/blueprints/${id}`, data);
  },

  /**
   * Delete a blueprint
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/blueprints/${id}`);
  },

  /**
   * Toggle active status
   */
  async toggle(id: string): Promise<{ id: string; is_active: boolean }> {
    return apiClient.post<{ id: string; is_active: boolean }>(`/blueprints/${id}/toggle`);
  },

  /**
   * Import blueprints from existing fixed expenses
   */
  async importFromFixed(): Promise<{ imported: number; skipped: number }> {
    return apiClient.post<{ imported: number; skipped: number }>('/blueprints/import-from-fixed');
  },

  /**
   * Bulk create blueprints (for 2025 reconstruction)
   *
   * Accepts an array of items and creates them all at once.
   * Auto-generates match_pattern from name if not provided.
   * Default match_type is 'contains'.
   */
  async bulkCreate(items: BulkBlueprintItem[]): Promise<BulkBlueprintResult> {
    return apiClient.post<BulkBlueprintResult>('/blueprints/bulk', { items });
  },

  /**
   * Get preview of next month's budget card from current blueprints
   */
  async preview(): Promise<BlueprintPreview> {
    return apiClient.get<BlueprintPreview>('/blueprints/preview');
  },
};
