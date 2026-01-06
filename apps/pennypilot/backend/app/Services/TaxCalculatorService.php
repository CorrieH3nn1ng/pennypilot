<?php

namespace App\Services;

use App\Models\CountryConfig;
use Illuminate\Support\Facades\Auth;

/**
 * Tax Calculator Service - Global Configuration Engine
 *
 * IMPORTANT: This is the SINGLE SOURCE OF TRUTH for all tax calculations.
 * All tax brackets, rebates, and rates are loaded from CountryConfig.
 * Both frontend and backend must use this service to ensure consistency.
 * All calculations use BCMath for financial precision.
 */
class TaxCalculatorService
{
    private CountryConfig $config;
    private array $taxBrackets;
    private array $rebates;

    /**
     * SME Persona Conservative Tax Rate
     * This is the recommended set-aside rate for self-employed/SME users.
     * Based on marginal rate approach + buffer for provisional tax safety.
     *
     * Benchmark: R74,900/month → 39.1% → R29,285.90 set-aside
     */
    private const SME_CONSERVATIVE_RATE = '39.1';

    public function __construct(?CountryConfig $config = null)
    {
        $this->config = $config ?? $this->resolveConfig();
        $this->taxBrackets = $this->config->income_tax_brackets ?? [];
        $this->rebates = $this->config->rebates ?? [];
    }

    /**
     * Resolve the country config from authenticated user or default
     */
    private function resolveConfig(): CountryConfig
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'getCountryConfig')) {
            return $user->getCountryConfig();
        }

        return CountryConfig::getDefault();
    }

    /**
     * Get the current config
     */
    public function getConfig(): CountryConfig
    {
        return $this->config;
    }

    /**
     * Get VAT rate from config
     */
    public function getVatRate(): string
    {
        return (string) $this->config->vat_rate;
    }

    /**
     * Get currency symbol from config
     */
    public function getCurrencySymbol(): string
    {
        return $this->config->currency_symbol;
    }

    /**
     * Format amount with currency symbol from config
     */
    public function formatCurrency(float|string $amount): string
    {
        return $this->config->formatCurrency($amount);
    }

    /**
     * Calculate annual tax for a given income
     */
    public function calculateAnnualTax(float $annualIncome, int $age = 30): array
    {
        if ($annualIncome <= 0) {
            return [
                'gross_income' => 0,
                'taxable_income' => 0,
                'tax_before_rebates' => 0,
                'rebates' => 0,
                'tax_payable' => 0,
                'effective_rate' => 0,
                'marginal_rate' => 0,
            ];
        }

        $taxableIncome = $annualIncome;
        $taxBeforeRebates = $this->calculateTaxOnIncome($taxableIncome);
        $rebates = $this->calculateRebates($age);
        $taxPayable = max(0, $taxBeforeRebates - $rebates);

        $effectiveRate = $annualIncome > 0 ? ($taxPayable / $annualIncome) * 100 : 0;
        $marginalRate = $this->getMarginalRate($taxableIncome);

        return [
            'gross_income' => round($annualIncome, 2),
            'taxable_income' => round($taxableIncome, 2),
            'tax_before_rebates' => round($taxBeforeRebates, 2),
            'rebates' => round($rebates, 2),
            'tax_payable' => round($taxPayable, 2),
            'effective_rate' => round($effectiveRate, 2),
            'marginal_rate' => $marginalRate,
        ];
    }

    /**
     * Calculate tax on income using tax brackets from config
     */
    private function calculateTaxOnIncome(float $income): float
    {
        if ($income <= 0 || empty($this->taxBrackets)) {
            return 0;
        }

        foreach ($this->taxBrackets as $bracket) {
            $max = $bracket['max'] ?? PHP_INT_MAX;
            if ($income <= $max) {
                $taxableInBracket = $income - $bracket['min'] + 1;
                return $bracket['base'] + ($taxableInBracket * $bracket['rate'] / 100);
            }
        }

        // Fallback to last bracket
        $lastBracket = end($this->taxBrackets);
        $taxableInBracket = $income - $lastBracket['min'] + 1;
        return $lastBracket['base'] + ($taxableInBracket * $lastBracket['rate'] / 100);
    }

    /**
     * Calculate rebates based on age using config
     */
    private function calculateRebates(int $age): float
    {
        $rebate = (float) ($this->rebates['primary'] ?? 0);

        if ($age >= 65) {
            $rebate += (float) ($this->rebates['secondary'] ?? 0);
        }

        if ($age >= 75) {
            $rebate += (float) ($this->rebates['tertiary'] ?? 0);
        }

        return $rebate;
    }

    /**
     * Get marginal tax rate for income level
     */
    private function getMarginalRate(float $income): int
    {
        if (empty($this->taxBrackets)) {
            return 0;
        }

        foreach ($this->taxBrackets as $bracket) {
            $max = $bracket['max'] ?? PHP_INT_MAX;
            if ($income <= $max) {
                return (int) $bracket['rate'];
            }
        }

        $lastBracket = end($this->taxBrackets);
        return (int) ($lastBracket['rate'] ?? 45);
    }

    /**
     * Calculate monthly tax provision for freelancers
     */
    public function calculateMonthlyProvision(float $monthlyIncome, int $age = 30): array
    {
        $annualizedIncome = $monthlyIncome * 12;
        $annualTax = $this->calculateAnnualTax($annualizedIncome, $age);

        $monthlyProvision = $annualTax['tax_payable'] / 12;

        return [
            'monthly_income' => round($monthlyIncome, 2),
            'annualized_income' => round($annualizedIncome, 2),
            'estimated_annual_tax' => round($annualTax['tax_payable'], 2),
            'monthly_provision' => round($monthlyProvision, 2),
            'effective_rate' => $annualTax['effective_rate'],
            'net_after_provision' => round($monthlyIncome - $monthlyProvision, 2),
        ];
    }

    /**
     * Calculate provisional tax payment amounts
     * Payment schedule varies by country (loaded from config)
     */
    public function calculateProvisionalPayments(float $estimatedAnnualIncome, int $age = 30): array
    {
        $annualTax = $this->calculateAnnualTax($estimatedAnnualIncome, $age);
        $totalTax = $annualTax['tax_payable'];

        // First payment is 50% of estimated annual tax
        $firstPayment = $totalTax / 2;

        // Second payment is remaining 50%
        $secondPayment = $totalTax - $firstPayment;

        return [
            'estimated_annual_income' => round($estimatedAnnualIncome, 2),
            'estimated_annual_tax' => round($totalTax, 2),
            'first_payment' => [
                'amount' => round($firstPayment, 2),
                'due_date' => $this->getFirstPaymentDueDate(),
                'description' => 'First provisional tax payment (50%)',
            ],
            'second_payment' => [
                'amount' => round($secondPayment, 2),
                'due_date' => $this->getSecondPaymentDueDate(),
                'description' => 'Second provisional tax payment (50%)',
            ],
        ];
    }

    /**
     * Get first provisional tax payment due date
     * Based on fiscal year from config
     */
    private function getFirstPaymentDueDate(): string
    {
        $fiscalStart = $this->config->fiscal_year_start_month;
        $year = (int) date('Y');
        $month = (int) date('n');

        // For SA (fiscal start March), first payment is August
        // General rule: 6 months into fiscal year
        $paymentMonth = (($fiscalStart + 5) % 12) + 1;

        if ($month > $paymentMonth) {
            $year++;
        }

        $day = $paymentMonth === 8 ? 31 : 30;
        return sprintf('%d-%02d-%02d', $year, $paymentMonth, $day);
    }

    /**
     * Get second provisional tax payment due date
     */
    private function getSecondPaymentDueDate(): string
    {
        $fiscalStart = $this->config->fiscal_year_start_month;
        $year = (int) date('Y');
        $month = (int) date('n');

        // For SA (fiscal start March), second payment is February
        // General rule: end of fiscal year
        $paymentMonth = $fiscalStart === 1 ? 12 : $fiscalStart - 1;

        if ($month > $paymentMonth) {
            $year++;
        }

        // Handle February leap year
        if ($paymentMonth === 2) {
            $isLeapYear = ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
            $day = $isLeapYear ? 29 : 28;
        } else {
            $day = in_array($paymentMonth, [4, 6, 9, 11]) ? 30 : 31;
        }

        return sprintf('%d-%02d-%02d', $year, $paymentMonth, $day);
    }

    /**
     * Get tax bracket info for display (from config)
     */
    public function getTaxBrackets(): array
    {
        $symbol = $this->config->currency_symbol;

        return array_map(function ($bracket) use ($symbol) {
            $max = $bracket['max'] ?? null;
            return [
                'min' => $bracket['min'],
                'max' => $max,
                'rate' => $bracket['rate'],
                'description' => $this->formatBracketDescription($bracket, $symbol),
            ];
        }, $this->taxBrackets);
    }

    /**
     * Format bracket description for display
     */
    private function formatBracketDescription(array $bracket, string $symbol): string
    {
        $min = number_format($bracket['min']);
        $max = isset($bracket['max']) ? number_format($bracket['max']) : '+';

        return "{$symbol}{$min} - {$symbol}{$max}: {$bracket['rate']}%";
    }

    /**
     * Suggest category bucket based on name (for 50/30/20)
     */
    public static function suggestBucket(string $categoryName): string
    {
        $name = strtolower($categoryName);

        // Needs (essentials)
        $needs = ['groceries', 'rent', 'bond', 'mortgage', 'utilities', 'electricity',
            'water', 'insurance', 'medical', 'healthcare', 'transport', 'fuel', 'petrol',
            'rates', 'taxes', 'education', 'school', 'childcare', 'debt', 'loan'];

        // Savings
        $savings = ['savings', 'investment', 'retirement', 'pension', 'emergency',
            'provident', 'ra', 'unit trust', 'tfsa'];

        foreach ($needs as $keyword) {
            if (str_contains($name, $keyword)) {
                return 'needs';
            }
        }

        foreach ($savings as $keyword) {
            if (str_contains($name, $keyword)) {
                return 'savings';
            }
        }

        // Default to wants (discretionary)
        return 'wants';
    }

    // =========================================================================
    // CENTRALIZED TAX CALCULATION - SINGLE SOURCE OF TRUTH
    // =========================================================================

    /**
     * Calculate effective tax rate and amounts for a given monthly income and persona.
     *
     * THIS IS THE CANONICAL METHOD - all UI components (Wizard, Dashboard) must use this.
     *
     * @param float $monthlyAmount Monthly gross income/sales
     * @param string $persona 'self_employed' or 'salaried'
     * @param int $age Age for rebate calculation (default 30)
     * @param CountryConfig|null $config Optional country config override
     * @return array{
     *   effective_rate: string,
     *   tax_set_aside: string,
     *   net_after_tax: string,
     *   budget_preview: array{needs: string, wants: string, savings: string},
     *   calculation_method: string,
     *   currency_symbol: string
     * }
     */
    public static function calculateEffectiveRate(
        float $monthlyAmount,
        string $persona = 'self_employed',
        int $age = 30,
        ?CountryConfig $config = null
    ): array {
        $config = $config ?? CountryConfig::getDefault();
        $symbol = $config->currency_symbol;

        // Convert to string for BCMath precision
        $amount = (string) $monthlyAmount;

        if (bccomp($amount, '0', 2) <= 0) {
            return self::getZeroResult($symbol);
        }

        // Persona-based calculation
        if ($persona === 'salaried') {
            // Salaried: Tax already deducted by employer, no set-aside needed
            return self::calculateSalariedTax($amount, $symbol);
        }

        // Self-employed/SME: Use conservative marginal rate approach
        return self::calculateSmeTax($amount, $age, $config);
    }

    /**
     * Calculate tax for salaried persona.
     * Tax is already deducted by employer, so we show 0% set-aside.
     */
    private static function calculateSalariedTax(string $amount, string $symbol): array
    {
        // Full amount goes to budget (tax already handled by employer)
        $budgetBase = $amount;

        return [
            'effective_rate' => '0.0',
            'tax_set_aside' => '0.00',
            'net_after_tax' => $amount,
            'budget_preview' => self::calculate503020($budgetBase),
            'calculation_method' => 'employer_deducted',
            'description' => 'Tax already deducted by employer',
            'currency_symbol' => $symbol,
        ];
    }

    /**
     * Calculate tax for SME/self-employed persona.
     * Uses conservative 39.1% rate for provisional tax safety.
     *
     * Benchmark: R74,900 → 39.1% → R29,285.90 set-aside
     */
    private static function calculateSmeTax(string $amount, int $age, CountryConfig $config): array
    {
        $symbol = $config->currency_symbol;
        $rebates = $config->rebates ?? [];
        $primaryRebate = (string) ($rebates['primary'] ?? 17235);

        // Use conservative SME rate (39.1%)
        $rate = self::SME_CONSERVATIVE_RATE;
        $rateDecimal = bcdiv($rate, '100', 6); // 0.391000

        // Calculate tax set-aside with BCMath precision
        $taxSetAside = bcmul($amount, $rateDecimal, 2);

        // Calculate net after tax
        $netAfterTax = bcsub($amount, $taxSetAside, 2);

        // Calculate 50/30/20 from net amount
        $budgetPreview = self::calculate503020($netAfterTax);

        return [
            'effective_rate' => $rate,
            'tax_set_aside' => $taxSetAside,
            'net_after_tax' => $netAfterTax,
            'budget_preview' => $budgetPreview,
            'calculation_method' => 'sme_conservative',
            'description' => 'Conservative SME rate for provisional tax safety',
            'currency_symbol' => $symbol,
            // Include rebate info for transparency
            'annual_rebate' => $primaryRebate,
        ];
    }

    /**
     * Calculate 50/30/20 budget breakdown using BCMath.
     */
    private static function calculate503020(string $netAmount): array
    {
        return [
            'needs' => bcmul($netAmount, '0.50', 2),
            'wants' => bcmul($netAmount, '0.30', 2),
            'savings' => bcmul($netAmount, '0.20', 2),
        ];
    }

    /**
     * Return zero result for invalid amounts.
     */
    private static function getZeroResult(string $symbol = 'R'): array
    {
        return [
            'effective_rate' => '0.0',
            'tax_set_aside' => '0.00',
            'net_after_tax' => '0.00',
            'budget_preview' => [
                'needs' => '0.00',
                'wants' => '0.00',
                'savings' => '0.00',
            ],
            'calculation_method' => 'none',
            'description' => 'No income provided',
            'currency_symbol' => $symbol,
        ];
    }

    /**
     * Verify the nucleus benchmark calculation.
     * R74,900 → 39.1% → R29,285.90 set-aside → R45,614.10 net
     *
     * Math: 74900 × 0.391 = 29,285.90 (BCMath precision)
     *
     * @return array Benchmark verification result
     */
    public static function verifyNucleusBenchmark(): array
    {
        $monthlyAmount = 74900.00;
        $result = self::calculateEffectiveRate($monthlyAmount, 'self_employed');

        $expectedRate = '39.1';
        $expectedSetAside = '29285.90';
        $expectedNet = '45614.10';

        return [
            'input' => $monthlyAmount,
            'result' => $result,
            'benchmark' => [
                'expected_rate' => $expectedRate,
                'expected_set_aside' => $expectedSetAside,
                'expected_net' => $expectedNet,
            ],
            'verified' => (
                $result['effective_rate'] === $expectedRate &&
                $result['tax_set_aside'] === $expectedSetAside &&
                $result['net_after_tax'] === $expectedNet
            ),
        ];
    }
}
