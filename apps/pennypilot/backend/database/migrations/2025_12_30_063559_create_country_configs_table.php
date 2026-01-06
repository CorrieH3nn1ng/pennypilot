<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global Configuration Engine
 *
 * Decouples regional logic (VAT, currency, fiscal year) from the core application.
 * Each country has its own configuration for tax rules, currency formatting, and fiscal periods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_configs', function (Blueprint $table) {
            $table->string('country_code', 2)->primary(); // ISO 3166-1 alpha-2 (ZA, US, GB)
            $table->string('country_name', 100);

            // Currency
            $table->string('currency_code', 3); // ISO 4217 (ZAR, USD, GBP)
            $table->string('currency_symbol', 5); // R, $, £
            $table->string('currency_name', 50); // South African Rand
            $table->enum('currency_position', ['before', 'after'])->default('before'); // R 100 vs 100 R
            $table->string('decimal_separator', 1)->default('.'); // . or ,
            $table->string('thousands_separator', 1)->default(' '); // space, comma, period

            // VAT/Tax
            $table->decimal('vat_rate', 5, 2)->default(0); // Standard VAT rate (15.00 for SA)
            $table->decimal('vat_threshold', 15, 2)->nullable(); // VAT registration threshold (1000000 for SA)
            $table->string('vat_name', 20)->default('VAT'); // VAT, GST, Sales Tax

            // Fiscal Year
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1); // 1=Jan, 3=Mar for SA
            $table->string('tax_authority', 100)->nullable(); // SARS, IRS, HMRC

            // Tax Brackets (JSON for flexibility)
            $table->json('income_tax_brackets')->nullable(); // Array of brackets
            $table->json('rebates')->nullable(); // Age-based rebates

            // Feature Flags
            $table->boolean('provisional_tax_enabled')->default(false);
            $table->boolean('vat_tracking_enabled')->default(true);

            // Locale
            $table->string('locale', 10)->default('en'); // en, af, zu
            $table->string('date_format', 20)->default('Y-m-d');
            $table->string('timezone', 50)->default('UTC');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default South Africa config BEFORE adding FK
        DB::table('country_configs')->insert([
            'country_code' => 'ZA',
            'country_name' => 'South Africa',
            'currency_code' => 'ZAR',
            'currency_symbol' => 'R',
            'currency_name' => 'South African Rand',
            'currency_position' => 'before',
            'decimal_separator' => ',',
            'thousands_separator' => ' ',
            'vat_rate' => 15.00,
            'vat_threshold' => 1000000.00,
            'vat_name' => 'VAT',
            'fiscal_year_start_month' => 3,
            'tax_authority' => 'South African Revenue Service (SARS)',
            'income_tax_brackets' => json_encode([
                ['min' => 0, 'max' => 237100, 'rate' => 18, 'base' => 0],
                ['min' => 237101, 'max' => 370500, 'rate' => 26, 'base' => 42678],
                ['min' => 370501, 'max' => 512800, 'rate' => 31, 'base' => 77362],
                ['min' => 512801, 'max' => 673000, 'rate' => 36, 'base' => 121475],
                ['min' => 673001, 'max' => 857900, 'rate' => 39, 'base' => 179147],
                ['min' => 857901, 'max' => 1817000, 'rate' => 41, 'base' => 251258],
                ['min' => 1817001, 'max' => null, 'rate' => 45, 'base' => 644489],
            ]),
            'rebates' => json_encode([
                'primary' => 17235,
                'secondary' => 9444,
                'tertiary' => 3145,
                'tax_threshold' => 95750,
                'tax_threshold_65' => 148217,
                'tax_threshold_75' => 165689,
            ]),
            'provisional_tax_enabled' => true,
            'vat_tracking_enabled' => true,
            'locale' => 'en-ZA',
            'date_format' => 'd/m/Y',
            'timezone' => 'Africa/Johannesburg',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now add country_code to users table with FK
        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 2)->default('ZA')->after('email');
            $table->foreign('country_code')->references('country_code')->on('country_configs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
            $table->dropColumn('country_code');
        });

        Schema::dropIfExists('country_configs');
    }
};
