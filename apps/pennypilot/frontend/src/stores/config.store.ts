import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  configApi,
  type CountryConfig,
  formatCurrency as formatCurrencyUtil,
} from '@/services/api/config.api';

/**
 * Global Configuration Store
 *
 * This store is the single source of truth for all regional settings.
 * It should be initialized on app start and used throughout.
 *
 * Usage:
 *   const configStore = useConfigStore();
 *   await configStore.loadConfig();
 *   const formatted = configStore.formatCurrency(1234.56);
 */
export const useConfigStore = defineStore('config', () => {
  // State
  const config = ref<CountryConfig | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const isLoaded = computed(() => config.value !== null);

  const currencySymbol = computed(() => config.value?.currency.symbol ?? 'R');

  const vatRate = computed(() => config.value?.vat.rate ?? 15);

  const vatRateDecimal = computed(() => (config.value?.vat.rate ?? 15) / 100);

  const vatThreshold = computed(() => config.value?.vat.threshold ?? 1000000);

  const countryCode = computed(() => config.value?.country_code ?? 'ZA');

  const taxBrackets = computed(() => config.value?.income_tax_brackets ?? []);

  const rebates = computed(() => config.value?.rebates ?? { primary: 17235, secondary: 9444, tertiary: 3145 });

  const fiscalYearStartMonth = computed(() => config.value?.fiscal.start_month ?? 3);

  const taxYearLabel = computed(() => config.value?.fiscal.tax_year_label ?? '2024/2025');

  const provisionalTaxEnabled = computed(() => config.value?.features.provisional_tax_enabled ?? true);

  const vatTrackingEnabled = computed(() => config.value?.features.vat_tracking_enabled ?? true);

  // Actions
  async function loadConfig() {
    if (isLoading.value) return;

    isLoading.value = true;
    error.value = null;

    try {
      config.value = await configApi.getConfig();
    } catch (e) {
      console.error('Failed to load config:', e);
      error.value = 'Failed to load configuration';

      // Fall back to default ZA config
      try {
        config.value = await configApi.getConfigByCountry('ZA');
      } catch {
        // Use hardcoded fallback
        config.value = getDefaultConfig();
      }
    } finally {
      isLoading.value = false;
    }
  }

  async function loadConfigByCountry(countryCode: string) {
    isLoading.value = true;
    error.value = null;

    try {
      config.value = await configApi.getConfigByCountry(countryCode);
    } catch (e) {
      console.error(`Failed to load config for ${countryCode}:`, e);
      error.value = 'Country not supported';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Format currency using the current config
   *
   * @param amount Amount to format
   * @returns Formatted string like "R 1 234,56"
   */
  function formatCurrency(amount: number | string): string {
    if (!config.value) {
      // Fallback for ZA format
      const num = typeof amount === 'string' ? parseFloat(amount) : amount;
      return `R ${num.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    return formatCurrencyUtil(amount, config.value);
  }

  /**
   * Format amount without currency symbol (for display)
   */
  function formatAmount(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (!config.value) {
      return num.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const abs = Math.abs(num);
    const [intPart, decPart] = abs.toFixed(2).split('.');
    const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, config.value.currency.thousands_separator);
    const sign = num < 0 ? '-' : '';
    return `${sign}${formattedInt}${config.value.currency.decimal_separator}${decPart}`;
  }

  /**
   * Calculate VAT on a net amount
   */
  function calculateVat(netAmount: number): number {
    return netAmount * vatRateDecimal.value;
  }

  /**
   * Calculate net from gross (remove VAT)
   */
  function calculateNetFromGross(grossAmount: number): number {
    return grossAmount / (1 + vatRateDecimal.value);
  }

  /**
   * Get hardcoded fallback config (ZA)
   */
  function getDefaultConfig(): CountryConfig {
    return {
      country_code: 'ZA',
      country_name: 'South Africa',
      currency: {
        code: 'ZAR',
        symbol: 'R',
        name: 'South African Rand',
        position: 'before',
        decimal_separator: ',',
        thousands_separator: ' ',
      },
      vat: {
        rate: 15,
        rate_decimal: '0.15',
        threshold: 1000000,
        name: 'VAT',
      },
      fiscal: {
        start_month: 3,
        current_tax_year: new Date().getFullYear() + (new Date().getMonth() >= 2 ? 1 : 0),
        tax_year_label: `${new Date().getFullYear()}/${new Date().getFullYear() + 1}`,
        tax_authority: 'South African Revenue Service (SARS)',
      },
      income_tax_brackets: [
        { min: 0, max: 237100, rate: 18, base: 0 },
        { min: 237101, max: 370500, rate: 26, base: 42678 },
        { min: 370501, max: 512800, rate: 31, base: 77362 },
        { min: 512801, max: 673000, rate: 36, base: 121475 },
        { min: 673001, max: 857900, rate: 39, base: 179147 },
        { min: 857901, max: 1817000, rate: 41, base: 251258 },
        { min: 1817001, max: null, rate: 45, base: 644489 },
      ],
      rebates: {
        primary: 17235,
        secondary: 9444,
        tertiary: 3145,
      },
      features: {
        provisional_tax_enabled: true,
        vat_tracking_enabled: true,
      },
      locale: {
        code: 'en-ZA',
        date_format: 'd/m/Y',
        timezone: 'Africa/Johannesburg',
      },
    };
  }

  return {
    // State
    config,
    isLoading,
    error,

    // Getters
    isLoaded,
    currencySymbol,
    vatRate,
    vatRateDecimal,
    vatThreshold,
    countryCode,
    taxBrackets,
    rebates,
    fiscalYearStartMonth,
    taxYearLabel,
    provisionalTaxEnabled,
    vatTrackingEnabled,

    // Actions
    loadConfig,
    loadConfigByCountry,
    formatCurrency,
    formatAmount,
    calculateVat,
    calculateNetFromGross,
  };
});
