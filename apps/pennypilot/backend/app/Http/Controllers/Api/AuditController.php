<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BudgetCard;
use App\Models\BudgetCardItem;
use App\Models\Transaction;
use App\Models\TransactionAudit;
use App\Models\TransactionRule;
use App\Services\AuditEngineService;
use App\Services\BlueprintMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * AuditController - Transaction Audit Operations
 *
 * Handles the audit engine operations: running audits, manual matching,
 * managing leaks/windfalls, and retrieving audit statistics.
 */
class AuditController extends Controller
{
    public function __construct(
        private AuditEngineService $auditEngine,
        private BlueprintMatchingService $blueprintMatcher
    ) {}

    /**
     * Run audit on a budget card
     *
     * POST /api/audit/run
     */
    public function run(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'budget_card_id' => 'required|uuid',
            'auto_match' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $card = BudgetCard::where('user_id', $request->user()->id)
            ->where('status', '!=', 'finalized')
            ->findOrFail($request->budget_card_id);

        $autoMatch = $request->boolean('auto_match', true);

        $results = $this->auditEngine->auditBudgetCard($card, $autoMatch);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => sprintf(
                'Audit complete: %d processed, %d matched, %d leaks, %d transfers.',
                $results['processed'],
                $results['matched'],
                $results['leaks'],
                $results['transfers']
            ),
        ]);
    }

    /**
     * Run audit on current month's card
     *
     * POST /api/audit/run-current
     */
    public function runCurrent(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $card = BudgetCard::where('user_id', $user->id)
            ->forPeriod($now->year, $now->month)
            ->where('status', '!=', 'finalized')
            ->first();

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'No active budget card for current month.',
            ], 404);
        }

        $autoMatch = $request->boolean('auto_match', true);
        $results = $this->auditEngine->auditBudgetCard($card, $autoMatch);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => sprintf(
                'Audit complete: %d processed, %d matched, %d leaks.',
                $results['processed'],
                $results['matched'],
                $results['leaks']
            ),
        ]);
    }

    /**
     * Manually match a transaction to a budget card item
     *
     * POST /api/audit/match
     */
    public function match(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid',
            'budget_card_item_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($request->transaction_id);

        $item = BudgetCardItem::whereHas('budgetCard', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($request->budget_card_item_id);

        $card = $item->budgetCard;

        if ($card->status === 'finalized') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify a finalized budget card.',
            ], 400);
        }

        $result = $this->auditEngine->manualMatch($transaction, $item, $card);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => "Transaction matched to '{$item->name}'.",
        ]);
    }

    /**
     * Unmatch a transaction from a budget card item
     *
     * POST /api/audit/unmatch
     */
    public function unmatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'budget_card_item_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $item = BudgetCardItem::whereHas('budgetCard', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($request->budget_card_item_id);

        $card = $item->budgetCard;

        if ($card->status === 'finalized') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify a finalized budget card.',
            ], 400);
        }

        if (!$item->isMatched()) {
            return response()->json([
                'success' => false,
                'message' => 'Item is not currently matched.',
            ], 400);
        }

        $result = $this->auditEngine->manualUnmatch($item, $card);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Transaction unmatched.',
        ]);
    }

    /**
     * Silence a leak (acknowledge it, exclude from grade)
     *
     * POST /api/audit/silence
     */
    public function silence(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'audit_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $audit = TransactionAudit::whereHas('budgetCard', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($request->audit_id);

        if ($audit->budgetCard->status === 'finalized') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify a finalized budget card.',
            ], 400);
        }

        $success = $this->auditEngine->silenceLeak($audit);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Leak silenced.' : 'Only leaks can be silenced.',
        ]);
    }

    /**
     * Unsilence a previously silenced leak
     *
     * POST /api/audit/unsilence
     */
    public function unsilence(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'audit_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $audit = TransactionAudit::whereHas('budgetCard', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($request->audit_id);

        if ($audit->budgetCard->status === 'finalized') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify a finalized budget card.',
            ], 400);
        }

        $audit->unsilence();
        $this->auditEngine->recalculateCardTotals($audit->budgetCard);

        return response()->json([
            'success' => true,
            'message' => 'Leak unsilenced.',
        ]);
    }

    /**
     * Convert a leak to a blueprint item (make it recurring)
     *
     * POST /api/audit/leak-to-blueprint
     */
    public function leakToBlueprint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'audit_id' => 'required|uuid',
            'bucket' => 'required|in:needs,wants,savings',
            'due_day' => 'nullable|integer|min:1|max:31',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $audit = TransactionAudit::whereHas('budgetCard', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with('transaction')->findOrFail($request->audit_id);

        if ($audit->classification !== 'leak') {
            return response()->json([
                'success' => false,
                'message' => 'Only leaks can be converted to blueprint items.',
            ], 400);
        }

        $tx = $audit->transaction;

        // Create blueprint from leak
        $blueprint = \App\Models\Blueprint::create([
            'user_id' => $user->id,
            'name' => $this->extractPatternFromDescription($tx->description),
            'expected_amount' => abs($tx->amount),
            'amount_tolerance' => 10.00, // Wider tolerance for new patterns
            'due_day' => $request->due_day ?? (int) $tx->transaction_date->format('d'),
            'due_day_tolerance' => 5,
            'item_type' => 'expense',
            'bucket' => $request->bucket,
            'match_pattern' => $this->extractPatternFromDescription($tx->description),
            'match_type' => 'contains',
            'frequency' => 'monthly',
            'is_active' => true,
            'priority' => 0,
        ]);

        // Silence the leak
        $audit->silence();

        return response()->json([
            'success' => true,
            'data' => [
                'blueprint_id' => $blueprint->id,
                'name' => $blueprint->name,
            ],
            'message' => "Created blueprint '{$blueprint->name}' from leak.",
        ]);
    }

    /**
     * Get all leaks for a budget card
     *
     * GET /api/audit/leaks/{budget_card_id}
     */
    public function leaks(Request $request, string $budgetCardId): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->findOrFail($budgetCardId);

        $leaks = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'leak')
            ->with('transaction:id,transaction_date,description,amount,raw_description')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'transaction_id' => $audit->transaction_id,
                    'transaction_date' => $audit->transaction->transaction_date,
                    'description' => $audit->transaction->description,
                    'amount' => abs($audit->transaction->amount),
                    'is_silenced' => $audit->is_silenced,
                    'created_at' => $audit->created_at,
                ];
            });

        $totalUnsilenced = $leaks->where('is_silenced', false)->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'leaks' => $leaks,
                'count' => $leaks->count(),
                'silenced_count' => $leaks->where('is_silenced', true)->count(),
                'total_unsilenced' => $totalUnsilenced,
            ],
        ]);
    }

    /**
     * Get all windfalls for a budget card
     *
     * GET /api/audit/windfalls/{budget_card_id}
     */
    public function windfalls(Request $request, string $budgetCardId): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->findOrFail($budgetCardId);

        $windfalls = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'windfall')
            ->with('transaction:id,transaction_date,description,amount')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'transaction_id' => $audit->transaction_id,
                    'transaction_date' => $audit->transaction->transaction_date,
                    'description' => $audit->transaction->description,
                    'amount' => abs($audit->transaction->amount),
                    'created_at' => $audit->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'windfalls' => $windfalls,
                'count' => $windfalls->count(),
                'total' => $windfalls->sum('amount'),
            ],
        ]);
    }

    /**
     * Get pending (unaudited) transactions for current month
     *
     * GET /api/audit/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $startDate = $now->copy()->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('audit_status', 'pending')
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'transaction_date' => $tx->transaction_date,
                    'description' => $tx->description,
                    'amount' => $tx->amount,
                    'is_income' => $tx->isIncome(),
                    'is_transfer' => $tx->is_transfer,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'count' => $transactions->count(),
                'period' => $now->format('F Y'),
            ],
        ]);
    }

    /**
     * Get audit statistics for a budget card
     *
     * GET /api/audit/stats/{budget_card_id}
     */
    public function stats(Request $request, string $budgetCardId): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->findOrFail($budgetCardId);

        $stats = $this->auditEngine->getAuditStats($card);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get suggestions (potential matches below auto-threshold)
     *
     * GET /api/audit/suggestions/{budget_card_id}
     */
    public function suggestions(Request $request, string $budgetCardId): JsonResponse
    {
        $user = $request->user();

        $card = BudgetCard::where('user_id', $user->id)
            ->findOrFail($budgetCardId);

        $startDate = Carbon::create($card->year, $card->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get pending transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('audit_status', 'pending')
            ->get();

        // Get pending items
        $items = $card->items()->where('audit_status', 'pending')->get();

        $suggestions = [];

        foreach ($transactions as $tx) {
            // Skip transfers
            if ($this->auditEngine->isTransfer($tx)) {
                continue;
            }

            foreach ($items as $item) {
                // Type match
                if ($tx->isIncome() && $item->item_type !== 'income') continue;
                if ($tx->isExpense() && $item->item_type !== 'expense') continue;

                $score = $this->auditEngine->calculateMatchScore($tx, $item);

                // Only suggest if between 50-74 (below auto-match)
                if ($score >= 50 && $score < 75) {
                    $suggestions[] = [
                        'transaction_id' => $tx->id,
                        'transaction_date' => $tx->transaction_date,
                        'transaction_description' => $tx->description,
                        'transaction_amount' => $tx->amount,
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_expected_amount' => $item->expected_amount,
                        'confidence' => $score,
                    ];
                }
            }
        }

        // Sort by confidence descending
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'count' => count($suggestions),
            ],
        ]);
    }

    /**
     * Helper: Extract a clean pattern from transaction description
     */
    private function extractPatternFromDescription(string $description): string
    {
        $clean = strtoupper($description);

        // Remove card numbers (6+ digits)
        $clean = preg_replace('/\d{6,}/', '', $clean);

        // Remove dates (25Jan2025 format)
        $clean = preg_replace('/\d{1,2}[A-Z]{3}\d{4}/', '', $clean);

        // Remove reference patterns
        $clean = preg_replace('/\*[A-Z0-9]+\*/', '', $clean);

        // Remove extra whitespace
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Take first 2-3 words as pattern
        $words = explode(' ', trim($clean));
        $pattern = implode(' ', array_slice($words, 0, 3));

        return trim($pattern) ?: substr($description, 0, 20);
    }

    // =============================================
    // BLUEPRINT RECONCILIATION ENDPOINTS
    // =============================================

    /**
     * Run Blueprint-to-Transaction reconciliation for a date range
     *
     * POST /api/audit/reconcile
     */
    public function reconcileBlueprints(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $results = $this->blueprintMatcher->reconcile($user, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => sprintf(
                'Reconciliation complete: %d matched, %d unmatched, %d leaks, %d zombies.',
                $results['matched'],
                $results['unmatched'],
                $results['leaks'],
                $results['zombies']
            ),
        ]);
    }

    /**
     * Get variance report for a specific month
     *
     * GET /api/audit/variance/{year}/{month}
     */
    public function variance(Request $request, int $year, int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid month. Must be between 1 and 12.',
            ], 422);
        }

        $user = $request->user();
        $report = $this->blueprintMatcher->getVarianceReport($user, $year, $month);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get zombie subscriptions (cancelled blueprints still charging)
     *
     * GET /api/audit/zombies
     */
    public function zombies(Request $request): JsonResponse
    {
        $user = $request->user();
        $zombies = $this->blueprintMatcher->getZombies($user);

        return response()->json([
            'success' => true,
            'data' => [
                'zombies' => $zombies->values(),
                'count' => $zombies->count(),
                'total_charges' => $zombies->sum('recent_charges'),
            ],
        ]);
    }

    /**
     * Cancel a blueprint (mark as cancelled for zombie tracking)
     *
     * POST /api/audit/blueprints/{id}/cancel
     */
    public function cancelBlueprint(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $this->blueprintMatcher->cancelBlueprint($blueprint);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'cancelled_at' => $blueprint->cancelled_at,
            ],
            'message' => "Blueprint '{$blueprint->name}' marked as cancelled. Will now track as zombie if charges continue.",
        ]);
    }

    /**
     * Pause a blueprint (payment holiday)
     *
     * POST /api/audit/blueprints/{id}/pause
     */
    public function pauseBlueprint(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : null;

        $this->blueprintMatcher->pauseBlueprint(
            $blueprint,
            $request->reason,
            $startDate,
            $endDate
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'status' => 'paused',
                'pause_start_date' => $blueprint->pause_start_date?->format('Y-m-d'),
                'pause_end_date' => $blueprint->pause_end_date?->format('Y-m-d'),
                'pause_reason' => $blueprint->pause_reason,
            ],
            'message' => $endDate
                ? "Blueprint '{$blueprint->name}' paused until {$endDate->format('M j, Y')}."
                : "Blueprint '{$blueprint->name}' paused indefinitely.",
        ]);
    }

    /**
     * Resume a blueprint from payment holiday
     *
     * POST /api/audit/blueprints/{id}/resume
     */
    public function resumeBlueprint(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($blueprint->status !== 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'Blueprint is not currently paused.',
            ], 400);
        }

        $this->blueprintMatcher->resumeBlueprint($blueprint);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'status' => 'active',
            ],
            'message' => "Blueprint '{$blueprint->name}' resumed from payment holiday.",
        ]);
    }

    /**
     * Get spendable cash projection for upcoming months
     * Accounts for payment holidays and their end dates
     *
     * GET /api/audit/spendable-projection
     */
    public function spendableProjection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'months' => 'sometimes|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $monthsAhead = $request->input('months', 6);
        $now = Carbon::now();

        $projection = [];
        $blueprints = $user->blueprints()
            ->with('category')
            ->get();

        // Get blueprints resuming from holiday (for highlighting)
        $resumingItems = [];

        for ($i = 0; $i < $monthsAhead; $i++) {
            $date = $now->copy()->addMonths($i);
            $year = $date->year;
            $month = $date->month;

            $monthlyIncome = 0;
            $monthlyExpenses = 0;
            $businessIncome = 0;
            $businessExpenses = 0;
            $resumingThisMonth = [];

            foreach ($blueprints as $blueprint) {
                // Skip cancelled blueprints
                if ($blueprint->status === 'cancelled') {
                    continue;
                }

                // Check if this blueprint is resuming this month
                // (was paused last month but not this month)
                if ($i > 0) {
                    $prevDate = $now->copy()->addMonths($i - 1);
                    $wasPausedLastMonth = $blueprint->isPausedForMonth($prevDate->year, $prevDate->month);
                    $isPausedThisMonth = $blueprint->isPausedForMonth($year, $month);

                    if ($wasPausedLastMonth && !$isPausedThisMonth) {
                        $resumingThisMonth[] = [
                            'id' => $blueprint->id,
                            'name' => $blueprint->name,
                            'amount' => $this->getFrequencyAdjustedAmount($blueprint),
                            'item_type' => $blueprint->item_type,
                        ];
                    }
                }

                // Get expected amount for this month (0 if paused)
                $amount = $blueprint->getExpectedAmountForMonth($year, $month);
                $frequencyAdjusted = $this->getFrequencyAdjustedAmountForMonth($blueprint, $year, $month);

                if ($blueprint->item_type === 'income') {
                    $monthlyIncome += $frequencyAdjusted;
                    if ($blueprint->nature === 'business') {
                        $businessIncome += $frequencyAdjusted;
                    }
                } else {
                    $monthlyExpenses += $frequencyAdjusted;
                    if ($blueprint->nature === 'business') {
                        $businessExpenses += $frequencyAdjusted;
                    }
                }
            }

            // Calculate tax reserve (39.1% on business profit)
            $taxableIncome = max(0, $businessIncome - $businessExpenses);
            $taxReserve = $taxableIncome * 0.391;
            $netSpendable = $monthlyIncome - $taxReserve - $monthlyExpenses;

            $projection[] = [
                'year' => $year,
                'month' => $month,
                'period' => $date->format('F Y'),
                'income' => round($monthlyIncome, 2),
                'expenses' => round($monthlyExpenses, 2),
                'tax_reserve' => round($taxReserve, 2),
                'net_spendable' => round($netSpendable, 2),
                'resuming_items' => $resumingThisMonth,
                'resuming_count' => count($resumingThisMonth),
                'resuming_total' => array_sum(array_column($resumingThisMonth, 'amount')),
            ];

            if (!empty($resumingThisMonth)) {
                $resumingItems[$date->format('Y-m')] = $resumingThisMonth;
            }
        }

        // Get currently paused blueprints for reference
        $pausedBlueprints = $blueprints
            ->where('status', 'paused')
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'expected_amount' => $b->expected_amount,
                'pause_reason' => $b->pause_reason,
                'pause_end_date' => $b->pause_end_date?->format('Y-m-d'),
                'resumes_in' => $b->pause_end_date
                    ? $now->diffInMonths($b->pause_end_date, false) . ' months'
                    : 'indefinite',
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'projection' => $projection,
                'paused_blueprints' => $pausedBlueprints,
                'paused_count' => $pausedBlueprints->count(),
            ],
        ]);
    }

    /**
     * Helper: Get frequency-adjusted amount
     */
    private function getFrequencyAdjustedAmount(Blueprint $blueprint): float
    {
        $amount = (float) $blueprint->expected_amount;

        return match ($blueprint->frequency) {
            'weekly' => $amount * 4.33,
            'quarterly' => $amount / 3,
            'annual' => $amount / 12,
            default => $amount,
        };
    }

    /**
     * Helper: Get frequency-adjusted amount for a specific month (respects holidays)
     */
    private function getFrequencyAdjustedAmountForMonth(Blueprint $blueprint, int $year, int $month): float
    {
        if ($blueprint->isPausedForMonth($year, $month)) {
            return 0;
        }

        return $this->getFrequencyAdjustedAmount($blueprint);
    }

    /**
     * Manually match a transaction to a blueprint
     *
     * POST /api/audit/match-blueprint
     */
    public function matchBlueprint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid',
            'blueprint_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($request->transaction_id);

        $blueprint = Blueprint::where('user_id', $user->id)
            ->findOrFail($request->blueprint_id);

        $this->blueprintMatcher->manualMatch($transaction, $blueprint);

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $transaction->id,
                'blueprint_id' => $blueprint->id,
                'blueprint_name' => $blueprint->name,
            ],
            'message' => "Transaction matched to blueprint '{$blueprint->name}'.",
        ]);
    }

    /**
     * Unmatch a transaction from its blueprint
     *
     * POST /api/audit/unmatch-blueprint
     */
    public function unmatchBlueprint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($request->transaction_id);

        if (!$transaction->blueprint_id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not matched to any blueprint.',
            ], 400);
        }

        $this->blueprintMatcher->unmatch($transaction);

        return response()->json([
            'success' => true,
            'message' => 'Transaction unmatched from blueprint.',
        ]);
    }

    /**
     * Assign a transaction to a blueprint with PATTERN LEARNING.
     *
     * When assigning, we:
     * 1. Extract the "Anchor String" (e.g., 'UIM SA', 'MOMENTUM')
     * 2. Create/update a TransactionRule with 'contains' matching
     * 3. Auto-apply to ALL matching transactions across ALL months
     *
     * POST /api/audit/assign-blueprint
     */
    public function assignBlueprint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid',
            'blueprint_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($request->transaction_id);

        // Find or create the blueprint by name
        $blueprint = Blueprint::where('user_id', $user->id)
            ->where('name', $request->blueprint_name)
            ->first();

        if (!$blueprint) {
            // Create new blueprint with sensible defaults
            $isIncome = $transaction->amount > 0;
            $blueprint = Blueprint::create([
                'user_id' => $user->id,
                'name' => $request->blueprint_name,
                'expected_amount' => abs($transaction->amount),
                'item_type' => $isIncome ? 'income' : 'expense',
                'nature' => 'private',
                'due_day' => 1,
                'status' => 'active',
            ]);
        }

        // =============================================
        // PATTERN LEARNING: Extract Anchor String
        // =============================================
        $anchorPattern = TransactionRule::extractPattern($transaction->description);

        // Create or update the transaction rule for this pattern
        $rule = TransactionRule::updateOrCreate(
            [
                'user_id' => $user->id,
                'pattern' => $anchorPattern,
                'match_type' => 'contains',
            ],
            [
                'blueprint_id' => $blueprint->id,
                'is_active' => true,
                'priority' => 10, // Higher priority for user-created rules
            ]
        );
        $rule->recordHit();

        // =============================================
        // AUTO-MAP: Apply to ALL matching transactions
        // =============================================
        $matchedCount = 0;
        $unmatchedTransactions = Transaction::where('user_id', $user->id)
            ->whereNull('blueprint_id')
            ->where(function ($query) {
                $query->whereNull('match_status')
                      ->orWhere('match_status', '!=', 'manual');
            })
            ->get();

        foreach ($unmatchedTransactions as $tx) {
            // Check if description contains the anchor pattern (case-insensitive)
            if (stripos($tx->description, $anchorPattern) !== false) {
                $tx->blueprint_id = $blueprint->id;
                $tx->match_status = 'auto';
                $tx->save();
                $matchedCount++;
                $rule->recordHit();
            }
        }

        // Also assign the original transaction
        $transaction->blueprint_id = $blueprint->id;
        $transaction->match_status = 'manual';
        $transaction->save();

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $transaction->id,
                'blueprint_id' => $blueprint->id,
                'blueprint_name' => $blueprint->name,
                'anchor_pattern' => $anchorPattern,
                'auto_matched' => $matchedCount,
            ],
            'message' => $matchedCount > 0
                ? "Assigned to '{$blueprint->name}'. Pattern '{$anchorPattern}' auto-matched {$matchedCount} more transactions!"
                : "Assigned to '{$blueprint->name}'. Pattern '{$anchorPattern}' saved for future matching.",
        ]);
    }

    /**
     * Get unmatched transactions for a date range
     *
     * GET /api/audit/unmatched
     */
    public function unmatched(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->startOfMonth();
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now()->endOfMonth();

        $transactions = Transaction::where('user_id', $user->id)
            ->dateRange($startDate, $endDate)
            ->unmatched()
            ->where('match_status', '!=', 'unmapped')
            ->orWhereNull('match_status')
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(fn($tx) => [
                'id' => $tx->id,
                'transaction_date' => $tx->transaction_date->format('Y-m-d'),
                'description' => $tx->description,
                'amount' => $tx->amount,
                'balance' => $tx->balance_after,  // Running balance from Excel
                'is_income' => $tx->isIncome(),
                'category' => $tx->category?->name,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'count' => $transactions->count(),
                'period' => $startDate->format('M j') . ' - ' . $endDate->format('M j, Y'),
            ],
        ]);
    }

    // =============================================
    // RESUMPTION ALERTS & DASHBOARD IMPACT
    // =============================================

    /**
     * Get blueprints resuming within N days (default 30)
     * Used for resumption alerts
     *
     * GET /api/audit/resumption-alerts
     */
    public function resumptionAlerts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'sometimes|integer|min:1|max:90',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $days = $request->input('days', 30);

        $alerts = $this->blueprintMatcher->getResumptionAlerts($user, $days);

        // Calculate total amount resuming
        $totalAmount = $alerts->sum('expected_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => $alerts->values(),
                'count' => $alerts->count(),
                'total_amount' => round($totalAmount, 2),
                'within_days' => $days,
            ],
            'message' => $alerts->count() > 0
                ? "You have {$alerts->count()} payment(s) resuming within {$days} days"
                : "No payments resuming within {$days} days",
        ]);
    }

    /**
     * Get next month impact preview for dashboard
     * Shows how spendable cash will change when paused items resume
     *
     * GET /api/audit/next-month-impact
     */
    public function nextMonthImpact(Request $request): JsonResponse
    {
        $user = $request->user();

        $impact = $this->blueprintMatcher->getNextMonthImpact($user);

        return response()->json([
            'success' => true,
            'data' => $impact,
        ]);
    }

    /**
     * Get data availability info for smart month defaulting
     * Returns the date range of transactions and most recent month with data
     *
     * GET /api/audit/data-range
     */
    public function dataRange(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get min and max transaction dates
        $minDate = $user->transactions()->min('transaction_date');
        $maxDate = $user->transactions()->max('transaction_date');

        if (!$minDate || !$maxDate) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_data' => false,
                    'min_date' => null,
                    'max_date' => null,
                    'most_recent_month' => null,
                    'most_recent_year' => null,
                    'total_transactions' => 0,
                    'months_with_data' => [],
                ],
            ]);
        }

        $minDateCarbon = Carbon::parse($minDate);
        $maxDateCarbon = Carbon::parse($maxDate);

        // Get transaction counts by month for the date range
        // Use DATE_TRUNC for PostgreSQL to get month start, then extract year/month
        $monthsWithData = $user->transactions()
            ->selectRaw("date_trunc('month', transaction_date) as month_start, COUNT(*) as count")
            ->groupByRaw("date_trunc('month', transaction_date)")
            ->orderByRaw("date_trunc('month', transaction_date) DESC")
            ->get()
            ->map(function ($row) {
                $monthDate = Carbon::parse($row->month_start);
                return [
                    'year' => $monthDate->year,
                    'month' => $monthDate->month,
                    'count' => (int) $row->count,
                    'label' => $monthDate->format('F Y'),
                ];
            });

        // Most recent month with data
        $mostRecent = $monthsWithData->first();

        return response()->json([
            'success' => true,
            'data' => [
                'has_data' => true,
                'min_date' => $minDateCarbon->format('Y-m-d'),
                'max_date' => $maxDateCarbon->format('Y-m-d'),
                'most_recent_month' => $mostRecent ? $mostRecent['month'] : null,
                'most_recent_year' => $mostRecent ? $mostRecent['year'] : null,
                'total_transactions' => $user->transactions()->count(),
                'months_with_data' => $monthsWithData->toArray(),
            ],
        ]);
    }

    // =============================================
    // GLOBAL RULES MANAGEMENT
    // =============================================

    /**
     * Apply global transaction rules to all unmatched transactions.
     * This runs the pattern-based matching across ALL months.
     *
     * POST /api/audit/apply-rules
     */
    public function applyRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : null;

        $results = $this->blueprintMatcher->applyGlobalRules($user, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => $results['matched'] > 0
                ? "Applied rules: {$results['matched']} transactions matched."
                : 'No transactions matched.',
        ]);
    }

    /**
     * Get all global transaction rules for the user.
     *
     * GET /api/audit/rules
     */
    public function getRules(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = TransactionRule::where('user_id', $user->id)
            ->with(['blueprint:id,name,item_type,bucket', 'category:id,name'])
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get()
            ->map(fn($rule) => [
                'id' => $rule->id,
                'pattern' => $rule->pattern,
                'match_type' => $rule->match_type,
                'blueprint_id' => $rule->blueprint_id,
                'blueprint_name' => $rule->blueprint?->name,
                'category_id' => $rule->category_id,
                'category_name' => $rule->category?->name,
                'bucket' => $rule->bucket ?? $rule->blueprint?->bucket,
                'is_active' => $rule->is_active,
                'hit_count' => $rule->hit_count,
                'priority' => $rule->priority,
                'created_at' => $rule->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'rules' => $rules,
                'count' => $rules->count(),
                'active_count' => $rules->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Create a new global transaction rule.
     *
     * POST /api/audit/rules
     */
    public function createRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pattern' => 'required|string|max:100',
            'match_type' => 'sometimes|in:contains,starts_with,exact',
            'blueprint_id' => 'required|uuid|exists:blueprints,id',
            'priority' => 'sometimes|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify blueprint belongs to user
        $blueprint = Blueprint::where('user_id', $user->id)
            ->findOrFail($request->blueprint_id);

        // Create or update the rule
        $rule = TransactionRule::updateOrCreate(
            [
                'user_id' => $user->id,
                'pattern' => strtoupper($request->pattern),
                'match_type' => $request->input('match_type', 'contains'),
            ],
            [
                'blueprint_id' => $blueprint->id,
                'is_active' => true,
                'priority' => $request->input('priority', 10),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rule->id,
                'pattern' => $rule->pattern,
                'match_type' => $rule->match_type,
                'blueprint_id' => $rule->blueprint_id,
                'blueprint_name' => $blueprint->name,
            ],
            'message' => "Rule created: '{$rule->pattern}' → {$blueprint->name}",
        ]);
    }

    /**
     * Update a global transaction rule.
     *
     * PUT /api/audit/rules/{id}
     */
    public function updateRule(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pattern' => 'sometimes|string|max:100',
            'match_type' => 'sometimes|in:contains,starts_with,exact',
            'blueprint_id' => 'sometimes|uuid|exists:blueprints,id',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $rule = TransactionRule::where('user_id', $user->id)
            ->findOrFail($id);

        // If changing blueprint, verify it belongs to user
        if ($request->has('blueprint_id')) {
            Blueprint::where('user_id', $user->id)
                ->findOrFail($request->blueprint_id);
        }

        $rule->update([
            'pattern' => $request->has('pattern') ? strtoupper($request->pattern) : $rule->pattern,
            'match_type' => $request->input('match_type', $rule->match_type),
            'blueprint_id' => $request->input('blueprint_id', $rule->blueprint_id),
            'is_active' => $request->input('is_active', $rule->is_active),
            'priority' => $request->input('priority', $rule->priority),
        ]);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Rule updated.',
        ]);
    }

    /**
     * Delete a global transaction rule.
     *
     * DELETE /api/audit/rules/{id}
     */
    public function deleteRule(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $rule = TransactionRule::where('user_id', $user->id)
            ->findOrFail($id);

        $pattern = $rule->pattern;
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => "Rule '{$pattern}' deleted.",
        ]);
    }

    /**
     * Get tax year summary for provisional tax calculations
     * South African tax year runs March to February
     *
     * GET /api/audit/tax-year/{year}
     */
    public function taxYearSummary(Request $request, int $year): JsonResponse
    {
        $user = $request->user();

        // SA Tax Year: March 1 of $year to February 28/29 of $year+1
        $startDate = Carbon::create($year, 3, 1)->startOfMonth();
        $endDate = Carbon::create($year + 1, 2, 1)->endOfMonth();

        // Get monthly breakdown
        $monthlyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $monthYear = $currentDate->year;
            $monthNum = $currentDate->month;

            // Get transactions for this month
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();

            $transactions = $user->transactions()
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->get();

            $income = $transactions->where('amount', '>', 0)->sum('amount');
            $expenses = abs($transactions->where('amount', '<', 0)->sum('amount'));

            $monthlyData[] = [
                'year' => $monthYear,
                'month' => $monthNum,
                'label' => $currentDate->format('M Y'),
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'net' => round($income - $expenses, 2),
                'transaction_count' => $transactions->count(),
            ];

            $currentDate->addMonth();
        }

        // Calculate totals
        $totalIncome = collect($monthlyData)->sum('income');
        $totalExpenses = collect($monthlyData)->sum('expenses');

        // Business vs Private breakdown (using blueprints)
        $businessExpenseIds = $user->blueprints()
            ->where('nature', 'business')
            ->where('item_type', 'expense')
            ->pluck('id');

        $businessExpenses = $user->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('blueprint_id', $businessExpenseIds)
            ->where('amount', '<', 0)
            ->sum('amount');

        $businessExpenses = abs($businessExpenses);

        // Tax calculation (39.1% on business income - business expenses)
        $taxableIncome = max(0, $totalIncome - $businessExpenses);
        $estimatedTax = $taxableIncome * 0.391;

        // Provisional tax periods (SA has 2 provisional payments)
        $nextYear = $year + 1;
        $firstProvisional = Carbon::create($year, 8, 31); // Due Aug 31
        $secondProvisional = Carbon::create($nextYear, 2, 28); // Due Feb 28

        return response()->json([
            'success' => true,
            'data' => [
                'tax_year' => "{$year}/{$nextYear}",
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'label' => $startDate->format('M Y') . ' - ' . $endDate->format('M Y'),
                ],
                'summary' => [
                    'total_income' => round($totalIncome, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'business_expenses' => round($businessExpenses, 2),
                    'net' => round($totalIncome - $totalExpenses, 2),
                ],
                'tax' => [
                    'taxable_income' => round($taxableIncome, 2),
                    'estimated_tax' => round($estimatedTax, 2),
                    'tax_rate' => '39.1%',
                ],
                'provisional_payments' => [
                    [
                        'period' => 'First Provisional',
                        'due_date' => $firstProvisional->format('Y-m-d'),
                        'amount' => round($estimatedTax / 2, 2),
                    ],
                    [
                        'period' => 'Second Provisional',
                        'due_date' => $secondProvisional->format('Y-m-d'),
                        'amount' => round($estimatedTax / 2, 2),
                    ],
                ],
                'monthly_breakdown' => $monthlyData,
            ],
        ]);
    }
}
