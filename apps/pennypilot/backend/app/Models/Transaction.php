<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'transaction_date',
        'description',
        'amount',
        'balance_after',
        'bank_reference',
        'import_source',
        'raw_description',
        'is_categorized',
        'categorized_by',
        'confidence_score',
        'notes',
        'tags',
        'local_id',
        'sync_status',
        'last_synced_at',
        'version',
        'is_encrypted',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'is_categorized' => 'boolean',
        'is_encrypted' => 'boolean',
        'tags' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Check if transaction is an expense
     */
    public function isExpense(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Check if transaction is income
     */
    public function isIncome(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Get absolute amount (always positive)
     */
    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->amount);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope for pending sync
     */
    public function scopePendingSync($query)
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Scope for uncategorized transactions
     */
    public function scopeUncategorized($query)
    {
        return $query->where('is_categorized', false);
    }
}
