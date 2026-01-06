<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        // Business Info
        'business_name',
        'trading_name',
        'registration_number',
        'tax_number',
        'vat_number',
        'is_vat_registered',
        'vat_registration_date',
        // Contact Info
        'email',
        'phone',
        'website',
        'address',
        // Banking Details
        'bank_name',
        'bank_branch',
        'bank_branch_code',
        'account_holder',
        'account_number',
        'account_type',
        // Invoice Settings
        'invoice_prefix',
        'invoice_notes',
        'payment_terms',
        'default_tax_rate',
        // Logo
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'is_vat_registered' => 'boolean',
            'vat_registration_date' => 'date',
        ];
    }

    /**
     * Check if business is VAT registered
     */
    public function isVatRegistered(): bool
    {
        return $this->is_vat_registered === true && !empty($this->vat_number);
    }

    /**
     * Get the applicable VAT rate for invoices
     * Returns 15% if VAT registered, 0% otherwise
     */
    public function getApplicableVatRate(): string
    {
        if ($this->isVatRegistered()) {
            return \App\Services\FinancialCalculator::VAT_RATE;
        }

        return '0.00';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create business profile for a user
     */
    public static function getOrCreateForUser(string $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'invoice_prefix' => 'INV-',
                'default_tax_rate' => 0,
            ]
        );
    }

    /**
     * Check if banking details are complete
     */
    public function hasBankingDetails(): bool
    {
        return !empty($this->bank_name)
            && !empty($this->account_holder)
            && !empty($this->account_number);
    }

    /**
     * Get formatted banking details for invoice
     */
    public function getFormattedBankingDetails(): array
    {
        return [
            'bank_name' => $this->bank_name,
            'branch' => $this->bank_branch,
            'branch_code' => $this->bank_branch_code,
            'account_holder' => $this->account_holder,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type,
        ];
    }
}
