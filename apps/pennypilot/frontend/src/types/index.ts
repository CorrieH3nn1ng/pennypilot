// Core Types for PennyPilot

export interface User {
  id: string;
  name: string;
  email: string;
  consent_given: boolean;
  consent_date: string | null;
  created_at: string;
}

export interface Category {
  id: string;
  user_id: string | null;
  name: string;
  icon: string;
  color: string;
  parent_id: string | null;
  is_income: boolean;
  is_system: boolean;
  sort_order: number;
  local_id?: string;
  sync_status: SyncStatus;
}

export interface Transaction {
  id?: string;
  user_id?: string;
  category_id: string | null;
  category?: Category;
  transaction_date: string;
  description: string;
  amount: number;
  balance_after: number | null;
  bank_reference: string | null;
  import_source: ImportSource;
  raw_description: string | null;
  is_categorized: boolean;
  categorized_by: CategorizationMethod | null;
  confidence_score: number | null;
  notes: string | null;
  tags: string[];
  local_id: string;
  sync_status: SyncStatus;
  last_synced_at: string | null;
  version: number;
  created_at?: string;
  updated_at?: string;
}

export type SyncStatus = 'synced' | 'pending' | 'conflict';
export type ImportSource = 'manual' | 'csv' | 'api' | 'sync';
export type CategorizationMethod = 'manual' | 'rule' | 'ai' | 'system';
export type BankFormat = 'nedbank' | 'fnb' | 'absa' | 'standard' | 'capitec' | 'unknown';

// API Response Types
export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    total: number;
    limit: number;
    offset: number;
  };
}

export interface AuthResponse {
  user: User;
  token: string;
}

// Transaction Summary Types
export interface CategorySummary {
  category_id: string | null;
  category: Category | null;
  total_expenses: number;
  total_income: number;
  transaction_count: number;
}

export interface TransactionSummary {
  by_category: CategorySummary[];
  totals: {
    total_expenses: number;
    total_income: number;
    transaction_count: number;
  };
  period: {
    start_date: string;
    end_date: string;
  };
}

// CSV Parser Types
export interface ParsedTransaction {
  transactionDate: string;
  description: string;
  amount: number;
  balanceAfter: number | null;
  bankReference: string | null;
  rawDescription: string;
  importSource: 'csv';
}

export interface ParseError {
  row: number;
  field: string;
  message: string;
  rawValue: unknown;
}

export interface ParseResult {
  success: boolean;
  transactions: ParsedTransaction[];
  errors: ParseError[];
  warnings: string[];
  stats: {
    totalRows: number;
    parsedRows: number;
    skippedRows: number;
    dateRange: { start: string; end: string } | null;
  };
}

// Store Types
export interface TransactionFilters {
  startDate: string | null;
  endDate: string | null;
  categoryId: string | null;
  isCategorized: boolean | null;
  searchQuery: string;
}

export interface SyncResult {
  success: boolean;
  pushed?: number;
  pulled?: number;
  conflicts?: number;
  error?: string;
}
