<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * Liability Model - Debt Recovery Tracking
 *
 * Tracks debts being paid off through Blueprint items.
 * Supports arrears, loans, credit cards, and store accounts.
 */
class Liability extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'liability_type',
        'original_balance',
        'current_balance',
        'monthly_installment',
        'interest_rate',
        'total_installments',
        'completed_installments',
        'status',
        'paid_off_at',
        'amount_freed',
        'creditor',
        'reference_number',
        'start_date',
        'target_payoff_date',
        'notes',
    ];

    protected $casts = [
        'original_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_installments' => 'integer',
        'completed_installments' => 'integer',
        'amount_freed' => 'decimal:2',
        'start_date' => 'date',
        'target_payoff_date' => 'date',
        'paid_off_at' => 'datetime',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function blueprints(): HasMany
    {
        return $this->hasMany(Blueprint::class);
    }

    public function budgetCardItems(): HasMany
    {
        return $this->hasMany(BudgetCardItem::class);
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Get percentage paid off
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->original_balance <= 0) return 100;
        $paid = $this->original_balance - $this->current_balance;
        return round(($paid / $this->original_balance) * 100, 1);
    }

    /**
     * Get amount already paid
     */
    public function getAmountPaidAttribute(): float
    {
        return (float) bcsub((string) $this->original_balance, (string) $this->current_balance, 2);
    }

    /**
     * Get estimated remaining installments
     */
    public function getRemainingInstallmentsAttribute(): int
    {
        if ($this->monthly_installment <= 0) return 0;
        return (int) ceil($this->current_balance / $this->monthly_installment);
    }

    /**
     * Get estimated payoff date based on current installment
     */
    public function getEstimatedPayoffDateAttribute(): ?Carbon
    {
        if ($this->remaining_installments <= 0) return null;
        return Carbon::now()->addMonths($this->remaining_installments);
    }

    /**
     * Check if liability is paid off
     */
    public function getIsPaidOffAttribute(): bool
    {
        return $this->status === 'paid_off' || $this->current_balance <= 0;
    }

    /**
     * Get display label for installment progress
     */
    public function getInstallmentLabelAttribute(): string
    {
        if ($this->total_installments) {
            return "Installment {$this->completed_installments} of {$this->total_installments}";
        }
        return "Installment {$this->completed_installments}";
    }

    // =========================================
    // Business Logic
    // =========================================

    /**
     * Record a payment against this liability
     *
     * @param float $amount Payment amount
     * @return array Result with isPaidOff flag and amountFreed
     */
    public function recordPayment(float $amount): array
    {
        $newBalance = bcsub((string) $this->current_balance, (string) $amount, 2);

        // Ensure balance doesn't go negative
        if ($newBalance < 0) {
            $newBalance = '0.00';
        }

        $this->current_balance = $newBalance;
        $this->completed_installments++;

        // Check if paid off
        $isPaidOff = $this->current_balance <= 0;

        if ($isPaidOff) {
            $this->status = 'paid_off';
            $this->paid_off_at = now();
            $this->amount_freed = $this->monthly_installment;
        }

        $this->save();

        return [
            'isPaidOff' => $isPaidOff,
            'amountFreed' => $isPaidOff ? (float) $this->monthly_installment : 0,
            'remainingBalance' => (float) $this->current_balance,
            'completedInstallments' => $this->completed_installments,
        ];
    }

    /**
     * Deactivate linked blueprint when paid off
     */
    public function deactivateLinkedBlueprints(): int
    {
        return $this->blueprints()->update([
            'is_active' => false,
            'notes' => 'Auto-deactivated: Liability paid off on ' . now()->format('Y-m-d'),
        ]);
    }

    /**
     * Calculate next installment number
     */
    public function getNextInstallmentNumber(): int
    {
        return $this->completed_installments + 1;
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaidOff($query)
    {
        return $query->where('status', 'paid_off');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('liability_type', $type);
    }

    // =========================================
    // Static Factory Methods
    // =========================================

    /**
     * Calculate total installments needed
     */
    public static function calculateInstallments(float $balance, float $monthlyPayment): int
    {
        if ($monthlyPayment <= 0) return 0;
        return (int) ceil($balance / $monthlyPayment);
    }
}
