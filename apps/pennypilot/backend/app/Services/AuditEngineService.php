<?php

namespace App\Services;

use App\Models\BudgetCard;
use App\Models\BudgetCardItem;
use App\Models\Transaction;
use App\Models\TransactionAudit;
use App\Models\Blueprint;
use App\Models\Liability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * AuditEngineService - The Strategic Audit Engine
 *
 * This service implements the "Plan First, Audit Second" philosophy.
 * It compares bank statement transactions against the Budget Card
 * and classifies them as: MATCHED, LEAK, WINDFALL, or TRANSFER.
 *
 * INSTITUTIONAL RULES:
 * 1. Tax & VAT (39.1% Rule): Income matches calculate 15% VAT + 39.1% tax reserve
 * 2. Weighted Success Grade: Income(40%) + Discipline(40%) + Leak Penalty(20%)
 * 3. R2,120.06 Anchor: First card uses this as ground truth rollover
 * 4. Transfer Shield: Internal transfers are silenced and excluded from grades
 */
class AuditEngineService
{
    // Tax constants (South African 2025/26)
    public const VAT_RATE = 0.15;           // 15% VAT
    public const SME_TAX_RATE = 0.391;      // 39.1% SME tax reserve
    public const GROUND_TRUTH_ANCHOR = 2120.06; // R2,120.06 starting balance

    // Matching thresholds
    public const AUTO_MATCH_THRESHOLD = 75;  // Confidence score for auto-match
    public const SUGGEST_THRESHOLD = 50;     // Confidence score for suggestions

    // Score weights
    public const SCORE_DATE = 40;
    public const SCORE_AMOUNT = 35;
    public const SCORE_DESCRIPTION = 25;

    // Success grade weights
    public const GRADE_WEIGHT_INCOME = 0.40;     // 40% - Did revenue arrive?
    public const GRADE_WEIGHT_DISCIPLINE = 0.40; // 40% - Within expense limits?
    public const GRADE_WEIGHT_LEAK = 0.20;       // 20% - Leak penalty

    // Transfer detection patterns (SA banks)
    private array $transferPatterns = [
        // Credit card payments
        'CREDIT CARD',
        'CC PAYMENT',
        'CARD PAYMENT',
        // Inter-account transfers
        'TRANSFER TO',
        'TRANSFER FROM',
        'TRF TO',
        'TRF FROM',
        'INTERNAL TRANSFER',
        'INTERACC',
        'INTER ACC',
        'OWN ACCOUNT',
        // Savings movements
        'SAVE TO',
        'SAVINGS',
        'MONEY MARKET',
        'INVESTMENT ACC',
        // Nedbank specific
        'MY POCKET',
        'POCKET TRANSFER',
    ];

    // High-confidence income source patterns
    // These patterns get boosted scoring because income from known clients
    // should match even if amount/date varies
    private array $highConfidenceIncomePatterns = [
        'NUCLEUS',        // Nucleus Mining Logistics
        'NML',            // Nucleus Mining Logistics abbreviation
    ];

    /**
     * Run a full audit on a Budget Card against transactions for that month
     *
     * @param BudgetCard $card The budget card to audit
     * @param bool $autoMatch Whether to auto-match above threshold
     * @return array Audit results with stats
     */
    public function auditBudgetCard(BudgetCard $card, bool $autoMatch = true): array
    {
        $startDate = Carbon::create($card->year, $card->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all pending transactions for this month
        $transactions = Transaction::where('user_id', $card->user_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('audit_status', 'pending')
            ->orderBy('transaction_date')
            ->get();

        $results = [
            'processed' => 0,
            'matched' => 0,
            'leaks' => 0,
            'windfalls' => 0,
            'transfers' => 0,
            'suggestions' => [],
            'tax_reserve' => 0,
            'vat_collected' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($transactions as $transaction) {
                $classification = $this->classifyTransaction($transaction, $card, $autoMatch);
                $results['processed']++;

                switch ($classification['type']) {
                    case 'matched':
                        $results['matched']++;
                        if ($classification['tax_reserve'] ?? 0) {
                            $results['tax_reserve'] = bcadd(
                                (string) $results['tax_reserve'],
                                (string) $classification['tax_reserve'],
                                2
                            );
                        }
                        if ($classification['vat'] ?? 0) {
                            $results['vat_collected'] = bcadd(
                                (string) $results['vat_collected'],
                                (string) $classification['vat'],
                                2
                            );
                        }
                        break;

                    case 'leak':
                        $results['leaks']++;
                        break;

                    case 'windfall':
                        $results['windfalls']++;
                        break;

                    case 'transfer':
                        $results['transfers']++;
                        break;

                    case 'suggestion':
                        $results['suggestions'][] = $classification;
                        break;
                }
            }

            // Recalculate card totals
            $this->recalculateCardTotals($card);

            DB::commit();

            Log::info('Budget card audit completed', [
                'card_id' => $card->id,
                'results' => $results,
            ]);

            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Budget card audit failed', [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Classify a single transaction against the Budget Card
     *
     * Priority order:
     * 1. Check if it's a transfer (shield it)
     * 2. Try to match against budget card items
     * 3. Classify as leak (expense) or windfall (income)
     */
    public function classifyTransaction(
        Transaction $transaction,
        BudgetCard $card,
        bool $autoMatch = true
    ): array {
        // RULE 4: Transfer Shield - Check first
        if ($this->isTransfer($transaction)) {
            return $this->createTransferAudit($transaction, $card);
        }

        // Get pending budget card items for matching
        $items = $card->items()
            ->where('audit_status', 'pending')
            ->get();

        // Find best match
        $bestMatch = null;
        $bestScore = 0;

        foreach ($items as $item) {
            // Only match same type (income to income, expense to expense)
            if ($transaction->isIncome() && $item->item_type !== 'income') {
                continue;
            }
            if ($transaction->isExpense() && $item->item_type !== 'expense') {
                continue;
            }

            $score = $this->calculateMatchScore($transaction, $item);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        // Decide based on score
        if ($bestMatch && $bestScore >= self::AUTO_MATCH_THRESHOLD && $autoMatch) {
            return $this->createMatchedAudit($transaction, $card, $bestMatch, $bestScore);
        }

        if ($bestMatch && $bestScore >= self::SUGGEST_THRESHOLD) {
            // Return as suggestion for manual review
            return [
                'type' => 'suggestion',
                'transaction_id' => $transaction->id,
                'suggested_item_id' => $bestMatch->id,
                'suggested_item_name' => $bestMatch->name,
                'confidence' => $bestScore,
                'reasons' => $this->getMatchReasons($transaction, $bestMatch),
            ];
        }

        // No match found - classify as leak or windfall
        if ($transaction->isIncome()) {
            return $this->createWindfallAudit($transaction, $card);
        } else {
            return $this->createLeakAudit($transaction, $card);
        }
    }

    /**
     * Calculate match confidence score (0-100)
     *
     * Scoring breakdown:
     * - Date proximity: 40 points max
     * - Amount similarity: 35 points max
     * - Description pattern: 25 points max
     */
    public function calculateMatchScore(Transaction $transaction, BudgetCardItem $item): float
    {
        $score = 0;

        // DATE SCORE (40 points max)
        $score += $this->calculateDateScore($transaction, $item);

        // AMOUNT SCORE (35 points max)
        $score += $this->calculateAmountScore($transaction, $item);

        // DESCRIPTION SCORE (25 points max)
        $score += $this->calculateDescriptionScore($transaction, $item);

        return min(100, $score);
    }

    /**
     * Calculate date proximity score
     */
    private function calculateDateScore(Transaction $transaction, BudgetCardItem $item): float
    {
        if (!$item->expected_day) {
            return self::SCORE_DATE * 0.5; // Partial credit if no specific day
        }

        $txDay = (int) $transaction->transaction_date->format('d');
        $expectedDay = $item->expected_day;

        $diff = abs($txDay - $expectedDay);

        // Handle month wrap (e.g., expected 2nd, transaction 30th = ~3 days)
        if ($diff > 15) {
            $daysInMonth = $transaction->transaction_date->daysInMonth;
            $diff = $daysInMonth - $diff;
        }

        return match (true) {
            $diff === 0 => self::SCORE_DATE,           // Exact day: 40
            $diff === 1 => self::SCORE_DATE * 0.875,   // 1 day off: 35
            $diff <= 3 => self::SCORE_DATE * 0.625,    // Within 3 days: 25
            $diff <= 5 => self::SCORE_DATE * 0.375,    // Within 5 days: 15
            $diff <= 7 => self::SCORE_DATE * 0.25,     // Within week: 10
            default => 0,
        };
    }

    /**
     * Calculate amount similarity score
     */
    private function calculateAmountScore(Transaction $transaction, BudgetCardItem $item): float
    {
        $expected = abs((float) $item->expected_amount);
        $actual = abs((float) $transaction->amount);

        if ($expected == 0) {
            return 0;
        }

        $diff = abs($expected - $actual);
        $percentDiff = ($diff / $expected) * 100;

        return match (true) {
            $diff < 0.01 => self::SCORE_AMOUNT,           // Exact (within 1 cent): 35
            $percentDiff <= 1 => self::SCORE_AMOUNT * 0.9,  // Within 1%: 31.5
            $percentDiff <= 2 => self::SCORE_AMOUNT * 0.85, // Within 2%: ~30
            $percentDiff <= 5 => self::SCORE_AMOUNT * 0.7,  // Within 5%: ~24.5
            $percentDiff <= 10 => self::SCORE_AMOUNT * 0.5, // Within 10%: 17.5
            $percentDiff <= 20 => self::SCORE_AMOUNT * 0.3, // Within 20%: 10.5
            default => 0,
        };
    }

    /**
     * Calculate description pattern score
     *
     * For income items, high-confidence patterns get a score boost
     * because income sources are identified by name, not exact amounts.
     */
    private function calculateDescriptionScore(Transaction $transaction, BudgetCardItem $item): float
    {
        if (!$item->match_pattern) {
            return self::SCORE_DESCRIPTION * 0.4; // Partial credit if no pattern
        }

        $pattern = strtoupper(trim($item->match_pattern));
        $description = strtoupper(trim($transaction->description));
        $rawDescription = strtoupper(trim($transaction->raw_description ?? ''));

        // Check both description and raw_description
        $matchInDesc = $this->patternMatches($pattern, $description, $item->match_type);
        $matchInRaw = $this->patternMatches($pattern, $rawDescription, $item->match_type);

        if ($matchInDesc || $matchInRaw) {
            $baseScore = match ($item->match_type) {
                'exact' => self::SCORE_DESCRIPTION,             // Exact match: 25
                'starts_with' => self::SCORE_DESCRIPTION * 0.8, // Starts with: 20
                'contains' => self::SCORE_DESCRIPTION * 0.6,    // Contains: 15
                default => self::SCORE_DESCRIPTION * 0.6,
            };

            // BOOST: High-confidence income pattern matching
            // For income items matching known client patterns, boost the score
            // to ensure reliable matching even when amounts vary
            if ($item->item_type === 'income' && $this->isHighConfidenceIncomePattern($pattern)) {
                // Boost to full 25 points + 15 bonus (compensates for amount variance)
                return min(40, self::SCORE_DESCRIPTION + 15);
            }

            return $baseScore;
        }

        return 0;
    }

    /**
     * Check if a pattern is a high-confidence income source
     */
    private function isHighConfidenceIncomePattern(string $pattern): bool
    {
        foreach ($this->highConfidenceIncomePatterns as $hcPattern) {
            if (str_contains($pattern, $hcPattern) || str_contains($hcPattern, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if pattern matches text based on match type
     */
    private function patternMatches(string $pattern, string $text, string $matchType): bool
    {
        return match ($matchType) {
            'exact' => $text === $pattern,
            'starts_with' => str_starts_with($text, $pattern),
            'contains' => str_contains($text, $pattern),
            default => str_contains($text, $pattern),
        };
    }

    /**
     * Get human-readable match reasons
     */
    private function getMatchReasons(Transaction $transaction, BudgetCardItem $item): array
    {
        $reasons = [];

        // Date reason
        if ($item->expected_day) {
            $txDay = (int) $transaction->transaction_date->format('d');
            $diff = abs($txDay - $item->expected_day);
            if ($diff <= 3) {
                $reasons[] = "Date matches (expected day {$item->expected_day}, got {$txDay})";
            }
        }

        // Amount reason
        $expected = abs((float) $item->expected_amount);
        $actual = abs((float) $transaction->amount);
        $percentDiff = $expected > 0 ? (abs($expected - $actual) / $expected) * 100 : 100;
        if ($percentDiff <= 5) {
            $reasons[] = sprintf("Amount matches (expected R%.2f, got R%.2f)", $expected, $actual);
        }

        // Description reason
        if ($item->match_pattern) {
            $pattern = strtoupper($item->match_pattern);
            $desc = strtoupper($transaction->description);
            if (str_contains($desc, $pattern)) {
                $reasons[] = "Description contains '{$item->match_pattern}'";
            }
        }

        return $reasons;
    }

    /**
     * RULE 4: Check if transaction is an internal transfer
     */
    public function isTransfer(Transaction $transaction): bool
    {
        // Already marked as transfer
        if ($transaction->is_transfer) {
            return true;
        }

        $description = strtoupper($transaction->description ?? '');
        $rawDescription = strtoupper($transaction->raw_description ?? '');

        foreach ($this->transferPatterns as $pattern) {
            if (str_contains($description, $pattern) || str_contains($rawDescription, $pattern)) {
                return true;
            }
        }

        // Heuristic: Two transactions on same day with exact opposite amounts
        // (This would need to be checked against other transactions in the batch)

        return false;
    }

    /**
     * Create a MATCHED audit record
     *
     * RULE 1: For income matches, calculate tax reserves
     * RULE 5: For liability-linked items, track debt recovery
     */
    private function createMatchedAudit(
        Transaction $transaction,
        BudgetCard $card,
        BudgetCardItem $item,
        float $confidence
    ): array {
        $taxReserve = 0;
        $vat = 0;
        $liabilityResult = null;

        // RULE 1: Tax & VAT calculation for income
        if ($item->item_type === 'income') {
            $amount = abs((float) $transaction->amount);

            // Calculate VAT (15% of gross)
            $vat = bcmul((string) $amount, (string) self::VAT_RATE, 2);

            // Calculate tax reserve (39.1% of net after VAT)
            $netAfterVat = bcsub((string) $amount, $vat, 2);
            $taxReserve = bcmul($netAfterVat, (string) self::SME_TAX_RATE, 2);
        }

        // Match the item to the transaction
        $item->matchToTransaction($transaction, $confidence, 'auto');

        // RULE 5: Liability tracking - record payment if linked
        if ($item->liability_id) {
            $liabilityResult = $this->processLiabilityPayment($item, $transaction);
        }

        $result = [
            'type' => 'matched',
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'confidence' => $confidence,
            'vat' => (float) $vat,
            'tax_reserve' => (float) $taxReserve,
        ];

        // Add liability info if applicable
        if ($liabilityResult) {
            $result['liability'] = $liabilityResult;
        }

        return $result;
    }

    /**
     * RULE 5: Process liability payment when budget card item is matched
     *
     * Records the payment, updates balances, and handles pay-off celebration
     */
    private function processLiabilityPayment(BudgetCardItem $item, Transaction $transaction): array
    {
        $liability = Liability::find($item->liability_id);

        if (!$liability || $liability->status !== 'active') {
            return ['processed' => false, 'reason' => 'Liability not found or not active'];
        }

        $paymentAmount = abs((float) $transaction->amount);

        // Store the balance BEFORE this payment for display purposes
        $balanceBeforePayment = (float) $liability->current_balance;

        // Record the payment
        $result = $liability->recordPayment($paymentAmount);

        // Update the budget card item with liability info
        $item->update([
            'installment_number' => $liability->completed_installments,
            'remaining_balance_at_match' => $result['remainingBalance'],
        ]);

        // If paid off, deactivate linked blueprints
        if ($result['isPaidOff']) {
            $deactivatedCount = $liability->deactivateLinkedBlueprints();

            Log::info('Liability paid off', [
                'liability_id' => $liability->id,
                'liability_name' => $liability->name,
                'amount_freed' => $result['amountFreed'],
                'blueprints_deactivated' => $deactivatedCount,
            ]);

            return [
                'processed' => true,
                'is_paid_off' => true,
                'liability_id' => $liability->id,
                'liability_name' => $liability->name,
                'original_balance' => (float) $liability->original_balance,
                'final_payment' => $paymentAmount,
                'amount_freed' => $result['amountFreed'],
                'total_installments' => $result['completedInstallments'],
                'blueprints_deactivated' => $deactivatedCount,
                'celebration' => [
                    'type' => 'liability_paid_off',
                    'title' => 'Goal Reached!',
                    'message' => "{$liability->name} Paid Off",
                    'subtitle' => "R" . number_format($result['amountFreed'], 2) . " freed up in monthly budget",
                ],
            ];
        }

        return [
            'processed' => true,
            'is_paid_off' => false,
            'liability_id' => $liability->id,
            'liability_name' => $liability->name,
            'payment_amount' => $paymentAmount,
            'balance_before' => $balanceBeforePayment,
            'balance_after' => $result['remainingBalance'],
            'completed_installments' => $result['completedInstallments'],
            'remaining_installments' => $liability->remaining_installments,
            'installment_label' => $liability->installment_label,
            'progress_percent' => $liability->progress_percent,
        ];
    }

    /**
     * Create a LEAK audit record (unplanned expense)
     */
    private function createLeakAudit(Transaction $transaction, BudgetCard $card): array
    {
        TransactionAudit::createLeak($card, $transaction);

        return [
            'type' => 'leak',
            'transaction_id' => $transaction->id,
            'amount' => abs((float) $transaction->amount),
            'description' => $transaction->description,
        ];
    }

    /**
     * Create a WINDFALL audit record (unplanned income)
     */
    private function createWindfallAudit(Transaction $transaction, BudgetCard $card): array
    {
        TransactionAudit::createWindfall($card, $transaction);

        return [
            'type' => 'windfall',
            'transaction_id' => $transaction->id,
            'amount' => abs((float) $transaction->amount),
            'description' => $transaction->description,
        ];
    }

    /**
     * Create a TRANSFER audit record (silenced, excluded from grades)
     */
    private function createTransferAudit(Transaction $transaction, BudgetCard $card): array
    {
        // Mark the transaction as a transfer
        $transaction->update(['is_transfer' => true]);

        TransactionAudit::createTransfer($card, $transaction);

        return [
            'type' => 'transfer',
            'transaction_id' => $transaction->id,
            'amount' => abs((float) $transaction->amount),
            'description' => $transaction->description,
        ];
    }

    /**
     * Manually match a transaction to a budget card item
     */
    public function manualMatch(
        Transaction $transaction,
        BudgetCardItem $item,
        BudgetCard $card
    ): array {
        // Remove any existing audit for this transaction
        TransactionAudit::where('transaction_id', $transaction->id)
            ->where('budget_card_id', $card->id)
            ->delete();

        $taxReserve = 0;
        $vat = 0;
        $liabilityResult = null;

        // RULE 1: Tax calculation for income
        if ($item->item_type === 'income') {
            $amount = abs((float) $transaction->amount);
            $vat = bcmul((string) $amount, (string) self::VAT_RATE, 2);
            $netAfterVat = bcsub((string) $amount, $vat, 2);
            $taxReserve = bcmul($netAfterVat, (string) self::SME_TAX_RATE, 2);
        }

        $item->matchToTransaction($transaction, 100.0, 'manual');

        // RULE 5: Liability tracking for manual matches
        if ($item->liability_id) {
            $liabilityResult = $this->processLiabilityPayment($item, $transaction);
        }

        $this->recalculateCardTotals($card);

        $result = [
            'success' => true,
            'item_id' => $item->id,
            'transaction_id' => $transaction->id,
            'vat' => (float) $vat,
            'tax_reserve' => (float) $taxReserve,
        ];

        if ($liabilityResult) {
            $result['liability'] = $liabilityResult;
        }

        return $result;
    }

    /**
     * Manually unmatch a transaction from a budget card item
     */
    public function manualUnmatch(BudgetCardItem $item, BudgetCard $card): array
    {
        $transactionId = $item->matched_transaction_id;
        $item->unmatch();

        $this->recalculateCardTotals($card);

        return [
            'success' => true,
            'item_id' => $item->id,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Recalculate all totals on a Budget Card
     */
    public function recalculateCardTotals(BudgetCard $card): void
    {
        // Income matched
        $matchedIncome = $card->items()
            ->where('item_type', 'income')
            ->where('audit_status', 'matched')
            ->sum('matched_amount');

        // Expenses matched
        $matchedExpense = $card->items()
            ->where('item_type', 'expense')
            ->where('audit_status', 'matched')
            ->sum('matched_amount');

        // Total matched (absolute values)
        $totalMatched = bcadd((string) abs($matchedIncome), (string) abs($matchedExpense), 2);

        // Missing (pending items are potential missing at month end)
        $missingIncome = $card->items()
            ->where('item_type', 'income')
            ->where('audit_status', 'missing')
            ->sum('expected_amount');

        $missingExpense = $card->items()
            ->where('item_type', 'expense')
            ->where('audit_status', 'missing')
            ->sum('expected_amount');

        $totalMissing = bcadd((string) abs($missingIncome), (string) abs($missingExpense), 2);

        // Leaks (unplanned expenses - NOT silenced)
        $totalLeaked = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->join('transactions', 'transaction_audits.transaction_id', '=', 'transactions.id')
            ->sum(DB::raw('ABS(transactions.amount)'));

        // Windfalls (unplanned income)
        $totalWindfall = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'windfall')
            ->join('transactions', 'transaction_audits.transaction_id', '=', 'transactions.id')
            ->sum(DB::raw('ABS(transactions.amount)'));

        // Calculate tax reserves for matched income
        $taxReserve = 0;
        $vatCollected = 0;

        $matchedIncomeItems = $card->items()
            ->where('item_type', 'income')
            ->where('audit_status', 'matched')
            ->whereNotNull('matched_amount')
            ->get();

        foreach ($matchedIncomeItems as $item) {
            $amount = abs((float) $item->matched_amount);
            $vat = bcmul((string) $amount, (string) self::VAT_RATE, 2);
            $netAfterVat = bcsub((string) $amount, $vat, 2);
            $tax = bcmul($netAfterVat, (string) self::SME_TAX_RATE, 2);

            $vatCollected = bcadd($vatCollected, $vat, 2);
            $taxReserve = bcadd($taxReserve, $tax, 2);
        }

        $card->update([
            'total_matched' => $totalMatched,
            'total_missing' => $totalMissing,
            'total_leaked' => $totalLeaked,
            'total_windfall' => $totalWindfall,
        ]);
    }

    /**
     * RULE 2: Calculate weighted success grade
     *
     * Income Score (40%): % of expected income that arrived
     * Discipline Score (40%): % of expenses within planned amounts
     * Leak Penalty (20%): Deduction for unplanned spending
     */
    public function calculateSuccessGrade(BudgetCard $card): array
    {
        // INCOME SCORE (40%)
        $expectedIncome = $card->items()
            ->where('item_type', 'income')
            ->sum('expected_amount');

        $matchedIncome = $card->items()
            ->where('item_type', 'income')
            ->where('audit_status', 'matched')
            ->sum('matched_amount');

        $incomeScore = $expectedIncome > 0
            ? min(100, ($matchedIncome / $expectedIncome) * 100)
            : 100; // No expected income = full score

        // DISCIPLINE SCORE (40%)
        // How well did expenses match planned amounts?
        $expectedExpense = $card->items()
            ->where('item_type', 'expense')
            ->sum('expected_amount');

        $matchedExpense = $card->items()
            ->where('item_type', 'expense')
            ->where('audit_status', 'matched')
            ->sum('matched_amount');

        $disciplineScore = 100;
        if ($expectedExpense > 0) {
            // Check if actual spending exceeded planned
            $overSpend = max(0, $matchedExpense - $expectedExpense);
            $overSpendPenalty = min(50, ($overSpend / $expectedExpense) * 100);

            // Check how many planned items were matched vs missed
            $plannedExpenseCount = $card->items()->where('item_type', 'expense')->count();
            $matchedExpenseCount = $card->items()
                ->where('item_type', 'expense')
                ->where('audit_status', 'matched')
                ->count();

            $matchRatio = $plannedExpenseCount > 0
                ? ($matchedExpenseCount / $plannedExpenseCount) * 100
                : 100;

            // Discipline = match ratio - overspend penalty
            $disciplineScore = max(0, $matchRatio - $overSpendPenalty);
        }

        // LEAK PENALTY (20%)
        // Count and value of unplanned expenses
        $leakCount = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->count();

        $leakAmount = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->join('transactions', 'transaction_audits.transaction_id', '=', 'transactions.id')
            ->sum(DB::raw('ABS(transactions.amount)'));

        // Leak score starts at 100, loses points for each leak
        // -10 points per leak, -1 point per R100 in leaks
        $leakCountPenalty = min(50, $leakCount * 10);
        $leakAmountPenalty = min(50, ($leakAmount / 100));
        $leakScore = max(0, 100 - $leakCountPenalty - $leakAmountPenalty);

        // Calculate weighted grade
        $weightedGrade =
            ($incomeScore * self::GRADE_WEIGHT_INCOME) +
            ($disciplineScore * self::GRADE_WEIGHT_DISCIPLINE) +
            ($leakScore * self::GRADE_WEIGHT_LEAK);

        $finalGrade = round($weightedGrade, 2);

        // Determine letter grade
        $letterGrade = match (true) {
            $finalGrade >= 90 => 'A',
            $finalGrade >= 80 => 'B',
            $finalGrade >= 70 => 'C',
            $finalGrade >= 60 => 'D',
            default => 'F',
        };

        // Calculate surplus
        $actualIncome = bcadd((string) $matchedIncome, (string) $card->total_windfall, 2);
        $actualExpense = bcadd((string) $matchedExpense, (string) $card->total_leaked, 2);
        $surplus = bcsub($actualIncome, $actualExpense, 2);

        // Update card
        $card->update([
            'success_grade' => $finalGrade,
            'surplus_amount' => $surplus,
        ]);

        return [
            'grade' => $finalGrade,
            'letter_grade' => $letterGrade,
            'breakdown' => [
                'income_score' => round($incomeScore, 2),
                'income_weight' => self::GRADE_WEIGHT_INCOME * 100 . '%',
                'income_contribution' => round($incomeScore * self::GRADE_WEIGHT_INCOME, 2),

                'discipline_score' => round($disciplineScore, 2),
                'discipline_weight' => self::GRADE_WEIGHT_DISCIPLINE * 100 . '%',
                'discipline_contribution' => round($disciplineScore * self::GRADE_WEIGHT_DISCIPLINE, 2),

                'leak_score' => round($leakScore, 2),
                'leak_weight' => self::GRADE_WEIGHT_LEAK * 100 . '%',
                'leak_contribution' => round($leakScore * self::GRADE_WEIGHT_LEAK, 2),
            ],
            'metrics' => [
                'expected_income' => (float) $expectedIncome,
                'matched_income' => (float) $matchedIncome,
                'expected_expense' => (float) $expectedExpense,
                'matched_expense' => (float) $matchedExpense,
                'leak_count' => $leakCount,
                'leak_amount' => (float) $leakAmount,
            ],
            'surplus' => (float) $surplus,
        ];
    }

    /**
     * RULE 3: Get rollover amount for a new Budget Card
     *
     * First card ever: Use R2,120.06 anchor
     * Subsequent cards: Use previous month's surplus
     */
    public function getRolloverAmount(string $userId, int $year, int $month): float
    {
        // Check if any finalized cards exist for this user
        $previousCard = BudgetCard::where('user_id', $userId)
            ->where('status', 'finalized')
            ->where(function ($query) use ($year, $month) {
                $query->where('year', '<', $year)
                    ->orWhere(function ($q) use ($year, $month) {
                        $q->where('year', '=', $year)
                          ->where('month', '<', $month);
                    });
            })
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        if (!$previousCard) {
            // RULE 3: First card - use ground truth anchor
            return self::GROUND_TRUTH_ANCHOR;
        }

        // Return previous month's surplus (or 0 if they chose to bank it)
        return $previousCard->surplus_action === 'savings'
            ? 0.00
            : (float) ($previousCard->surplus_amount ?? 0);
    }

    /**
     * Mark end-of-month missing items
     *
     * Called during finalization - any pending items become "missing"
     */
    public function markMissingItems(BudgetCard $card): int
    {
        $count = $card->items()
            ->where('audit_status', 'pending')
            ->update(['audit_status' => 'missing']);

        $this->recalculateCardTotals($card);

        return $count;
    }

    /**
     * Silence a leak (acknowledge it, exclude from grade)
     */
    public function silenceLeak(TransactionAudit $audit): bool
    {
        if ($audit->classification !== 'leak') {
            return false;
        }

        $audit->silence();

        // Recalculate totals
        $this->recalculateCardTotals($audit->budgetCard);

        return true;
    }

    /**
     * Get audit statistics for a Budget Card
     */
    public function getAuditStats(BudgetCard $card): array
    {
        $totalItems = $card->items()->count();
        $matchedItems = $card->items()->where('audit_status', 'matched')->count();
        $pendingItems = $card->items()->where('audit_status', 'pending')->count();
        $missingItems = $card->items()->where('audit_status', 'missing')->count();

        $leakCount = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->count();

        $windfallCount = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'windfall')
            ->count();

        $transferCount = TransactionAudit::where('budget_card_id', $card->id)
            ->where('classification', 'transfer')
            ->count();

        // Calculate tax reserves
        $taxReserve = 0;
        $vatCollected = 0;

        $matchedIncomeItems = $card->items()
            ->where('item_type', 'income')
            ->where('audit_status', 'matched')
            ->whereNotNull('matched_amount')
            ->get();

        foreach ($matchedIncomeItems as $item) {
            $amount = abs((float) $item->matched_amount);
            $vat = bcmul((string) $amount, (string) self::VAT_RATE, 2);
            $netAfterVat = bcsub((string) $amount, $vat, 2);
            $tax = bcmul($netAfterVat, (string) self::SME_TAX_RATE, 2);

            $vatCollected = bcadd($vatCollected, $vat, 2);
            $taxReserve = bcadd($taxReserve, $tax, 2);
        }

        return [
            'items' => [
                'total' => $totalItems,
                'matched' => $matchedItems,
                'pending' => $pendingItems,
                'missing' => $missingItems,
                'match_rate' => $totalItems > 0
                    ? round(($matchedItems / $totalItems) * 100, 1)
                    : 0,
            ],
            'transactions' => [
                'leaks' => $leakCount,
                'windfalls' => $windfallCount,
                'transfers' => $transferCount,
            ],
            'tax' => [
                'vat_collected' => (float) $vatCollected,
                'tax_reserve' => (float) $taxReserve,
                'total_liability' => (float) bcadd($vatCollected, $taxReserve, 2),
            ],
            'totals' => [
                'expected_income' => (float) $card->total_expected_income,
                'expected_expense' => (float) $card->total_expected_expense,
                'matched' => (float) $card->total_matched,
                'leaked' => (float) $card->total_leaked,
                'windfall' => (float) $card->total_windfall,
                'missing' => (float) $card->total_missing,
            ],
            'grade' => [
                'score' => (float) $card->success_grade,
                'letter' => $card->letter_grade,
            ],
            'rollover' => (float) $card->rollover_from_previous,
            'surplus' => (float) $card->surplus_amount,
        ];
    }
}
