<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_period_id',
        'category_id',
        'name',
        'planned_amount',
        'actual_amount',
        'bucket',
        'is_fixed',
        'rollover_amount',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'rollover_amount' => 'decimal:2',
            'is_fixed' => 'boolean',
        ];
    }

    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get variance (positive = under budget, negative = over budget)
     */
    public function getVarianceAttribute(): float
    {
        return (float) $this->planned_amount - (float) $this->actual_amount;
    }

    /**
     * Get percentage spent
     */
    public function getPercentSpentAttribute(): float
    {
        if ((float) $this->planned_amount <= 0) {
            return 0;
        }

        return min(100, ((float) $this->actual_amount / (float) $this->planned_amount) * 100);
    }

    /**
     * Check if over budget
     */
    public function getIsOverBudgetAttribute(): bool
    {
        return (float) $this->actual_amount > (float) $this->planned_amount;
    }

    /**
     * Get display name (category name or custom name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->category?->name ?? $this->name ?? 'Uncategorized';
    }
}
