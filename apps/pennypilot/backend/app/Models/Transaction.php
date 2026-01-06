<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'blueprint_id',
        'bucket',
        'transaction_date',
        'description',
        'amount',
        'balance_after',
        'bank_reference',
        'import_source',
        'raw_description',
        'is_categorized',
        'is_transfer',
        'audit_status',
        'categorized_by',
        'confidence_score',
        'match_score',
        'match_status',
        'notes',
        'tags',
        'local_id',
        'sync_status',
        'last_synced_at',
        'version',
        'is_encrypted',
        // Blockchain/Notary prep fields
        'metadata_hash',
        'hash_computed_at',
        'blockchain_anchor',
        'anchored_at',
        // Accountant Intelligence fields
        'flagged_for_accountant',
        'accountant_status',
        'accountant_question',
        'accountant_reviewed_at',
        'accountant_notes',
        'tax_deduction_amount',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'match_score' => 'decimal:2',
        'is_categorized' => 'boolean',
        'is_transfer' => 'boolean',
        'is_encrypted' => 'boolean',
        'flagged_for_accountant' => 'boolean',
        'tax_deduction_amount' => 'decimal:2',
        'tags' => 'array',
        'last_synced_at' => 'datetime',
        'hash_computed_at' => 'datetime',
        'anchored_at' => 'datetime',
        'accountant_reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function transactionAudits(): HasMany
    {
        return $this->hasMany(TransactionAudit::class);
    }

    public function budgetCardItem(): HasOne
    {
        return $this->hasOne(BudgetCardItem::class, 'matched_transaction_id');
    }

    // Scopes for audit status

    public function scopePendingAudit($query)
    {
        return $query->where('audit_status', 'pending');
    }

    public function scopeAudited($query)
    {
        return $query->where('audit_status', 'audited');
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

    /**
     * Scope for transactions matched to a blueprint
     */
    public function scopeMatched($query)
    {
        return $query->whereNotNull('blueprint_id');
    }

    /**
     * Scope for unmatched transactions (no blueprint)
     */
    public function scopeUnmatched($query)
    {
        return $query->whereNull('blueprint_id');
    }

    /**
     * Scope for unmapped leaks (flagged as unmapped)
     */
    public function scopeUnmappedLeaks($query)
    {
        return $query->where('match_status', 'unmapped');
    }

    // =============================================
    // ACCOUNTANT INTELLIGENCE SCOPES & METHODS
    // =============================================

    /**
     * Scope for transactions flagged for accountant review
     */
    public function scopeFlaggedForAccountant($query)
    {
        return $query->where('flagged_for_accountant', true);
    }

    /**
     * Scope for pending accountant review
     */
    public function scopePendingAccountantReview($query)
    {
        return $query->where('flagged_for_accountant', true)
            ->where('accountant_status', 'pending');
    }

    /**
     * Scope for approved business expenses
     */
    public function scopeApprovedBusiness($query)
    {
        return $query->where('accountant_status', 'approved_business');
    }

    /**
     * Scope for rejected to private
     */
    public function scopeRejectedPrivate($query)
    {
        return $query->where('accountant_status', 'rejected_private');
    }

    /**
     * Flag transaction for accountant with a draft question
     */
    public function flagForAccountant(?string $question = null): void
    {
        $this->update([
            'flagged_for_accountant' => true,
            'accountant_status' => 'pending',
            'accountant_question' => $question,
        ]);
    }

    /**
     * Unflag transaction from accountant review
     */
    public function unflagFromAccountant(): void
    {
        $this->update([
            'flagged_for_accountant' => false,
            'accountant_status' => null,
            'accountant_question' => null,
            'accountant_reviewed_at' => null,
            'accountant_notes' => null,
            'tax_deduction_amount' => null,
        ]);
    }

    /**
     * Approve as business expense (tax-deductible)
     *
     * @param string|null $notes Review notes
     * @return float The tax deduction amount
     */
    public function approveAsBusiness(?string $notes = null): float
    {
        $deductionAmount = abs($this->amount);

        $this->update([
            'accountant_status' => 'approved_business',
            'accountant_reviewed_at' => now(),
            'accountant_notes' => $notes,
            'tax_deduction_amount' => $deductionAmount,
        ]);

        return $deductionAmount;
    }

    /**
     * Reject to private (not tax-deductible)
     *
     * @param string|null $notes Review notes
     */
    public function rejectToPrivate(?string $notes = null): void
    {
        $this->update([
            'accountant_status' => 'rejected_private',
            'accountant_reviewed_at' => now(),
            'accountant_notes' => $notes,
            'tax_deduction_amount' => null,
        ]);
    }

    /**
     * Check if transaction is pending accountant review
     */
    public function isPendingAccountantReview(): bool
    {
        return $this->flagged_for_accountant &&
            $this->accountant_status === 'pending';
    }

    /**
     * Check if transaction was approved as business expense
     */
    public function isApprovedBusiness(): bool
    {
        return $this->accountant_status === 'approved_business';
    }

    /**
     * Compute metadata hash for blockchain/notary integration
     * This creates a deterministic hash of the transaction's immutable data
     */
    public function computeMetadataHash(): string
    {
        // Only hash immutable transaction data
        $data = [
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'description' => $this->description,
            'amount' => (string) $this->amount,
            'bank_reference' => $this->bank_reference,
            'raw_description' => $this->raw_description,
        ];

        // Sort keys for deterministic output
        ksort($data);

        return hash('sha256', json_encode($data));
    }

    /**
     * Update the metadata hash
     */
    public function updateMetadataHash(): void
    {
        $this->metadata_hash = $this->computeMetadataHash();
        $this->hash_computed_at = now();
        $this->save();
    }

    /**
     * Verify the metadata hash is valid
     */
    public function verifyMetadataHash(): bool
    {
        if (!$this->metadata_hash) {
            return false;
        }

        return $this->metadata_hash === $this->computeMetadataHash();
    }
}
