<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blueprint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'category_id',
        'liability_id',
        'name',
        'expected_amount',
        'amount_tolerance',
        'due_day',
        'due_day_tolerance',
        'item_type',
        'bucket',
        'nature',  // 'business' (tax-deductible) or 'private' (50/30/20)
        'match_pattern',
        'match_aliases',  // JSON array of alternative merchant names
        'match_type',
        'frequency',
        'status',  // 'active', 'paused', 'cancelled'
        'pause_start_date',
        'pause_end_date',
        'pause_reason',
        'is_active',  // Legacy - use status instead
        'is_cancelled',  // Legacy - use status instead
        'cancelled_at',
        'priority',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'amount_tolerance' => 'decimal:2',
            'due_day' => 'integer',
            'due_day_tolerance' => 'integer',
            'is_active' => 'boolean',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'date',
            'match_aliases' => 'array',
            'priority' => 'integer',
            'pause_start_date' => 'date',
            'pause_end_date' => 'date',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }

    public function budgetCardItems(): HasMany
    {
        return $this->hasMany(BudgetCardItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        // Use status-based filtering (covers active and paused but not cancelled)
        // Paused items are still "active" for budget purposes, just with 0 expected amount
        return $query->whereIn('status', ['active', 'paused'])
            ->orWhere(function ($q) {
                // Legacy support: if status is null, fall back to is_active flag
                $q->whereNull('status')->where('is_active', true);
            });
    }

    public function scopeIncome($query)
    {
        return $query->where('item_type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('item_type', 'expense');
    }

    public function scopeByBucket($query, string $bucket)
    {
        return $query->where('bucket', $bucket);
    }

    public function scopeWithLiability($query)
    {
        return $query->whereNotNull('liability_id');
    }

    public function scopeBusiness($query)
    {
        return $query->where('nature', 'business');
    }

    public function scopePrivate($query)
    {
        return $query->where('nature', 'private');
    }

    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('is_cancelled', false);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeActiveStatus($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotCancelledStatus($query)
    {
        return $query->whereIn('status', ['active', 'paused']);
    }

    public function scopeResumingSoon($query, int $days = 30)
    {
        $now = \Carbon\Carbon::now();
        $futureDate = $now->copy()->addDays($days);

        return $query->where('status', 'paused')
            ->whereNotNull('pause_end_date')
            ->whereBetween('pause_end_date', [$now, $futureDate]);
    }

    // Accessors

    /**
     * Check if this blueprint is linked to a liability
     */
    public function getIsLiabilityItemAttribute(): bool
    {
        return $this->liability_id !== null;
    }

    /**
     * Check if this is a business (tax-deductible) expense
     */
    public function getIsBusinessExpenseAttribute(): bool
    {
        return $this->nature === 'business' && $this->item_type === 'expense';
    }

    /**
     * Check if this is a private expense (50/30/20 framework)
     */
    public function getIsPrivateExpenseAttribute(): bool
    {
        return $this->nature === 'private' && $this->item_type === 'expense';
    }

    // Helper methods

    /**
     * Check if an amount matches within tolerance
     */
    public function matchesAmount(float $amount): bool
    {
        $expected = abs($this->expected_amount);
        $actual = abs($amount);
        $tolerance = $this->amount_tolerance / 100;

        $minAmount = $expected * (1 - $tolerance);
        $maxAmount = $expected * (1 + $tolerance);

        return $actual >= $minAmount && $actual <= $maxAmount;
    }

    /**
     * Check if a date matches within tolerance
     */
    public function matchesDate(int $dayOfMonth): bool
    {
        if (!$this->due_day) {
            return true; // No specific day required
        }

        $diff = abs($dayOfMonth - $this->due_day);

        // Handle month wrap (e.g., due_day=2, transaction=30 should be ~3 days apart)
        if ($diff > 15) {
            $diff = 31 - $diff; // Approximate month length
        }

        return $diff <= $this->due_day_tolerance;
    }

    /**
     * Check if description matches pattern
     */
    public function matchesDescription(string $description): bool
    {
        if (!$this->match_pattern) {
            return true; // No pattern required
        }

        $pattern = strtoupper($this->match_pattern);
        $desc = strtoupper($description);

        return match ($this->match_type) {
            'exact' => $desc === $pattern,
            'starts_with' => str_starts_with($desc, $pattern),
            'contains' => str_contains($desc, $pattern),
            default => str_contains($desc, $pattern),
        };
    }

    /**
     * Calculate match confidence score (0-100)
     */
    public function calculateMatchScore(float $amount, int $dayOfMonth, string $description): float
    {
        $score = 0;

        // Amount match (35 points)
        if ($this->matchesAmount($amount)) {
            $expected = abs($this->expected_amount);
            $actual = abs($amount);
            $diff = abs($expected - $actual) / $expected;

            if ($diff < 0.001) {
                $score += 35; // Exact match
            } elseif ($diff < 0.02) {
                $score += 30; // Within 2%
            } elseif ($diff < 0.05) {
                $score += 25; // Within 5%
            } else {
                $score += 15; // Within tolerance but larger difference
            }
        }

        // Date match (40 points)
        if ($this->due_day) {
            $dayDiff = abs($dayOfMonth - $this->due_day);
            if ($dayDiff > 15) {
                $dayDiff = 31 - $dayDiff;
            }

            if ($dayDiff === 0) {
                $score += 40; // Exact day
            } elseif ($dayDiff === 1) {
                $score += 35; // 1 day off
            } elseif ($dayDiff <= 3) {
                $score += 25; // Within 3 days
            } elseif ($dayDiff <= 5) {
                $score += 15; // Within 5 days
            }
        } else {
            $score += 20; // No specific day - partial credit
        }

        // Description match (25 points)
        if ($this->match_pattern) {
            if ($this->matchesDescription($description)) {
                $score += match ($this->match_type) {
                    'exact' => 25,
                    'starts_with' => 20,
                    'contains' => 15,
                    default => 15,
                };
            }
        } else {
            $score += 10; // No pattern - partial credit
        }

        return min(100, $score);
    }

    // Payment Holiday Methods

    /**
     * Check if this blueprint is paused (on payment holiday) for a specific date
     */
    public function isPausedForDate(\Carbon\Carbon $date): bool
    {
        if ($this->status !== 'paused') {
            return false;
        }

        // If no dates specified, it's indefinitely paused
        if (!$this->pause_start_date && !$this->pause_end_date) {
            return true;
        }

        // Check if date falls within pause period
        $afterStart = !$this->pause_start_date || $date->gte($this->pause_start_date);
        $beforeEnd = !$this->pause_end_date || $date->lte($this->pause_end_date);

        return $afterStart && $beforeEnd;
    }

    /**
     * Check if this blueprint is paused for a specific month
     */
    public function isPausedForMonth(int $year, int $month): bool
    {
        // Create date representing the middle of the month
        $monthDate = \Carbon\Carbon::create($year, $month, 15);
        return $this->isPausedForDate($monthDate);
    }

    /**
     * Get the expected amount for a specific month
     * Returns 0 if paused during that month, otherwise the expected_amount
     */
    public function getExpectedAmountForMonth(int $year, int $month): float
    {
        // Cancelled items have no expected amount
        if ($this->status === 'cancelled') {
            return 0;
        }

        // Paused items have no expected amount during the pause period
        if ($this->isPausedForMonth($year, $month)) {
            return 0;
        }

        return (float) $this->expected_amount;
    }

    /**
     * Get status label for display (including holiday info)
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'paused' => 'Payment Holiday',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status ?? 'active'),
        };
    }

    /**
     * Check if this is currently on payment holiday
     */
    public function getIsOnHolidayAttribute(): bool
    {
        return $this->isPausedForDate(\Carbon\Carbon::now());
    }

    /**
     * Get the date when this payment holiday ends (null if not paused or indefinite)
     */
    public function getHolidayEndsAtAttribute(): ?\Carbon\Carbon
    {
        if ($this->status !== 'paused') {
            return null;
        }
        return $this->pause_end_date;
    }

    /**
     * Check if this blueprint is resuming within N days
     */
    public function isResumingWithinDays(int $days = 30): bool
    {
        if ($this->status !== 'paused' || !$this->pause_end_date) {
            return false;
        }

        $now = \Carbon\Carbon::now();
        $daysUntilResume = $now->diffInDays($this->pause_end_date, false);

        // Must be in the future and within the specified days
        return $daysUntilResume >= 0 && $daysUntilResume <= $days;
    }

    /**
     * Get days until this blueprint resumes (null if not paused or indefinite)
     */
    public function getDaysUntilResumeAttribute(): ?int
    {
        if ($this->status !== 'paused' || !$this->pause_end_date) {
            return null;
        }

        $now = \Carbon\Carbon::now();
        $days = $now->diffInDays($this->pause_end_date, false);

        return max(0, $days);
    }

    /**
     * Check if this blueprint requires a resumption alert (within 30 days)
     */
    public function getNeedsResumptionAlertAttribute(): bool
    {
        return $this->isResumingWithinDays(30);
    }

    /**
     * Put this blueprint on payment holiday
     */
    public function pause(?string $reason = null, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): self
    {
        $this->status = 'paused';
        $this->pause_reason = $reason;
        $this->pause_start_date = $startDate ?? \Carbon\Carbon::now();
        $this->pause_end_date = $endDate;
        $this->save();

        return $this;
    }

    /**
     * Resume this blueprint from payment holiday
     */
    public function resume(): self
    {
        $this->status = 'active';
        $this->pause_reason = null;
        $this->pause_start_date = null;
        $this->pause_end_date = null;
        $this->save();

        return $this;
    }

    /**
     * Cancel this blueprint permanently
     */
    public function cancel(): self
    {
        $this->status = 'cancelled';
        $this->is_cancelled = true;  // Legacy field
        $this->cancelled_at = \Carbon\Carbon::now();
        $this->save();

        return $this;
    }
}
