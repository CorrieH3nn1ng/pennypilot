// Core Types for PennyPilot

export interface User {
  id: string;
  name: string;
  email: string;
  consent_given: boolean;
  consent_date: string | null;
  subscription_tier: SubscriptionTier;
  subscription_expires_at: string | null;
  is_premium: boolean; // true if tier is 'cruiser' or 'captain'
  is_admin: boolean;
  income_type: IncomeType;
  net_monthly_income: number | null;
  persona: UserPersona;
  onboarding_completed: boolean;
  created_at: string;
}

// Income type for user persona
export type IncomeType = 'self_employed' | 'salaried';

// User persona features (computed by backend based on income_type)
export interface UserPersona {
  income_type: IncomeType;
  show_invoicing: boolean;
  show_clients: boolean;
  show_vat_threshold: boolean;
  show_tax_provisions: boolean;
  budget_source: 'net_income' | 'invoiced_income';
  net_monthly_income: number | null;
}

// Forward declaration for User interface (SubscriptionTier defined at bottom)
// Includes 'premium' for backwards compatibility with existing users
export type SubscriptionTier = 'free' | 'cruiser' | 'captain' | 'premium';

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
  bucket?: BudgetBucket | null;
  transaction_date: string;
  description: string;
  amount: number;
  balance_after: number | null;
  bank_reference: string | null;
  import_source: ImportSource;
  raw_description: string | null;
  is_categorized: boolean;
  is_transfer: boolean;
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

// ============================================
// Income Source Types
// ============================================

export type IncomeSourceType = 'salary' | 'freelance' | 'investment' | 'rental' | 'side_hustle' | 'consulting' | 'retainer' | 'project' | 'royalties' | 'other';
export type IncomeFrequency = 'monthly' | 'weekly' | 'bi_weekly' | 'annual' | 'irregular';

export interface TaxEstimate {
  annual_tax: number;
  monthly_tax: number;
  effective_rate: number;
  marginal_rate: number;
  rebate_applied: number;
}

export interface IncomeSource {
  id: string;
  user_id: string;
  client_id: string | null; // Link to Client for relational tracking
  client?: Client; // Populated when loaded with relations
  name: string;
  type: IncomeSourceType;
  gross_amount: number;
  net_amount: number | null;
  tax_amount: number | null;
  frequency: IncomeFrequency;
  is_active: boolean;
  notes: string | null;
  // Computed by backend
  monthly_amount: number;
  annual_amount: number;
  tax_estimate: TaxEstimate;
  invoices_count?: number; // Number of linked invoices
  created_at: string;
  updated_at: string;
}

export interface IncomeSummary {
  total_monthly_gross: number;
  total_annual_gross: number;
  total_monthly_tax: number;
  total_annual_tax: number;
  active_count: number;
}

export interface IncomeSourcesResponse {
  data: IncomeSource[];
  summary: IncomeSummary;
}

// ============================================
// Budget Types
// ============================================

export type BudgetMethodology = '50-30-20' | 'zero-based' | 'profit-first';
export type BudgetStatus = 'draft' | 'active' | 'closed';
export type BudgetBucket = 'needs' | 'wants' | 'savings' | 'business' | 'transfer' | 'tax_paid';
export type RolloverOption = 'savings' | 'rollover' | 'reset';

export interface BudgetPeriod {
  id: string;
  user_id: string;
  year: number;
  month: number;
  methodology: BudgetMethodology;
  total_income: number;
  tax_provision: number;
  net_available: number;
  total_planned: number;
  status: BudgetStatus;
  rollover_option: RolloverOption;
  period_name: string; // Computed accessor
  remaining: number; // Computed accessor
  items?: BudgetItem[];
  created_at: string;
  updated_at: string;
}

export interface BudgetItem {
  id: string;
  budget_period_id: string;
  category_id: string | null;
  category?: Category;
  name: string | null;
  planned_amount: number;
  actual_amount: number;
  bucket: BudgetBucket;
  is_fixed: boolean;
  variance: number; // Computed
  percent_spent: number; // Computed
  created_at: string;
  updated_at: string;
}

export interface BucketTotals {
  needs: { planned: number; actual: number };
  wants: { planned: number; actual: number };
  savings: { planned: number; actual: number };
}

export interface TargetAllocations {
  needs: number;
  wants: number;
  savings: number;
}

export interface BudgetResponse {
  data: BudgetPeriod;
  bucket_totals: BucketTotals;
  target_allocations: TargetAllocations;
}

export interface BudgetSummary {
  period: string;
  methodology: BudgetMethodology;
  income: {
    gross: number;
    tax_provision: number;
    net_available: number;
  };
  buckets: {
    needs: { target: number; planned: number; actual: number; variance: number };
    wants: { target: number; planned: number; actual: number; variance: number };
    savings: { target: number; planned: number; actual: number; variance: number };
  };
  totals: {
    planned: number;
    actual: number;
    remaining: number;
  };
}

// ============================================
// Fixed Expense Types
// ============================================

export interface FixedExpense {
  id: string;
  user_id: string;
  category_id: string | null;
  category?: Category;
  name: string;
  amount: number;
  due_day: number | null;
  bucket: BudgetBucket;
  is_active: boolean;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface FixedExpenseSummary {
  total_monthly: number;
  total_annual: number;
  active_count: number;
  by_bucket: {
    needs: number;
    wants: number;
    savings: number;
  };
}

export interface FixedExpensesResponse {
  data: FixedExpense[];
  summary: FixedExpenseSummary;
}

// ============================================
// Tax Types
// ============================================

export type EmploymentType = 'employed' | 'self_employed' | 'mixed';

export interface TaxSetting {
  id: string;
  user_id: string;
  employment_type: EmploymentType;
  tax_number: string | null;
  vat_registered: boolean;
  vat_number: string | null;
  estimated_annual_income: number | null;
  estimated_tax_rate: number | null;
  provisional_tax_enabled: boolean;
  tax_year_start_month: number;
  created_at: string;
  updated_at: string;
}

export interface TaxProvision {
  id: string;
  user_id: string;
  year: number;
  month: number;
  tax_year: number;
  income_amount: number;
  provision_amount: number;
  provision_rate: number | null;
  is_paid: boolean;
  payment_date: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface TaxBracket {
  min: number;
  max: number | null;
  rate: number;
  description: string;
}

export interface AnnualTaxCalculation {
  gross_income: number;
  taxable_income: number;
  tax_before_rebates: number;
  rebates: number;
  tax_payable: number;
  effective_rate: number;
  marginal_rate: number;
}

export interface MonthlyProvisionCalculation {
  monthly_income: number;
  annualized_income: number;
  estimated_annual_tax: number;
  monthly_provision: number;
  effective_rate: number;
  net_after_provision: number;
}

export interface ProvisionalPayment {
  amount: number;
  due_date: string;
  description: string;
}

export interface ProvisionalPaymentsInfo {
  estimated_annual_income: number;
  estimated_annual_tax: number;
  first_payment: ProvisionalPayment;
  second_payment: ProvisionalPayment;
}

export interface TaxYearSummary {
  tax_year: number;
  months_recorded: number;
  total_income: number;
  total_provision: number;
  annualized_income: number;
  estimated_annual_tax: number;
  effective_rate: number;
  provisional_payments: ProvisionalPaymentsInfo;
  monthly_breakdown: TaxProvision[];
}

// ============================================
// Client Types
// ============================================

export interface Client {
  id: string;
  user_id: string;
  client_code: string;
  name: string;
  email: string | null;
  contact_person: string | null;
  phone: string | null;
  address: string | null;
  notes: string | null;
  is_active: boolean;
  invoices_count?: number;
  invoices?: Invoice[];
  income_sources_count?: number;
  income_sources?: IncomeSource[];
  total_revenue?: number; // Rolling 12-month revenue
  total_revenue_ytd?: number; // Year-to-date revenue
  billing_period_start?: number | null;
  billing_period_end?: number | null;
  payment_day?: number | null;
  payment_terms?: number | null;
  default_rate?: number | null;
  rate_type?: RateType | null;
  created_at: string;
  updated_at: string;
}

export interface ClientsResponse {
  data: Client[];
  summary: {
    total_count: number;
    active_count: number;
  };
}

// ============================================
// Invoice Types
// ============================================

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
export type InvoiceTypeValue = 'regular' | 'historical';
export type MatchMethod = 'auto' | 'manual';

export interface InvoiceItem {
  id?: string;
  invoice_id?: string;
  description: string;
  quantity: number;
  unit: string | null;
  unit_price: number;
  amount: number;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
}

export interface Invoice {
  id: string;
  user_id: string;
  client_id: string;
  client?: Client;
  invoice_number: string;
  sequence_number: number;
  status: InvoiceStatus;
  invoice_type: InvoiceTypeValue;
  invoice_date: string;
  due_date: string;
  paid_date: string | null;
  subtotal: number;
  tax_rate: number;
  tax_amount: number;
  total: number;
  title: string | null;
  notes: string | null;
  uploaded_file_path: string | null;
  income_source_id: string | null;
  income_source?: IncomeSource;
  // Transaction matching
  transaction_id: string | null;
  transaction?: Transaction;
  matched_at: string | null;
  match_method: MatchMethod | null;
  match_confidence: number | null;
  items: InvoiceItem[];
  created_at: string;
  updated_at: string;
}

export interface InvoiceSummary {
  total_count: number;
  draft_count: number;
  sent_count: number;
  paid_count: number;
  overdue_count: number;
  total_outstanding: number;
  total_paid: number;
}

export interface InvoicesResponse {
  data: Invoice[];
  summary: InvoiceSummary;
}

// ============================================
// Business Profile Types
// ============================================

export type BankAccountType = 'cheque' | 'savings' | 'transmission' | 'current';

export interface BusinessProfile {
  id: string;
  user_id: string;
  // Business Info
  business_name: string | null;
  trading_name: string | null;
  registration_number: string | null;
  tax_number: string | null;
  vat_number: string | null;
  // Contact Info
  email: string | null;
  phone: string | null;
  website: string | null;
  address: string | null;
  // Banking Details
  bank_name: string | null;
  bank_branch: string | null;
  bank_branch_code: string | null;
  account_holder: string | null;
  account_number: string | null;
  account_type: BankAccountType | null;
  // Invoice Settings
  invoice_prefix: string;
  invoice_notes: string | null;
  payment_terms: string | null;
  default_tax_rate: number;
  // Logo
  logo_path: string | null;
  created_at: string;
  updated_at: string;
}

export interface BusinessProfileResponse {
  data: BusinessProfile;
  has_banking_details: boolean;
}

// ============================================
// Oplog Types (Sync Infrastructure)
// ============================================

export type OplogEntityType = 'transaction' | 'category' | 'invoice' | 'client' | 'income_source' | 'fixed_expense';
export type OplogOperation = 'create' | 'update' | 'delete';
export type OplogSyncStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface OplogEntry {
  id: string;
  timestamp: number;
  entity_type: OplogEntityType;
  entity_id: string;
  operation: OplogOperation;
  data: Record<string, unknown>;
  previous_data?: Record<string, unknown>; // For rollback on conflict
  sync_status: OplogSyncStatus;
  synced_at?: number;
  retry_count: number;
  error_message?: string;
  checksum?: string; // For integrity verification
}

export interface SyncState {
  is_syncing: boolean;
  is_online: boolean;
  last_sync_at: string | null;
  pending_count: number;
  failed_count: number;
  last_error: string | null;
}

export interface SyncBatchResult {
  success: boolean;
  applied: number;
  failed: number;
  conflicts: Array<{
    entry_id: string;
    entity_type: OplogEntityType;
    entity_id: string;
    error: string;
    server_version?: number;
  }>;
}

// ============================================
// User Tier Features
// ============================================

export interface TierFeatures {
  sync_enabled: boolean;
  ocr_enabled: boolean;
  multi_device: boolean;
  export_pdf: boolean;
  priority_support: boolean;
  max_transactions: number | null; // null = unlimited
  max_invoices: number | null;
}

// ============================================
// Blueprint Types (Strategic Audit System)
// ============================================

export type BlueprintItemType = 'income' | 'expense';
export type BlueprintMatchType = 'contains' | 'starts_with' | 'exact';
export type BlueprintFrequency = 'monthly' | 'weekly' | 'quarterly' | 'annual';
export type BlueprintNature = 'business' | 'private';

export interface Blueprint {
  id: string;
  user_id: string;
  category_id: string | null;
  category?: Category;
  liability_id: string | null;
  liability?: Liability;
  name: string;
  expected_amount: number;
  amount_tolerance: number;
  due_day: number | null;
  due_day_tolerance: number;
  item_type: BlueprintItemType;
  bucket: BudgetBucket;
  nature: BlueprintNature;
  match_pattern: string | null;
  match_type: BlueprintMatchType;
  frequency: BlueprintFrequency;
  is_active: boolean;
  is_liability_item: boolean;
  is_business_expense: boolean;
  is_private_expense: boolean;
  priority: number;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

// Bulk create item for the Bulk Blueprint Entry Tool
export interface BulkBlueprintItem {
  name: string;
  expected_amount: number;
  item_type: BlueprintItemType;
  bucket?: BudgetBucket;
  nature?: BlueprintNature;
  due_day?: number | null;
  category_id?: string | null;
  liability_id?: string | null;
  match_pattern?: string | null;
  notes?: string | null;
}

export interface BulkBlueprintResult {
  created: Blueprint[];
  created_count: number;
  skipped: Array<{ index: number; name: string; reason: string }>;
  skipped_count: number;
}

export interface BlueprintTotals {
  income: number;
  expense: number;
  needs: number;
  wants: number;
  savings: number;
  active_count: number;
  total_count: number;
  // Tax calculations (Business Income - Business Expenses = Taxable Income)
  business_income?: number;
  business_expenses?: number;
  taxable_income?: number;
  tax_rate?: number;
  tax_reserve?: number;
  // The number that matters
  actual_spendable?: number;
}

export interface BlueprintGrouped {
  income: Blueprint[];
  expense: {
    needs: Blueprint[];
    wants: Blueprint[];
    savings: Blueprint[];
  };
}

export interface BlueprintsResponse {
  blueprints: Blueprint[];
  grouped: BlueprintGrouped;
  totals: BlueprintTotals;
}

export interface BlueprintPreview {
  items: Blueprint[];
  summary: {
    total_income: number;
    total_expense: number;
    net_cash_flow: number;
    needs: number;
    wants: number;
    savings: number;
  };
  tax: {
    vat_reserve: number;
    tax_reserve: number;
    total_liability: number;
    net_after_tax: number;
  };
  item_count: number;
}

// ============================================
// Budget Card Types (Monthly Audit)
// ============================================

export type BudgetCardStatus = 'draft' | 'active' | 'finalized';
export type AuditStatus = 'pending' | 'matched' | 'missing';
export type AuditClassification = 'matched' | 'leak' | 'windfall' | 'transfer';
export type MatchMethod = 'auto' | 'manual';
export type SurplusAction = 'savings' | 'buffer';

export interface BudgetCardItem {
  id: string;
  budget_card_id: string;
  blueprint_id: string | null;
  invoice_id: string | null;
  liability_id: string | null;
  liability?: Liability;
  category_id: string | null;
  category?: Category;
  item_type: BlueprintItemType;
  name: string;
  expected_amount: number;
  expected_day: number | null;
  bucket: BudgetBucket;
  match_pattern: string | null;
  match_type: BlueprintMatchType;
  is_one_off: boolean;
  is_liability_item: boolean;
  audit_status: AuditStatus;
  matched_transaction_id: string | null;
  matchedTransaction?: Transaction;
  matched_amount: number | null;
  matched_date: string | null;
  match_confidence: number | null;
  // Liability tracking fields
  installment_number: number | null;
  remaining_balance_at_match: number | null;
  installment_label: string | null;
  remaining_balance: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface TransactionAudit {
  id: string;
  budget_card_id: string;
  transaction_id: string;
  transaction?: Transaction;
  budget_card_item_id: string | null;
  classification: AuditClassification;
  match_method: MatchMethod | null;
  match_confidence: number | null;
  is_silenced: boolean;
  created_at: string;
  updated_at: string;
}

export interface BudgetCard {
  id: string;
  user_id: string;
  year: number;
  month: number;
  status: BudgetCardStatus;
  period_label: string;
  letter_grade: string | null;
  total_expected_income: number;
  total_expected_expense: number;
  total_matched: number;
  total_leaked: number;
  total_windfall: number;
  total_missing: number;
  success_grade: number | null;
  surplus_amount: number | null;
  surplus_action: SurplusAction | null;
  rollover_from_previous: number;
  is_current_month: boolean;
  is_finalized: boolean;
  finalized_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface BudgetCardItems {
  all: BudgetCardItem[];
  grouped: {
    pending: BudgetCardItem[];
    matched: BudgetCardItem[];
    missing: BudgetCardItem[];
  };
}

export interface BudgetCardAudits {
  leaks: TransactionAudit[];
  windfalls: TransactionAudit[];
  transfers: TransactionAudit[];
}

export interface AuditStats {
  total_items: number;
  matched_items: number;
  pending_items: number;
  missing_items: number;
  match_rate: number;
  total_expected_income: number;
  total_expected_expense: number;
  total_matched_income: number;
  total_matched_expense: number;
  total_leaked: number;
  total_windfall: number;
  leak_count: number;
  windfall_count: number;
  silenced_count: number;
  transfer_count: number;
  tax_reserve: {
    vat: number;
    income_tax: number;
    total: number;
  };
}

export interface BudgetCardDetails {
  card: BudgetCard;
  items: BudgetCardItems;
  audits: BudgetCardAudits;
  stats: AuditStats;
}

export interface SuccessGrade {
  success_grade: number;
  letter_grade: string;
  income_score: number;
  discipline_score: number;
  leak_penalty: number;
  income_weight: number;
  discipline_weight: number;
  leak_weight: number;
  details: {
    expected_income: number;
    matched_income: number;
    expected_expense: number;
    matched_expense: number;
    total_leaked: number;
    total_windfall: number;
  };
}

export interface AuditResult {
  card_id: string;
  transactions_processed: number;
  matched: number;
  leaks: number;
  windfalls: number;
  transfers: number;
  stats: AuditStats;
}

export interface MatchCandidate {
  item_id: string;
  item_name: string;
  expected_amount: number;
  expected_day: number | null;
  confidence: number;
  confidence_breakdown: {
    date: number;
    amount: number;
    description: number;
  };
}

// ============================================
// Liability Types (Debt Recovery)
// ============================================

export type LiabilityType = 'arrears' | 'loan' | 'credit_card' | 'store_account' | 'other';
export type LiabilityStatus = 'active' | 'paid_off' | 'paused';

export interface Liability {
  id: string;
  user_id: string;
  category_id: string | null;
  category?: Category;
  name: string;
  liability_type: LiabilityType;
  original_balance: number;
  current_balance: number;
  monthly_installment: number;
  interest_rate: number | null;
  total_installments: number | null;
  completed_installments: number;
  remaining_installments: number;
  status: LiabilityStatus;
  is_paid_off: boolean;
  paid_off_at: string | null;
  amount_freed: number | null;
  progress_percent: number;
  amount_paid: number;
  estimated_payoff_date: string | null;
  installment_label: string;
  creditor: string | null;
  reference_number: string | null;
  start_date: string | null;
  target_payoff_date: string | null;
  notes: string | null;
  blueprints?: Blueprint[];
  created_at: string;
}

export interface LiabilitySummary {
  total_count: number;
  active_count: number;
  paid_off_count: number;
  total_original_debt: number;
  total_remaining_debt: number;
  total_monthly_payments: number;
  total_amount_freed: number;
}

export interface LiabilitiesResponse {
  liabilities: Liability[];
  summary: LiabilitySummary;
}

export interface LiabilityPaymentResult {
  isPaidOff: boolean;
  amountFreed: number;
  remainingBalance: number;
  completedInstallments: number;
}

export interface LiabilityCelebration {
  type: 'liability_paid_off';
  title: string;
  message: string;
  subtitle: string;
  liability_name?: string;
  amount_freed?: number;
}
