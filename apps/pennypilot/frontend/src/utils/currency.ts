/**
 * Currency Formatting Utilities for South African Rand (ZAR)
 *
 * South African format: R 1 234,56 (space as thousand separator, comma as decimal)
 * This utility provides consistent formatting across the app and handles
 * input parsing for both comma and point decimal separators.
 */

const ZA_LOCALE = 'en-ZA';

/**
 * Format a number as South African currency (display only)
 * Example: 1234.56 -> "R 1 234,56"
 */
export function formatCurrency(amount: number | null | undefined, showSymbol = true): string {
  const value = amount ?? 0;
  const formatted = Math.abs(value).toLocaleString(ZA_LOCALE, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  if (showSymbol) {
    return value < 0 ? `-R ${formatted}` : `R ${formatted}`;
  }
  return value < 0 ? `-${formatted}` : formatted;
}

/**
 * Format a number with sign indicator for income/expense display
 * Example: -1234.56 -> "-R 1 234,56" (red), 1234.56 -> "+R 1 234,56" (green)
 */
export function formatSignedCurrency(amount: number | null | undefined): {
  text: string;
  color: string;
  isPositive: boolean;
} {
  const value = amount ?? 0;
  const formatted = Math.abs(value).toLocaleString(ZA_LOCALE, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  if (value >= 0) {
    return {
      text: `+R ${formatted}`,
      color: 'positive',
      isPositive: true,
    };
  }
  return {
    text: `-R ${formatted}`,
    color: 'negative',
    isPositive: false,
  };
}

/**
 * Format a number without currency symbol (for display in tables, etc.)
 * Example: 1234.56 -> "1 234,56"
 */
export function formatNumber(amount: number | null | undefined, decimals = 2): string {
  const value = amount ?? 0;
  return value.toLocaleString(ZA_LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

/**
 * Format a percentage
 * Example: 15.5 -> "15,50%"
 */
export function formatPercentage(value: number | null | undefined, decimals = 2): string {
  const num = value ?? 0;
  return `${num.toLocaleString(ZA_LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })}%`;
}

/**
 * Parse a user-input string to a number
 * Accepts both comma (SA format) and point (standard) as decimal separators
 *
 * Examples:
 *   "1234,56" -> 1234.56
 *   "1234.56" -> 1234.56
 *   "1 234,56" -> 1234.56
 *   "R 1 234,56" -> 1234.56
 */
export function parseNumber(input: string | number | null | undefined): number {
  if (input === null || input === undefined || input === '') {
    return 0;
  }

  if (typeof input === 'number') {
    return input;
  }

  // Remove currency symbol, spaces, and thousand separators
  let cleaned = input
    .replace(/[Rr\s]/g, '') // Remove R symbol and spaces
    .replace(/\u00A0/g, ''); // Remove non-breaking spaces

  // Determine which separator is used for decimals
  // If both comma and point exist, the last one is likely the decimal separator
  const lastComma = cleaned.lastIndexOf(',');
  const lastPoint = cleaned.lastIndexOf('.');

  if (lastComma > lastPoint) {
    // Comma is the decimal separator (SA format)
    // Remove any points (thousand separators) and convert comma to point
    cleaned = cleaned.replace(/\./g, '').replace(',', '.');
  } else if (lastPoint > lastComma) {
    // Point is the decimal separator (standard format)
    // Remove any commas (thousand separators)
    cleaned = cleaned.replace(/,/g, '');
  } else if (lastComma !== -1) {
    // Only comma exists - treat as decimal separator
    cleaned = cleaned.replace(',', '.');
  }

  const result = parseFloat(cleaned);
  return isNaN(result) ? 0 : result;
}

/**
 * Format a number for input field display
 * Uses point as decimal separator for HTML input compatibility
 * Example: 1234.56 -> "1234.56"
 */
export function formatForInput(amount: number | null | undefined): string {
  const value = amount ?? 0;
  return value.toFixed(2);
}

/**
 * Compact currency format for small spaces
 * Example: 1234567.89 -> "R 1,23M"
 */
export function formatCompactCurrency(amount: number | null | undefined): string {
  const value = amount ?? 0;
  const absValue = Math.abs(value);

  if (absValue >= 1_000_000) {
    const millions = absValue / 1_000_000;
    return `${value < 0 ? '-' : ''}R ${millions.toLocaleString(ZA_LOCALE, { maximumFractionDigits: 2 })}M`;
  }
  if (absValue >= 1_000) {
    const thousands = absValue / 1_000;
    return `${value < 0 ? '-' : ''}R ${thousands.toLocaleString(ZA_LOCALE, { maximumFractionDigits: 1 })}K`;
  }

  return formatCurrency(value);
}
