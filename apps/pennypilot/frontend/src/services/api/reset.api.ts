import { apiClient } from './client';

/**
 * Reset API Service
 *
 * Provides atomic server-side data wipe functionality.
 * The frontend should:
 * 1. Call execute()
 * 2. Wait for success response
 * 3. Clear all local storage
 * 4. Redirect to /wizard
 */

export interface ResetPreview {
  counts: {
    transactions: number;
    invoices: number;
    clients: number;
    income_sources: number;
    fixed_expenses: number;
    budget_periods: number;
    transaction_rules: number;
  };
  total_transactions_value: string;
}

export interface ResetResult {
  success: boolean;
  message: string;
  deleted: Record<string, number>;
  user: {
    id: string;
    email: string;
    onboarding_completed: boolean;
  };
}

/**
 * Preview what data will be deleted.
 * Useful for showing confirmation dialog with counts.
 */
export async function getResetPreview(): Promise<ResetPreview> {
  const response = await apiClient.get<{ success: boolean; data: ResetPreview }>(
    '/reset/preview'
  );
  return response.data.data;
}

/**
 * Execute a complete user data reset.
 *
 * This is an atomic operation - either all data is deleted or none.
 * The server returns 200 OK only AFTER the DB transaction is committed.
 *
 * @returns Promise<ResetResult> - Contains deleted counts and updated user state
 * @throws Error if the reset fails
 */
export async function executeReset(): Promise<ResetResult> {
  const response = await apiClient.post<ResetResult>('/reset/execute');
  return response.data;
}
