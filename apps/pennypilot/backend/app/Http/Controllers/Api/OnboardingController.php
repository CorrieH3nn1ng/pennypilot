<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    /**
     * Complete user onboarding - saves persona and creates initial budget.
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'income_type' => 'required|in:self_employed,salaried',
            'monthly_base_amount' => 'required|numeric|min:0',
            'vat_status' => 'nullable|in:registered,not_registered,approaching',
            'selected_methodology' => 'required|in:50-30-20,zero-based,profit-first',
        ]);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {
            // 1. Update user persona
            $user->update([
                'income_type' => $validated['income_type'],
                'net_monthly_income' => $validated['income_type'] === 'salaried'
                    ? $validated['monthly_base_amount']
                    : null,
                'onboarding_completed_at' => now(),
            ]);

            // 2. Create initial budget for current month (November 2024 or current)
            $now = now();
            $year = $now->year;
            $month = $now->month;

            // Calculate tax provision for self-employed (rough estimate)
            $grossIncome = $validated['monthly_base_amount'];
            $taxProvision = $validated['income_type'] === 'self_employed'
                ? $this->estimateMonthlyTax($grossIncome)
                : 0; // Salaried users already have tax deducted

            $netAvailable = $grossIncome - $taxProvision;

            // Create budget period
            BudgetPeriod::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'methodology' => $validated['selected_methodology'],
                    'total_income' => $grossIncome,
                    'tax_provision' => $taxProvision,
                    'net_available' => $netAvailable,
                    'total_planned' => $netAvailable, // Initially all planned
                    'status' => 'active',
                    'rollover_option' => 'reset',
                ]
            );

            // 3. Update VAT tracking preference for self-employed
            if ($validated['income_type'] === 'self_employed' && $validated['vat_status']) {
                $user->update([
                    'vat_status' => $validated['vat_status'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Onboarding completed successfully',
            'data' => [
                'user' => $this->formatUserResponse($user->fresh()),
                'budget_created' => true,
            ],
        ]);
    }

    /**
     * Estimate monthly tax provision for self-employed users.
     * Uses simplified SARS brackets for 2024/2025 tax year.
     */
    private function estimateMonthlyTax(float $monthlyIncome): float
    {
        $annualIncome = $monthlyIncome * 12;

        // SARS 2024/2025 Tax Brackets (simplified)
        $annualTax = 0;

        if ($annualIncome <= 237100) {
            $annualTax = $annualIncome * 0.18;
        } elseif ($annualIncome <= 370500) {
            $annualTax = 42678 + ($annualIncome - 237100) * 0.26;
        } elseif ($annualIncome <= 512800) {
            $annualTax = 77362 + ($annualIncome - 370500) * 0.31;
        } elseif ($annualIncome <= 673000) {
            $annualTax = 121475 + ($annualIncome - 512800) * 0.36;
        } elseif ($annualIncome <= 857900) {
            $annualTax = 179147 + ($annualIncome - 673000) * 0.39;
        } elseif ($annualIncome <= 1817000) {
            $annualTax = 251258 + ($annualIncome - 857900) * 0.41;
        } else {
            $annualTax = 644489 + ($annualIncome - 1817000) * 0.45;
        }

        // Apply primary rebate (R17,235 for 2024/2025)
        $annualTax = max(0, $annualTax - 17235);

        return round($annualTax / 12, 2);
    }

    /**
     * Format user response with persona.
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'income_type' => $user->income_type,
            'net_monthly_income' => $user->net_monthly_income,
            'subscription_tier' => $user->subscription_tier,
            'is_premium' => $user->isPremium(),
            'is_admin' => $user->is_admin,
            'persona' => $user->getPersonaFeatures(),
            'onboarding_completed' => $user->onboarding_completed_at !== null,
        ];
    }

    /**
     * Extract income data from uploaded invoice or payslip using Gemini Vision.
     *
     * POST /api/onboarding/extract
     *
     * This uses the SA accountant prompt to:
     * 1. Detect document type (invoice vs payslip)
     * 2. Extract amounts (gross, net, VAT, tax deducted)
     * 3. Return calculated monthly base amount with tax preview
     */
    public function extractDocument(Request $request, GeminiService $geminiService): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,png,jpg,jpeg|max:10240', // 10MB max
        ]);

        // Check if Gemini is configured
        if (!$geminiService->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => 'AI extraction is not configured. Please enter your income manually.',
            ], 503);
        }

        try {
            // Store the file temporarily
            $file = $request->file('document');
            $tempPath = $file->store('temp/onboarding', 'local');
            $fullPath = storage_path('app/' . $tempPath);

            // Extract data using Gemini
            $result = $geminiService->extractOnboardingDocument($fullPath);

            // Clean up temp file
            @unlink($fullPath);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to extract document data',
                ], 422);
            }

            $data = $result['data'];

            // Calculate tax preview using BCMath for precision
            $isPayslip = $data['is_payslip'] ?? false;
            $monthlyBaseAmount = $data['monthly_base_amount'] ?? 0;

            // For payslips: Net Pay goes directly to budget pool (tax already deducted)
            // For invoices: Calculate tax provision from gross amount
            $taxPreview = null;
            if (!$isPayslip && $monthlyBaseAmount > 0) {
                $taxProvision = $this->estimateMonthlyTax($monthlyBaseAmount);
                $netAfterTax = bcsub((string) $monthlyBaseAmount, (string) $taxProvision, 2);
                $effectiveRate = bcmul(
                    bcdiv((string) $taxProvision, (string) $monthlyBaseAmount, 4),
                    '100',
                    1
                );

                $taxPreview = [
                    'gross_amount' => (float) $monthlyBaseAmount,
                    'tax_provision' => (float) $taxProvision,
                    'net_after_tax' => (float) $netAfterTax,
                    'effective_rate' => (float) $effectiveRate,
                    // 50/30/20 preview based on net
                    'budget_preview' => [
                        'needs' => (float) bcmul($netAfterTax, '0.5', 2),
                        'wants' => (float) bcmul($netAfterTax, '0.3', 2),
                        'savings' => (float) bcmul($netAfterTax, '0.2', 2),
                    ],
                ];
            } else if ($isPayslip && $monthlyBaseAmount > 0) {
                // Payslip: net pay is already the budget pool
                $taxPreview = [
                    'gross_amount' => (float) ($data['total_amount'] ?? $monthlyBaseAmount),
                    'tax_provision' => (float) ($data['tax_deducted'] ?? 0),
                    'net_after_tax' => (float) $monthlyBaseAmount,
                    'effective_rate' => null, // N/A for payslips
                    'budget_preview' => [
                        'needs' => (float) bcmul((string) $monthlyBaseAmount, '0.5', 2),
                        'wants' => (float) bcmul((string) $monthlyBaseAmount, '0.3', 2),
                        'savings' => (float) bcmul((string) $monthlyBaseAmount, '0.2', 2),
                    ],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'document_type' => $data['document_type'],
                    'is_payslip' => $isPayslip,
                    'entity_name' => $data['entity_name'],
                    'document_date' => $data['document_date'],
                    'total_amount' => $data['total_amount'],
                    'net_amount' => $data['net_amount'],
                    'vat_amount' => $data['vat_amount'],
                    'tax_deducted' => $data['tax_deducted'],
                    'monthly_base_amount' => $monthlyBaseAmount,
                    'confidence' => $data['confidence'],
                    'notes' => $data['notes'],
                    // Suggested income type based on document
                    'suggested_income_type' => $isPayslip ? 'salaried' : 'self_employed',
                    // Tax preview for wizard display
                    'tax_preview' => $taxPreview,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Onboarding document extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process document. Please try again or enter manually.',
            ], 500);
        }
    }
}
