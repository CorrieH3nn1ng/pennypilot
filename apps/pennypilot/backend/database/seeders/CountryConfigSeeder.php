<?php

namespace Database\Seeders;

use App\Models\CountryConfig;
use Illuminate\Database\Seeder;

/**
 * Country Configuration Seeder
 *
 * Seeds regional configurations for supported countries.
 * South Africa is the primary market with full tax bracket support.
 */
class CountryConfigSeeder extends Seeder
{
    public function run(): void
    {
        // South Africa - Primary Market
        CountryConfig::updateOrCreate(
            ['country_code' => 'ZA'],
            [
                'country_name' => 'South Africa',

                // Currency
                'currency_code' => 'ZAR',
                'currency_symbol' => 'R',
                'currency_name' => 'South African Rand',
                'currency_position' => 'before',
                'decimal_separator' => ',',
                'thousands_separator' => ' ',

                // VAT
                'vat_rate' => 15.00,
                'vat_threshold' => 1000000.00, // R1 million
                'vat_name' => 'VAT',

                // Fiscal Year (March to February)
                'fiscal_year_start_month' => 3,
                'tax_authority' => 'South African Revenue Service (SARS)',

                // SARS 2024/2025 Tax Brackets
                'income_tax_brackets' => [
                    ['min' => 0, 'max' => 237100, 'rate' => 18, 'base' => 0],
                    ['min' => 237101, 'max' => 370500, 'rate' => 26, 'base' => 42678],
                    ['min' => 370501, 'max' => 512800, 'rate' => 31, 'base' => 77362],
                    ['min' => 512801, 'max' => 673000, 'rate' => 36, 'base' => 121475],
                    ['min' => 673001, 'max' => 857900, 'rate' => 39, 'base' => 179147],
                    ['min' => 857901, 'max' => 1817000, 'rate' => 41, 'base' => 251258],
                    ['min' => 1817001, 'max' => null, 'rate' => 45, 'base' => 644489],
                ],

                // SARS Rebates 2024/2025
                'rebates' => [
                    'primary' => 17235, // All taxpayers
                    'secondary' => 9444, // 65 and older
                    'tertiary' => 3145, // 75 and older
                    'tax_threshold' => 95750, // Under 65
                    'tax_threshold_65' => 148217, // 65-74
                    'tax_threshold_75' => 165689, // 75+
                ],

                // Feature Flags
                'provisional_tax_enabled' => true,
                'vat_tracking_enabled' => true,

                // Locale
                'locale' => 'en-ZA',
                'date_format' => 'd/m/Y',
                'timezone' => 'Africa/Johannesburg',

                'is_active' => true,
            ]
        );

        // United States - Placeholder for future expansion
        CountryConfig::updateOrCreate(
            ['country_code' => 'US'],
            [
                'country_name' => 'United States',

                // Currency
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'currency_name' => 'US Dollar',
                'currency_position' => 'before',
                'decimal_separator' => '.',
                'thousands_separator' => ',',

                // Sales Tax (varies by state, default 0)
                'vat_rate' => 0,
                'vat_threshold' => null,
                'vat_name' => 'Sales Tax',

                // Fiscal Year (Calendar year)
                'fiscal_year_start_month' => 1,
                'tax_authority' => 'Internal Revenue Service (IRS)',

                // US Federal Tax Brackets 2024 (Single filer)
                'income_tax_brackets' => [
                    ['min' => 0, 'max' => 11600, 'rate' => 10, 'base' => 0],
                    ['min' => 11601, 'max' => 47150, 'rate' => 12, 'base' => 1160],
                    ['min' => 47151, 'max' => 100525, 'rate' => 22, 'base' => 5426],
                    ['min' => 100526, 'max' => 191950, 'rate' => 24, 'base' => 17168.50],
                    ['min' => 191951, 'max' => 243725, 'rate' => 32, 'base' => 39110.50],
                    ['min' => 243726, 'max' => 609350, 'rate' => 35, 'base' => 55678.50],
                    ['min' => 609351, 'max' => null, 'rate' => 37, 'base' => 183647.25],
                ],

                'rebates' => [
                    'standard_deduction' => 14600, // 2024 single filer
                ],

                'provisional_tax_enabled' => true,
                'vat_tracking_enabled' => false,

                'locale' => 'en-US',
                'date_format' => 'm/d/Y',
                'timezone' => 'America/New_York',

                'is_active' => false, // Not yet supported
            ]
        );

        // United Kingdom - Placeholder for future expansion
        CountryConfig::updateOrCreate(
            ['country_code' => 'GB'],
            [
                'country_name' => 'United Kingdom',

                // Currency
                'currency_code' => 'GBP',
                'currency_symbol' => '£',
                'currency_name' => 'British Pound',
                'currency_position' => 'before',
                'decimal_separator' => '.',
                'thousands_separator' => ',',

                // VAT
                'vat_rate' => 20.00,
                'vat_threshold' => 85000.00, // £85,000
                'vat_name' => 'VAT',

                // Fiscal Year (April to March)
                'fiscal_year_start_month' => 4,
                'tax_authority' => 'HM Revenue & Customs (HMRC)',

                // UK Income Tax Bands 2024/25
                'income_tax_brackets' => [
                    ['min' => 0, 'max' => 12570, 'rate' => 0, 'base' => 0], // Personal allowance
                    ['min' => 12571, 'max' => 50270, 'rate' => 20, 'base' => 0],
                    ['min' => 50271, 'max' => 125140, 'rate' => 40, 'base' => 7540],
                    ['min' => 125141, 'max' => null, 'rate' => 45, 'base' => 37428],
                ],

                'rebates' => [
                    'personal_allowance' => 12570,
                ],

                'provisional_tax_enabled' => false,
                'vat_tracking_enabled' => true,

                'locale' => 'en-GB',
                'date_format' => 'd/m/Y',
                'timezone' => 'Europe/London',

                'is_active' => false, // Not yet supported
            ]
        );

        $this->command->info('Country configurations seeded successfully.');
        $this->command->info('Active countries: South Africa (ZA)');
    }
}
