<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Income Source Model
 *
 * Hierarchy: Client → Income Source → Invoices
 * Each income source belongs to a client and can have multiple invoices.
 */
class IncomeSource extends Model
{
    use HasFactory, HasUuids;

    /**
     * SARS 2025/26 Tax Tables
     * Primary Rebate: R17,235 (for under 65)
     * Reference: SARS Tax Tables 1 March 2025 - 28 February 2026
     */
    private const PRIMARY_REBATE_2025_26 = 17235;

    private const TAX_BRACKETS_2025_26 = [
        ['min' => 1, 'max' => 237100, 'rate' => 0.18, 'base' => 0],
        ['min' => 237101, 'max' => 370500, 'rate' => 0.26, 'base' => 42678],
        ['min' => 370501, 'max' => 512800, 'rate' => 0.31, 'base' => 77362],
        ['min' => 512801, 'max' => 673000, 'rate' => 0.36, 'base' => 121475],
        ['min' => 673001, 'max' => 857900, 'rate' => 0.39, 'base' => 179147],
        ['min' => 857901, 'max' => 1817000, 'rate' => 0.41, 'base' => 251258],
        ['min' => 1817001, 'max' => PHP_INT_MAX, 'rate' => 0.45, 'base' => 644489],
    ];

    protected $fillable = [
        'user_id',
        'client_id',
        'name',
        'type',
        'gross_amount',
        'net_amount',
        'tax_amount',
        'frequency',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // =========================================
    // Relationships (Hierarchy: Client → Income Source → Invoices)
    // =========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // =========================================
    // Computed Attributes
    // =========================================

    /**
     * Get monthly equivalent amount
     */
    public function getMonthlyAmountAttribute(): float
    {
        $amount = (float) $this->gross_amount;

        return match ($this->frequency) {
            'weekly' => $amount * 52 / 12,
            'bi_weekly' => $amount * 26 / 12,
            'annual' => $amount / 12,
            'irregular' => $amount, // User should enter monthly average
            default => $amount, // monthly
        };
    }

    /**
     * Get annualized income from this source
     */
    public function getAnnualAmountAttribute(): float
    {
        return $this->monthly_amount * 12;
    }

    /**
     * Calculate provisional tax estimate for this income source
     * Logic: (Annualized Amount × Bracket Rate) - Pro-rated Primary Rebate
     *
     * The pro-rated rebate is calculated based on this source's contribution
     * to total annual income.
     */
    public function getTaxEstimateAttribute(): array
    {
        $annualAmount = $this->annual_amount;

        if ($annualAmount <= 0) {
            return [
                'annual_tax' => 0,
                'monthly_tax' => 0,
                'effective_rate' => 0,
                'marginal_rate' => 0,
                'rebate_applied' => 0,
            ];
        }

        // Calculate tax before rebates using SARS brackets
        $taxBeforeRebate = $this->calculateTaxFromBrackets($annualAmount);

        // Apply full primary rebate (assuming single income source for simplicity)
        // In practice, rebate should be allocated proportionally across sources
        $rebateApplied = min(self::PRIMARY_REBATE_2025_26, $taxBeforeRebate);
        $annualTax = max(0, $taxBeforeRebate - $rebateApplied);

        $monthlyTax = $annualTax / 12;
        $effectiveRate = $annualAmount > 0 ? ($annualTax / $annualAmount) * 100 : 0;
        $marginalRate = $this->getMarginalRate($annualAmount) * 100;

        return [
            'annual_tax' => round($annualTax, 2),
            'monthly_tax' => round($monthlyTax, 2),
            'effective_rate' => round($effectiveRate, 2),
            'marginal_rate' => round($marginalRate, 0),
            'rebate_applied' => round($rebateApplied, 2),
        ];
    }

    /**
     * Calculate tax using SARS 2025/26 brackets
     */
    private function calculateTaxFromBrackets(float $taxableIncome): float
    {
        if ($taxableIncome <= 0) {
            return 0;
        }

        foreach (self::TAX_BRACKETS_2025_26 as $bracket) {
            if ($taxableIncome >= $bracket['min'] && $taxableIncome <= $bracket['max']) {
                $excessAmount = $taxableIncome - $bracket['min'] + 1;
                return $bracket['base'] + ($excessAmount * $bracket['rate']);
            }
        }

        // Fallback to highest bracket
        $lastBracket = end(self::TAX_BRACKETS_2025_26);
        $excessAmount = $taxableIncome - $lastBracket['min'] + 1;
        return $lastBracket['base'] + ($excessAmount * $lastBracket['rate']);
    }

    /**
     * Get marginal tax rate for given income
     */
    private function getMarginalRate(float $taxableIncome): float
    {
        foreach (self::TAX_BRACKETS_2025_26 as $bracket) {
            if ($taxableIncome >= $bracket['min'] && $taxableIncome <= $bracket['max']) {
                return $bracket['rate'];
            }
        }
        return end(self::TAX_BRACKETS_2025_26)['rate'];
    }
}
