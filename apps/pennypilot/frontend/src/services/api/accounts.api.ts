import { apiClient } from './client';

export interface Account {
  id: string;
  user_id: string;
  name: string;
  account_number: string | null;
  bank_name: string;
  opening_balance: number;
  opening_balance_date: string | null;
  current_balance: number | null;
  balance_updated_at: string | null;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface AccountWithBalance {
  account: Account;
  transaction_sum: number;
  calculated_balance: number;
  opening_balance?: number;
  current_balance?: number;
}

export const accountsApi = {
  async getDefault(): Promise<AccountWithBalance> {
    return apiClient.get<AccountWithBalance>('/accounts/default');
  },

  async setBalance(currentBalance: number): Promise<AccountWithBalance> {
    return apiClient.post<AccountWithBalance>('/accounts/set-balance', {
      current_balance: currentBalance,
    });
  },

  async update(id: string, data: Partial<Account>): Promise<Account> {
    return apiClient.put<Account>(`/accounts/${id}`, data);
  },
};
