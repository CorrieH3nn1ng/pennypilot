<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Country Configuration Model
 *
 * Stores regional settings for VAT, currency, fiscal year, and tax brackets.
 * This is the SINGLE SOURCE OF TRUTH for all regional financial calculations.
 *
 * @property string $country_code ISO 3166-1 alpha-2 code (ZA, US, GB)
 * @property string $country_name Full country name
 * @property string $currency_code ISO 4217 code (ZAR, USD, GBP)
 * @property string $currency_symbol Display symbol (R, $, £)
 * @property string $currency_name Full currency name
 * @property string $currency_position 'before' or 'after'
 * @property string $decimal_separator
 * @property string $thousands_separator
 * @property float $vat_rate Standard VAT rate (15.00 for SA)
 * @property float|null $vat_threshold VAT registration threshold
 * @property string $vat_name VAT, GST, Sales Tax
 * @property int $fiscal_year_start_month 1=Jan, 3=Mar for SA
 * @property string|null $tax_authority SARS, IRS, HMRC
 * @property array|null $income_tax_brackets
 * @property array|null $rebates
 * @property bool $provisional_tax_enabled
 * @property bool $vat_tracking_enabled
 * @property string $locale
 * @property string $date_format
 * @property string $timezone
 * @property bool $is_active
 */
class CountryConfig extends Model
{
    protected $primaryKey = 'country_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'currency_symbol',
        'currency_name',
        'currency_position',
        'decimal_separator',
        'thousands_separator',
        'vat_rate',
        'vat_threshold',
        'vat_name',
        'fiscal_year_start_month',
        'tax_authority',
        'income_tax_brackets',
        'rebates',
        'provisional_tax_enabled',
        'vat_tracking_enabled',
        'locale',
        'date_format',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'vat_rate' => 'decimal:2',
        'vat_threshold' => 'decimal:2',
        'fiscal_year_start_month' => 'integer',
        'income_tax_brackets' => 'array',
        'rebates' => 'array',
        'provisional_tax_enabled' => 'boolean',
        'vat_tracking_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get users in this country
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_code', 'country_code');
    }

    /**
     * Get config by country code with caching
     */
    public static function getConfig(string $countryCode): ?self
    {
        return Cache::remember(
            "country_config:{$countryCode}",
            self::CACHE_TTL,
            fn () => self::find($countryCode)
        );
    }

    /**
     * Get the default country config (South Africa)
     */
    public static function getDefault(): self
    {
        return self::getConfig('ZA') ?? throw new \RuntimeException('Default country config (ZA) not found');
    }

    /**
     * Clear cache for a specific country
     */
    public static function clearCache(string $countryCode): void
    {
        Cache::forget("country_config:{$countryCode}");
    }

    /**
     * Format an amount with the country's currency
     *
     * @param float|string $amount
     * @return string Formatted amount (e.g., "R 1 234,56")
     */
    public function formatCurrency($amount): string
    {
        $amount = (float) $amount;

        $formatted = number_format(
            abs($amount),
            2,
            $this->decimal_separator,
            $this->thousands_separator
        );

        $sign = $amount < 0 ? '-' : '';

        if ($this->currency_position === 'before') {
            return $sign . $this->currency_symbol . ' ' . $formatted;
        }

        return $sign . $formatted . ' ' . $this->currency_symbol;
    }

    /**
     * Get VAT rate as a decimal (e.g., 0.15 for 15%)
     */
    public function getVatRateDecimal(): string
    {
        return bcdiv((string) $this->vat_rate, '100', 6);
    }

    /**
     * Calculate VAT on a net amount
     */
    public function calculateVat(string $netAmount): string
    {
        return bcmul($netAmount, $this->getVatRateDecimal(), 2);
    }

    /**
     * Calculate gross from net (add VAT)
     */
    public function calculateGrossFromNet(string $netAmount): string
    {
        $vat = $this->calculateVat($netAmount);
        return bcadd($netAmount, $vat, 2);
    }

    /**
     * Calculate net from gross (remove VAT)
     */
    public function calculateNetFromGross(string $grossAmount): string
    {
        $divisor = bcadd('1', $this->getVatRateDecimal(), 6);
        return bcdiv($grossAmount, $divisor, 2);
    }

    /**
     * Get the current tax year based on fiscal year start
     */
    public function getCurrentTaxYear(): int
    {
        $now = now();
        $year = $now->year;
        $month = $now->month;

        // If we're before the fiscal year start, we're in the previous tax year
        if ($month < $this->fiscal_year_start_month) {
            return $year;
        }

        // If fiscal year starts in month > 1, tax year is labeled as next calendar year
        return $this->fiscal_year_start_month > 1 ? $year + 1 : $year;
    }

    /**
     * Get tax year label (e.g., "2024/2025" for SA)
     */
    public function getTaxYearLabel(?int $taxYear = null): string
    {
        $year = $taxYear ?? $this->getCurrentTaxYear();

        if ($this->fiscal_year_start_month > 1) {
            return ($year - 1) . '/' . $year;
        }

        return (string) $year;
    }

    /**
     * Get primary rebate amount for tax calculations
     */
    public function getPrimaryRebate(): float
    {
        $rebates = $this->rebates ?? [];
        return (float) ($rebates['primary'] ?? 0);
    }

    /**
     * Get secondary rebate (65+)
     */
    public function getSecondaryRebate(): float
    {
        $rebates = $this->rebates ?? [];
        return (float) ($rebates['secondary'] ?? 0);
    }

    /**
     * Get tertiary rebate (75+)
     */
    public function getTertiaryRebate(): float
    {
        $rebates = $this->rebates ?? [];
        return (float) ($rebates['tertiary'] ?? 0);
    }

    /**
     * Get all active countries for dropdown
     */
    public static function getActiveCountries(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            'active_countries',
            self::CACHE_TTL,
            fn () => self::where('is_active', true)->orderBy('country_name')->get()
        );
    }

    /**
     * Boot method to clear cache on save
     */
    protected static function booted(): void
    {
        static::saved(function (self $config) {
            self::clearCache($config->country_code);
            Cache::forget('active_countries');
        });

        static::deleted(function (self $config) {
            self::clearCache($config->country_code);
            Cache::forget('active_countries');
        });
    }
}
