<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetCard;
use App\Models\BudgetCardItem;
use App\Models\Blueprint;
use App\Services\AuditEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * BudgetCardController - Monthly Budget Card Management
 *
 * Manages the creation, viewing, and finalization of monthly Budget Cards.
 * Each card is a snapshot of the user's Blueprint for that month, plus
 * any one-off items specific to that period.
 */
class BudgetCardController extends Controller
{
    public function __construct(
        private AuditEngineService $auditEngine
    ) {}

    /**
     * List all budget cards for the user
     *
     * GET /api/budget-cards
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cards = BudgetCard::where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($card) {
                return [
                    'id' => $card->id,
                    'year' => $card->year,
                    'month' => $card->month,
                    'period_label' => $card->period_label,
                    'status' => $card->status,
                    'success_grade' => $card->success_grade,
                    'letter_grade' => $card->letter_grade,
                    'total_expected_income' => $card->total_expected_income,
                    'total_expected_expense' => $card->total_expected_expense,
                    'total_matched' => $card->total_matched,
                    'total_leaked' => $card->total_leaked,
                    'surplus_amount' => $card->surplus_amount,
                    'is_current_month' => $card->is_current_month,
                    'finalized_at' => $card->finalized_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    /**
     * Get current month's budget card (or create if doesn't exist)
     *
     * GET /api/budget-cards/current
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $card = BudgetCard::where('user_id', $user->id)
            ->forPeriod($now->year, $now->month)
            ->first();

        if (!$card) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No budget card exists for this month. Generate one first.',
            ]);
        }

        return $this->getCardDetails($card);
    }

    /**
     * Get a specific budget card with full details
     *
     * GET /api/budget-cards/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->getCardDetails($card);
    }

    /**
     * Generate a new budget card from blueprints
     *
     * POST /api/budget-cards/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $year = $request->year;
        $month = $request->month;

        // Check if card already exists
        $existing = BudgetCard::where('user_id', $user->id)
            ->forPeriod($year, $month)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A budget card already exists for this period.',
                'data' => ['card_id' => $existing->id],
            ], 409);
        }

        // Check for active blueprints
        $blueprintCount = Blueprint::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        if ($blueprintCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No active blueprint items found. Create your blueprint first.',
            ], 400);
        }

        // RULE 3: Get rollover amount (anchor or previous surplus)
        $rollover = $this->auditEngine->getRolloverAmount($user->id, $year, $month);

        // Generate the card
        $card = BudgetCard::generateFromBlueprints($user->id, $year, $month);
        $card->update(['rollover_from_previous' => $rollover]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $card->id,
                'period_label' => $card->period_label,
                'status' => $card->status,
                'item_count' => $card->items()->count(),
                'total_expected_income' => $card->total_expected_income,
                'total_expected_expense' => $card->total_expected_expense,
                'rollover_from_previous' => $rollover,
            ],
            'message' => 'Budget card generated successfully.',
        ]);
    }

    /**
     * Renew current card for next month (copy blueprint to new card)
     *
     * POST /api/budget-cards/renew
     */
    public function renew(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now()->addMonth(); // Next month

        return $this->generate(new Request([
            'year' => $now->year,
            'month' => $now->month,
        ]));
    }

    /**
     * Add a one-off item to a budget card
     *
     * POST /api/budget-cards/{id}/items
     */
    public function addItem(Request $request, string $id): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->where('status', '!=', 'finalized')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'expected_amount' => 'required|numeric|min:0.01',
            'item_type' => 'required|in:income,expense',
            'bucket' => 'required_if:item_type,expense|in:needs,wants,savings',
            'expected_day' => 'nullable|integer|min:1|max:31',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'match_pattern' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = BudgetCardItem::create([
            'budget_card_id' => $card->id,
            'blueprint_id' => null, // One-off, not from blueprint
            'item_type' => $request->item_type,
            'name' => $request->name,
            'expected_amount' => $request->expected_amount,
            'expected_day' => $request->expected_day,
            'bucket' => $request->item_type === 'income' ? 'needs' : $request->bucket,
            'category_id' => $request->category_id,
            'match_pattern' => $request->match_pattern ?? strtoupper($request->name),
            'match_type' => 'contains',
            'is_one_off' => true,
            'audit_status' => 'pending',
            'notes' => $request->notes,
        ]);

        // Update card totals
        if ($request->item_type === 'income') {
            $card->increment('total_expected_income', $request->expected_amount);
        } else {
            $card->increment('total_expected_expense', $request->expected_amount);
        }

        $item->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'One-off item added to budget card.',
        ], 201);
    }

    /**
     * Update a budget card item
     *
     * PUT /api/budget-cards/{cardId}/items/{itemId}
     */
    public function updateItem(Request $request, string $cardId, string $itemId): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->where('status', '!=', 'finalized')
            ->findOrFail($cardId);

        $item = BudgetCardItem::where('budget_card_id', $card->id)
            ->findOrFail($itemId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'expected_amount' => 'sometimes|numeric|min:0.01',
            'expected_day' => 'nullable|integer|min:1|max:31',
            'bucket' => 'sometimes|in:needs,wants,savings',
            'match_pattern' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Track amount change for totals update
        $oldAmount = $item->expected_amount;
        $newAmount = $request->expected_amount ?? $oldAmount;

        $item->update($request->only([
            'name', 'expected_amount', 'expected_day', 'bucket', 'match_pattern', 'notes',
        ]));

        // Update card totals if amount changed
        if ($newAmount != $oldAmount) {
            $diff = $newAmount - $oldAmount;
            if ($item->item_type === 'income') {
                $card->increment('total_expected_income', $diff);
            } else {
                $card->increment('total_expected_expense', $diff);
            }
        }

        $item->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Item updated successfully.',
        ]);
    }

    /**
     * Remove a one-off item from a budget card
     *
     * DELETE /api/budget-cards/{cardId}/items/{itemId}
     */
    public function removeItem(Request $request, string $cardId, string $itemId): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->where('status', '!=', 'finalized')
            ->findOrFail($cardId);

        $item = BudgetCardItem::where('budget_card_id', $card->id)
            ->where('is_one_off', true) // Can only delete one-offs
            ->findOrFail($itemId);

        // Update card totals
        if ($item->item_type === 'income') {
            $card->decrement('total_expected_income', $item->expected_amount);
        } else {
            $card->decrement('total_expected_expense', $item->expected_amount);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from budget card.',
        ]);
    }

    /**
     * Activate a draft budget card
     *
     * POST /api/budget-cards/{id}/activate
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        $card->activate();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $card->id,
                'status' => $card->status,
            ],
            'message' => 'Budget card activated.',
        ]);
    }

    /**
     * Finalize a budget card (month-end CFO review)
     *
     * POST /api/budget-cards/{id}/finalize
     */
    public function finalize(Request $request, string $id): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'active'])
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'surplus_action' => 'required|in:savings,buffer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Mark any pending items as missing
        $missingCount = $this->auditEngine->markMissingItems($card);

        // Calculate final grade
        $gradeResult = $this->auditEngine->calculateSuccessGrade($card);

        // Finalize the card
        $card->finalize($request->surplus_action);

        // Get final stats
        $stats = $this->auditEngine->getAuditStats($card);

        return response()->json([
            'success' => true,
            'data' => [
                'card_id' => $card->id,
                'status' => 'finalized',
                'finalized_at' => $card->finalized_at,
                'grade' => $gradeResult,
                'stats' => $stats,
                'missing_count' => $missingCount,
                'surplus_action' => $request->surplus_action,
            ],
            'message' => "Budget card finalized with grade {$gradeResult['letter_grade']}.",
        ]);
    }

    /**
     * Get grade breakdown for a budget card
     *
     * GET /api/budget-cards/{id}/grade
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $card = BudgetCard::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $gradeResult = $this->auditEngine->calculateSuccessGrade($card);

        return response()->json([
            'success' => true,
            'data' => $gradeResult,
        ]);
    }

    /**
     * Helper: Get detailed card data
     */
    private function getCardDetails(BudgetCard $card): JsonResponse
    {
        $items = $card->items()
            ->with('category:id,name,color,icon')
            ->with('matchedTransaction:id,transaction_date,description,amount')
            ->orderBy('item_type')
            ->orderBy('bucket')
            ->orderBy('expected_day')
            ->get();

        // Group items by status
        $grouped = [
            'pending' => $items->where('audit_status', 'pending')->values(),
            'matched' => $items->where('audit_status', 'matched')->values(),
            'missing' => $items->where('audit_status', 'missing')->values(),
        ];

        // Get leaks and windfalls
        $leaks = $card->transactionAudits()
            ->where('classification', 'leak')
            ->with('transaction:id,transaction_date,description,amount')
            ->get();

        $windfalls = $card->transactionAudits()
            ->where('classification', 'windfall')
            ->with('transaction:id,transaction_date,description,amount')
            ->get();

        $transfers = $card->transactionAudits()
            ->where('classification', 'transfer')
            ->with('transaction:id,transaction_date,description,amount')
            ->get();

        // Get stats
        $stats = $this->auditEngine->getAuditStats($card);

        return response()->json([
            'success' => true,
            'data' => [
                'card' => [
                    'id' => $card->id,
                    'year' => $card->year,
                    'month' => $card->month,
                    'period_label' => $card->period_label,
                    'status' => $card->status,
                    'is_current_month' => $card->is_current_month,
                    'is_finalized' => $card->is_finalized,
                    'finalized_at' => $card->finalized_at,
                    'rollover_from_previous' => $card->rollover_from_previous,
                    'surplus_amount' => $card->surplus_amount,
                    'surplus_action' => $card->surplus_action,
                ],
                'items' => [
                    'all' => $items,
                    'grouped' => $grouped,
                ],
                'audits' => [
                    'leaks' => $leaks,
                    'windfalls' => $windfalls,
                    'transfers' => $transfers,
                ],
                'stats' => $stats,
            ],
        ]);
    }
}
