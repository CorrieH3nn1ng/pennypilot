<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountryConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Global Configuration API Controller
 *
 * Provides country-specific configuration to the frontend.
 * This is the single source of truth for regional settings.
 */
class ConfigController extends Controller
{
    /**
     * Get the current user's country configuration
     *
     * GET /api/config
     *
     * Returns currency, VAT, tax brackets, and other regional settings.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $config = $user?->getCountryConfig() ?? CountryConfig::getDefault();

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config),
        ]);
    }

    /**
     * Get config for a specific country
     *
     * GET /api/config/{countryCode}
     */
    public function showByCountry(string $countryCode): JsonResponse
    {
        $config = CountryConfig::getConfig(strtoupper($countryCode));

        if (!$config || !$config->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Country not supported',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config),
        ]);
    }

    /**
     * Get list of active/supported countries
     *
     * GET /api/config/countries
     */
    public function countries(): JsonResponse
    {
        $countries = CountryConfig::getActiveCountries()
            ->map(fn ($config) => [
                'country_code' => $config->country_code,
                'country_name' => $config->country_name,
                'currency_code' => $config->currency_code,
                'currency_symbol' => $config->currency_symbol,
            ]);

        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }

    /**
     * Format config for API response
     */
    private function formatConfig(CountryConfig $config): array
    {
        return [
            // Country info
            'country_code' => $config->country_code,
            'country_name' => $config->country_name,

            // Currency
            'currency' => [
                'code' => $config->currency_code,
                'symbol' => $config->currency_symbol,
                'name' => $config->currency_name,
                'position' => $config->currency_position,
                'decimal_separator' => $config->decimal_separator,
                'thousands_separator' => $config->thousands_separator,
            ],

            // VAT/Tax
            'vat' => [
                'rate' => (float) $config->vat_rate,
                'rate_decimal' => $config->getVatRateDecimal(),
                'threshold' => $config->vat_threshold ? (float) $config->vat_threshold : null,
                'name' => $config->vat_name,
            ],

            // Fiscal Year
            'fiscal' => [
                'start_month' => $config->fiscal_year_start_month,
                'current_tax_year' => $config->getCurrentTaxYear(),
                'tax_year_label' => $config->getTaxYearLabel(),
                'tax_authority' => $config->tax_authority,
            ],

            // Tax Brackets
            'income_tax_brackets' => $config->income_tax_brackets ?? [],

            // Rebates
            'rebates' => [
                'primary' => $config->getPrimaryRebate(),
                'secondary' => $config->getSecondaryRebate(),
                'tertiary' => $config->getTertiaryRebate(),
            ],

            // Feature Flags
            'features' => [
                'provisional_tax_enabled' => $config->provisional_tax_enabled,
                'vat_tracking_enabled' => $config->vat_tracking_enabled,
            ],

            // Locale
            'locale' => [
                'code' => $config->locale,
                'date_format' => $config->date_format,
                'timezone' => $config->timezone,
            ],
        ];
    }
}
