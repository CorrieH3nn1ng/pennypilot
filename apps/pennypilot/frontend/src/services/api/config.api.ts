import { apiClient } from './client';

/**
 * Currency Configuration
 */
export interface CurrencyConfig {
  code: string; // ISO 4217 (ZAR, USD, GBP)
  symbol: string; // R, $, £
  name: string; // South African Rand
  position: 'before' | 'after';
  decimal_separator: string;
  thousands_separator: string;
}

/**
 * VAT Configuration
 */
export interface VatConfig {
  rate: number; // e.g., 15 for 15%
  rate_decimal: string; // e.g., "0.15"
  threshold: number | null; // VAT registration threshold
  name: string; // VAT, GST, Sales Tax
}

/**
 * Fiscal Year Configuration
 */
export interface FiscalConfig {
  start_month: number; // 1=Jan, 3=Mar for SA
  current_tax_year: number;
  tax_year_label: string; // "2024/2025"
  tax_authority: string | null;
}

/**
 * Tax Bracket
 */
export interface TaxBracket {
  min: number;
  max: number | null;
  rate: number;
  base: number;
}

/**
 * Tax Rebates
 */
export interface TaxRebates {
  primary: number;
  secondary: number;
  tertiary: number;
}

/**
 * Feature Flags
 */
export interface FeatureFlags {
  provisional_tax_enabled: boolean;
  vat_tracking_enabled: boolean;
}

/**
 * Locale Configuration
 */
export interface LocaleConfig {
  code: string; // en-ZA
  date_format: string; // d/m/Y
  timezone: string;
}

/**
 * Complete Country Configuration
 *
 * This is the single source of truth for all regional settings.
 * Load this on app init and use throughout the application.
 */
export interface CountryConfig {
  country_code: string;
  country_name: string;
  currency: CurrencyConfig;
  vat: VatConfig;
  fiscal: FiscalConfig;
  income_tax_brackets: TaxBracket[];
  rebates: TaxRebates;
  features: FeatureFlags;
  locale: LocaleConfig;
}

/**
 * Country list item (for dropdowns)
 */
export interface CountryListItem {
  country_code: string;
  country_name: string;
  currency_code: string;
  currency_symbol: string;
}

/**
 * Config API Service
 *
 * Provides access to regional configuration.
 * Cache the result and use throughout the app.
 */
export const configApi = {
  /**
   * Get the current user's country configuration.
   * This is the primary method - use after login.
   */
  async getConfig(): Promise<CountryConfig> {
    const response = await apiClient.getRaw<{ success: boolean; data: CountryConfig }>('/config');
    return response.data;
  },

  /**
   * Get config for a specific country code.
   * Use for previewing other countries or before login.
   */
  async getConfigByCountry(countryCode: string): Promise<CountryConfig> {
    const response = await apiClient.getRaw<{ success: boolean; data: CountryConfig }>(
      `/config/${countryCode}`
    );
    return response.data;
  },

  /**
   * Get list of supported countries.
   * Use for country selection dropdowns.
   */
  async getCountries(): Promise<CountryListItem[]> {
    const response = await apiClient.getRaw<{ success: boolean; data: CountryListItem[] }>(
      '/config/countries'
    );
    return response.data;
  },
};

/**
 * Format amount with currency from config
 *
 * @param amount The amount to format
 * @param config The country config
 * @returns Formatted string like "R 1 234,56" or "$1,234.56"
 */
export function formatCurrency(amount: number | string, config: CountryConfig): string {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount;
  const abs = Math.abs(num);
  const sign = num < 0 ? '-' : '';

  // Format with locale-appropriate separators
  const [intPart, decPart] = abs.toFixed(2).split('.');
  const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, config.currency.thousands_separator);
  const formatted = `${formattedInt}${config.currency.decimal_separator}${decPart}`;

  // Apply currency symbol position
  if (config.currency.position === 'before') {
    return `${sign}${config.currency.symbol} ${formatted}`;
  }
  return `${sign}${formatted} ${config.currency.symbol}`;
}

/**
 * Format amount for input fields (no currency symbol)
 */
export function formatAmountForInput(amount: number | string, config: CountryConfig): string {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount;
  const [intPart, decPart] = num.toFixed(2).split('.');
  const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, config.currency.thousands_separator);
  return `${formattedInt}${config.currency.decimal_separator}${decPart}`;
}

/**
 * Parse amount from localized string
 */
export function parseLocalizedAmount(value: string, config: CountryConfig): number {
  // Remove currency symbol and whitespace
  let cleaned = value.replace(config.currency.symbol, '').trim();

  // Replace thousands separator with nothing
  cleaned = cleaned.split(config.currency.thousands_separator).join('');

  // Replace decimal separator with period for parsing
  cleaned = cleaned.replace(config.currency.decimal_separator, '.');

  return parseFloat(cleaned) || 0;
}

/**
 * Get VAT rate as decimal
 */
export function getVatRateDecimal(config: CountryConfig): number {
  return config.vat.rate / 100;
}

/**
 * Calculate VAT amount
 */
export function calculateVat(netAmount: number, config: CountryConfig): number {
  return netAmount * getVatRateDecimal(config);
}

/**
 * Calculate net from gross (remove VAT)
 */
export function calculateNetFromGross(grossAmount: number, config: CountryConfig): number {
  const rate = getVatRateDecimal(config);
  return grossAmount / (1 + rate);
}
