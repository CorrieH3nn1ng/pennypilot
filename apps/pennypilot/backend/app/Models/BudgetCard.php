<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BudgetCard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'status',
        'generated_from_blueprint_at',
        'total_expected_income',
        'total_expected_expense',
        'total_matched',
        'total_leaked',
        'total_windfall',
        'total_missing',
        'success_grade',
        'surplus_amount',
        'surplus_action',
        'rollover_from_previous',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'generated_from_blueprint_at' => 'datetime',
            'total_expected_income' => 'decimal:2',
            'total_expected_expense' => 'decimal:2',
            'total_matched' => 'decimal:2',
            'total_leaked' => 'decimal:2',
            'total_windfall' => 'decimal:2',
            'total_missing' => 'decimal:2',
            'success_grade' => 'decimal:2',
            'surplus_amount' => 'decimal:2',
            'rollover_from_previous' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetCardItem::class);
    }

    public function transactionAudits(): HasMany
    {
        return $this->hasMany(TransactionAudit::class);
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    // Computed attributes

    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function getStartDateAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfMonth();
    }

    public function getEndDateAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->endOfMonth();
    }

    public function getIsCurrentMonthAttribute(): bool
    {
        $now = Carbon::now();
        return $this->year === $now->year && $this->month === $now->month;
    }

    public function getIsFinalizedAttribute(): bool
    {
        return $this->status === 'finalized';
    }

    public function getTotalExpectedAttribute(): float
    {
        return $this->total_expected_income - $this->total_expected_expense;
    }

    // Helper methods

    /**
     * Generate a Budget Card from user's active blueprints
     */
    public static function generateFromBlueprints(string $userId, int $year, int $month): self
    {
        $card = self::create([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
            'status' => 'draft',
            'generated_from_blueprint_at' => now(),
        ]);

        // Get active blueprints and create card items
        $blueprints = Blueprint::where('user_id', $userId)
            ->active()
            ->orderBy('priority', 'desc')
            ->get();

        $totalExpectedIncome = 0;
        $totalExpectedExpense = 0;

        foreach ($blueprints as $blueprint) {
            // Check frequency - only include if applicable to this month
            if (!$card->blueprintApplicableThisMonth($blueprint)) {
                continue;
            }

            BudgetCardItem::create([
                'budget_card_id' => $card->id,
                'blueprint_id' => $blueprint->id,
                'category_id' => $blueprint->category_id,
                'item_type' => $blueprint->item_type,
                'name' => $blueprint->name,
                'expected_amount' => $blueprint->expected_amount,
                'expected_day' => $blueprint->due_day,
                'bucket' => $blueprint->bucket,
                'match_pattern' => $blueprint->match_pattern,
                'match_type' => $blueprint->match_type,
                'is_one_off' => false,
                'audit_status' => 'pending',
            ]);

            if ($blueprint->item_type === 'income') {
                $totalExpectedIncome += $blueprint->expected_amount;
            } else {
                $totalExpectedExpense += $blueprint->expected_amount;
            }
        }

        $card->update([
            'total_expected_income' => $totalExpectedIncome,
            'total_expected_expense' => $totalExpectedExpense,
        ]);

        return $card;
    }

    /**
     * Check if a blueprint applies to this month based on frequency
     */
    protected function blueprintApplicableThisMonth(Blueprint $blueprint): bool
    {
        return match ($blueprint->frequency) {
            'monthly' => true,
            'weekly' => true, // Weekly items show every month
            'quarterly' => in_array($this->month, [1, 4, 7, 10]), // Jan, Apr, Jul, Oct
            'annual' => $this->month === 1, // January only (could be configurable)
            default => true,
        };
    }

    /**
     * Activate the budget card
     */
    public function activate(): self
    {
        $this->update(['status' => 'active']);
        return $this;
    }

    /**
     * Finalize and lock the budget card
     */
    public function finalize(?string $surplusAction = null): self
    {
        $this->recalculateTotals();
        $this->calculateSuccessGrade();

        $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'surplus_action' => $surplusAction,
        ]);

        return $this;
    }

    /**
     * Recalculate totals from items and audits
     */
    public function recalculateTotals(): self
    {
        $matched = $this->items()->where('audit_status', 'matched')->sum('matched_amount');
        $missing = $this->items()->where('audit_status', 'missing')->sum('expected_amount');

        $leaked = $this->transactionAudits()
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->join('transactions', 'transaction_audits.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');

        $windfall = $this->transactionAudits()
            ->where('classification', 'windfall')
            ->join('transactions', 'transaction_audits.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');

        $this->update([
            'total_matched' => abs($matched),
            'total_missing' => abs($missing),
            'total_leaked' => abs($leaked),
            'total_windfall' => abs($windfall),
        ]);

        return $this;
    }

    /**
     * Calculate success grade (% of planned items matched)
     */
    public function calculateSuccessGrade(): self
    {
        $totalItems = $this->items()->count();
        $matchedItems = $this->items()->where('audit_status', 'matched')->count();

        $grade = $totalItems > 0 ? ($matchedItems / $totalItems) * 100 : 0;

        // Also factor in leaks (reduce grade for unplanned spending)
        $leakCount = $this->transactionAudits()
            ->where('classification', 'leak')
            ->where('is_silenced', false)
            ->count();

        // Penalty: -5% per leak (max 30% reduction)
        $leakPenalty = min(30, $leakCount * 5);
        $grade = max(0, $grade - $leakPenalty);

        $surplus = $this->total_expected_expense - ($this->total_matched + $this->total_leaked);

        $this->update([
            'success_grade' => round($grade, 2),
            'surplus_amount' => $surplus,
        ]);

        return $this;
    }

    /**
     * Get letter grade from numeric grade
     */
    public function getLetterGradeAttribute(): string
    {
        if ($this->success_grade >= 90) return 'A';
        if ($this->success_grade >= 80) return 'B';
        if ($this->success_grade >= 70) return 'C';
        if ($this->success_grade >= 60) return 'D';
        return 'F';
    }
}
