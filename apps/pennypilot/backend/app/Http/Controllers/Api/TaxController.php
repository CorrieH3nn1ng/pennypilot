<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use App\Models\TaxProvision;
use App\Models\IncomeSource;
use App\Models\User;
use App\Services\FinancialCalculator;
use App\Services\TaxCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxController extends Controller
{
    public function __construct(
        private TaxCalculatorService $taxCalculator
    ) {}

    /**
     * Get user's tax settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = TaxSetting::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'employment_type' => 'employed',
                'provisional_tax_enabled' => false,
                'tax_year_start_month' => 3, // March
            ]
        );

        return response()->json(['data' => $settings]);
    }

    /**
     * Update tax settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employment_type' => 'sometimes|in:employed,self_employed,mixed',
            'tax_number' => 'nullable|string|max:20',
            'vat_registered' => 'boolean',
            'vat_number' => 'nullable|string|max:20',
            'estimated_annual_income' => 'nullable|numeric|min:0',
            'provisional_tax_enabled' => 'boolean',
        ]);

        $settings = TaxSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        // Calculate and store estimated tax rate if income provided
        if (!empty($validated['estimated_annual_income'])) {
            $taxCalc = $this->taxCalculator->calculateAnnualTax($validated['estimated_annual_income']);
            $settings->update([
                'estimated_tax_rate' => $taxCalc['effective_rate'],
            ]);
        }

        return response()->json([
            'data' => $settings->fresh(),
            'message' => 'Tax settings updated successfully',
        ]);
    }

    /**
     * Get all tax provisions for user
     */
    public function getProvisions(Request $request): JsonResponse
    {
        $query = TaxProvision::where('user_id', Auth::id())
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->has('tax_year')) {
            $query->where('tax_year', $request->tax_year);
        }

        $provisions = $query->get();

        return response()->json(['data' => $provisions]);
    }

    /**
     * Get current tax year summary
     */
    public function getCurrentSummary(): JsonResponse
    {
        $taxYear = TaxSetting::getCurrentTaxYear();

        $provisions = TaxProvision::where('user_id', Auth::id())
            ->where('tax_year', $taxYear)
            ->get();

        $totalIncome = $provisions->sum('income_amount');
        $totalProvision = $provisions->sum('provision_amount');

        // Calculate estimated annual tax
        $annualizedIncome = $totalIncome * (12 / max(1, $provisions->count()));
        $taxEstimate = $this->taxCalculator->calculateAnnualTax($annualizedIncome);

        // Get provisional payment info
        $provisionalPayments = $this->taxCalculator->calculateProvisionalPayments($annualizedIncome);

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'months_recorded' => $provisions->count(),
                'total_income' => round($totalIncome, 2),
                'total_provision' => round($totalProvision, 2),
                'annualized_income' => round($annualizedIncome, 2),
                'estimated_annual_tax' => $taxEstimate['tax_payable'],
                'effective_rate' => $taxEstimate['effective_rate'],
                'provisional_payments' => $provisionalPayments,
                'monthly_breakdown' => $provisions,
            ],
        ]);
    }

    /**
     * Record a monthly tax provision
     */
    public function storeProvision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'income_amount' => 'required|numeric|min:0',
            'provision_amount' => 'required|numeric|min:0',
            'provision_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        // Determine tax year (SA: March to February)
        $taxYear = $validated['month'] >= 3
            ? $validated['year'] + 1
            : $validated['year'];

        $provision = TaxProvision::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                ...$validated,
                'tax_year' => $taxYear,
            ]
        );

        return response()->json([
            'data' => $provision,
            'message' => 'Tax provision recorded successfully',
        ], 201);
    }

    /**
     * Mark a provisional payment as paid
     */
    public function markPaid(Request $request, string $id): JsonResponse
    {
        $provision = TaxProvision::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $provision->update([
            'is_paid' => true,
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? $provision->notes,
        ]);

        return response()->json([
            'data' => $provision->fresh(),
            'message' => 'Provision marked as paid',
        ]);
    }

    /**
     * Calculate tax for a given amount
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:annual,monthly',
            'age' => 'integer|min:18|max:120',
        ]);

        $age = $validated['age'] ?? 30;
        $amount = $validated['amount'];

        if ($validated['type'] === 'monthly') {
            $result = $this->taxCalculator->calculateMonthlyProvision($amount, $age);
        } else {
            $result = $this->taxCalculator->calculateAnnualTax($amount, $age);
            $result['provisional_payments'] = $this->taxCalculator->calculateProvisionalPayments($amount, $age);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Get tax brackets for display
     */
    public function getBrackets(): JsonResponse
    {
        return response()->json([
            'data' => $this->taxCalculator->getTaxBrackets(),
            'tax_year' => '2024/2025',
            'effective_date' => '1 March 2024',
        ]);
    }

    /**
     * Suggest bucket for category (for 50/30/20)
     */
    public function suggestBucket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => 'required|string',
        ]);

        $bucket = TaxCalculatorService::suggestBucket($validated['category_name']);

        return response()->json([
            'data' => [
                'category_name' => $validated['category_name'],
                'suggested_bucket' => $bucket,
            ],
        ]);
    }

    /**
     * Calculate effective tax rate for a given amount and persona.
     *
     * THIS IS THE SINGLE SOURCE OF TRUTH for tax calculations.
     * Both Wizard and Dashboard must use this endpoint.
     *
     * GET /api/tax/effective-rate?amount=74900&persona=self_employed
     *
     * Benchmark: R74,900 + self_employed → 39.1% → R29,286.90 set-aside
     */
    public function getEffectiveRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'persona' => 'sometimes|in:self_employed,salaried',
            'age' => 'sometimes|integer|min:18|max:120',
        ]);

        $amount = (float) $validated['amount'];
        $persona = $validated['persona'] ?? 'self_employed';
        $age = $validated['age'] ?? 30;

        $result = TaxCalculatorService::calculateEffectiveRate($amount, $persona, $age);

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_amount' => number_format($amount, 2, '.', ''),
                'persona' => $persona,
                'effective_rate' => $result['effective_rate'],
                'tax_set_aside' => $result['tax_set_aside'],
                'net_after_tax' => $result['net_after_tax'],
                'budget_preview' => $result['budget_preview'],
                'calculation_method' => $result['calculation_method'],
                'description' => $result['description'] ?? null,
            ],
        ]);
    }

    /**
     * Verify the nucleus benchmark calculation.
     * Used for testing/debugging to ensure tax calculations are correct.
     *
     * GET /api/tax/verify-benchmark
     */
    public function verifyBenchmark(): JsonResponse
    {
        $result = TaxCalculatorService::verifyNucleusBenchmark();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get VAT registration threshold status
     *
     * Returns rolling 12-month turnover and threshold warnings:
     * - null: Under R800,000 (safe)
     * - 'warning': R800,000 - R1,000,000 (Yellow warning - approaching threshold)
     * - 'exceeded': Over R1,000,000 (Red warning - must register for VAT)
     */
    public function getVatThresholdStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $status = $user->getVatThresholdStatus();
        $monthlyBreakdown = $user->getMonthlyTurnoverBreakdown();

        // Update user's VAT threshold status cache
        $user->update([
            'vat_threshold_status' => $status['status'],
            'vat_threshold_checked_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'status' => $status['status'],
                'turnover' => FinancialCalculator::format($status['turnover']),
                'turnover_raw' => $status['turnover'],
                'threshold' => FinancialCalculator::format($status['threshold']),
                'warning_threshold' => FinancialCalculator::format($status['warning_threshold']),
                'percentage' => round($status['percentage'], 1),
                'monthly_breakdown' => $monthlyBreakdown,
                'should_register' => $status['status'] === 'exceeded',
                'is_warning' => $status['status'] === 'warning',
            ],
            'message' => match($status['status']) {
                'exceeded' => 'Your rolling 12-month turnover exceeds R1,000,000. You must register for VAT with SARS.',
                'warning' => 'Your turnover is approaching the R1,000,000 VAT threshold. Consider consulting a tax advisor.',
                default => 'Your turnover is below the VAT registration threshold.',
            },
        ]);
    }
}
