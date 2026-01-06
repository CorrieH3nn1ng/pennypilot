import { apiClient } from './client';
import type { Transaction, Category, BudgetBucket } from '@/types';

export interface TransactionRule {
  id: string;
  user_id: string;
  pattern: string;
  match_type: 'contains' | 'starts_with' | 'exact';
  category_id: string | null;
  category?: Category;
  bucket: BudgetBucket | null;
  is_active: boolean;
  hit_count: number;
  priority: number;
  created_at: string;
  updated_at: string;
}

export interface PatternGroup {
  pattern: string;
  transactions: Transaction[];
  total_amount: number;
  count: number;
  sample_description: string;
  category: Category | null;
}

export interface UnassignedResponse {
  total_unassigned: number;
  patterns: PatternGroup[];
  transactions: Transaction[];
}

export interface AssignBucketRequest {
  pattern: string;
  bucket: BudgetBucket;
  category_id?: string | null;
  transaction_ids?: string[];
  create_budget_item?: boolean;
  planned_amount?: number;
}

export interface AssignBucketResponse {
  rule: TransactionRule;
  updated_count: number;
  budget_item: unknown | null;
}

export interface DiscoveryStats {
  unassigned_count: number;
  rules_count: number;
  needs_attention: boolean;
}

export const discoveryApi = {
  /**
   * Get transactions that need bucket assignment
   */
  async getUnassigned(): Promise<UnassignedResponse> {
    return apiClient.get<UnassignedResponse>('/discovery/unassigned');
  },

  /**
   * Assign a bucket to a pattern (creates rule + updates transactions)
   */
  async assignBucket(data: AssignBucketRequest): Promise<AssignBucketResponse> {
    return apiClient.post<AssignBucketResponse>('/discovery/assign-bucket', data);
  },

  /**
   * Get all user's transaction rules
   */
  async getRules(): Promise<TransactionRule[]> {
    return apiClient.get<TransactionRule[]>('/discovery/rules');
  },

  /**
   * Delete a transaction rule
   */
  async deleteRule(ruleId: string): Promise<void> {
    return apiClient.delete(`/discovery/rules/${ruleId}`);
  },

  /**
   * Apply all rules to uncategorized transactions
   */
  async applyRules(): Promise<{ applied_count: number }> {
    return apiClient.post<{ applied_count: number }>('/discovery/apply-rules');
  },

  /**
   * Get discovery stats for dashboard
   */
  async getStats(): Promise<DiscoveryStats> {
    return apiClient.get<DiscoveryStats>('/discovery/stats');
  },
};
