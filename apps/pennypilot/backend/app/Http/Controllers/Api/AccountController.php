<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * Get user's accounts
     */
    public function index(): JsonResponse
    {
        $accounts = Account::where('user_id', Auth::id())->get();

        return response()->json(['data' => $accounts]);
    }

    /**
     * Get or create default account
     */
    public function getDefault(): JsonResponse
    {
        $account = Account::firstOrCreate(
            ['user_id' => Auth::id(), 'is_default' => true],
            [
                'name' => 'Main Account',
                'bank_name' => 'Nedbank',
                'opening_balance' => 0,
            ]
        );

        // Calculate current balance from transactions
        $transactionSum = Transaction::where('user_id', Auth::id())->sum('amount');
        $calculatedBalance = $account->opening_balance + $transactionSum;

        return response()->json([
            'data' => [
                'account' => $account,
                'transaction_sum' => (float) $transactionSum,
                'calculated_balance' => (float) $calculatedBalance,
            ],
        ]);
    }

    /**
     * Set the current balance and calculate opening balance
     */
    public function setBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_balance' => 'required|numeric',
            'account_id' => 'nullable|uuid',
        ]);

        // Get or create default account
        $account = Account::firstOrCreate(
            ['user_id' => Auth::id(), 'is_default' => true],
            [
                'name' => 'Main Account',
                'bank_name' => 'Nedbank',
                'opening_balance' => 0,
            ]
        );

        // Calculate sum of all transactions
        $transactionSum = Transaction::where('user_id', Auth::id())->sum('amount');

        // Calculate opening balance: current_balance - sum_of_transactions
        $openingBalance = $validated['current_balance'] - $transactionSum;

        // Update account
        $account->update([
            'current_balance' => $validated['current_balance'],
            'opening_balance' => $openingBalance,
            'opening_balance_date' => now()->toDateString(),
            'balance_updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'account' => $account->fresh(),
                'transaction_sum' => (float) $transactionSum,
                'opening_balance' => (float) $openingBalance,
                'current_balance' => (float) $validated['current_balance'],
            ],
            'message' => 'Balance updated successfully',
        ]);
    }

    /**
     * Update account details
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = Account::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'sometimes|string|max:100',
        ]);

        $account->update($validated);

        return response()->json([
            'data' => $account,
            'message' => 'Account updated successfully',
        ]);
    }
}
