<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TaxProvision;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * AccountantController - Accountant Intelligence System
 *
 * Manages the workflow for flagging transactions for professional review:
 * 1. Flag with draft question (captured while memory is fresh)
 * 2. Export flagged items to PDF for accountant review
 * 3. Approve as Business (tax-deductible) or Reject to Private
 * 4. Auto-recalculate tax reserves on approval
 */
class AccountantController extends Controller
{
    /**
     * Get all flagged transactions for accountant review
     *
     * GET /api/accountant/flagged
     */
    public function flagged(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $query = Transaction::where('user_id', $userId)
            ->flaggedForAccountant()
            ->with('category:id,name,icon,color')
            ->orderBy('transaction_date', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('accountant_status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $transactions = $query->get();

        // Calculate summary statistics
        $summary = [
            'total_flagged' => $transactions->count(),
            'pending_review' => $transactions->where('accountant_status', 'pending')->count(),
            'approved_business' => $transactions->where('accountant_status', 'approved_business')->count(),
            'rejected_private' => $transactions->where('accountant_status', 'rejected_private')->count(),
            'total_pending_amount' => $transactions->where('accountant_status', 'pending')
                ->sum(fn($t) => abs($t->amount)),
            'total_approved_deductions' => $transactions->where('accountant_status', 'approved_business')
                ->sum('tax_deduction_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Flag a transaction for accountant review
     *
     * POST /api/accountant/flag/{id}
     */
    public function flag(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('user_id', Auth::id())
            ->findOrFail($id);

        $transaction->flagForAccountant($request->question);

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction flagged for accountant review.',
        ]);
    }

    /**
     * Unflag a transaction from accountant review
     *
     * POST /api/accountant/unflag/{id}
     */
    public function unflag(string $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', Auth::id())
            ->findOrFail($id);

        $transaction->unflagFromAccountant();

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction removed from accountant review.',
        ]);
    }

    /**
     * Approve transaction as business expense (tax-deductible)
     *
     * POST /api/accountant/approve/{id}
     *
     * This triggers automatic tax reserve recalculation for the month.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('user_id', Auth::id())
            ->findOrFail($id);

        if (!$transaction->flagged_for_accountant) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not flagged for accountant review.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Approve as business
            $deductionAmount = $transaction->approveAsBusiness($request->notes);

            // Recalculate tax reserve for the month
            $taxImpact = $this->recalculateTaxReserve(
                Auth::id(),
                $transaction->transaction_date->year,
                $transaction->transaction_date->month
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => $transaction->fresh(),
                    'deduction_amount' => $deductionAmount,
                    'tax_impact' => $taxImpact,
                ],
                'message' => sprintf(
                    'Approved as business expense. R%.2f deduction, R%.2f tax savings.',
                    $deductionAmount,
                    $taxImpact['tax_savings']
                ),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject transaction to private (not tax-deductible)
     *
     * POST /api/accountant/reject/{id}
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('user_id', Auth::id())
            ->findOrFail($id);

        if (!$transaction->flagged_for_accountant) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not flagged for accountant review.',
            ], 400);
        }

        $transaction->rejectToPrivate($request->notes);

        return response()->json([
            'success' => true,
            'data' => $transaction->fresh(),
            'message' => 'Transaction rejected as private expense.',
        ]);
    }

    /**
     * Update the draft question for a flagged transaction
     *
     * PUT /api/accountant/question/{id}
     */
    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('user_id', Auth::id())
            ->where('flagged_for_accountant', true)
            ->findOrFail($id);

        $transaction->update(['accountant_question' => $request->question]);

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Draft question updated.',
        ]);
    }

    /**
     * Generate PDF report of flagged transactions for accountant
     *
     * GET /api/accountant/report/pdf
     */
    public function generatePdfReport(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Get filter parameters
        $status = $request->get('status', 'pending');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = Transaction::where('user_id', $userId)
            ->flaggedForAccountant()
            ->with('category:id,name,icon')
            ->orderBy('transaction_date', 'desc');

        if ($status !== 'all') {
            $query->where('accountant_status', $status);
        }

        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }

        $transactions = $query->get();

        // Group by month
        $groupedByMonth = $transactions->groupBy(function ($tx) {
            return $tx->transaction_date->format('Y-m');
        })->map(function ($group) {
            return [
                'month_label' => Carbon::parse($group->first()->transaction_date)->format('F Y'),
                'transactions' => $group,
                'total_amount' => $group->sum(fn($t) => abs($t->amount)),
                'count' => $group->count(),
            ];
        });

        // Calculate totals
        $totals = [
            'total_flagged' => $transactions->count(),
            'total_amount' => $transactions->sum(fn($t) => abs($t->amount)),
            'potential_tax_savings' => $transactions->sum(fn($t) => abs($t->amount) * 0.391),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.accountant-review', [
            'user' => $user,
            'groupedByMonth' => $groupedByMonth,
            'totals' => $totals,
            'status' => $status,
            'fromDate' => $fromDate ? Carbon::parse($fromDate)->format('d M Y') : 'All',
            'toDate' => $toDate ? Carbon::parse($toDate)->format('d M Y') : 'All',
            'generatedAt' => now()->format('d M Y H:i'),
        ]);

        $filename = sprintf(
            'accountant-review-%s-%s.pdf',
            $user->name ?? 'user',
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Get summary statistics for accountant review
     *
     * GET /api/accountant/summary
     */
    public function summary(): JsonResponse
    {
        $userId = Auth::id();

        // Current tax year (March - February for SA)
        $now = Carbon::now();
        $taxYearStart = $now->month >= 3
            ? Carbon::create($now->year, 3, 1)
            : Carbon::create($now->year - 1, 3, 1);
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();

        // Get statistics
        $pendingCount = Transaction::where('user_id', $userId)
            ->pendingAccountantReview()
            ->count();

        $pendingAmount = Transaction::where('user_id', $userId)
            ->pendingAccountantReview()
            ->get()
            ->sum(fn($t) => abs($t->amount));

        $approvedThisYear = Transaction::where('user_id', $userId)
            ->approvedBusiness()
            ->whereBetween('transaction_date', [$taxYearStart, $taxYearEnd])
            ->get();

        $totalDeductions = $approvedThisYear->sum('tax_deduction_amount');

        // Calculate actual tax savings (39.1% of deductions)
        $taxSavings = bcmul((string) $totalDeductions, '0.391', 2);

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => [
                    'count' => $pendingCount,
                    'amount' => (float) $pendingAmount,
                    'potential_savings' => (float) bcmul((string) $pendingAmount, '0.391', 2),
                ],
                'tax_year' => [
                    'start' => $taxYearStart->format('Y-m-d'),
                    'end' => $taxYearEnd->format('Y-m-d'),
                    'label' => $taxYearStart->format('M Y') . ' - ' . $taxYearEnd->format('M Y'),
                ],
                'approved' => [
                    'count' => $approvedThisYear->count(),
                    'total_deductions' => (float) $totalDeductions,
                    'tax_savings' => (float) $taxSavings,
                ],
            ],
        ]);
    }

    /**
     * Recalculate tax reserve for a specific month
     *
     * Called automatically when approving a business expense.
     */
    private function recalculateTaxReserve(string $userId, int $year, int $month): array
    {
        // Get all approved business deductions for the month
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $approvedDeductions = Transaction::where('user_id', $userId)
            ->approvedBusiness()
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('tax_deduction_amount');

        // Get total income for the month
        $totalIncome = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->where('amount', '>', 0)
            ->sum('amount');

        // Calculate taxable income after deductions
        $taxableIncome = max(0, $totalIncome - $approvedDeductions);

        // Calculate VAT (15% on gross income - businesses collect VAT)
        $vatAmount = bcmul((string) $totalIncome, '0.15', 2);

        // Calculate tax reserve (39.1% on taxable income after VAT)
        $incomeAfterVat = bcsub((string) $taxableIncome, $vatAmount, 2);
        $taxReserve = bcmul($incomeAfterVat, '0.391', 2);

        // Tax savings from deductions
        $taxSavings = bcmul((string) $approvedDeductions, '0.391', 2);

        // Update or create tax provision record
        $provision = TaxProvision::updateOrCreate(
            [
                'user_id' => $userId,
                'year' => $year,
                'month' => $month,
            ],
            [
                'gross_income' => $totalIncome,
                'business_deductions' => $approvedDeductions,
                'taxable_income' => $taxableIncome,
                'vat_amount' => $vatAmount,
                'tax_amount' => $taxReserve,
            ]
        );

        return [
            'month' => $monthStart->format('F Y'),
            'gross_income' => (float) $totalIncome,
            'deductions' => (float) $approvedDeductions,
            'taxable_income' => (float) $taxableIncome,
            'vat_reserve' => (float) $vatAmount,
            'tax_reserve' => (float) $taxReserve,
            'tax_savings' => (float) $taxSavings,
        ];
    }
}
