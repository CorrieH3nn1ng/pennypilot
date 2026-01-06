<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use App\Models\IncomeSource;
use App\Models\FixedExpense;
use App\Models\BudgetPeriod;
use App\Models\BudgetItem;
use App\Models\TaxSetting;
use App\Models\TaxProvision;
use App\Models\TransactionRule;
use App\Models\Oplog;
use App\Models\Blueprint;
use App\Models\BudgetCard;
use App\Models\BudgetCardItem;
use App\Models\TransactionAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UserResetService - Atomic Server Wipe
 *
 * Provides complete data reset functionality for users.
 * All operations are wrapped in a database transaction to ensure atomicity.
 * Uses BCMath for any financial calculations to maintain precision.
 */
class UserResetService
{
    /**
     * Perform a complete reset of all user data.
     *
     * This method:
     * 1. Deletes all transactional data (invoices, transactions, budgets, etc.)
     * 2. Resets onboarding status
     * 3. Preserves the user account itself
     *
     * @param User $user The user to reset
     * @return array{success: bool, deleted: array, message: string}
     * @throws \Exception If the transaction fails
     */
    public function resetUserData(User $user): array
    {
        $deletedCounts = [];

        try {
            DB::beginTransaction();

            $userId = $user->id;

            // Delete in order respecting foreign key constraints
            // 1. Invoice Items (depends on Invoices)
            $deletedCounts['invoice_items'] = InvoiceItem::whereHas('invoice', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->forceDelete();

            // 2. Invoices (depends on Clients, may link to Transactions)
            $deletedCounts['invoices'] = Invoice::where('user_id', $userId)->forceDelete();

            // 3. Clients
            $deletedCounts['clients'] = Client::where('user_id', $userId)->forceDelete();

            // 4. Transaction Audits (depends on Transactions and Budget Cards)
            $deletedCounts['transaction_audits'] = TransactionAudit::whereHas('budgetCard', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->delete();

            // 5. Budget Card Items (depends on Budget Cards)
            $deletedCounts['budget_card_items'] = BudgetCardItem::whereHas('budgetCard', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->delete();

            // 6. Budget Cards
            $deletedCounts['budget_cards'] = BudgetCard::where('user_id', $userId)->delete();

            // 7. Blueprints
            $deletedCounts['blueprints'] = Blueprint::where('user_id', $userId)->delete();

            // 8. Transactions (core financial data)
            $deletedCounts['transactions'] = Transaction::where('user_id', $userId)->forceDelete();

            // 5. Transaction Rules (categorization patterns)
            $deletedCounts['transaction_rules'] = TransactionRule::where('user_id', $userId)->delete();

            // 6. Budget Items (depends on Budget Periods)
            $deletedCounts['budget_items'] = BudgetItem::whereHas('budgetPeriod', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->delete();

            // 7. Budget Periods
            $deletedCounts['budget_periods'] = BudgetPeriod::where('user_id', $userId)->delete();

            // 8. Income Sources
            $deletedCounts['income_sources'] = IncomeSource::where('user_id', $userId)->delete();

            // 9. Fixed Expenses
            $deletedCounts['fixed_expenses'] = FixedExpense::where('user_id', $userId)->delete();

            // 10. Tax Provisions
            $deletedCounts['tax_provisions'] = TaxProvision::where('user_id', $userId)->delete();

            // 11. Tax Settings
            $deletedCounts['tax_settings'] = TaxSetting::where('user_id', $userId)->delete();

            // 12. Operation Log (sync history)
            $deletedCounts['oplog'] = Oplog::where('user_id', $userId)->delete();

            // Reset user onboarding status
            $user->onboarding_completed_at = null;
            $user->save();

            // Commit the transaction - only after this point is data truly deleted
            DB::commit();

            Log::info('User data reset completed', [
                'user_id' => $userId,
                'deleted_counts' => $deletedCounts,
            ]);

            return [
                'success' => true,
                'deleted' => $deletedCounts,
                'message' => 'All user data has been permanently deleted.',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('User data reset failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a summary of data that would be deleted.
     * Useful for showing users what will be removed before confirming.
     *
     * @param User $user The user to check
     * @return array{counts: array, total_transactions_value: string}
     */
    public function getResetPreview(User $user): array
    {
        $userId = $user->id;

        $counts = [
            'transactions' => Transaction::where('user_id', $userId)->count(),
            'invoices' => Invoice::where('user_id', $userId)->count(),
            'clients' => Client::where('user_id', $userId)->count(),
            'income_sources' => IncomeSource::where('user_id', $userId)->count(),
            'fixed_expenses' => FixedExpense::where('user_id', $userId)->count(),
            'budget_periods' => BudgetPeriod::where('user_id', $userId)->count(),
            'transaction_rules' => TransactionRule::where('user_id', $userId)->count(),
            'blueprints' => Blueprint::where('user_id', $userId)->count(),
            'budget_cards' => BudgetCard::where('user_id', $userId)->count(),
        ];

        // Calculate total transaction value using BCMath for precision
        $transactions = Transaction::where('user_id', $userId)
            ->select('amount')
            ->get();

        $totalValue = '0.00';
        foreach ($transactions as $tx) {
            $totalValue = bcadd($totalValue, (string) $tx->amount, 2);
        }

        return [
            'counts' => $counts,
            'total_transactions_value' => $totalValue,
        ];
    }
}
