<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BlueprintMatchingService
{
    protected MerchantNormalizerService $normalizer;

    /**
     * Minimum score threshold for auto-matching (0-100)
     */
    const AUTO_MATCH_THRESHOLD = 75;

    public function __construct(MerchantNormalizerService $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * Known fee patterns that should be classified as Unmapped Leaks
     */
    const LEAK_PATTERNS = [
        'SASWITCH',
        'BANK FEE',
        'BANK CHARGE',
        'SERVICE FEE',
        'CARD FEE',
        'MONTHLY FEE',
        'ACCOUNT FEE',
        'MAINTENANCE FEE',
        'INSTANT FEE',
        'INSTANT PAYMENT FEE',
        'INTEREST',
        'INT CREDIT',
        'INT DEBIT',
        'ADMIN FEE',
        'CASH WITHDRAWAL FEE',
        'ATM FEE',
        'NEDBANK LOTTO FEE',
        'LOTTO FEE',
    ];

    /**
     * Apply global transaction rules (pattern-based matching) to unmatched transactions.
     *
     * This is the PRIMARY matching mechanism - user-defined rules take priority
     * over fuzzy matching. Rules persist in the database and apply across ALL months.
     *
     * @return array{matched: int, rules_applied: array<string, int>}
     */
    public function applyGlobalRules(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Get all active rules for this user, ordered by priority and hit count
        $rules = TransactionRule::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('blueprint_id')
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->with('blueprint')
            ->get();

        if ($rules->isEmpty()) {
            return [
                'matched' => 0,
                'rules_applied' => [],
            ];
        }

        // Get unmatched transactions (optionally within date range)
        $query = Transaction::where('user_id', $user->id)
            ->whereNull('blueprint_id')
            ->where(function ($q) {
                $q->whereNull('match_status')
                    ->orWhereNotIn('match_status', ['manual', 'unmapped']);
            });

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $transactions = $query->get();

        $matched = 0;
        $rulesApplied = [];

        foreach ($transactions as $transaction) {
            // Check each rule against this transaction's description
            foreach ($rules as $rule) {
                if (!$rule->blueprint) {
                    continue; // Skip rules without valid blueprints
                }

                if ($rule->matches($transaction->description)) {
                    // Verify type compatibility (income rule shouldn't match expense, etc.)
                    $isIncome = $transaction->amount > 0;
                    if ($rule->blueprint->item_type === 'income' && !$isIncome) {
                        continue;
                    }
                    if ($rule->blueprint->item_type === 'expense' && $isIncome) {
                        continue;
                    }

                    // Apply the rule!
                    $transaction->update([
                        'blueprint_id' => $rule->blueprint_id,
                        'match_score' => 100, // Perfect match via rule
                        'match_status' => 'rule',
                        'category_id' => $rule->blueprint->category_id ?? $transaction->category_id,
                        'is_categorized' => true,
                    ]);

                    // Track stats
                    $rule->recordHit();
                    $matched++;
                    $rulesApplied[$rule->pattern] = ($rulesApplied[$rule->pattern] ?? 0) + 1;

                    break; // Move to next transaction
                }
            }
        }

        return [
            'matched' => $matched,
            'rules_applied' => $rulesApplied,
        ];
    }

    /**
     * Run reconciliation for a user within a date range
     * Matches transactions to blueprints based on fuzzy matching
     *
     * @return array{matched: int, unmatched: int, leaks: int, zombies: int}
     */
    public function reconcile(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // =============================================
        // PHASE 1: Apply Global Rules (highest priority)
        // User-defined patterns from TransactionRule table
        // =============================================
        $ruleResults = $this->applyGlobalRules($user, $startDate, $endDate);

        // =============================================
        // PHASE 2: Fuzzy Matching for remaining transactions
        // =============================================
        $blueprints = $user->blueprints()
            ->active()
            ->orderByDesc('priority')
            ->get();

        $transactions = $user->transactions()
            ->dateRange($startDate, $endDate)
            ->whereNull('blueprint_id') // Only unmatched (after rules)
            ->get();

        $stats = [
            'matched' => 0,
            'unmatched' => 0,
            'leaks' => 0,
            'zombies' => 0,
            'rule_matched' => $ruleResults['matched'], // From Phase 1
            'rules_applied' => $ruleResults['rules_applied'],
        ];

        $unmappedLeaksCategory = $this->getUnmappedLeaksCategory();

        foreach ($transactions as $transaction) {
            // First check if it's a known leak pattern
            if ($this->isLeakPattern($transaction->description)) {
                $this->markAsLeak($transaction, $unmappedLeaksCategory);
                $stats['leaks']++;
                continue;
            }

            // Find best matching blueprint
            $bestMatch = $this->findBestMatch($transaction, $blueprints);

            if ($bestMatch) {
                $this->matchTransaction($transaction, $bestMatch['blueprint'], $bestMatch['score']);
                $stats['matched']++;

                // Check if it's a zombie (cancelled blueprint still charging)
                if ($bestMatch['blueprint']->is_cancelled) {
                    $stats['zombies']++;
                }
            } else {
                $stats['unmatched']++;
            }
        }

        // Total matched = rule matched + fuzzy matched
        $stats['total_matched'] = $stats['rule_matched'] + $stats['matched'];

        return $stats;
    }

    /**
     * Find the best matching blueprint for a transaction
     *
     * @return array{blueprint: Blueprint, score: float}|null
     */
    public function findBestMatch(Transaction $transaction, Collection $blueprints): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        $dayOfMonth = $transaction->transaction_date->day;
        $amount = $transaction->amount;
        $description = $transaction->description;

        // Normalize the description (strip card numbers, dates, reference codes)
        $normalizedDesc = $this->normalizer->normalize($description);

        foreach ($blueprints as $blueprint) {
            // Check item type matches transaction direction
            if ($blueprint->item_type === 'income' && $amount < 0) {
                continue; // Blueprint expects income but transaction is expense
            }
            if ($blueprint->item_type === 'expense' && $amount > 0) {
                continue; // Blueprint expects expense but transaction is income
            }

            // Calculate match score using Blueprint's built-in method
            $score = $blueprint->calculateMatchScore($amount, $dayOfMonth, $normalizedDesc);

            // Also check match_aliases using normalizer
            if ($blueprint->match_aliases) {
                $aliasScore = $this->normalizer->calculateMatchScore(
                    $normalizedDesc,
                    $blueprint->match_pattern ?? '',
                    $blueprint->match_aliases
                );
                $score = max($score, $aliasScore);
            }

            // Direct pattern match on normalized description
            if ($blueprint->match_pattern) {
                if ($this->normalizer->matches($normalizedDesc, $blueprint->match_pattern, $blueprint->match_aliases ?? [])) {
                    $score = max($score, 80); // Pattern match is strong
                }
            }

            if ($score >= self::AUTO_MATCH_THRESHOLD && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'blueprint' => $blueprint,
                    'score' => $score,
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Match a transaction to a blueprint
     */
    public function matchTransaction(Transaction $transaction, Blueprint $blueprint, float $score, string $status = 'auto'): void
    {
        $transaction->update([
            'blueprint_id' => $blueprint->id,
            'match_score' => $score,
            'match_status' => $status,
            // Also set category from blueprint if available
            'category_id' => $blueprint->category_id ?? $transaction->category_id,
            'is_categorized' => true,
        ]);
    }

    /**
     * Mark a transaction as an unmapped leak
     */
    public function markAsLeak(Transaction $transaction, ?Category $leakCategory): void
    {
        $transaction->update([
            'match_status' => 'unmapped',
            'category_id' => $leakCategory?->id ?? $transaction->category_id,
            'is_categorized' => true,
        ]);
    }

    /**
     * Check if description matches known leak patterns
     */
    public function isLeakPattern(string $description): bool
    {
        $upperDesc = strtoupper($description);

        foreach (self::LEAK_PATTERNS as $pattern) {
            if (str_contains($upperDesc, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the Unmapped Leaks category
     */
    protected function getUnmappedLeaksCategory(): ?Category
    {
        return Category::where('name', 'Unmapped Leaks')
            ->where('is_system', true)
            ->first();
    }

    /**
     * Generate variance report for a specific month
     *
     * @return array
     */
    public function getVarianceReport(User $user, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all blueprints (include paused but not cancelled)
        $blueprints = $user->blueprints()
            ->with('category')
            ->get();

        // Get all transactions for the period
        $transactions = $user->transactions()
            ->dateRange($startDate, $endDate)
            ->with(['blueprint', 'category'])
            ->get();

        // Calculate blueprint expected amounts (monthly)
        $blueprintExpected = [
            'income' => 0,
            'expenses' => 0,
        ];

        $blueprintDetails = [];
        foreach ($blueprints as $blueprint) {
            // Skip cancelled items entirely
            if ($blueprint->status === 'cancelled') {
                continue;
            }

            // Use the month-aware expected amount (returns 0 for paused items)
            $expected = $this->getMonthlyAmountForPeriod($blueprint, $year, $month);
            $isPaused = $blueprint->isPausedForMonth($year, $month);

            // Get the original amount (what would be expected if not paused)
            $originalExpected = $this->getMonthlyAmount($blueprint);

            if ($blueprint->item_type === 'income') {
                $blueprintExpected['income'] += $expected;
            } else {
                $blueprintExpected['expenses'] += $expected;
            }

            // Determine variance classification
            $varianceLabel = null;
            if ($isPaused) {
                $varianceLabel = 'Budgeted Zero (Contractual Holiday)';
            }

            $blueprintDetails[$blueprint->id] = [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'item_type' => $blueprint->item_type,
                'bucket' => $blueprint->bucket,
                'nature' => $blueprint->nature,
                'expected' => $expected,
                'original_expected' => $isPaused ? $originalExpected : null,  // Show what was expected before pause
                'actual' => 0,
                'variance' => 0,
                'variance_label' => $varianceLabel,
                'status' => $blueprint->status,
                'is_paused' => $isPaused,
                'is_contractual_holiday' => $isPaused,  // Explicit flag for holiday classification
                'is_cancelled' => $blueprint->status === 'cancelled',
                'pause_reason' => $isPaused ? $blueprint->pause_reason : null,
                'pause_end_date' => $isPaused && $blueprint->pause_end_date
                    ? $blueprint->pause_end_date->format('Y-m-d')
                    : null,
                'category' => $blueprint->category?->name,
            ];
        }

        // Calculate actual amounts from transactions
        $actualIncome = 0;
        $actualExpenses = 0;
        $unmappedLeaks = [];
        $ghostFees = [];

        foreach ($transactions as $transaction) {
            $amount = abs($transaction->amount);

            if ($transaction->amount > 0) {
                $actualIncome += $amount;
            } else {
                $actualExpenses += $amount;
            }

            // Track against blueprint
            if ($transaction->blueprint_id && isset($blueprintDetails[$transaction->blueprint_id])) {
                $blueprintDetails[$transaction->blueprint_id]['actual'] += $amount;
            } elseif ($transaction->match_status === 'unmapped') {
                // Group unmapped leaks by description pattern
                $key = $this->normalizeDescription($transaction->description);
                if (!isset($unmappedLeaks[$key])) {
                    $unmappedLeaks[$key] = [
                        'description' => $key,
                        'amount' => 0,
                        'count' => 0,
                    ];
                }
                $unmappedLeaks[$key]['amount'] += $amount;
                $unmappedLeaks[$key]['count']++;
            } elseif (!$transaction->blueprint_id && $transaction->amount < 0) {
                // No blueprint, not a leak = ghost fee
                $ghostFees[] = [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'amount' => $amount,
                    'date' => $transaction->transaction_date->format('Y-m-d'),
                ];
            }
        }

        // Calculate variances for each blueprint
        // Variance = Expected - Actual
        // POSITIVE = underspent (GREEN) | NEGATIVE = overspent (RED)
        foreach ($blueprintDetails as &$detail) {
            $detail['variance'] = $detail['expected'] - $detail['actual'];
        }

        // Find zombies (cancelled blueprints with matching transactions)
        $zombies = [];
        foreach ($blueprints as $blueprint) {
            if ($blueprint->is_cancelled) {
                $zombieTransactions = $transactions
                    ->where('blueprint_id', $blueprint->id)
                    ->sum(fn($t) => abs($t->amount));

                if ($zombieTransactions > 0) {
                    $zombies[] = [
                        'blueprint' => $blueprint->name,
                        'blueprint_id' => $blueprint->id,
                        'cancelled_at' => $blueprint->cancelled_at?->format('Y-m-d'),
                        'charges' => $zombieTransactions,
                    ];
                }
            }
        }

        // Calculate contractual holiday savings FIRST (before net spendable)
        // This is the money "saved" because expenses are paused
        $contractualHolidayTotal = 0;
        $contractualHolidayItems = [];
        foreach ($blueprintDetails as $detail) {
            if ($detail['is_contractual_holiday'] && $detail['original_expected']) {
                $contractualHolidayTotal += $detail['original_expected'];
                $contractualHolidayItems[] = [
                    'id' => $detail['id'],
                    'name' => $detail['name'],
                    'amount' => $detail['original_expected'],
                    'pause_reason' => $detail['pause_reason'],
                    'pause_end_date' => $detail['pause_end_date'],
                ];
            }
        }

        // Calculate net spendable using 39.1% tax on business income
        // Use month-aware amounts that respect payment holidays
        $businessIncome = $blueprints
            ->where('item_type', 'income')
            ->where('nature', 'business')
            ->filter(fn($b) => $b->status !== 'cancelled')
            ->sum(fn($b) => $this->getMonthlyAmountForPeriod($b, $year, $month));

        $businessExpenses = $blueprints
            ->where('item_type', 'expense')
            ->where('nature', 'business')
            ->filter(fn($b) => $b->status !== 'cancelled')
            ->sum(fn($b) => $this->getMonthlyAmountForPeriod($b, $year, $month));

        $taxableIncome = max(0, $businessIncome - $businessExpenses);
        $taxReserve = $taxableIncome * 0.391;
        $netSpendable = $blueprintExpected['income'] - $taxReserve - $blueprintExpected['expenses'];

        // Calculate actual vs expected variance
        $actualNet = $actualIncome - $actualExpenses;
        $rawVariance = round($actualNet - $netSpendable, 2);

        // Holiday surplus = money saved from paused payments
        // This is a POSITIVE contribution to your cash position
        $holidaySurplus = round($contractualHolidayTotal, 2);

        // Adjusted variance INCLUDING holiday savings as surplus
        // If you had to pay these items, your variance would be worse by this amount
        $adjustedVariance = $rawVariance + $holidaySurplus;

        // True savings = what you saved through your own behavior (excluding holiday effect)
        $trueSavings = $rawVariance;

        // Determine variance status for UI
        $varianceStatus = 'neutral';
        if ($adjustedVariance > 100) {
            $varianceStatus = 'surplus';
        } elseif ($adjustedVariance < -100) {
            $varianceStatus = 'shortfall';
        }

        return [
            'period' => Carbon::create($year, $month, 1)->format('F Y'),
            'blueprint_expected' => [
                'income' => round($blueprintExpected['income'], 2),
                'expenses' => round($blueprintExpected['expenses'], 2),
                'tax_reserve' => round($taxReserve, 2),
                'net_spendable' => round($netSpendable, 2),
            ],
            'actual' => [
                'income' => round($actualIncome, 2),
                'expenses' => round($actualExpenses, 2),
                'net' => round($actualNet, 2),
            ],
            // Main variance = adjusted to include holiday savings as surplus
            'variance' => $adjustedVariance,
            'variance_status' => $varianceStatus,
            // Holiday surplus = money saved from paused payments (always positive)
            'holiday_surplus' => $holidaySurplus,
            'variance_breakdown' => [
                'raw_variance' => $rawVariance,  // Before holiday adjustment
                'holiday_surplus' => $holidaySurplus,  // From paused payments
                'adjusted_variance' => $adjustedVariance,  // Final variance (raw + holiday)
                'true_savings' => round($trueSavings, 2),  // What you saved on your own
                'contractual_holiday_items' => $contractualHolidayItems,
            ],
            'blueprint_details' => array_values($blueprintDetails),
            'unmapped_leaks' => array_values($unmappedLeaks),
            'ghost_fees' => $ghostFees,
            'zombies' => $zombies,
            'summary' => [
                'total_transactions' => $transactions->count(),
                'matched' => $transactions->whereNotNull('blueprint_id')->count(),
                'unmatched' => $transactions->whereNull('blueprint_id')->count(),
                'leak_count' => $transactions->where('match_status', 'unmapped')->count(),
                'holiday_count' => count($contractualHolidayItems),
            ],
        ];
    }

    /**
     * Get monthly amount for a blueprint (adjusts for frequency)
     */
    protected function getMonthlyAmount(Blueprint $blueprint): float
    {
        $amount = (float) $blueprint->expected_amount;

        return match ($blueprint->frequency) {
            'weekly' => $amount * 4.33,
            'quarterly' => $amount / 3,
            'annual' => $amount / 12,
            default => $amount, // monthly
        };
    }

    /**
     * Get monthly amount for a specific period, respecting payment holidays
     * Returns 0 if the blueprint is paused during the specified month
     */
    protected function getMonthlyAmountForPeriod(Blueprint $blueprint, int $year, int $month): float
    {
        // If paused for this month, expected amount is 0
        if ($blueprint->isPausedForMonth($year, $month)) {
            return 0;
        }

        // Otherwise, return the frequency-adjusted amount
        return $this->getMonthlyAmount($blueprint);
    }

    /**
     * Normalize description for grouping
     */
    protected function normalizeDescription(string $description): string
    {
        // Remove dates, reference numbers
        $normalized = preg_replace('/\d{4,}/', '', $description);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Extract first meaningful words
        $words = explode(' ', trim($normalized));
        return implode(' ', array_slice($words, 0, 3));
    }

    /**
     * Get list of zombie subscriptions for a user
     */
    public function getZombies(User $user): Collection
    {
        $cancelledBlueprints = $user->blueprints()
            ->cancelled()
            ->with(['transactions' => function ($query) {
                $query->where('transaction_date', '>=', now()->subMonths(3));
            }])
            ->get();

        return $cancelledBlueprints->filter(function ($blueprint) {
            return $blueprint->transactions->isNotEmpty();
        })->map(function ($blueprint) {
            return [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'cancelled_at' => $blueprint->cancelled_at,
                'expected_amount' => $blueprint->expected_amount,
                'recent_charges' => $blueprint->transactions->sum(fn($t) => abs($t->amount)),
                'transaction_count' => $blueprint->transactions->count(),
                'last_charge_date' => $blueprint->transactions->max('transaction_date'),
            ];
        });
    }

    /**
     * Cancel a blueprint and track for zombie detection
     */
    public function cancelBlueprint(Blueprint $blueprint): void
    {
        $blueprint->cancel();
    }

    /**
     * Pause a blueprint (payment holiday)
     */
    public function pauseBlueprint(
        Blueprint $blueprint,
        ?string $reason = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): void {
        $blueprint->pause($reason, $startDate, $endDate);
    }

    /**
     * Resume a blueprint from payment holiday
     */
    public function resumeBlueprint(Blueprint $blueprint): void
    {
        $blueprint->resume();
    }

    /**
     * Manually link a transaction to a blueprint
     */
    public function manualMatch(Transaction $transaction, Blueprint $blueprint): void
    {
        $this->matchTransaction($transaction, $blueprint, 100, 'manual');
    }

    /**
     * Unlink a transaction from its blueprint
     */
    public function unmatch(Transaction $transaction): void
    {
        $transaction->update([
            'blueprint_id' => null,
            'match_score' => null,
            'match_status' => null,
        ]);
    }

    /**
     * Get blueprints resuming within N days (default 30)
     * For resumption alerts
     */
    public function getResumptionAlerts(User $user, int $days = 30): Collection
    {
        return $user->blueprints()
            ->resumingSoon($days)
            ->with('category')
            ->get()
            ->map(function ($blueprint) {
                return [
                    'id' => $blueprint->id,
                    'name' => $blueprint->name,
                    'expected_amount' => $this->getMonthlyAmount($blueprint),
                    'item_type' => $blueprint->item_type,
                    'bucket' => $blueprint->bucket,
                    'nature' => $blueprint->nature,
                    'category' => $blueprint->category?->name,
                    'pause_reason' => $blueprint->pause_reason,
                    'pause_end_date' => $blueprint->pause_end_date->format('Y-m-d'),
                    'days_until_resume' => $blueprint->days_until_resume,
                    'resumes_on' => $blueprint->pause_end_date->format('M j, Y'),
                ];
            });
    }

    /**
     * Get next month impact preview for dashboard
     * Shows how spendable cash will change when paused items resume
     */
    public function getNextMonthImpact(User $user): array
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        // Get next month
        $nextMonth = $now->copy()->addMonth();
        $nextYear = $nextMonth->year;
        $nextMonthNum = $nextMonth->month;

        // Get all blueprints
        $blueprints = $user->blueprints()
            ->with('category')
            ->get();

        // Calculate current month totals
        $currentIncome = 0;
        $currentExpenses = 0;
        $currentBusinessIncome = 0;
        $currentBusinessExpenses = 0;

        // Calculate next month totals
        $nextIncome = 0;
        $nextExpenses = 0;
        $nextBusinessIncome = 0;
        $nextBusinessExpenses = 0;

        $resumingItems = [];

        foreach ($blueprints as $blueprint) {
            if ($blueprint->status === 'cancelled') {
                continue;
            }

            $currentAmount = $this->getMonthlyAmountForPeriod($blueprint, $currentYear, $currentMonth);
            $nextAmount = $this->getMonthlyAmountForPeriod($blueprint, $nextYear, $nextMonthNum);

            if ($blueprint->item_type === 'income') {
                $currentIncome += $currentAmount;
                $nextIncome += $nextAmount;
                if ($blueprint->nature === 'business') {
                    $currentBusinessIncome += $currentAmount;
                    $nextBusinessIncome += $nextAmount;
                }
            } else {
                $currentExpenses += $currentAmount;
                $nextExpenses += $nextAmount;
                if ($blueprint->nature === 'business') {
                    $currentBusinessExpenses += $currentAmount;
                    $nextBusinessExpenses += $nextAmount;
                }
            }

            // Check if this item is resuming next month
            $isPausedNow = $blueprint->isPausedForMonth($currentYear, $currentMonth);
            $isPausedNextMonth = $blueprint->isPausedForMonth($nextYear, $nextMonthNum);

            if ($isPausedNow && !$isPausedNextMonth) {
                $resumingItems[] = [
                    'id' => $blueprint->id,
                    'name' => $blueprint->name,
                    'amount' => $nextAmount,
                    'item_type' => $blueprint->item_type,
                    'bucket' => $blueprint->bucket,
                    'category' => $blueprint->category?->name,
                    'pause_reason' => $blueprint->pause_reason,
                ];
            }
        }

        // Calculate tax reserves
        $currentTaxableIncome = max(0, $currentBusinessIncome - $currentBusinessExpenses);
        $currentTaxReserve = $currentTaxableIncome * 0.391;
        $currentSpendable = $currentIncome - $currentTaxReserve - $currentExpenses;

        $nextTaxableIncome = max(0, $nextBusinessIncome - $nextBusinessExpenses);
        $nextTaxReserve = $nextTaxableIncome * 0.391;
        $nextSpendable = $nextIncome - $nextTaxReserve - $nextExpenses;

        // Calculate impact (negative = less spendable cash due to resuming expenses)
        $spendableImpact = $nextSpendable - $currentSpendable;

        // Sum resuming expense amounts
        $resumingExpenseTotal = collect($resumingItems)
            ->where('item_type', 'expense')
            ->sum('amount');

        return [
            'current_month' => [
                'period' => $now->format('F Y'),
                'income' => round($currentIncome, 2),
                'expenses' => round($currentExpenses, 2),
                'tax_reserve' => round($currentTaxReserve, 2),
                'net_spendable' => round($currentSpendable, 2),
            ],
            'next_month' => [
                'period' => $nextMonth->format('F Y'),
                'income' => round($nextIncome, 2),
                'expenses' => round($nextExpenses, 2),
                'tax_reserve' => round($nextTaxReserve, 2),
                'net_spendable' => round($nextSpendable, 2),
            ],
            'impact' => [
                'spendable_change' => round($spendableImpact, 2),
                'resuming_expenses' => round($resumingExpenseTotal, 2),
                'resuming_count' => count($resumingItems),
                'resuming_items' => $resumingItems,
            ],
            'alert' => $resumingExpenseTotal > 0
                ? "Warning: R" . number_format($resumingExpenseTotal, 2) . " in expenses resuming next month"
                : null,
        ];
    }
}
