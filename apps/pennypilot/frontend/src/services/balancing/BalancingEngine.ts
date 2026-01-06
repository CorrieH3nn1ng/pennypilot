import type { Transaction } from '@/types';

/**
 * Automatic Balancing Engine
 *
 * Calculates the derived opening balance based on current balance and transaction history.
 * This ensures the 50/30/20 budget balances to zero.
 *
 * Formula: Derived_Opening = current_balance - (inflows) + (outflows)
 *
 * Example:
 *   Current Balance: R 5,000
 *   Inflows (deposits): +R 10,000
 *   Outflows (payments): -R 6,000
 *   Derived Opening: 5,000 - 10,000 + 6,000 = R 1,000
 *
 * This tells us: "If you started at R1,000, received R10,000, and spent R6,000,
 * you'd end up with R5,000"
 */

export interface BalancingResult {
  derivedOpeningBalance: number;
  totalInflows: number;
  totalOutflows: number;
  netChange: number;
  transactionCount: number;
  dateRange: {
    earliest: string | null;
    latest: string | null;
  };
}

export interface BalanceDiscrepancy {
  hasDiscrepancy: boolean;
  derivedBalance: number;
  userProvidedBalance: number | null;
  difference: number;
  percentageDiff: number;
}

/**
 * Calculate the derived opening balance from current balance and transactions.
 *
 * @param currentBalance The user's current bank balance (from statement or input)
 * @param transactions Array of transactions (amounts: positive = inflow, negative = outflow)
 * @returns BalancingResult with derived opening balance and breakdown
 */
export function getDerivedOpeningBalance(
  currentBalance: number,
  transactions: Transaction[]
): BalancingResult {
  // Calculate inflows (positive amounts = money in)
  const totalInflows = transactions
    .filter(t => t.amount > 0)
    .reduce((sum, t) => sum + t.amount, 0);

  // Calculate outflows (negative amounts = money out, but we want absolute value)
  const totalOutflows = transactions
    .filter(t => t.amount < 0)
    .reduce((sum, t) => sum + Math.abs(t.amount), 0);

  // Net change = inflows - outflows
  const netChange = totalInflows - totalOutflows;

  // Derived Opening Balance = Current - Inflows + Outflows
  // Or equivalently: Current - (Inflows - Outflows) = Current - NetChange
  const derivedOpeningBalance = currentBalance - netChange;

  // Get date range
  let earliest: string | null = null;
  let latest: string | null = null;

  if (transactions.length > 0) {
    const dates = transactions.map(t => t.transaction_date).sort();
    earliest = dates[0];
    latest = dates[dates.length - 1];
  }

  return {
    derivedOpeningBalance: Math.round(derivedOpeningBalance * 100) / 100,
    totalInflows: Math.round(totalInflows * 100) / 100,
    totalOutflows: Math.round(totalOutflows * 100) / 100,
    netChange: Math.round(netChange * 100) / 100,
    transactionCount: transactions.length,
    dateRange: {
      earliest,
      latest,
    },
  };
}

/**
 * Check if there's a discrepancy between derived and user-provided opening balance.
 *
 * @param derivedBalance The calculated opening balance
 * @param userProvidedBalance The user's manual input (or null if not provided)
 * @param tolerancePercent Acceptable variance percentage (default 1%)
 * @returns Discrepancy details
 */
export function checkBalanceDiscrepancy(
  derivedBalance: number,
  userProvidedBalance: number | null,
  tolerancePercent: number = 1
): BalanceDiscrepancy {
  // No user input = always show discrepancy prompt
  if (userProvidedBalance === null || userProvidedBalance === 0) {
    return {
      hasDiscrepancy: true,
      derivedBalance,
      userProvidedBalance,
      difference: derivedBalance,
      percentageDiff: 100,
    };
  }

  const difference = Math.abs(derivedBalance - userProvidedBalance);
  const percentageDiff = userProvidedBalance !== 0
    ? (difference / Math.abs(userProvidedBalance)) * 100
    : 100;

  return {
    hasDiscrepancy: percentageDiff > tolerancePercent,
    derivedBalance,
    userProvidedBalance,
    difference: Math.round(difference * 100) / 100,
    percentageDiff: Math.round(percentageDiff * 100) / 100,
  };
}

/**
 * Get the start of the current month as a date string.
 * Used for Free user lockdown - they can only use current month's data.
 */
export function getCurrentMonthStart(): string {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), 1)
    .toISOString()
    .split('T')[0];
}

/**
 * Filter transactions to current month only.
 * Used for Free users who cannot go back in time.
 *
 * @param transactions All transactions
 * @returns Transactions from current month only
 */
export function filterToCurrentMonth(transactions: Transaction[]): Transaction[] {
  const monthStart = getCurrentMonthStart();
  const now = new Date();
  const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    .toISOString()
    .split('T')[0];

  return transactions.filter(t => {
    const date = t.transaction_date;
    return date >= monthStart && date <= monthEnd;
  });
}

/**
 * Check if a date is before the current month.
 * Used to enforce Free user lockdown.
 *
 * @param date Date string to check
 * @returns true if date is before current month start
 */
export function isBeforeCurrentMonth(date: string): boolean {
  return date < getCurrentMonthStart();
}

/**
 * Calculate a "Day 1" balance for Free users.
 * This is their starting point - the variance they must accept.
 *
 * @param currentBalance Current bank balance
 * @param currentMonthTransactions Transactions from current month only
 * @returns Day 1 balance for Free users
 */
export function getDay1Balance(
  currentBalance: number,
  currentMonthTransactions: Transaction[]
): number {
  const result = getDerivedOpeningBalance(currentBalance, currentMonthTransactions);
  return result.derivedOpeningBalance;
}

// ============================================================================
// DECEMBER STATEMENT LOOP - Anchor Functions
// ============================================================================

export interface DecemberAnchorResult extends BalancingResult {
  anchorYear: number;
  anchorMonth: number;
  dec1OpeningBalance: number;
  dec30Balance: number;
}

export interface YearDetectionResult {
  detectedYears: number[];
  primaryYear: number | null;
  hasMultipleYears: boolean;
  decemberTransactionCount: number;
}

/**
 * Filter transactions to a specific month and year.
 *
 * @param transactions All transactions
 * @param year The year to filter (e.g., 2024)
 * @param month The month to filter (1-12, where 12 = December)
 * @returns Transactions from the specified month only
 */
export function filterToMonth(
  transactions: Transaction[],
  year: number,
  month: number
): Transaction[] {
  return transactions.filter(t => {
    const date = new Date(t.transaction_date);
    return date.getFullYear() === year && date.getMonth() + 1 === month;
  });
}

/**
 * Filter transactions to a PAY CYCLE window.
 *
 * Pay Cycle Logic:
 * - SMEs/freelancers don't budget by calendar months
 * - Budget starts when income arrives (e.g., 25th of prior month)
 * - "December budget" = Nov 25 to Dec 31 (income + expenses until month end)
 *
 * @param transactions All transactions
 * @param year The target budget year (e.g., 2025)
 * @param month The target budget month (1-12, where 12 = December)
 * @param payCycleStartDay Day of month when pay arrives (e.g., 25)
 * @returns Transactions from pay cycle start to month end
 */
export function filterToPayCycle(
  transactions: Transaction[],
  year: number,
  month: number,
  payCycleStartDay: number = 25
): Transaction[] {
  // Pay cycle start: previous month's payCycleStartDay
  // Pay cycle end: last day of target month
  let startYear = year;
  let startMonth = month - 1; // Previous month
  if (startMonth < 1) {
    startMonth = 12;
    startYear = year - 1;
  }

  // Build date range strings (YYYY-MM-DD format)
  const startDate = `${startYear}-${String(startMonth).padStart(2, '0')}-${String(payCycleStartDay).padStart(2, '0')}`;
  const endDate = `${year}-${String(month).padStart(2, '0')}-31`; // 31 is safe, will match any valid date

  console.log(`Pay Cycle Filter: ${startDate} to ${endDate}`);

  return transactions.filter(t => {
    const txDate = t.transaction_date;
    return txDate >= startDate && txDate <= endDate;
  });
}

/**
 * Check if transactions contain December data for a given year.
 *
 * @param transactions All transactions
 * @param year The year to check (e.g., 2024)
 * @returns true if December transactions exist for that year
 */
export function hasDecemberTransactions(
  transactions: Transaction[],
  year: number
): boolean {
  return filterToMonth(transactions, year, 12).length > 0;
}

/**
 * Auto-detect years present in transaction data.
 * Penny is smart enough to see "2024" or "2025" in the dates.
 *
 * @param transactions All transactions
 * @returns Year detection result with primary year recommendation
 */
export function detectYearFromTransactions(
  transactions: Transaction[]
): YearDetectionResult {
  const yearCounts: Record<number, number> = {};
  const decemberCounts: Record<number, number> = {};

  transactions.forEach(t => {
    const date = new Date(t.transaction_date);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;

    yearCounts[year] = (yearCounts[year] || 0) + 1;

    if (month === 12) {
      decemberCounts[year] = (decemberCounts[year] || 0) + 1;
    }
  });

  const detectedYears = Object.keys(yearCounts)
    .map(Number)
    .sort((a, b) => b - a); // Most recent first

  // Primary year = the one with most December transactions
  let primaryYear: number | null = null;
  let maxDecemberCount = 0;

  for (const year of detectedYears) {
    const decCount = decemberCounts[year] || 0;
    if (decCount > maxDecemberCount) {
      maxDecemberCount = decCount;
      primaryYear = year;
    }
  }

  // If no December transactions, use most recent year
  if (!primaryYear && detectedYears.length > 0) {
    primaryYear = detectedYears[0];
  }

  return {
    detectedYears,
    primaryYear,
    hasMultipleYears: detectedYears.length > 1,
    decemberTransactionCount: Object.values(decemberCounts).reduce((a, b) => a + b, 0),
  };
}

/**
 * Calculate opening balance for the Statement Loop using PAY CYCLE logic.
 *
 * Pay Cycle Accounting:
 * - SMEs/freelancers budget from pay day to pay day, not calendar months
 * - "December budget" starts when Nov income arrives (e.g., Nov 25th)
 * - Opening balance = balance BEFORE pay day income
 * - Tax set-aside (39.1%) must include the pay day income
 *
 * Formula: Opening = Closing - (PayCycle_Inflows) + (PayCycle_Outflows)
 *
 * @param closingBalance The user's bank balance at statement end
 * @param transactions All transactions (will be filtered to pay cycle)
 * @param year The anchor year (default: auto-detect or 2024)
 * @param payCycleStartDay Day when income arrives (default: 25)
 * @returns Anchor result with derived opening balance
 */
export function getDecemberAnchorBalance(
  closingBalance: number,
  transactions: Transaction[],
  year?: number,
  payCycleStartDay: number = 25
): DecemberAnchorResult {
  // Auto-detect year if not provided
  const detectedYear = year ?? detectYearFromTransactions(transactions).primaryYear ?? 2024;

  // Filter to PAY CYCLE (e.g., Nov 25 to Dec 31 for "December" budget)
  const payCycleTransactions = filterToPayCycle(transactions, detectedYear, 12, payCycleStartDay);

  console.log(`Pay Cycle Anchor: ${payCycleTransactions.length} transactions from pay cycle`);
  console.log(`Pay Cycle Start Day: ${payCycleStartDay}, Closing Balance: ${closingBalance}`);

  // Calculate using the standard formula on PAY CYCLE transactions
  const result = getDerivedOpeningBalance(closingBalance, payCycleTransactions);

  console.log(`Derived Opening Balance: ${result.derivedOpeningBalance}`);
  console.log(`Inflows: ${result.totalInflows}, Outflows: ${result.totalOutflows}`);

  return {
    ...result,
    anchorYear: detectedYear,
    anchorMonth: 12,
    dec1OpeningBalance: result.derivedOpeningBalance,
    dec30Balance: closingBalance,
  };
}

/**
 * Validate that December transactions are sufficient for anchoring.
 * Penny needs at least a few transactions to establish a reliable anchor.
 *
 * @param transactions December transactions
 * @returns Validation result with recommendations
 */
export function validateDecemberAnchor(
  transactions: Transaction[]
): { isValid: boolean; message: string; transactionCount: number } {
  const count = transactions.length;

  if (count === 0) {
    return {
      isValid: false,
      message: 'No December transactions found. Please upload a statement with December data.',
      transactionCount: 0,
    };
  }

  if (count < 5) {
    return {
      isValid: true,
      message: `Only ${count} transactions found. Your anchor may have limited accuracy.`,
      transactionCount: count,
    };
  }

  return {
    isValid: true,
    message: `${count} transactions ready for anchoring.`,
    transactionCount: count,
  };
}
