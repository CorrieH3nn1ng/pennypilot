<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedExpense;
use App\Services\TaxCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FixedExpenseController extends Controller
{
    /**
     * List all fixed expenses for the user
     */
    public function index(): JsonResponse
    {
        $expenses = FixedExpense::where('user_id', Auth::id())
            ->with('category:id,name,icon,color')
            ->orderBy('is_active', 'desc')
            ->orderBy('due_day')
            ->get();

        // Calculate totals
        $activeExpenses = $expenses->where('is_active', true);
        $totalMonthly = $activeExpenses->sum('amount');

        // Group by bucket
        $byBucket = [
            'needs' => $activeExpenses->where('bucket', 'needs')->sum('amount'),
            'wants' => $activeExpenses->where('bucket', 'wants')->sum('amount'),
            'savings' => $activeExpenses->where('bucket', 'savings')->sum('amount'),
        ];

        return response()->json([
            'data' => $expenses,
            'summary' => [
                'total_monthly' => round($totalMonthly, 2),
                'total_annual' => round($totalMonthly * 12, 2),
                'active_count' => $activeExpenses->count(),
                'by_bucket' => $byBucket,
            ],
        ]);
    }

    /**
     * Create a new fixed expense
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|uuid|exists:categories,id',
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'bucket' => 'required|in:needs,wants,savings',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Auto-suggest bucket if not provided based on category
        if (empty($validated['bucket']) && !empty($validated['name'])) {
            $validated['bucket'] = TaxCalculatorService::suggestBucket($validated['name']);
        }

        $expense = FixedExpense::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'data' => $expense->load('category:id,name,icon,color'),
            'message' => 'Fixed expense created successfully',
        ], 201);
    }

    /**
     * Get a single fixed expense
     */
    public function show(string $id): JsonResponse
    {
        $expense = FixedExpense::where('user_id', Auth::id())
            ->with('category:id,name,icon,color')
            ->findOrFail($id);

        return response()->json(['data' => $expense]);
    }

    /**
     * Update a fixed expense
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $expense = FixedExpense::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|uuid|exists:categories,id',
            'name' => 'sometimes|string|max:100',
            'amount' => 'sometimes|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'bucket' => 'sometimes|in:needs,wants,savings',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json([
            'data' => $expense->fresh()->load('category:id,name,icon,color'),
            'message' => 'Fixed expense updated successfully',
        ]);
    }

    /**
     * Delete a fixed expense
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = FixedExpense::where('user_id', Auth::id())
            ->findOrFail($id);

        $expense->delete();

        return response()->json([
            'message' => 'Fixed expense deleted successfully',
        ]);
    }

    /**
     * Get expenses due soon
     */
    public function dueSoon(): JsonResponse
    {
        $expenses = FixedExpense::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotNull('due_day')
            ->with('category:id,name,icon,color')
            ->get()
            ->filter(fn($e) => $e->isDueSoon());

        return response()->json([
            'data' => $expenses->values(),
            'count' => $expenses->count(),
        ]);
    }
}
