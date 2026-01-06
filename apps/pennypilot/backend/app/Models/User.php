<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\HasVatThreshold;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, HasVatThreshold, Notifiable;

    // =========================================
    // Relationships
    // =========================================

    /**
     * Get the country configuration for this user
     */
    public function countryConfig(): BelongsTo
    {
        return $this->belongsTo(CountryConfig::class, 'country_code', 'country_code');
    }

    /**
     * Get country config with caching (for performance)
     */
    public function getCountryConfig(): CountryConfig
    {
        return CountryConfig::getConfig($this->country_code ?? 'ZA')
            ?? CountryConfig::getDefault();
    }

    /**
     * Get all transactions for this user
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all blueprints for this user
     */
    public function blueprints(): HasMany
    {
        return $this->hasMany(Blueprint::class);
    }

    /**
     * Get all categories for this user (custom categories)
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all invoices for this user
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all clients for this user
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Get all income sources for this user
     */
    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    /**
     * Get all budget cards for this user
     */
    public function budgetCards(): HasMany
    {
        return $this->hasMany(BudgetCard::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country_code',
        'subscription_tier',
        'subscription_expires_at',
        'is_admin',
        'income_type',
        'net_monthly_income',
        'onboarding_completed_at',
        'vat_status',
        'vat_threshold_status',
        'vat_threshold_checked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
            'is_admin' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'vat_threshold_checked_at' => 'datetime',
        ];
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Check if user has an active premium subscription (Cruiser or Captain tier)
     * Admins always have premium access
     */
    public function isPremium(): bool
    {
        // Admins always have premium access
        if ($this->isAdmin()) {
            return true;
        }

        // Cruiser, Captain, and legacy 'premium' are premium tiers
        if (!in_array($this->subscription_tier, ['cruiser', 'captain', 'premium'])) {
            return false;
        }

        // If no expiry set, treat as lifetime premium
        if ($this->subscription_expires_at === null) {
            return true;
        }

        return $this->subscription_expires_at->isFuture();
    }

    /**
     * Check if user can use sync features (Cruiser or Captain)
     */
    public function canSync(): bool
    {
        return $this->isPremium();
    }

    /**
     * Check if user can use OCR features (Captain only)
     */
    public function canUseOcr(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->subscription_tier !== 'captain') {
            return false;
        }

        if ($this->subscription_expires_at === null) {
            return true;
        }

        return $this->subscription_expires_at->isFuture();
    }

    /**
     * Get tier features
     */
    public function getTierFeatures(): array
    {
        $tier = $this->subscription_tier;

        $features = [
            'free' => [
                'sync_enabled' => false,
                'ocr_enabled' => false,
                'multi_device' => false,
                'export_pdf' => false,
                'priority_support' => false,
                'max_transactions' => 500,
                'max_invoices' => 10,
            ],
            'cruiser' => [
                'sync_enabled' => true,
                'ocr_enabled' => false,
                'multi_device' => true,
                'export_pdf' => true,
                'priority_support' => false,
                'max_transactions' => null, // Unlimited
                'max_invoices' => 100,
            ],
            'captain' => [
                'sync_enabled' => true,
                'ocr_enabled' => true,
                'multi_device' => true,
                'export_pdf' => true,
                'priority_support' => true,
                'max_transactions' => null,
                'max_invoices' => null,
            ],
        ];

        return $features[$tier] ?? $features['free'];
    }

    // =========================================
    // Income Type Methods (User Persona)
    // =========================================

    /**
     * Check if user is self-employed (freelancer/sole proprietor)
     * Self-employed users get full invoicing and VAT tracking features
     */
    public function isSelfEmployed(): bool
    {
        return $this->income_type === 'self_employed';
    }

    /**
     * Check if user is salaried (employed)
     * Salaried users focus on expense tracking and payslip management
     */
    public function isSalaried(): bool
    {
        return $this->income_type === 'salaried';
    }

    /**
     * Check if user should see invoicing features
     * Only self-employed users need sales invoicing
     */
    public function canUseInvoicing(): bool
    {
        return $this->isSelfEmployed();
    }

    /**
     * Check if VAT threshold tracking applies
     * Only relevant for self-employed users (SARS R1m threshold)
     */
    public function shouldTrackVatThreshold(): bool
    {
        return $this->isSelfEmployed();
    }

    /**
     * Get the budget starting point based on income type
     * - Self-employed: Based on invoiced income
     * - Salaried: Based on net monthly income from payslip
     */
    public function getBudgetStartingPoint(): ?float
    {
        if ($this->isSalaried()) {
            return $this->net_monthly_income;
        }

        // For self-employed, return null (calculated from invoices/income sources)
        return null;
    }

    /**
     * Get persona-specific features
     */
    public function getPersonaFeatures(): array
    {
        return [
            'income_type' => $this->income_type,
            'show_invoicing' => $this->canUseInvoicing(),
            'show_clients' => $this->canUseInvoicing(),
            'show_vat_threshold' => $this->shouldTrackVatThreshold(),
            'show_tax_provisions' => $this->isSelfEmployed(),
            'budget_source' => $this->isSalaried() ? 'net_income' : 'invoiced_income',
            'net_monthly_income' => $this->net_monthly_income,
        ];
    }
}
