<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetPeriod;
use App\Models\BudgetItem;
use App\Models\IncomeSource;
use App\Models\FixedExpense;
use App\Models\Transaction;
use App\Services\TaxCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function __construct(
        private TaxCalculatorService $taxCalculator
    ) {}

    /**
     * List all budget periods for the user
     */
    public function index(Request $request): JsonResponse
    {
        $query = BudgetPeriod::where('user_id', Auth::id())
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $budgets = $query->get();

        return response()->json(['data' => $budgets]);
    }

    /**
     * Get current month's budget
     */
    public function current(): JsonResponse
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->where('year', $year)
            ->where('month', $month)
            ->with('items.category')
            ->first();

        if (!$budget) {
            return response()->json([
                'data' => null,
                'message' => 'No budget for current month. Create one to get started.',
            ]);
        }

        // Calculate actuals from transactions
        $this->updateActualsFromTransactions($budget);

        return response()->json([
            'data' => $budget,
            'bucket_totals' => $budget->getBucketTotals(),
            'target_allocations' => $budget->getTargetAllocations(),
        ]);
    }

    /**
     * Create a new budget period
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'methodology' => 'required|in:50-30-20,zero-based,envelope,pay-yourself-first',
            'rollover_option' => 'in:savings,rollover,reset',
        ]);

        // Check if budget already exists
        $existing = BudgetPeriod::where('user_id', Auth::id())
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Budget for this period already exists',
                'data' => $existing,
            ], 422);
        }

        // Calculate income and tax provision
        $incomeSources = IncomeSource::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        $totalMonthlyGross = $incomeSources->sum(fn($i) => $i->monthly_amount);
        $taxProvision = $this->taxCalculator->calculateMonthlyProvision($totalMonthlyGross);

        $budget = BudgetPeriod::create([
            'user_id' => Auth::id(),
            'year' => $validated['year'],
            'month' => $validated['month'],
            'methodology' => $validated['methodology'],
            'total_income' => $totalMonthlyGross,
            'tax_provision' => $taxProvision['monthly_provision'],
            'net_available' => $taxProvision['net_after_provision'],
            'rollover_option' => $validated['rollover_option'] ?? 'reset',
            'status' => 'draft',
        ]);

        // Auto-create budget items from fixed expenses
        $this->createItemsFromFixedExpenses($budget);

        return response()->json([
            'data' => $budget->load('items.category'),
            'message' => 'Budget created successfully',
        ], 201);
    }

    /**
     * Get a single budget period
     */
    public function show(string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->with('items.category')
            ->findOrFail($id);

        $this->updateActualsFromTransactions($budget);

        return response()->json([
            'data' => $budget,
            'bucket_totals' => $budget->getBucketTotals(),
            'target_allocations' => $budget->getTargetAllocations(),
        ]);
    }

    /**
     * Update a budget period
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'methodology' => 'sometimes|in:50-30-20,zero-based,envelope,pay-yourself-first',
            'total_income' => 'sometimes|numeric|min:0',
            'tax_provision' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,active,closed',
            'rollover_option' => 'sometimes|in:savings,rollover,reset',
        ]);

        // Recalculate net_available if income or tax changes
        if (isset($validated['total_income']) || isset($validated['tax_provision'])) {
            $income = $validated['total_income'] ?? $budget->total_income;
            $tax = $validated['tax_provision'] ?? $budget->tax_provision;
            $validated['net_available'] = $income - $tax;
        }

        $budget->update($validated);

        return response()->json([
            'data' => $budget->fresh()->load('items.category'),
            'message' => 'Budget updated successfully',
        ]);
    }

    /**
     * Delete a budget period
     */
    public function destroy(string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->findOrFail($id);

        $budget->delete(); // Cascade deletes items

        return response()->json([
            'message' => 'Budget deleted successfully',
        ]);
    }

    /**
     * Get budget items for a period
     */
    public function items(string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->findOrFail($id);

        $items = $budget->items()->with('category')->get();

        return response()->json([
            'data' => $items,
            'by_bucket' => $budget->getItemsByBucket(),
        ]);
    }

    /**
     * Add or update budget items
     */
    public function updateItems(Request $request, string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'nullable|uuid',
            'items.*.category_id' => 'nullable|uuid|exists:categories,id',
            'items.*.name' => 'nullable|string|max:100',
            'items.*.planned_amount' => 'required|numeric|min:0',
            'items.*.bucket' => 'required|in:needs,wants,savings',
            'items.*.is_fixed' => 'boolean',
        ]);

        $updatedItems = [];

        foreach ($validated['items'] as $itemData) {
            if (!empty($itemData['id'])) {
                // Update existing
                $item = BudgetItem::where('budget_period_id', $budget->id)
                    ->find($itemData['id']);

                if ($item) {
                    $item->update($itemData);
                    $updatedItems[] = $item->fresh();
                }
            } else {
                // Create new
                $item = BudgetItem::create([
                    ...$itemData,
                    'budget_period_id' => $budget->id,
                ]);
                $updatedItems[] = $item;
            }
        }

        // Update total planned
        $budget->update([
            'total_planned' => $budget->items()->sum('planned_amount'),
        ]);

        return response()->json([
            'data' => $updatedItems,
            'message' => 'Budget items updated successfully',
        ]);
    }

    /**
     * Get budget summary with actuals
     */
    public function summary(string $id): JsonResponse
    {
        $budget = BudgetPeriod::where('user_id', Auth::id())
            ->with('items.category')
            ->findOrFail($id);

        $this->updateActualsFromTransactions($budget);

        $bucketTotals = $budget->getBucketTotals();
        $targets = $budget->getTargetAllocations();

        // Calculate variances
        $summary = [
            'period' => $budget->period_name,
            'methodology' => $budget->methodology,
            'income' => [
                'gross' => $budget->total_income,
                'tax_provision' => $budget->tax_provision,
                'net_available' => $budget->net_available,
            ],
            'buckets' => [
                'needs' => [
                    'target' => $targets['needs'],
                    'planned' => $bucketTotals['needs']['planned'],
                    'actual' => $bucketTotals['needs']['actual'],
                    'variance' => $bucketTotals['needs']['planned'] - $bucketTotals['needs']['actual'],
                ],
                'wants' => [
                    'target' => $targets['wants'],
                    'planned' => $bucketTotals['wants']['planned'],
                    'actual' => $bucketTotals['wants']['actual'],
                    'variance' => $bucketTotals['wants']['planned'] - $bucketTotals['wants']['actual'],
                ],
                'savings' => [
                    'target' => $targets['savings'],
                    'planned' => $bucketTotals['savings']['planned'],
                    'actual' => $bucketTotals['savings']['actual'],
                    'variance' => $bucketTotals['savings']['planned'] - $bucketTotals['savings']['actual'],
                ],
            ],
            'totals' => [
                'planned' => $budget->total_planned,
                'actual' => $budget->items()->sum('actual_amount'),
                'remaining' => $budget->remaining,
            ],
        ];

        return response()->json(['data' => $summary]);
    }

    /**
     * Create budget items from fixed expenses
     */
    private function createItemsFromFixedExpenses(BudgetPeriod $budget): void
    {
        $fixedExpenses = FixedExpense::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        foreach ($fixedExpenses as $expense) {
            BudgetItem::create([
                'budget_period_id' => $budget->id,
                'category_id' => $expense->category_id,
                'name' => $expense->name,
                'planned_amount' => $expense->amount,
                'bucket' => $expense->bucket,
                'is_fixed' => true,
            ]);
        }

        // Update total planned
        $budget->update([
            'total_planned' => $budget->items()->sum('planned_amount'),
        ]);
    }

    /**
     * Update actual amounts from transactions
     */
    private function updateActualsFromTransactions(BudgetPeriod $budget): void
    {
        $startDate = sprintf('%04d-%02d-01', $budget->year, $budget->month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // Get transactions for this period
        $transactions = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('is_transfer', false)
            ->where('amount', '<', 0) // Only expenses
            ->get();

        // Update each budget item's actual amount based on category
        foreach ($budget->items as $item) {
            if ($item->category_id) {
                $actual = $transactions
                    ->where('category_id', $item->category_id)
                    ->sum(fn($t) => abs($t->amount));

                $item->update(['actual_amount' => $actual]);
            }
        }
    }
}
