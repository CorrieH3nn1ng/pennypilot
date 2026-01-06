<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'methodology',
        'total_income',
        'total_planned',
        'tax_provision',
        'net_available',
        'status',
        'rollover_option',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_income' => 'decimal:2',
            'total_planned' => 'decimal:2',
            'tax_provision' => 'decimal:2',
            'net_available' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /**
     * Get budget items grouped by bucket (for 50/30/20)
     */
    public function getItemsByBucket(): array
    {
        $items = $this->items()->with('category')->get();

        return [
            'needs' => $items->where('bucket', 'needs'),
            'wants' => $items->where('bucket', 'wants'),
            'savings' => $items->where('bucket', 'savings'),
        ];
    }

    /**
     * Get totals by bucket
     */
    public function getBucketTotals(): array
    {
        $items = $this->items;

        return [
            'needs' => [
                'planned' => $items->where('bucket', 'needs')->sum('planned_amount'),
                'actual' => $items->where('bucket', 'needs')->sum('actual_amount'),
            ],
            'wants' => [
                'planned' => $items->where('bucket', 'wants')->sum('planned_amount'),
                'actual' => $items->where('bucket', 'wants')->sum('actual_amount'),
            ],
            'savings' => [
                'planned' => $items->where('bucket', 'savings')->sum('planned_amount'),
                'actual' => $items->where('bucket', 'savings')->sum('actual_amount'),
            ],
        ];
    }

    /**
     * Get target allocations based on methodology
     */
    public function getTargetAllocations(): array
    {
        $net = (float) $this->net_available;

        return match ($this->methodology) {
            '50-30-20' => [
                'needs' => $net * 0.50,
                'wants' => $net * 0.30,
                'savings' => $net * 0.20,
            ],
            'profit-first' => [
                // Profit First: 10% profit, 15% tax, 5% owner pay, 70% opex
                'profit' => $net * 0.10,
                'tax' => $net * 0.15,
                'owner_pay' => $net * 0.05,
                'opex' => $net * 0.70,
            ],
            'zero-based' => [
                // Zero-based: all income should be allocated
                'total_to_allocate' => $net,
                'unallocated' => $net - (float) $this->total_planned,
            ],
            default => [
                'needs' => $net,
                'wants' => 0,
                'savings' => 0,
            ],
        };
    }

    /**
     * Calculate remaining budget
     */
    public function getRemainingAttribute(): float
    {
        return (float) $this->net_available - (float) $this->items()->sum('planned_amount');
    }

    /**
     * Get period display name
     */
    public function getPeriodNameAttribute(): string
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March',
            4 => 'April', 5 => 'May', 6 => 'June',
            7 => 'July', 8 => 'August', 9 => 'September',
            10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return $months[$this->month] . ' ' . $this->year;
    }
}
