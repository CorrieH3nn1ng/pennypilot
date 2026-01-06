<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\FixedExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * BlueprintController - Master Template Management
 *
 * Manages the user's Blueprint - their strategic plan of recurring
 * income and expenses that forms the basis for monthly Budget Cards.
 */
class BlueprintController extends Controller
{
    /**
     * List all blueprints for the authenticated user
     *
     * GET /api/blueprints
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $blueprints = Blueprint::where('user_id', $user->id)
            ->with('category:id,name,color,icon')
            ->orderBy('item_type')
            ->orderBy('bucket')
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        // Group by type and bucket for easier frontend rendering
        $grouped = [
            'income' => $blueprints->where('item_type', 'income')->values(),
            'expense' => [
                'needs' => $blueprints->where('item_type', 'expense')->where('bucket', 'needs')->values(),
                'wants' => $blueprints->where('item_type', 'expense')->where('bucket', 'wants')->values(),
                'savings' => $blueprints->where('item_type', 'expense')->where('bucket', 'savings')->values(),
            ],
        ];

        // Calculate totals - INCOME only from item_type='income'
        $totalIncome = $blueprints->where('item_type', 'income')->where('is_active', true)->sum('expected_amount');
        $totalExpense = $blueprints->where('item_type', 'expense')->where('is_active', true)->sum('expected_amount');

        // Expense buckets - ONLY count expense items, not income
        $needsExpense = $blueprints->where('item_type', 'expense')->where('bucket', 'needs')->where('is_active', true)->sum('expected_amount');
        $wantsExpense = $blueprints->where('item_type', 'expense')->where('bucket', 'wants')->where('is_active', true)->sum('expected_amount');
        $savingsExpense = $blueprints->where('item_type', 'expense')->where('bucket', 'savings')->where('is_active', true)->sum('expected_amount');

        // Business income and expenses for tax calculation
        $businessIncome = $blueprints
            ->where('item_type', 'income')
            ->where('nature', 'business')
            ->where('is_active', true)
            ->sum('expected_amount');

        $businessExpenses = $blueprints
            ->where('item_type', 'expense')
            ->where('nature', 'business')
            ->where('is_active', true)
            ->sum('expected_amount');

        // TAXABLE INCOME = Business Income - Business Expenses (deductions reduce tax!)
        $taxableIncome = bcsub((string) $businessIncome, (string) $businessExpenses, 2);

        // Tax Reserve: 39.1% on TAXABLE income (not VAT-registered)
        $taxReserve = bcmul($taxableIncome, '0.391', 2);

        // ACTUAL SPENDABLE CASH = Income - Tax Reserve - Expenses (THE REAL NUMBER)
        $actualSpendable = bcsub(bcsub((string) $totalIncome, $taxReserve, 2), (string) $totalExpense, 2);

        $totals = [
            'income' => (float) $totalIncome,
            'expense' => (float) $totalExpense,
            'needs' => (float) $needsExpense,
            'wants' => (float) $wantsExpense,
            'savings' => (float) $savingsExpense,
            'active_count' => $blueprints->where('is_active', true)->count(),
            'total_count' => $blueprints->count(),
            // Tax calculations
            'business_income' => (float) $businessIncome,
            'business_expenses' => (float) $businessExpenses,
            'taxable_income' => (float) $taxableIncome,
            'tax_rate' => 39.1,
            'tax_reserve' => (float) $taxReserve,
            // The number that matters
            'actual_spendable' => (float) $actualSpendable,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'blueprints' => $blueprints,
                'grouped' => $grouped,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * Create a new blueprint item
     *
     * POST /api/blueprints
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'expected_amount' => 'required|numeric|min:0.01',
            'item_type' => 'required|in:income,expense',
            'bucket' => 'required_if:item_type,expense|in:needs,wants,savings',
            'nature' => 'nullable|in:business,private',
            'due_day' => 'nullable|integer|min:1|max:31',
            'due_day_tolerance' => 'nullable|integer|min:0|max:15',
            'amount_tolerance' => 'nullable|numeric|min:0|max:50',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'liability_id' => 'nullable|uuid|exists:liabilities,id',
            'match_pattern' => 'nullable|string|max:100',
            'match_type' => 'nullable|in:contains,starts_with,exact',
            'frequency' => 'nullable|in:monthly,weekly,quarterly,annual',
            'priority' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $blueprint = Blueprint::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'expected_amount' => $request->expected_amount,
            'item_type' => $request->item_type,
            'bucket' => $request->item_type === 'income' ? 'needs' : $request->bucket,
            'nature' => $request->nature ?? 'private',
            'due_day' => $request->due_day,
            'due_day_tolerance' => $request->due_day_tolerance ?? 3,
            'amount_tolerance' => $request->amount_tolerance ?? 5.00,
            'category_id' => $request->category_id,
            'liability_id' => $request->item_type === 'expense' ? $request->liability_id : null,
            'match_pattern' => $request->match_pattern ?? strtoupper($request->name),
            'match_type' => $request->match_type ?? 'contains',
            'frequency' => $request->frequency ?? 'monthly',
            'priority' => $request->priority ?? 0,
            'notes' => $request->notes,
            'is_active' => true,
        ]);

        $blueprint->load('category:id,name,color,icon', 'liability:id,name,current_balance');

        return response()->json([
            'success' => true,
            'data' => $blueprint,
            'message' => 'Blueprint item created successfully.',
        ], 201);
    }

    /**
     * Bulk create blueprint items
     *
     * POST /api/blueprints/bulk
     *
     * Designed for 2025 reconstruction - accepts multiple items at once
     * with auto-generation of match_pattern and match_type defaults.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:50',
            'items.*.name' => 'required|string|max:100',
            'items.*.expected_amount' => 'required|numeric|min:0.01',
            'items.*.item_type' => 'required|in:income,expense',
            'items.*.bucket' => 'required_if:items.*.item_type,expense|in:needs,wants,savings',
            'items.*.nature' => 'nullable|in:business,private',
            'items.*.due_day' => 'nullable|integer|min:1|max:31',
            'items.*.category_id' => 'nullable|uuid|exists:categories,id',
            'items.*.liability_id' => 'nullable|uuid|exists:liabilities,id',
            'items.*.match_pattern' => 'nullable|string|max:100',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $items = $request->input('items');
        $created = [];
        $skipped = [];

        \DB::beginTransaction();

        try {
            foreach ($items as $index => $item) {
                // Skip empty rows
                if (empty($item['name']) || empty($item['expected_amount'])) {
                    continue;
                }

                // Check for duplicate by name
                $exists = Blueprint::where('user_id', $user->id)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'index' => $index,
                        'name' => $item['name'],
                        'reason' => 'Already exists',
                    ];
                    continue;
                }

                // Auto-generate match_pattern from name if not provided
                $matchPattern = !empty($item['match_pattern'])
                    ? strtoupper(trim($item['match_pattern']))
                    : strtoupper(trim($item['name']));

                $blueprint = Blueprint::create([
                    'user_id' => $user->id,
                    'name' => trim($item['name']),
                    'expected_amount' => $item['expected_amount'],
                    'item_type' => $item['item_type'],
                    'bucket' => $item['item_type'] === 'income' ? 'needs' : ($item['bucket'] ?? 'needs'),
                    'nature' => $item['nature'] ?? 'private',
                    'due_day' => $item['due_day'] ?? null,
                    'due_day_tolerance' => 3,
                    'amount_tolerance' => 5.00,
                    'category_id' => $item['category_id'] ?? null,
                    'liability_id' => $item['item_type'] === 'expense' ? ($item['liability_id'] ?? null) : null,
                    'match_pattern' => $matchPattern,
                    'match_type' => 'contains',  // Default for bulk entry
                    'frequency' => 'monthly',
                    'is_active' => true,
                    'priority' => 0,
                    'notes' => $item['notes'] ?? null,
                ]);

                $blueprint->load('category:id,name,color,icon', 'liability:id,name,current_balance');
                $created[] = $blueprint;
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'created' => $created,
                    'created_count' => count($created),
                    'skipped' => $skipped,
                    'skipped_count' => count($skipped),
                ],
                'message' => sprintf(
                    'Created %d blueprint items, skipped %d duplicates.',
                    count($created),
                    count($skipped)
                ),
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create blueprints: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single blueprint
     *
     * GET /api/blueprints/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->with('category:id,name,color,icon')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $blueprint,
        ]);
    }

    /**
     * Update a blueprint item
     *
     * PUT /api/blueprints/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'expected_amount' => 'sometimes|numeric|min:0.01',
            'item_type' => 'sometimes|in:income,expense',
            'bucket' => 'sometimes|in:needs,wants,savings',
            'due_day' => 'nullable|integer|min:1|max:31',
            'due_day_tolerance' => 'nullable|integer|min:0|max:15',
            'amount_tolerance' => 'nullable|numeric|min:0|max:50',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'match_pattern' => 'nullable|string|max:100',
            'match_type' => 'nullable|in:contains,starts_with,exact',
            'frequency' => 'nullable|in:monthly,weekly,quarterly,annual',
            'priority' => 'nullable|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $blueprint->update($request->only([
            'name', 'expected_amount', 'item_type', 'bucket',
            'due_day', 'due_day_tolerance', 'amount_tolerance',
            'category_id', 'match_pattern', 'match_type',
            'frequency', 'priority', 'is_active', 'notes',
        ]));

        $blueprint->load('category:id,name,color,icon');

        return response()->json([
            'success' => true,
            'data' => $blueprint,
            'message' => 'Blueprint item updated successfully.',
        ]);
    }

    /**
     * Delete a blueprint item
     *
     * DELETE /api/blueprints/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $blueprint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blueprint item deleted successfully.',
        ]);
    }

    /**
     * Toggle active status
     *
     * POST /api/blueprints/{id}/toggle
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $blueprint = Blueprint::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $blueprint->update(['is_active' => !$blueprint->is_active]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $blueprint->id,
                'is_active' => $blueprint->is_active,
            ],
            'message' => $blueprint->is_active ? 'Item activated.' : 'Item deactivated.',
        ]);
    }

    /**
     * Import blueprints from existing FixedExpenses
     *
     * POST /api/blueprints/import-from-fixed
     */
    public function importFromFixed(Request $request): JsonResponse
    {
        $user = $request->user();

        $fixedExpenses = FixedExpense::where('user_id', $user->id)->get();

        if ($fixedExpenses->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'imported' => 0,
                    'skipped' => 0,
                ],
                'message' => 'No fixed expenses found to import.',
            ]);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($fixedExpenses as $expense) {
            // Check if already imported (by name match)
            $exists = Blueprint::where('user_id', $user->id)
                ->where('name', $expense->name)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Blueprint::create([
                'user_id' => $user->id,
                'category_id' => $expense->category_id,
                'name' => $expense->name,
                'expected_amount' => $expense->amount,
                'amount_tolerance' => 5.00,
                'due_day' => $expense->due_day,
                'due_day_tolerance' => 3,
                'item_type' => 'expense',
                'bucket' => $expense->bucket ?? 'needs',
                'match_pattern' => strtoupper($expense->name),
                'match_type' => 'contains',
                'frequency' => 'monthly',
                'is_active' => $expense->is_active,
                'priority' => 0,
                'notes' => $expense->notes,
            ]);

            $imported++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
            ],
            'message' => "Imported {$imported} items, skipped {$skipped} duplicates.",
        ]);
    }

    /**
     * Get blueprint summary/preview for next month's card
     *
     * GET /api/blueprints/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeBlueprints = Blueprint::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('category:id,name,color,icon')
            ->orderBy('item_type')
            ->orderBy('bucket')
            ->orderBy('due_day')
            ->get();

        $income = $activeBlueprints->where('item_type', 'income');
        $expenses = $activeBlueprints->where('item_type', 'expense');

        $totalIncome = $income->sum('expected_amount');
        $totalExpense = $expenses->sum('expected_amount');
        $netCashFlow = $totalIncome - $totalExpense;

        // Calculate tax reserves on expected income
        $vatReserve = bcmul((string) $totalIncome, '0.15', 2);
        $netAfterVat = bcsub((string) $totalIncome, $vatReserve, 2);
        $taxReserve = bcmul($netAfterVat, '0.391', 2);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $activeBlueprints,
                'summary' => [
                    'total_income' => (float) $totalIncome,
                    'total_expense' => (float) $totalExpense,
                    'net_cash_flow' => (float) $netCashFlow,
                    'needs' => (float) $expenses->where('bucket', 'needs')->sum('expected_amount'),
                    'wants' => (float) $expenses->where('bucket', 'wants')->sum('expected_amount'),
                    'savings' => (float) $expenses->where('bucket', 'savings')->sum('expected_amount'),
                ],
                'tax' => [
                    'vat_reserve' => (float) $vatReserve,
                    'tax_reserve' => (float) $taxReserve,
                    'total_liability' => (float) bcadd($vatReserve, $taxReserve, 2),
                    'net_after_tax' => (float) bcsub($netAfterVat, $taxReserve, 2),
                ],
                'item_count' => $activeBlueprints->count(),
            ],
        ]);
    }
}
