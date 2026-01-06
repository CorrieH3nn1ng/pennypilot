import { apiClient } from './client';
import type { Transaction } from '@/types';

// =============================================
// ACCOUNTANT INTELLIGENCE API
// Manages flagged transactions for professional review
// =============================================

export type AccountantStatus = 'pending' | 'approved_business' | 'rejected_private';

export interface FlaggedTransaction extends Transaction {
  flagged_for_accountant: boolean;
  accountant_status: AccountantStatus | null;
  accountant_question: string | null;
  accountant_reviewed_at: string | null;
  accountant_notes: string | null;
  tax_deduction_amount: number | null;
}

export interface FlaggedSummary {
  total_flagged: number;
  pending_review: number;
  approved_business: number;
  rejected_private: number;
  total_pending_amount: number;
  total_approved_deductions: number;
}

export interface FlaggedListResponse {
  transactions: FlaggedTransaction[];
  summary: FlaggedSummary;
}

export interface AccountantSummary {
  pending: {
    count: number;
    amount: number;
    potential_savings: number;
  };
  tax_year: {
    start: string;
    end: string;
    label: string;
  };
  approved: {
    count: number;
    total_deductions: number;
    tax_savings: number;
  };
}

export interface TaxImpact {
  month: string;
  gross_income: number;
  deductions: number;
  taxable_income: number;
  vat_reserve: number;
  tax_reserve: number;
  tax_savings: number;
}

export interface ApprovalResponse {
  transaction: FlaggedTransaction;
  deduction_amount: number;
  tax_impact: TaxImpact;
}

export interface FlaggedFilters {
  status?: AccountantStatus | 'all';
  from_date?: string;
  to_date?: string;
}

export const accountantApi = {
  /**
   * Get all flagged transactions for accountant review
   */
  async getFlagged(filters?: FlaggedFilters): Promise<FlaggedListResponse> {
    const response = await apiClient.getRaw<{ success: boolean; data: FlaggedListResponse }>(
      '/accountant/flagged',
      filters
    );
    return response.data;
  },

  /**
   * Get summary statistics for accountant review
   */
  async getSummary(): Promise<AccountantSummary> {
    const response = await apiClient.getRaw<{ success: boolean; data: AccountantSummary }>(
      '/accountant/summary'
    );
    return response.data;
  },

  /**
   * Flag a transaction for accountant review
   */
  async flag(transactionId: string, question?: string): Promise<FlaggedTransaction> {
    const response = await apiClient.postRaw<{ success: boolean; data: FlaggedTransaction }>(
      `/accountant/flag/${transactionId}`,
      { question }
    );
    return response.data;
  },

  /**
   * Remove flag from a transaction
   */
  async unflag(transactionId: string): Promise<FlaggedTransaction> {
    const response = await apiClient.postRaw<{ success: boolean; data: FlaggedTransaction }>(
      `/accountant/unflag/${transactionId}`
    );
    return response.data;
  },

  /**
   * Approve transaction as business expense (tax-deductible)
   * This triggers automatic tax reserve recalculation
   */
  async approve(transactionId: string, notes?: string): Promise<ApprovalResponse> {
    const response = await apiClient.postRaw<{ success: boolean; data: ApprovalResponse }>(
      `/accountant/approve/${transactionId}`,
      { notes }
    );
    return response.data;
  },

  /**
   * Reject transaction to private (not tax-deductible)
   */
  async reject(transactionId: string, notes?: string): Promise<FlaggedTransaction> {
    const response = await apiClient.postRaw<{ success: boolean; data: FlaggedTransaction }>(
      `/accountant/reject/${transactionId}`,
      { notes }
    );
    return response.data;
  },

  /**
   * Update the draft question for a flagged transaction
   */
  async updateQuestion(transactionId: string, question: string): Promise<FlaggedTransaction> {
    const response = await apiClient.putRaw<{ success: boolean; data: FlaggedTransaction }>(
      `/accountant/question/${transactionId}`,
      { question }
    );
    return response.data;
  },

  /**
   * Download PDF report of flagged transactions
   */
  async downloadReport(filters?: FlaggedFilters): Promise<Blob> {
    const params = new URLSearchParams();
    if (filters?.status) params.append('status', filters.status);
    if (filters?.from_date) params.append('from_date', filters.from_date);
    if (filters?.to_date) params.append('to_date', filters.to_date);

    const queryString = params.toString();
    const url = `/accountant/report/pdf${queryString ? `?${queryString}` : ''}`;

    return apiClient.downloadFile(url);
  },
};
