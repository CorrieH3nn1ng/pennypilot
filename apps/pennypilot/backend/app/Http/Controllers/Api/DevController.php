<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Development utilities controller - ONLY for testing environments
 */
class DevController extends Controller
{
    /**
     * Clear all user data (transactions, budgets, tax tables)
     * Preserves user accounts and categories
     */
    public function clearSlate(Request $request): JsonResponse
    {
        // Safety check - only allow in local/testing environment
        if (!app()->environment(['local', 'testing'])) {
            return response()->json([
                'message' => 'This endpoint is only available in development environment',
            ], 403);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            DB::beginTransaction();

            // Get counts for logging
            $counts = [
                'transactions' => DB::table('transactions')->where('user_id', $user->id)->count(),
                'budget_items' => DB::table('budget_items')
                    ->whereIn('budget_period_id', function($q) use ($user) {
                        $q->select('id')->from('budget_periods')->where('user_id', $user->id);
                    })->count(),
                'budget_periods' => DB::table('budget_periods')->where('user_id', $user->id)->count(),
                'tax_provisions' => DB::table('tax_provisions')->where('user_id', $user->id)->count(),
                'tax_settings' => DB::table('tax_settings')->where('user_id', $user->id)->count(),
                'income_sources' => DB::table('income_sources')->where('user_id', $user->id)->count(),
                'fixed_expenses' => DB::table('fixed_expenses')->where('user_id', $user->id)->count(),
            ];

            // Delete in order (respecting foreign key constraints)

            // 1. Budget items (depends on budget_periods)
            DB::table('budget_items')
                ->whereIn('budget_period_id', function($q) use ($user) {
                    $q->select('id')->from('budget_periods')->where('user_id', $user->id);
                })->delete();

            // 2. Budget periods
            DB::table('budget_periods')->where('user_id', $user->id)->delete();

            // 3. Tax deductions (depends on transactions)
            DB::table('tax_deductions')->where('user_id', $user->id)->delete();

            // 4. Provisional tax payments
            DB::table('provisional_tax_payments')->where('user_id', $user->id)->delete();

            // 5. Tax provisions
            DB::table('tax_provisions')->where('user_id', $user->id)->delete();

            // 6. Tax settings
            DB::table('tax_settings')->where('user_id', $user->id)->delete();

            // 7. Invoice items (depends on invoices)
            DB::table('invoice_items')
                ->whereIn('invoice_id', function($q) use ($user) {
                    $q->select('id')->from('invoices')->where('user_id', $user->id);
                })->delete();

            // 8. Invoices
            DB::table('invoices')->where('user_id', $user->id)->delete();

            // 9. Clients
            DB::table('clients')->where('user_id', $user->id)->delete();

            // 10. Transactions
            DB::table('transactions')->where('user_id', $user->id)->delete();

            // 11. Income sources
            DB::table('income_sources')->where('user_id', $user->id)->delete();

            // 12. Fixed expenses
            DB::table('fixed_expenses')->where('user_id', $user->id)->delete();

            // 13. Accounts (opening balance)
            DB::table('accounts')->where('user_id', $user->id)->delete();

            // 14. Business profiles
            DB::table('business_profiles')->where('user_id', $user->id)->delete();

            DB::commit();

            Log::info('Clear slate executed for user', [
                'user_id' => $user->id,
                'deleted' => $counts,
            ]);

            return response()->json([
                'message' => 'Data cleared successfully',
                'deleted' => $counts,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Clear slate failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to clear data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Seed test data for Tax Gap validation
     */
    public function seedTestData(Request $request): JsonResponse
    {
        // Safety check - only allow in local/testing environment
        if (!app()->environment(['local', 'testing'])) {
            return response()->json([
                'message' => 'This endpoint is only available in development environment',
            ], 403);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            DB::beginTransaction();

            // Find category IDs
            $incomeCategory = DB::table('categories')
                ->where('name', 'like', '%Income%')
                ->orWhere('name', 'like', '%Salary%')
                ->first();

            $medicalCategory = DB::table('categories')
                ->where('name', 'like', '%Medical%')
                ->orWhere('name', 'like', '%Health%')
                ->first();

            $charityCategory = DB::table('categories')
                ->where('name', 'like', '%Charity%')
                ->orWhere('name', 'like', '%Donation%')
                ->first();

            $groceriesCategory = DB::table('categories')
                ->where('name', 'like', '%Groceries%')
                ->orWhere('name', 'like', '%Food%')
                ->first();

            $transportCategory = DB::table('categories')
                ->where('name', 'like', '%Transport%')
                ->orWhere('name', 'like', '%Fuel%')
                ->first();

            $today = now()->toDateString();
            $timestamp = now();

            // Seed 9 transactions with explicit income (positive) and expense (negative) amounts
            $transactions = [
                // INCOME - Explicit positive amounts (MUST show green with + prefix)
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'SALARY - ACME CORPORATION',
                    'amount' => 30000.00,  // Positive = Income
                    'transaction_date' => $today,
                    'category_id' => $incomeCategory?->id,
                    'bank_reference' => 'SEED-SALARY-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'BONUS DECEMBER - ACME CORPORATION',
                    'amount' => 45000.00,  // Positive = Income
                    'transaction_date' => $today,
                    'category_id' => $incomeCategory?->id,
                    'bank_reference' => 'SEED-BONUS-DEC-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'EXTRA WORK BONUS - FREELANCE',
                    'amount' => 3500.00,  // Positive = Income
                    'transaction_date' => $today,
                    'category_id' => $incomeCategory?->id,
                    'bank_reference' => 'SEED-BONUS-EXTRA-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                // EXPENSES - Explicit negative amounts (MUST show red)
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'DISCOVERY MEDICAL AID',
                    'amount' => -1000.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $medicalCategory?->id,
                    'bank_reference' => 'SEED-MEDICAL-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'GIFT OF THE GIVERS DONATION',
                    'amount' => -500.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $charityCategory?->id,
                    'bank_reference' => 'SEED-CHARITY-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'PICK N PAY ROSEBANK',
                    'amount' => -2500.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $groceriesCategory?->id,
                    'bank_reference' => 'SEED-GROCERIES-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'SASOL SANDTON',
                    'amount' => -800.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $transportCategory?->id,
                    'bank_reference' => 'SEED-FUEL-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'UBER TRIPS - DECEMBER',
                    'amount' => -1200.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $transportCategory?->id,
                    'bank_reference' => 'SEED-UBER-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'description' => 'WOOLWORTHS SANDTON CITY',
                    'amount' => -650.00,  // Negative = Expense
                    'transaction_date' => $today,
                    'category_id' => $groceriesCategory?->id,
                    'bank_reference' => 'SEED-WOOLIES-' . time(),
                    'is_transfer' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ];

            DB::table('transactions')->insert($transactions);

            DB::commit();

            Log::info('Test data seeded for user', [
                'user_id' => $user->id,
                'transactions' => count($transactions),
            ]);

            return response()->json([
                'message' => 'Test data seeded successfully',
                'transactions' => count($transactions),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Seed test data failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to seed data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
