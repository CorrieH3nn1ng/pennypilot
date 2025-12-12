<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * List transactions for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|uuid',
            'is_categorized' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = Transaction::where('user_id', Auth::id())
            ->with('category:id,name,icon,color')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        if (!empty($validated['start_date'])) {
            $query->where('transaction_date', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->where('transaction_date', '<=', $validated['end_date']);
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['is_categorized'])) {
            $query->where('is_categorized', $validated['is_categorized']);
        }

        $limit = $validated['limit'] ?? 50;
        $offset = $validated['offset'] ?? 0;

        $total = $query->count();
        $transactions = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Store a new transaction
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric',
            'balance_after' => 'nullable|numeric',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'bank_reference' => 'nullable|string|max:100',
            'import_source' => ['nullable', Rule::in(['manual', 'csv', 'api', 'sync'])],
            'raw_description' => 'nullable|string',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'local_id' => 'nullable|string|max:100',
        ]);

        $transaction = Transaction::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_categorized' => !empty($validated['category_id']),
            'categorized_by' => !empty($validated['category_id']) ? 'manual' : null,
            'sync_status' => 'synced',
        ]);

        return response()->json([
            'data' => $transaction->load('category:id,name,icon,color'),
            'message' => 'Transaction created successfully',
        ], 201);
    }

    /**
     * Bulk store transactions (for CSV import)
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transactions' => 'required|array|min:1|max:500',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.description' => 'required|string|max:500',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.balance_after' => 'nullable|numeric',
            'transactions.*.bank_reference' => 'nullable|string|max:100',
            'transactions.*.raw_description' => 'nullable|string',
            'transactions.*.local_id' => 'nullable|string|max:100',
        ]);

        $created = [];
        $skipped = [];

        foreach ($validated['transactions'] as $data) {
            // Check for duplicate bank_reference
            if (!empty($data['bank_reference'])) {
                $exists = Transaction::where('user_id', Auth::id())
                    ->where('bank_reference', $data['bank_reference'])
                    ->exists();

                if ($exists) {
                    $skipped[] = $data['bank_reference'];
                    continue;
                }
            }

            $transaction = Transaction::create([
                ...$data,
                'user_id' => Auth::id(),
                'import_source' => 'csv',
                'sync_status' => 'synced',
            ]);

            $created[] = [
                'id' => $transaction->id,
                'local_id' => $data['local_id'] ?? null,
            ];
        }

        return response()->json([
            'data' => [
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'created' => $created,
                'skipped' => $skipped,
            ],
            'message' => count($created) . ' transactions imported successfully',
        ], 201);
    }

    /**
     * Show a single transaction
     */
    public function show(string $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', Auth::id())
            ->with('category')
            ->findOrFail($id);

        return response()->json(['data' => $transaction]);
    }

    /**
     * Update a transaction
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'transaction_date' => 'sometimes|date',
            'description' => 'sometimes|string|max:500',
            'amount' => 'sometimes|numeric',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        // Track if category was updated
        if (array_key_exists('category_id', $validated)) {
            $validated['is_categorized'] = !empty($validated['category_id']);
            $validated['categorized_by'] = !empty($validated['category_id']) ? 'manual' : null;
        }

        $validated['version'] = $transaction->version + 1;

        $transaction->update($validated);

        return response()->json([
            'data' => $transaction->fresh()->load('category:id,name,icon,color'),
            'message' => 'Transaction updated successfully',
        ]);
    }

    /**
     * Delete a transaction
     */
    public function destroy(string $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', Auth::id())->findOrFail($id);
        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }

    /**
     * Get spending summary by category
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $summary = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$validated['start_date'], $validated['end_date']])
            ->selectRaw('
                category_id,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                COUNT(*) as transaction_count
            ')
            ->groupBy('category_id')
            ->with('category:id,name,icon,color')
            ->get();

        $totals = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$validated['start_date'], $validated['end_date']])
            ->selectRaw('
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                COUNT(*) as transaction_count
            ')
            ->first();

        return response()->json([
            'data' => [
                'by_category' => $summary,
                'totals' => $totals,
                'period' => [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                ],
            ],
        ]);
    }
}
