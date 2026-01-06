<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetItem;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscoveryController extends Controller
{
    /**
     * Get transactions that need bucket assignment.
     * These are expenses without a bucket assigned.
     */
    public function getUnassigned(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get current month's date range
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Get expenses without bucket assignment
        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->where('amount', '<', 0) // Only expenses
            ->whereNull('bucket')
            ->where('is_transfer', false)
            ->with('category')
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Group by extracted pattern to show unique merchants
        $grouped = [];
        foreach ($transactions as $tx) {
            $pattern = TransactionRule::extractPattern($tx->description);
            if (!isset($grouped[$pattern])) {
                $grouped[$pattern] = [
                    'pattern' => $pattern,
                    'transactions' => [],
                    'total_amount' => 0,
                    'count' => 0,
                    'sample_description' => $tx->description,
                    'category' => $tx->category,
                ];
            }
            $grouped[$pattern]['transactions'][] = $tx;
            $grouped[$pattern]['total_amount'] += abs($tx->amount);
            $grouped[$pattern]['count']++;
        }

        // Convert to array and sort by total amount
        $patterns = array_values($grouped);
        usort($patterns, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        return response()->json([
            'data' => [
                'total_unassigned' => $transactions->count(),
                'patterns' => $patterns,
                'transactions' => $transactions,
            ],
        ]);
    }

    /**
     * Assign a bucket to a transaction pattern.
     * Creates a transaction rule and updates matching transactions.
     */
    public function assignBucket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pattern' => 'required|string|max:100',
            'bucket' => 'required|in:needs,wants,savings',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'uuid',
            'create_budget_item' => 'boolean',
            'planned_amount' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($user, $validated) {
            // 1. Create or update the transaction rule
            $rule = TransactionRule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'pattern' => strtoupper($validated['pattern']),
                    'match_type' => 'contains',
                ],
                [
                    'category_id' => $validated['category_id'] ?? null,
                    'bucket' => $validated['bucket'],
                    'is_active' => true,
                    'priority' => 10, // User rules get higher priority
                ]
            );

            // 2. Update all matching transactions with the bucket
            $query = Transaction::where('user_id', $user->id)
                ->where('amount', '<', 0)
                ->whereNull('bucket');

            // If specific transaction IDs provided, use those
            if (!empty($validated['transaction_ids'])) {
                $query->whereIn('id', $validated['transaction_ids']);
            } else {
                // Otherwise match by pattern
                $query->where(function ($q) use ($validated) {
                    $q->where('description', 'ILIKE', '%' . $validated['pattern'] . '%')
                      ->orWhere('raw_description', 'ILIKE', '%' . $validated['pattern'] . '%');
                });
            }

            $updatedCount = $query->update([
                'bucket' => $validated['bucket'],
                'category_id' => $validated['category_id'] ?? DB::raw('category_id'),
                'is_categorized' => $validated['category_id'] ? true : DB::raw('is_categorized'),
                'categorized_by' => $validated['category_id'] ? 'rule' : DB::raw('categorized_by'),
            ]);

            // 3. Create/update budget item if requested
            $budgetItem = null;
            if ($validated['create_budget_item'] ?? true) {
                $currentBudget = BudgetPeriod::where('user_id', $user->id)
                    ->where('year', now()->year)
                    ->where('month', now()->month)
                    ->first();

                if ($currentBudget) {
                    // Calculate actual amount from matching transactions
                    $actualAmount = Transaction::where('user_id', $user->id)
                        ->where('bucket', $validated['bucket'])
                        ->whereBetween('transaction_date', [
                            now()->startOfMonth(),
                            now()->endOfMonth(),
                        ])
                        ->when($validated['category_id'], function ($q) use ($validated) {
                            $q->where('category_id', $validated['category_id']);
                        })
                        ->sum(DB::raw('ABS(amount)'));

                    $budgetItem = BudgetItem::updateOrCreate(
                        [
                            'budget_period_id' => $currentBudget->id,
                            'category_id' => $validated['category_id'],
                        ],
                        [
                            'name' => $validated['category_id']
                                ? null
                                : ucwords(strtolower($validated['pattern'])),
                            'bucket' => $validated['bucket'],
                            'planned_amount' => $validated['planned_amount'] ?? $actualAmount,
                            'actual_amount' => $actualAmount,
                            'is_fixed' => false,
                        ]
                    );
                }
            }

            // Record hit on the rule
            $rule->recordHit();

            return response()->json([
                'message' => "Assigned {$updatedCount} transactions to {$validated['bucket']}",
                'data' => [
                    'rule' => $rule,
                    'updated_count' => $updatedCount,
                    'budget_item' => $budgetItem,
                ],
            ]);
        });
    }

    /**
     * Get user's transaction rules
     */
    public function getRules(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = TransactionRule::where('user_id', $user->id)
            ->with('category')
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        return response()->json([
            'data' => $rules,
        ]);
    }

    /**
     * Delete a transaction rule
     */
    public function deleteRule(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $rule = TransactionRule::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $rule->delete();

        return response()->json([
            'message' => 'Rule deleted successfully',
        ]);
    }

    /**
     * Apply rules to uncategorized transactions
     */
    public function applyRules(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = TransactionRule::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        $transactions = Transaction::where('user_id', $user->id)
            ->whereNull('bucket')
            ->where('amount', '<', 0)
            ->get();

        $applied = 0;
        foreach ($transactions as $tx) {
            foreach ($rules as $rule) {
                if ($rule->matches($tx->description) || $rule->matches($tx->raw_description ?? '')) {
                    $tx->update([
                        'bucket' => $rule->bucket,
                        'category_id' => $rule->category_id ?? $tx->category_id,
                        'is_categorized' => $rule->category_id ? true : $tx->is_categorized,
                        'categorized_by' => $rule->category_id ? 'rule' : $tx->categorized_by,
                    ]);
                    $rule->recordHit();
                    $applied++;
                    break;
                }
            }
        }

        return response()->json([
            'message' => "Applied rules to {$applied} transactions",
            'data' => [
                'applied_count' => $applied,
            ],
        ]);
    }

    /**
     * Get discovery stats for dashboard
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        $unassignedCount = Transaction::where('user_id', $user->id)
            ->whereBetween('transaction_date', [$now->startOfMonth(), $now->endOfMonth()])
            ->where('amount', '<', 0)
            ->whereNull('bucket')
            ->where('is_transfer', false)
            ->count();

        $rulesCount = TransactionRule::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'data' => [
                'unassigned_count' => $unassignedCount,
                'rules_count' => $rulesCount,
                'needs_attention' => $unassignedCount > 0,
            ],
        ]);
    }
}
