import { apiClient } from './client';
import type {
  AuditResult,
  AuditStats,
  TransactionAudit,
  BudgetCardItem,
  MatchCandidate,
  BudgetBucket,
} from '@/types';

export interface MatchData {
  transaction_id: string;
  budget_card_item_id: string;
}

export interface UnmatchData {
  budget_card_item_id: string;
}

export interface SilenceData {
  transaction_audit_id: string;
}

export interface LeakToBlueprintData {
  transaction_audit_id: string;
  bucket: BudgetBucket;
  due_day?: number | null;
  category_id?: string | null;
}

export interface PendingTransaction {
  id: string;
  transaction_date: string;
  description: string;
  amount: number;
  is_income: boolean;
  match_candidates: MatchCandidate[];
}

export interface LeakItem {
  audit: TransactionAudit;
  transaction: {
    id: string;
    transaction_date: string;
    description: string;
    amount: number;
  };
  suggested_bucket: BudgetBucket;
}

export interface WindfallItem {
  audit: TransactionAudit;
  transaction: {
    id: string;
    transaction_date: string;
    description: string;
    amount: number;
  };
}

export interface SuggestionItem {
  type: 'add_to_blueprint' | 'adjust_amount' | 'adjust_date';
  description: string;
  transaction_audit_id?: string;
  budget_card_item_id?: string;
  suggested_values?: Record<string, unknown>;
}

// Blueprint Reconciliation Types
export interface ReconcileRequest {
  start_date: string;
  end_date: string;
}

export interface ReconcileResult {
  matched: number;
  unmatched: number;
  leaks: number;
  zombies: number;
}

export interface BlueprintVariance {
  id: string;
  name: string;
  item_type: 'income' | 'expense';
  bucket: 'needs' | 'wants' | 'savings';
  nature: 'business' | 'private';
  expected: number;
  original_expected: number | null;  // Original amount before pause (null if not paused)
  actual: number;
  variance: number;
  variance_label: string | null;  // e.g., "Budgeted Zero (Contractual Holiday)"
  status: 'active' | 'paused' | 'cancelled';
  is_paused: boolean;
  is_contractual_holiday: boolean;  // Explicit flag for holiday classification
  is_cancelled: boolean;
  pause_reason: string | null;
  pause_end_date: string | null;
  category: string | null;
}

export interface PauseBlueprintRequest {
  reason?: string;
  start_date?: string;
  end_date?: string;
}

export interface PausedBlueprint {
  id: string;
  name: string;
  status: 'paused';
  pause_start_date: string | null;
  pause_end_date: string | null;
  pause_reason: string | null;
}

export interface ResumingItem {
  id: string;
  name: string;
  amount: number;
  item_type: 'income' | 'expense';
}

export interface MonthProjection {
  year: number;
  month: number;
  period: string;
  income: number;
  expenses: number;
  tax_reserve: number;
  net_spendable: number;
  resuming_items: ResumingItem[];
  resuming_count: number;
  resuming_total: number;
}

export interface PausedBlueprintInfo {
  id: string;
  name: string;
  expected_amount: number;
  pause_reason: string | null;
  pause_end_date: string | null;
  resumes_in: string;
}

export interface SpendableProjection {
  projection: MonthProjection[];
  paused_blueprints: PausedBlueprintInfo[];
  paused_count: number;
}

// Resumption Alerts
export interface ResumptionAlert {
  id: string;
  name: string;
  expected_amount: number;
  item_type: 'income' | 'expense';
  bucket: 'needs' | 'wants' | 'savings';
  nature: 'business' | 'private';
  category: string | null;
  pause_reason: string | null;
  pause_end_date: string;
  days_until_resume: number;
  resumes_on: string;
}

export interface ResumptionAlertsResponse {
  alerts: ResumptionAlert[];
  count: number;
  total_amount: number;
  within_days: number;
}

// Next Month Impact
export interface NextMonthImpact {
  current_month: {
    period: string;
    income: number;
    expenses: number;
    tax_reserve: number;
    net_spendable: number;
  };
  next_month: {
    period: string;
    income: number;
    expenses: number;
    tax_reserve: number;
    net_spendable: number;
  };
  impact: {
    spendable_change: number;
    resuming_expenses: number;
    resuming_count: number;
    resuming_items: ResumingItem[];
  };
  alert: string | null;
}

// Variance Breakdown (updated to include holiday info)
export interface VarianceBreakdown {
  raw_variance: number;              // Before holiday adjustment
  holiday_surplus: number;           // From paused payments
  adjusted_variance: number;         // Final variance (raw + holiday)
  true_savings: number;              // What you saved on your own
  contractual_holiday_items: {
    id: string;
    name: string;
    amount: number;
    pause_reason: string | null;
    pause_end_date: string | null;
  }[];
}

// Data Range for smart month defaulting
export interface MonthWithData {
  year: number;
  month: number;
  count: number;
  label: string;
}

export interface DataRangeInfo {
  has_data: boolean;
  min_date: string | null;
  max_date: string | null;
  most_recent_month: number | null;
  most_recent_year: number | null;
  total_transactions: number;
  months_with_data: MonthWithData[];
}

// Tax Year Summary
export interface TaxMonthBreakdown {
  year: number;
  month: number;
  label: string;
  income: number;
  expenses: number;
  net: number;
  transaction_count: number;
}

export interface TaxYearSummary {
  tax_year: string;
  period: {
    start: string;
    end: string;
    label: string;
  };
  summary: {
    total_income: number;
    total_expenses: number;
    business_expenses: number;
    net: number;
  };
  tax: {
    taxable_income: number;
    estimated_tax: number;
    tax_rate: string;
  };
  provisional_payments: {
    period: string;
    due_date: string;
    amount: number;
  }[];
  monthly_breakdown: TaxMonthBreakdown[];
}

export interface UnmappedLeak {
  description: string;
  amount: number;
  count: number;
}

export interface GhostFee {
  id: string;
  description: string;
  amount: number;
  date: string;
}

export interface ZombieSubscription {
  id: string;
  name: string;
  cancelled_at: string | null;
  expected_amount: number;
  recent_charges: number;
  transaction_count: number;
  last_charge_date: string;
}

export interface VarianceReport {
  period: string;
  blueprint_expected: {
    income: number;
    expenses: number;
    tax_reserve: number;
    net_spendable: number;
  };
  actual: {
    income: number;
    expenses: number;
    net: number;
  };
  variance: number;                           // Adjusted variance (includes holiday savings)
  variance_status: 'surplus' | 'shortfall' | 'neutral';  // For easy UI styling
  holiday_surplus: number;                    // Money saved from paused payments
  variance_breakdown: VarianceBreakdown;
  blueprint_details: BlueprintVariance[];
  unmapped_leaks: UnmappedLeak[];
  ghost_fees: GhostFee[];
  zombies: ZombieSubscription[];
  summary: {
    total_transactions: number;
    matched: number;
    unmatched: number;
    leak_count: number;
    holiday_count: number;
  };
}

export interface UnmatchedTransaction {
  id: string;
  transaction_date: string;
  description: string;
  amount: number;
  balance: number | null;  // Running balance from Excel statement
  is_income: boolean;
  category: string | null;
}

export const auditApi = {
  /**
   * Run full audit on a budget card
   */
  async run(budgetCardId: string): Promise<AuditResult> {
    const response = await apiClient.post<{ success: boolean; data: AuditResult }>('/audit/run', {
      budget_card_id: budgetCardId,
    });
    return response.data;
  },

  /**
   * Run audit on current month's budget card
   */
  async runCurrent(): Promise<AuditResult> {
    const response = await apiClient.post<{ success: boolean; data: AuditResult }>('/audit/run-current');
    return response.data;
  },

  /**
   * Manually match a transaction to a budget card item
   */
  async match(data: MatchData): Promise<BudgetCardItem> {
    const response = await apiClient.post<{ success: boolean; data: BudgetCardItem }>('/audit/match', data);
    return response.data;
  },

  /**
   * Unmatch a transaction from a budget card item
   */
  async unmatch(data: UnmatchData): Promise<BudgetCardItem> {
    const response = await apiClient.post<{ success: boolean; data: BudgetCardItem }>('/audit/unmatch', data);
    return response.data;
  },

  /**
   * Silence a leak (acknowledge but hide from grade)
   */
  async silence(data: SilenceData): Promise<TransactionAudit> {
    const response = await apiClient.post<{ success: boolean; data: TransactionAudit }>('/audit/silence', data);
    return response.data;
  },

  /**
   * Unsilence a previously silenced leak
   */
  async unsilence(data: SilenceData): Promise<TransactionAudit> {
    const response = await apiClient.post<{ success: boolean; data: TransactionAudit }>('/audit/unsilence', data);
    return response.data;
  },

  /**
   * Convert a leak to a blueprint (make recurring)
   */
  async leakToBlueprint(data: LeakToBlueprintData): Promise<{ blueprint_id: string; silenced: boolean }> {
    const response = await apiClient.post<{ success: boolean; data: { blueprint_id: string; silenced: boolean } }>(
      '/audit/leak-to-blueprint',
      data
    );
    return response.data;
  },

  /**
   * Get pending (unaudited) transactions for a budget card period
   */
  async getPending(budgetCardId: string): Promise<PendingTransaction[]> {
    const response = await apiClient.get<{ success: boolean; data: PendingTransaction[] }>(
      `/audit/pending?budget_card_id=${budgetCardId}`
    );
    return response.data;
  },

  /**
   * Get leaks for a budget card
   */
  async getLeaks(budgetCardId: string): Promise<LeakItem[]> {
    const response = await apiClient.get<{ success: boolean; data: LeakItem[] }>(`/audit/leaks/${budgetCardId}`);
    return response.data;
  },

  /**
   * Get windfalls for a budget card
   */
  async getWindfalls(budgetCardId: string): Promise<WindfallItem[]> {
    const response = await apiClient.get<{ success: boolean; data: WindfallItem[] }>(`/audit/windfalls/${budgetCardId}`);
    return response.data;
  },

  /**
   * Get AI suggestions for a budget card
   */
  async getSuggestions(budgetCardId: string): Promise<SuggestionItem[]> {
    const response = await apiClient.get<{ success: boolean; data: SuggestionItem[] }>(
      `/audit/suggestions/${budgetCardId}`
    );
    return response.data;
  },

  /**
   * Get audit statistics for a budget card
   */
  async getStats(budgetCardId: string): Promise<AuditStats> {
    const response = await apiClient.get<{ success: boolean; data: AuditStats }>(`/audit/stats/${budgetCardId}`);
    return response.data;
  },

  // =============================================
  // BLUEPRINT RECONCILIATION
  // =============================================

  /**
   * Run Blueprint-to-Transaction reconciliation for a date range
   */
  async reconcile(data: ReconcileRequest): Promise<ReconcileResult> {
    // apiClient.post already unwraps response.data.data
    return apiClient.post<ReconcileResult>('/audit/reconcile', data);
  },

  /**
   * Get variance report for a specific month
   */
  async getVariance(year: number, month: number): Promise<VarianceReport> {
    // apiClient.get already unwraps response.data.data, so we get VarianceReport directly
    return apiClient.get<VarianceReport>(`/audit/variance/${year}/${month}`);
  },

  /**
   * Get zombie subscriptions (cancelled blueprints still charging)
   */
  async getZombies(): Promise<{ zombies: ZombieSubscription[]; count: number; total_charges: number }> {
    return apiClient.get<{ zombies: ZombieSubscription[]; count: number; total_charges: number }>('/audit/zombies');
  },

  /**
   * Get unmatched transactions for a date range
   */
  async getUnmatched(startDate?: string, endDate?: string): Promise<{ transactions: UnmatchedTransaction[]; count: number; period: string }> {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    return apiClient.get<{ transactions: UnmatchedTransaction[]; count: number; period: string }>(`/audit/unmatched?${params.toString()}`);
  },

  /**
   * Cancel a blueprint (mark as cancelled for zombie tracking)
   */
  async cancelBlueprint(blueprintId: string): Promise<{ id: string; name: string; cancelled_at: string }> {
    return apiClient.post<{ id: string; name: string; cancelled_at: string }>(`/audit/blueprints/${blueprintId}/cancel`);
  },

  /**
   * Manually match a transaction to a blueprint
   */
  async matchBlueprint(transactionId: string, blueprintId: string): Promise<{ transaction_id: string; blueprint_id: string; blueprint_name: string }> {
    return apiClient.post<{ transaction_id: string; blueprint_id: string; blueprint_name: string }>('/audit/match-blueprint', {
      transaction_id: transactionId,
      blueprint_id: blueprintId,
    });
  },

  /**
   * Unmatch a transaction from its blueprint
   */
  async unmatchBlueprint(transactionId: string): Promise<void> {
    await apiClient.post('/audit/unmatch-blueprint', {
      transaction_id: transactionId,
    });
  },

  // =============================================
  // PAYMENT HOLIDAYS
  // =============================================

  /**
   * Pause a blueprint (payment holiday)
   */
  async pauseBlueprint(blueprintId: string, data?: PauseBlueprintRequest): Promise<PausedBlueprint> {
    return apiClient.post<PausedBlueprint>(`/audit/blueprints/${blueprintId}/pause`, data || {});
  },

  /**
   * Resume a blueprint from payment holiday
   */
  async resumeBlueprint(blueprintId: string): Promise<{ id: string; name: string; status: 'active' }> {
    return apiClient.post<{ id: string; name: string; status: 'active' }>(`/audit/blueprints/${blueprintId}/resume`);
  },

  /**
   * Get spendable cash projection for upcoming months
   * Shows when paused blueprints will resume
   */
  async getSpendableProjection(months = 6): Promise<SpendableProjection> {
    return apiClient.get<SpendableProjection>(`/audit/spendable-projection?months=${months}`);
  },

  /**
   * Get blueprints resuming within N days (default 30)
   * For resumption alerts on dashboard
   */
  async getResumptionAlerts(days = 30): Promise<ResumptionAlertsResponse> {
    return apiClient.get<ResumptionAlertsResponse>(`/audit/resumption-alerts?days=${days}`);
  },

  /**
   * Get next month impact preview for dashboard
   * Shows how spendable cash will change when paused items resume
   */
  async getNextMonthImpact(): Promise<NextMonthImpact> {
    return apiClient.get<NextMonthImpact>('/audit/next-month-impact');
  },

  /**
   * Get data range info for smart month defaulting
   * Returns date range and most recent month with data
   */
  async getDataRange(): Promise<DataRangeInfo> {
    // apiClient.get already unwraps response.data.data
    return apiClient.get<DataRangeInfo>('/audit/data-range');
  },

  /**
   * Get tax year summary for provisional tax
   * South African tax year runs March to February
   */
  async getTaxYearSummary(year: number): Promise<TaxYearSummary> {
    // apiClient.get already unwraps response.data.data
    return apiClient.get<TaxYearSummary>(`/audit/tax-year/${year}`);
  },

  /**
   * Assign a transaction to a blueprint by name
   * Creates the blueprint if it doesn't exist
   */
  async assignToBlueprint(transactionId: string, blueprintName: string): Promise<{
    transaction_id: string;
    blueprint_id: string;
    blueprint_name: string;
    anchor_pattern: string;
    auto_matched: number;
  }> {
    return apiClient.post<{
      transaction_id: string;
      blueprint_id: string;
      blueprint_name: string;
      anchor_pattern: string;
      auto_matched: number;
    }>('/audit/assign-blueprint', {
      transaction_id: transactionId,
      blueprint_name: blueprintName,
    });
  },
};
