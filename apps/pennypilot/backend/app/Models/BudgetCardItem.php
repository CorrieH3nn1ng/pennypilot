<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCardItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'budget_card_id',
        'blueprint_id',
        'invoice_id',
        'liability_id',
        'category_id',
        'item_type',
        'name',
        'expected_amount',
        'expected_day',
        'bucket',
        'match_pattern',
        'match_type',
        'is_one_off',
        'audit_status',
        'matched_transaction_id',
        'matched_amount',
        'matched_date',
        'match_confidence',
        'notes',
        'installment_number',
        'remaining_balance_at_match',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'expected_day' => 'integer',
            'is_one_off' => 'boolean',
            'matched_amount' => 'decimal:2',
            'matched_date' => 'date',
            'match_confidence' => 'decimal:2',
            'installment_number' => 'integer',
            'remaining_balance_at_match' => 'decimal:2',
        ];
    }

    // Relationships

    public function budgetCard(): BelongsTo
    {
        return $this->belongsTo(BudgetCard::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('audit_status', 'pending');
    }

    public function scopeMatched($query)
    {
        return $query->where('audit_status', 'matched');
    }

    public function scopeMissing($query)
    {
        return $query->where('audit_status', 'missing');
    }

    public function scopeIncome($query)
    {
        return $query->where('item_type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('item_type', 'expense');
    }

    public function scopeOneOffs($query)
    {
        return $query->where('is_one_off', true);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_one_off', false);
    }

    // Status checks

    public function isPending(): bool
    {
        return $this->audit_status === 'pending';
    }

    public function isMatched(): bool
    {
        return $this->audit_status === 'matched';
    }

    public function isMissing(): bool
    {
        return $this->audit_status === 'missing';
    }

    // Matching methods

    /**
     * Match this item to a transaction
     */
    public function matchToTransaction(Transaction $transaction, float $confidence, string $method = 'auto'): self
    {
        $this->update([
            'audit_status' => 'matched',
            'matched_transaction_id' => $transaction->id,
            'matched_amount' => abs($transaction->amount),
            'matched_date' => $transaction->transaction_date,
            'match_confidence' => $confidence,
        ]);

        // Mark transaction as audited
        $transaction->update(['audit_status' => 'audited']);

        // Create audit trail
        TransactionAudit::create([
            'budget_card_id' => $this->budget_card_id,
            'transaction_id' => $transaction->id,
            'budget_card_item_id' => $this->id,
            'classification' => 'matched',
            'match_method' => $method,
            'match_confidence' => $confidence,
        ]);

        return $this;
    }

    /**
     * Remove match from this item
     */
    public function unmatch(): self
    {
        if ($this->matched_transaction_id) {
            // Reset transaction audit status
            Transaction::where('id', $this->matched_transaction_id)
                ->update(['audit_status' => 'pending']);

            // Remove audit record
            TransactionAudit::where('budget_card_item_id', $this->id)->delete();
        }

        $this->update([
            'audit_status' => 'pending',
            'matched_transaction_id' => null,
            'matched_amount' => null,
            'matched_date' => null,
            'match_confidence' => null,
        ]);

        return $this;
    }

    /**
     * Mark as missing (month end, not found)
     */
    public function markAsMissing(): self
    {
        $this->update(['audit_status' => 'missing']);
        return $this;
    }

    /**
     * Get variance between expected and matched amount
     */
    public function getVarianceAttribute(): ?float
    {
        if (!$this->isMatched()) {
            return null;
        }

        return $this->expected_amount - $this->matched_amount;
    }

    /**
     * Check if matched amount is over expected
     */
    public function getIsOverBudgetAttribute(): bool
    {
        if (!$this->isMatched()) {
            return false;
        }

        return $this->matched_amount > $this->expected_amount;
    }

    /**
     * Check if this is a liability-linked item
     */
    public function getIsLiabilityItemAttribute(): bool
    {
        return $this->liability_id !== null;
    }

    /**
     * Get installment label for liability items
     */
    public function getInstallmentLabelAttribute(): ?string
    {
        if (!$this->is_liability_item || !$this->liability) {
            return null;
        }

        $liability = $this->liability;
        $installment = $this->installment_number ?? $liability->completed_installments + 1;

        if ($liability->total_installments) {
            return "Installment {$installment} of {$liability->total_installments}";
        }

        return "Installment {$installment}";
    }

    /**
     * Get remaining balance for liability items
     */
    public function getRemainingBalanceAttribute(): ?float
    {
        if (!$this->is_liability_item || !$this->liability) {
            return null;
        }

        // If matched, use the balance at match time
        if ($this->remaining_balance_at_match !== null) {
            return (float) $this->remaining_balance_at_match;
        }

        // Otherwise use current liability balance
        return (float) $this->liability->current_balance;
    }
}
