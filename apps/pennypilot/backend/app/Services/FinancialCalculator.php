<?php

namespace App\Services;

use App\Models\CountryConfig;

/**
 * Financial Calculator - Global Configuration Engine
 *
 * Uses BCMath for high-precision arithmetic to meet institutional
 * accounting standards. All regional settings (VAT rate, thresholds)
 * are loaded from CountryConfig.
 *
 * Rules:
 * - All intermediate calculations at scale 4
 * - Final amounts rounded to 2 decimal places
 * - VAT uses "Round Half Up" (PHP_ROUND_HALF_UP)
 */
class FinancialCalculator
{
    // Internal calculation scale (4 decimal places)
    private const CALC_SCALE = 4;

    // Display/storage scale (2 decimal places)
    private const DISPLAY_SCALE = 2;

    /**
     * Get VAT rate from config (or default to ZA's 15%)
     *
     * @deprecated Use CountryConfig::getDefault()->vat_rate instead
     */
    public const VAT_RATE = '15.00';

    /**
     * Get VAT threshold from config (or default to ZA's R1M)
     *
     * @deprecated Use CountryConfig::getDefault()->vat_threshold instead
     */
    public const VAT_THRESHOLD = '1000000.00';

    /**
     * Get VAT warning threshold (80% of threshold)
     *
     * @deprecated Calculate from CountryConfig::getDefault()->vat_threshold
     */
    public const VAT_WARNING_THRESHOLD = '800000.00';

    /**
     * Get the VAT rate from config
     */
    public static function getVatRate(?CountryConfig $config = null): string
    {
        $config = $config ?? CountryConfig::getDefault();
        return (string) $config->vat_rate;
    }

    /**
     * Get the VAT threshold from config
     */
    public static function getVatThreshold(?CountryConfig $config = null): string
    {
        $config = $config ?? CountryConfig::getDefault();
        return (string) ($config->vat_threshold ?? '1000000.00');
    }

    /**
     * Get the VAT warning threshold (80% of threshold)
     */
    public static function getVatWarningThreshold(?CountryConfig $config = null): string
    {
        $threshold = self::getVatThreshold($config);
        return self::multiply($threshold, '0.80');
    }

    /**
     * Get currency symbol from config
     */
    public static function getCurrencySymbol(?CountryConfig $config = null): string
    {
        $config = $config ?? CountryConfig::getDefault();
        return $config->currency_symbol;
    }

    /**
     * Add two monetary values
     */
    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::CALC_SCALE);
    }

    /**
     * Subtract two monetary values
     */
    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, self::CALC_SCALE);
    }

    /**
     * Multiply two monetary values
     */
    public static function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, self::CALC_SCALE);
    }

    /**
     * Divide two monetary values
     */
    public static function divide(string $a, string $b): string
    {
        if (bccomp($b, '0', self::CALC_SCALE) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return bcdiv($a, $b, self::CALC_SCALE);
    }

    /**
     * Compare two monetary values
     * Returns: -1 if a < b, 0 if a == b, 1 if a > b
     */
    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::CALC_SCALE);
    }

    /**
     * Round to display scale (2 decimal places) using ROUND_HALF_UP
     * This is the SARS-compliant rounding method
     */
    public static function round(string $value): string
    {
        // BCMath doesn't have native rounding, so we implement ROUND_HALF_UP
        $factor = bcpow('10', (string) self::DISPLAY_SCALE, 0);
        $multiplied = bcmul($value, $factor, self::CALC_SCALE);

        // Add 0.5 for positive, subtract 0.5 for negative (ROUND_HALF_UP)
        if (bccomp($multiplied, '0', self::CALC_SCALE) >= 0) {
            $multiplied = bcadd($multiplied, '0.5', self::CALC_SCALE);
        } else {
            $multiplied = bcsub($multiplied, '0.5', self::CALC_SCALE);
        }

        // Truncate to integer
        $truncated = self::truncateToInt($multiplied);

        // Divide back
        return bcdiv($truncated, $factor, self::DISPLAY_SCALE);
    }

    /**
     * Truncate decimal to integer string (floor for positive, ceil for negative)
     */
    private static function truncateToInt(string $value): string
    {
        $parts = explode('.', $value);
        return $parts[0];
    }

    /**
     * Calculate line item amount (quantity × unit_price)
     * Returns value at CALC_SCALE (4 decimals)
     */
    public static function calculateLineItemAmount(string $quantity, string $unitPrice): string
    {
        return self::multiply($quantity, $unitPrice);
    }

    /**
     * Calculate VAT amount from net amount
     * Uses SARS Round Half Up
     *
     * @param string $netAmount The net amount before VAT
     * @param string|null $vatRate Optional VAT rate (loads from config if null)
     * @param CountryConfig|null $config Optional country config
     */
    public static function calculateVat(
        string $netAmount,
        ?string $vatRate = null,
        ?CountryConfig $config = null
    ): string {
        $rate = $vatRate ?? self::getVatRate($config);
        $vatDecimal = self::divide($rate, '100');
        $vatAmount = self::multiply($netAmount, $vatDecimal);

        return self::round($vatAmount);
    }

    /**
     * Calculate net amount from gross (VAT-inclusive) amount
     *
     * @param string $grossAmount The gross amount including VAT
     * @param string|null $vatRate Optional VAT rate (loads from config if null)
     * @param CountryConfig|null $config Optional country config
     */
    public static function calculateNetFromGross(
        string $grossAmount,
        ?string $vatRate = null,
        ?CountryConfig $config = null
    ): string {
        $rate = $vatRate ?? self::getVatRate($config);
        $vatDecimal = self::divide($rate, '100');
        $divisor = self::add('1', $vatDecimal);
        $netAmount = self::divide($grossAmount, $divisor);

        return self::round($netAmount);
    }

    /**
     * Calculate invoice totals from line items
     * Returns array with subtotal, tax_amount, total (all rounded to 2 decimals)
     *
     * @param array $lineItems Array of line items with quantity and unit_price
     * @param string $vatRate VAT rate to apply (use '0' for no VAT)
     */
    public static function calculateInvoiceTotals(array $lineItems, string $vatRate = '0'): array
    {
        // Sum all line item amounts at full precision
        $subtotal = '0';
        foreach ($lineItems as $item) {
            $lineAmount = self::calculateLineItemAmount(
                (string) ($item['quantity'] ?? '0'),
                (string) ($item['unit_price'] ?? '0')
            );
            $subtotal = self::add($subtotal, $lineAmount);
        }

        // Round subtotal
        $subtotal = self::round($subtotal);

        // Calculate VAT on rounded subtotal
        $taxAmount = '0.00';
        if (self::compare($vatRate, '0') > 0) {
            $taxAmount = self::calculateVat($subtotal, $vatRate);
        }

        // Total = subtotal + VAT
        $total = self::round(self::add($subtotal, $taxAmount));

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /**
     * Validate invoice integrity
     * Ensures: Sum(Line_Item_Net) + Sum(Line_Item_VAT) == Total_Invoice_Amount
     */
    public static function validateInvoiceIntegrity(
        string $subtotal,
        string $taxAmount,
        string $total
    ): bool {
        $expectedTotal = self::round(self::add($subtotal, $taxAmount));
        return self::compare($expectedTotal, $total) === 0;
    }

    /**
     * Check VAT registration threshold status
     * Returns: null (under warning), 'warning' (80%-100%), 'exceeded' (over 100%)
     *
     * @param string $turnover Rolling 12-month turnover
     * @param CountryConfig|null $config Optional country config
     */
    public static function checkVatThresholdStatus(string $turnover, ?CountryConfig $config = null): ?string
    {
        $threshold = self::getVatThreshold($config);
        $warningThreshold = self::getVatWarningThreshold($config);

        if (self::compare($turnover, $threshold) >= 0) {
            return 'exceeded'; // Red warning - must register
        }

        if (self::compare($turnover, $warningThreshold) >= 0) {
            return 'warning'; // Yellow warning - approaching threshold
        }

        return null; // Safe
    }

    /**
     * Format amount for display (with 2 decimals, no currency symbol)
     */
    public static function format(string $value): string
    {
        return number_format((float) self::round($value), 2, '.', '');
    }

    /**
     * Format amount with currency symbol from config
     *
     * @param string|float $value The amount to format
     * @param CountryConfig|null $config Optional country config
     */
    public static function formatWithCurrency($value, ?CountryConfig $config = null): string
    {
        $config = $config ?? CountryConfig::getDefault();
        return $config->formatCurrency($value);
    }

    /**
     * Convert float/int to BCMath string
     */
    public static function toScale(float|int|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        return number_format($value, self::CALC_SCALE, '.', '');
    }

    /**
     * Sum an array of monetary values
     */
    public static function sum(array $values): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = self::add($total, self::toScale($value));
        }
        return $total;
    }
}
