<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Liability;
use App\Models\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * LiabilityController - Debt Recovery Management
 *
 * Manages liabilities (debts) being paid off through Blueprint items.
 * Handles the pay-off celebration when a liability reaches R0.
 */
class LiabilityController extends Controller
{
    /**
     * List all liabilities for the user
     *
     * GET /api/liabilities
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $liabilities = Liability::where('user_id', $user->id)
            ->with('category:id,name,color,icon')
            ->orderBy('status')
            ->orderBy('current_balance', 'desc')
            ->get()
            ->map(function ($liability) {
                return $this->formatLiability($liability);
            });

        // Summary stats
        $active = $liabilities->where('status', 'active');
        $paidOff = $liabilities->where('status', 'paid_off');

        $summary = [
            'total_count' => $liabilities->count(),
            'active_count' => $active->count(),
            'paid_off_count' => $paidOff->count(),
            'total_original_debt' => $liabilities->sum('original_balance'),
            'total_remaining_debt' => $active->sum('current_balance'),
            'total_monthly_payments' => $active->sum('monthly_installment'),
            'total_amount_freed' => $paidOff->sum('amount_freed'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'liabilities' => $liabilities,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Get a single liability with details
     *
     * GET /api/liabilities/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->with(['category:id,name,color,icon', 'blueprints:id,name,expected_amount,is_active'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatLiability($liability, true),
        ]);
    }

    /**
     * Create a new liability
     *
     * POST /api/liabilities
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'liability_type' => 'required|in:arrears,loan,credit_card,store_account,other',
            'original_balance' => 'required|numeric|min:0.01',
            'current_balance' => 'nullable|numeric|min:0',
            'monthly_installment' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'total_installments' => 'nullable|integer|min:1',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'creditor' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'target_payoff_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Default current_balance to original if not provided
        $currentBalance = $request->current_balance ?? $request->original_balance;

        // Auto-calculate total installments if not provided
        $totalInstallments = $request->total_installments
            ?? Liability::calculateInstallments($currentBalance, $request->monthly_installment);

        $liability = Liability::create([
            'user_id' => $user->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'liability_type' => $request->liability_type,
            'original_balance' => $request->original_balance,
            'current_balance' => $currentBalance,
            'monthly_installment' => $request->monthly_installment,
            'interest_rate' => $request->interest_rate,
            'total_installments' => $totalInstallments,
            'completed_installments' => 0,
            'status' => 'active',
            'creditor' => $request->creditor,
            'reference_number' => $request->reference_number,
            'start_date' => $request->start_date,
            'target_payoff_date' => $request->target_payoff_date,
            'notes' => $request->notes,
        ]);

        $liability->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $this->formatLiability($liability),
            'message' => 'Liability created successfully.',
        ], 201);
    }

    /**
     * Update a liability
     *
     * PUT /api/liabilities/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'liability_type' => 'sometimes|in:arrears,loan,credit_card,store_account,other',
            'current_balance' => 'sometimes|numeric|min:0',
            'monthly_installment' => 'sometimes|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'total_installments' => 'nullable|integer|min:1',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'creditor' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50',
            'target_payoff_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'status' => 'sometimes|in:active,paused',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $liability->update($request->only([
            'name', 'liability_type', 'current_balance', 'monthly_installment',
            'interest_rate', 'total_installments', 'category_id', 'creditor',
            'reference_number', 'target_payoff_date', 'notes', 'status',
        ]));

        $liability->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $this->formatLiability($liability),
            'message' => 'Liability updated successfully.',
        ]);
    }

    /**
     * Delete a liability
     *
     * DELETE /api/liabilities/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Unlink any blueprints
        Blueprint::where('liability_id', $id)->update(['liability_id' => null]);

        $liability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Liability deleted successfully.',
        ]);
    }

    /**
     * Record a payment against a liability (manual adjustment)
     *
     * POST /api/liabilities/{id}/payment
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $liability->recordPayment($request->amount);

        // If paid off, deactivate linked blueprints
        if ($result['isPaidOff']) {
            $deactivatedCount = $liability->deactivateLinkedBlueprints();

            return response()->json([
                'success' => true,
                'data' => [
                    'liability' => $this->formatLiability($liability->fresh()),
                    'result' => $result,
                    'deactivated_blueprints' => $deactivatedCount,
                ],
                'celebration' => [
                    'type' => 'liability_paid_off',
                    'title' => 'Goal Reached!',
                    'message' => "{$liability->name} Paid Off",
                    'subtitle' => "R" . number_format($result['amountFreed'], 2) . " freed up in monthly budget",
                    'liability_name' => $liability->name,
                    'amount_freed' => $result['amountFreed'],
                ],
                'message' => 'Congratulations! Liability has been paid off!',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'liability' => $this->formatLiability($liability->fresh()),
                'result' => $result,
            ],
            'message' => 'Payment recorded successfully.',
        ]);
    }

    /**
     * Link a liability to a blueprint
     *
     * POST /api/liabilities/{id}/link-blueprint
     */
    public function linkBlueprint(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'blueprint_id' => 'required|uuid|exists:blueprints,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify blueprint belongs to user
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($request->blueprint_id);

        $blueprint->update(['liability_id' => $liability->id]);

        return response()->json([
            'success' => true,
            'data' => [
                'liability_id' => $liability->id,
                'blueprint_id' => $blueprint->id,
            ],
            'message' => 'Blueprint linked to liability.',
        ]);
    }

    /**
     * Create a blueprint from a liability
     *
     * POST /api/liabilities/{id}/create-blueprint
     */
    public function createBlueprint(Request $request, string $id): JsonResponse
    {
        $liability = Liability::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bucket' => 'required|in:needs,wants,savings',
            'due_day' => 'nullable|integer|min:1|max:31',
            'match_pattern' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if blueprint already exists for this liability
        $existingBlueprint = Blueprint::where('liability_id', $liability->id)->first();
        if ($existingBlueprint) {
            return response()->json([
                'success' => false,
                'message' => 'A blueprint already exists for this liability.',
                'data' => ['blueprint_id' => $existingBlueprint->id],
            ], 409);
        }

        $blueprint = Blueprint::create([
            'user_id' => $request->user()->id,
            'liability_id' => $liability->id,
            'category_id' => $liability->category_id,
            'name' => $liability->name,
            'expected_amount' => $liability->monthly_installment,
            'amount_tolerance' => 5.00,
            'due_day' => $request->due_day,
            'due_day_tolerance' => 3,
            'item_type' => 'expense',
            'bucket' => $request->bucket,
            'match_pattern' => $request->match_pattern ?? strtoupper($liability->name),
            'match_type' => 'contains',
            'frequency' => 'monthly',
            'is_active' => true,
            'priority' => 10, // Higher priority for debt payments
            'notes' => "Linked to liability: {$liability->name} (R{$liability->current_balance} remaining)",
        ]);

        $blueprint->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $blueprint,
            'message' => 'Blueprint created and linked to liability.',
        ], 201);
    }

    /**
     * Get recently paid off liabilities (for celebration)
     *
     * GET /api/liabilities/recently-paid-off
     */
    public function recentlyPaidOff(Request $request): JsonResponse
    {
        $liabilities = Liability::where('user_id', $request->user()->id)
            ->where('status', 'paid_off')
            ->where('paid_off_at', '>=', now()->subDays(30))
            ->orderBy('paid_off_at', 'desc')
            ->get()
            ->map(function ($liability) {
                return [
                    'id' => $liability->id,
                    'name' => $liability->name,
                    'original_balance' => (float) $liability->original_balance,
                    'amount_freed' => (float) $liability->amount_freed,
                    'paid_off_at' => $liability->paid_off_at,
                    'celebration' => [
                        'type' => 'liability_paid_off',
                        'title' => 'Goal Reached!',
                        'message' => "{$liability->name} Paid Off",
                        'subtitle' => "R" . number_format($liability->amount_freed, 2) . " freed up in monthly budget",
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $liabilities,
        ]);
    }

    /**
     * Format liability for response
     */
    private function formatLiability(Liability $liability, bool $includeBlueprints = false): array
    {
        $data = [
            'id' => $liability->id,
            'name' => $liability->name,
            'liability_type' => $liability->liability_type,
            'original_balance' => (float) $liability->original_balance,
            'current_balance' => (float) $liability->current_balance,
            'monthly_installment' => (float) $liability->monthly_installment,
            'interest_rate' => $liability->interest_rate ? (float) $liability->interest_rate : null,
            'total_installments' => $liability->total_installments,
            'completed_installments' => $liability->completed_installments,
            'remaining_installments' => $liability->remaining_installments,
            'status' => $liability->status,
            'is_paid_off' => $liability->is_paid_off,
            'paid_off_at' => $liability->paid_off_at,
            'amount_freed' => $liability->amount_freed ? (float) $liability->amount_freed : null,
            'progress_percent' => $liability->progress_percent,
            'amount_paid' => $liability->amount_paid,
            'estimated_payoff_date' => $liability->estimated_payoff_date?->format('Y-m-d'),
            'installment_label' => $liability->installment_label,
            'creditor' => $liability->creditor,
            'reference_number' => $liability->reference_number,
            'start_date' => $liability->start_date?->format('Y-m-d'),
            'target_payoff_date' => $liability->target_payoff_date?->format('Y-m-d'),
            'notes' => $liability->notes,
            'category' => $liability->category,
            'created_at' => $liability->created_at,
        ];

        if ($includeBlueprints && $liability->relationLoaded('blueprints')) {
            $data['blueprints'] = $liability->blueprints;
        }

        return $data;
    }
}
