<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'account_number',
        'bank_name',
        'opening_balance',
        'opening_balance_date',
        'current_balance',
        'balance_updated_at',
        'is_default',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'balance_updated_at' => 'datetime',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate the opening balance based on current balance and transaction sum
     */
    public function calculateOpeningBalance(float $currentBalance, float $transactionSum): float
    {
        return $currentBalance - $transactionSum;
    }
}
