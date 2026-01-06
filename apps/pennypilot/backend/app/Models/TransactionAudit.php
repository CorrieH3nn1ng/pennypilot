<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAudit extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'budget_card_id',
        'transaction_id',
        'budget_card_item_id',
        'classification',
        'match_method',
        'match_confidence',
        'is_silenced',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'match_confidence' => 'decimal:2',
            'is_silenced' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    // Relationships

    public function budgetCard(): BelongsTo
    {
        return $this->belongsTo(BudgetCard::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function budgetCardItem(): BelongsTo
    {
        return $this->belongsTo(BudgetCardItem::class);
    }

    // Scopes

    public function scopeMatched($query)
    {
        return $query->where('classification', 'matched');
    }

    public function scopeLeaks($query)
    {
        return $query->where('classification', 'leak');
    }

    public function scopeWindfalls($query)
    {
        return $query->where('classification', 'windfall');
    }

    public function scopeTransfers($query)
    {
        return $query->where('classification', 'transfer');
    }

    public function scopeNotSilenced($query)
    {
        return $query->where('is_silenced', false);
    }

    public function scopeSilenced($query)
    {
        return $query->where('is_silenced', true);
    }

    // Helper methods

    public function isLeak(): bool
    {
        return $this->classification === 'leak';
    }

    public function isWindfall(): bool
    {
        return $this->classification === 'windfall';
    }

    public function isMatched(): bool
    {
        return $this->classification === 'matched';
    }

    public function isTransfer(): bool
    {
        return $this->classification === 'transfer';
    }

    /**
     * Silence this audit (acknowledge leak/windfall)
     */
    public function silence(): self
    {
        $this->update(['is_silenced' => true]);
        return $this;
    }

    /**
     * Unsilence this audit
     */
    public function unsilence(): self
    {
        $this->update(['is_silenced' => false]);
        return $this;
    }

    /**
     * Create a leak audit for an unplanned expense
     */
    public static function createLeak(BudgetCard $card, Transaction $transaction): self
    {
        $transaction->update(['audit_status' => 'audited']);

        return self::create([
            'budget_card_id' => $card->id,
            'transaction_id' => $transaction->id,
            'classification' => 'leak',
        ]);
    }

    /**
     * Create a windfall audit for an unplanned income
     */
    public static function createWindfall(BudgetCard $card, Transaction $transaction): self
    {
        $transaction->update(['audit_status' => 'audited']);

        return self::create([
            'budget_card_id' => $card->id,
            'transaction_id' => $transaction->id,
            'classification' => 'windfall',
        ]);
    }

    /**
     * Create a transfer audit (excluded from budget)
     */
    public static function createTransfer(BudgetCard $card, Transaction $transaction): self
    {
        $transaction->update(['audit_status' => 'audited']);

        return self::create([
            'budget_card_id' => $card->id,
            'transaction_id' => $transaction->id,
            'classification' => 'transfer',
            'is_silenced' => true, // Transfers are auto-silenced
        ]);
    }
}
