/**
 * December SME Summary
 *
 * Ties together the Universal Statement Loop:
 * 1. Parse any format (CSV, OFX, QIF, XLSX)
 * 2. Recognize income & apply 39.1% tax set-aside
 * 3. Auto-categorize expenses
 * 4. Calculate Net SME Position
 *
 * Goal: Show Net SME Income minus Solo Survival Costs
 */

import { UniversalStatementParser, type UniversalParseResult } from '../parsers/UniversalStatementParser';
import { SMEIncomeRecognizer, type IncomeRecognitionResult, type KnownClient } from './SMEIncomeRecognizer';
import { IntelligentCategorizer, type CategorizationResult, type CategorizedTransaction } from './IntelligentCategorizer';
import type { ParsedTransaction } from '@/types';

export interface DecemberSummary {
  // Period
  periodStart: string;
  periodEnd: string;
  year: number;
  month: number;

  // Income Analysis (with tax-deductible business expenses)
  income: {
    grossIncome: number;
    taxSetAside: number;          // grossIncome * 39.1% (FULL reserve until approved)
    netAfterTax: number;          // grossIncome - taxSetAside
    recognizedPayments: {
      clientName: string;
      amount: number;
      date: string;
      taxSetAside: number;
    }[];
    unrecognizedDeposits: ParsedTransaction[];

    // Pending Accountant Review (ghost numbers - not yet applied)
    pendingBusinessDeductions: number;  // Tagged as business, awaiting approval
    approvedBusinessDeductions: number; // Accountant approved
    estimatedRefund: number;            // pendingBusinessDeductions * 39.1% (ghost)
    confirmedRefund: number;            // approvedBusinessDeductions * 39.1% (real)

    // Tax Payments (SARS settlements - reduces remaining tax due)
    taxPaid: number;                    // Total payments to SARS this period
    remainingTaxDue: number;            // taxSetAside - taxPaid (progress toward next provisional)
    taxPayments: {                      // Individual payments for drilldown
      date: string;
      amount: number;
      description: string;
    }[];
  };

  // Expense Analysis
  expenses: {
    // Solo Survival Costs (what SME needs to live)
    essential: number;      // Bond, Rates, Utilities, Insurance
    repairs: number;        // Repairs & Maintenance
    survivalTotal: number;  // essential + repairs

    // Lifestyle (discretionary)
    fun: number;            // Entertainment, dining, shopping
    newCapital: number;     // New purchases, equipment

    // Business (tax-deductible)
    business: number;       // Software, hosting, services (reduces taxable income)

    // Totals (excludes tax_paid and transfers - they're not "expenses")
    totalExpenses: number;
    transfers: number;      // Excluded from budget (internal moves)
    taxPaid: number;        // Excluded from budget (statutory debt, not expense)
  };

  // Net Position
  netPosition: {
    netIncomeAfterTax: number;
    survivalCosts: number;
    lifestyleCosts: number;
    netSMEPosition: number;  // Net income - Survival - Lifestyle
    survivalRatio: number;   // Survival / Net Income (should be < 50%)
  };

  // Categorization Status
  categorization: {
    autoCategorized: number;
    needsInterrogation: number;
    totalTransactions: number;
    completionRate: number;
  };

  // Raw data for drilling down
  categorizedTransactions: CategorizedTransaction[];
  uncategorizedTransactions: ParsedTransaction[];

  // Balance info (if available from statement)
  balance?: {
    opening?: number;
    closing?: number;
    ledger?: number;
  };
}

export interface DecemberSummaryOptions {
  year?: number;
  month?: number;  // 12 for December, but can be any month
  knownClients?: KnownClient[];
  taxRate?: number;
  payCycleStartDay?: number;  // Day of month when income arrives (e.g., 25)
}

const DEFAULT_TAX_RATE = 0.391;

export class DecemberSMESummaryBuilder {
  private parser = UniversalStatementParser;
  private incomeRecognizer: SMEIncomeRecognizer;
  private categorizer: IntelligentCategorizer;
  private options: DecemberSummaryOptions;

  constructor(options: DecemberSummaryOptions = {}) {
    this.options = {
      year: options.year || new Date().getFullYear(),
      month: options.month || 12,
      taxRate: options.taxRate || DEFAULT_TAX_RATE,
      payCycleStartDay: options.payCycleStartDay || 25, // Default: income arrives on 25th
      ...options,
    };

    this.incomeRecognizer = new SMEIncomeRecognizer(
      options.knownClients,
      options.taxRate
    );
    this.categorizer = new IntelligentCategorizer();
  }

  /**
   * Build December summary from a statement file
   */
  async buildFromFile(file: File): Promise<DecemberSummary> {
    // Step 1: Parse the statement (format-blind)
    const parseResult = await this.parser.parse(file);

    return this.buildFromParsedData(parseResult);
  }

  /**
   * Build summary from already-parsed data
   */
  buildFromParsedData(parseResult: UniversalParseResult): DecemberSummary {
    const { transactions, ledgerBalance } = parseResult;

    // Filter to PAY CYCLE window (e.g., Nov 25 to Dec 31 for "December" budget)
    // This ensures Nov income is included in December tax calculation
    const payCycleTransactions = this.filterToPayCycle(
      transactions,
      this.options.year!,
      this.options.month!
    );

    console.log(`Pay Cycle Transactions: ${payCycleTransactions.length} (from ${transactions.length} total)`);

    // Step 2: Recognize income (includes Nov 25+ income)
    const incomeResult = this.incomeRecognizer.recognizeIncome(payCycleTransactions);

    // Step 3: Categorize expenses (only outflows)
    const expenseTransactions = payCycleTransactions.filter(tx => tx.amount < 0);
    const categorizationResult = this.categorizer.categorize(expenseTransactions);

    // Step 4: Build the summary
    return this.buildSummary(
      payCycleTransactions,
      incomeResult,
      categorizationResult,
      ledgerBalance ?? undefined
    );
  }

  /**
   * Filter transactions to PAY CYCLE window.
   *
   * Pay Cycle Logic:
   * - "December budget" starts when Nov income arrives (e.g., Nov 25th)
   * - Includes: Nov 25 to Dec 31 transactions
   * - Ensures 39.1% tax is calculated on the Nov income
   */
  private filterToPayCycle(
    transactions: ParsedTransaction[],
    year: number,
    month: number
  ): ParsedTransaction[] {
    const payCycleStartDay = this.options.payCycleStartDay || 25;

    // Pay cycle start: previous month's payCycleStartDay
    let startYear = year;
    let startMonth = month - 1; // Previous month
    if (startMonth < 1) {
      startMonth = 12;
      startYear = year - 1;
    }

    // Build date range strings (YYYY-MM-DD format)
    const startDate = `${startYear}-${String(startMonth).padStart(2, '0')}-${String(payCycleStartDay).padStart(2, '0')}`;
    const endDate = `${year}-${String(month).padStart(2, '0')}-31`;

    console.log(`SME Summary Pay Cycle: ${startDate} to ${endDate}`);

    return transactions.filter(tx => {
      return tx.transactionDate >= startDate && tx.transactionDate <= endDate;
    });
  }

  /**
   * Build the complete summary object
   */
  private buildSummary(
    transactions: ParsedTransaction[],
    incomeResult: IncomeRecognitionResult,
    categorizationResult: CategorizationResult,
    ledgerBalance?: number
  ): DecemberSummary {
    // Calculate date range
    const dates = transactions
      .map(tx => new Date(tx.transactionDate))
      .sort((a, b) => a.getTime() - b.getTime());

    const periodStart = dates[0]?.toISOString().split('T')[0] || '';
    const periodEnd = dates[dates.length - 1]?.toISOString().split('T')[0] || '';

    // Expense section
    const { summary } = categorizationResult;
    const pendingBusinessDeductions = summary.business || 0; // All tagged as business = pending until approved
    const taxPaidAmount = summary.taxPaid || 0; // SARS payments (statutory debt, not expense)
    const survivalTotal = summary.essential + summary.repairs;
    // totalExpenses excludes tax_paid (statutory debt) and transfers (internal moves)
    const totalExpenses = survivalTotal + summary.fun + summary.newCapital + pendingBusinessDeductions;

    const expenses = {
      essential: summary.essential,
      repairs: summary.repairs,
      survivalTotal,
      fun: summary.fun,
      newCapital: summary.newCapital,
      business: pendingBusinessDeductions,
      totalExpenses,
      transfers: summary.transfers,
      taxPaid: taxPaidAmount,
    };

    // Income section - KEEP FULL TAX RESERVE (business deductions are pending approval)
    const grossIncome = incomeResult.totalGrossIncome;
    const taxRate = this.options.taxRate || DEFAULT_TAX_RATE;

    // Conservative approach: Tax on full gross income (no deductions until approved)
    const approvedBusinessDeductions = 0; // TODO: Load from user's approved list
    const taxSetAside = Math.round(grossIncome * taxRate * 100) / 100;
    const netAfterTax = grossIncome - taxSetAside;

    // Ghost numbers - potential savings pending accountant approval
    const estimatedRefund = Math.round(pendingBusinessDeductions * taxRate * 100) / 100;
    const confirmedRefund = Math.round(approvedBusinessDeductions * taxRate * 100) / 100;

    // Tax payments tracking - progress toward next provisional
    const remainingTaxDue = Math.max(0, taxSetAside - taxPaidAmount);

    // Extract individual tax payment transactions for drilldown
    const taxPayments = categorizationResult.categorized
      .filter(tx => tx.smeCategory === 'tax_paid')
      .map(tx => ({
        date: tx.transactionDate,
        amount: Math.abs(tx.amount),
        description: tx.description,
      }));

    const income = {
      grossIncome,
      taxSetAside,
      netAfterTax,
      recognizedPayments: incomeResult.recognizedIncome.map(r => ({
        clientName: r.clientName,
        amount: r.amount,
        date: r.transactionDate,
        taxSetAside: r.taxSetAside,
      })),
      unrecognizedDeposits: incomeResult.unrecognizedDeposits,
      // Pending Accountant Review
      pendingBusinessDeductions,
      approvedBusinessDeductions,
      estimatedRefund,
      confirmedRefund,
      // Tax Payments (SARS settlements)
      taxPaid: taxPaidAmount,
      remainingTaxDue,
      taxPayments,
    };

    // Net position
    const netIncomeAfterTax = netAfterTax;
    const lifestyleCosts = summary.fun + summary.newCapital;
    const netSMEPosition = netIncomeAfterTax - survivalTotal - lifestyleCosts;
    const survivalRatio = netIncomeAfterTax > 0
      ? (survivalTotal / netIncomeAfterTax) * 100
      : 0;

    const netPosition = {
      netIncomeAfterTax,
      survivalCosts: survivalTotal,
      lifestyleCosts,
      netSMEPosition,
      survivalRatio: Math.round(survivalRatio * 10) / 10,
    };

    // Categorization status
    const totalExpenseTransactions = categorizationResult.categorized.length +
      categorizationResult.needsInterrogation.length;
    const categorization = {
      autoCategorized: categorizationResult.categorized.length,
      needsInterrogation: categorizationResult.needsInterrogation.length,
      totalTransactions: totalExpenseTransactions,
      completionRate: totalExpenseTransactions > 0
        ? Math.round((categorizationResult.categorized.length / totalExpenseTransactions) * 100)
        : 100,
    };

    return {
      periodStart,
      periodEnd,
      year: this.options.year!,
      month: this.options.month!,
      income,
      expenses,
      netPosition,
      categorization,
      categorizedTransactions: categorizationResult.categorized,
      uncategorizedTransactions: categorizationResult.needsInterrogation,
      balance: ledgerBalance ? { ledger: ledgerBalance } : undefined,
    };
  }

  /**
   * Add a client from invoice data for future recognition
   */
  addClientFromInvoice(clientName: string): void {
    this.incomeRecognizer.addClientFromInvoice(clientName);
  }

  /**
   * Get suggestions for new clients based on unrecognized deposits
   */
  suggestNewClients(summary: DecemberSummary): string[] {
    return this.incomeRecognizer.suggestNewClients(summary.income.unrecognizedDeposits);
  }
}

/**
 * Quick utility to build December summary from file
 */
export async function buildDecemberSummary(
  file: File,
  options?: DecemberSummaryOptions
): Promise<DecemberSummary> {
  const builder = new DecemberSMESummaryBuilder(options);
  return builder.buildFromFile(file);
}

/**
 * Format summary for display
 */
export function formatSummaryForDisplay(summary: DecemberSummary): {
  title: string;
  sections: {
    heading: string;
    items: { label: string; value: string; highlight?: boolean; ghost?: boolean }[];
  }[];
} {
  const formatCurrency = (amount: number) =>
    `R ${Math.abs(amount).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}`;

  // Build income items - conservative approach (full tax until accountant approves)
  const incomeItems: { label: string; value: string; highlight?: boolean; ghost?: boolean }[] = [
    { label: 'Gross Income', value: formatCurrency(summary.income.grossIncome) },
    { label: 'Tax Set-Aside (39.1%)', value: `-${formatCurrency(summary.income.taxSetAside)}` },
    { label: 'Net After Tax', value: formatCurrency(summary.income.netAfterTax), highlight: true },
  ];

  // Show ghost number for pending business deductions (estimated refund)
  if (summary.income.pendingBusinessDeductions > 0) {
    incomeItems.push(
      { label: '─── Pending Accountant Review ───', value: '', ghost: true },
      { label: 'Business Expenses Claimed', value: formatCurrency(summary.income.pendingBusinessDeductions), ghost: true },
      { label: '💰 Estimated Refund', value: `+${formatCurrency(summary.income.estimatedRefund)}`, ghost: true, highlight: true },
    );
  }

  // Show confirmed refund if any approved
  if (summary.income.confirmedRefund > 0) {
    incomeItems.push(
      { label: '✅ Accountant Approved Refund', value: `+${formatCurrency(summary.income.confirmedRefund)}`, highlight: true },
    );
  }

  // Tax Progress section (shows progress toward next provisional)
  const taxProgressItems: { label: string; value: string; highlight?: boolean }[] = [
    { label: 'Tax Set-Aside Target', value: formatCurrency(summary.income.taxSetAside) },
    { label: 'Tax Paid (SARS)', value: `-${formatCurrency(summary.income.taxPaid)}` },
    {
      label: 'Remaining Tax Due',
      value: formatCurrency(summary.income.remainingTaxDue),
      highlight: summary.income.remainingTaxDue > 0,
    },
  ];

  return {
    title: `December ${summary.year} SME Summary`,
    sections: [
      {
        heading: 'Income (After 39.1% Tax Set-Aside)',
        items: incomeItems,
      },
      {
        heading: 'Tax Progress (Provisional)',
        items: taxProgressItems,
      },
      {
        heading: 'Solo Survival Costs',
        items: [
          { label: 'Essential (Bond, Rates, etc.)', value: formatCurrency(summary.expenses.essential) },
          { label: 'Repairs & Maintenance', value: formatCurrency(summary.expenses.repairs) },
          { label: 'Survival Total', value: formatCurrency(summary.expenses.survivalTotal), highlight: true },
        ],
      },
      {
        heading: 'Lifestyle Spending',
        items: [
          { label: 'Fun & Entertainment', value: formatCurrency(summary.expenses.fun) },
          { label: 'New Purchases', value: formatCurrency(summary.expenses.newCapital) },
          { label: 'Lifestyle Total', value: formatCurrency(summary.netPosition.lifestyleCosts) },
        ],
      },
      {
        heading: 'Net SME Position',
        items: [
          { label: 'Net Income After Tax', value: formatCurrency(summary.netPosition.netIncomeAfterTax) },
          { label: 'Less: Survival Costs', value: `-${formatCurrency(summary.netPosition.survivalCosts)}` },
          { label: 'Less: Lifestyle', value: `-${formatCurrency(summary.netPosition.lifestyleCosts)}` },
          {
            label: 'Net Position',
            value: `${summary.netPosition.netSMEPosition >= 0 ? '+' : '-'}${formatCurrency(summary.netPosition.netSMEPosition)}`,
            highlight: true,
          },
        ],
      },
      {
        heading: 'Health Check',
        items: [
          {
            label: 'Survival Ratio',
            value: `${summary.netPosition.survivalRatio}%`,
            highlight: summary.netPosition.survivalRatio > 50,
          },
          { label: 'Auto-Categorized', value: `${summary.categorization.completionRate}%` },
          { label: 'Needs Review', value: `${summary.categorization.needsInterrogation} items` },
        ],
      },
    ],
  };
}
