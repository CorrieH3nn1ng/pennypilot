<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'employment_type',
        'tax_number',
        'vat_registered',
        'vat_number',
        'estimated_annual_income',
        'estimated_tax_rate',
        'provisional_tax_enabled',
        'tax_year_start_month',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'estimated_annual_income' => 'decimal:2',
            'estimated_tax_rate' => 'decimal:2',
            'provisional_tax_enabled' => 'boolean',
            'tax_year_start_month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user needs to pay provisional tax
     */
    public function requiresProvisionalTax(): bool
    {
        return in_array($this->employment_type, ['self_employed', 'mixed'])
            && $this->provisional_tax_enabled;
    }

    /**
     * Get current SA tax year
     * SA tax year runs March to February
     */
    public static function getCurrentTaxYear(): int
    {
        $month = (int) date('n');
        $year = (int) date('Y');

        // If we're in Jan-Feb, we're still in the previous tax year
        return $month >= 3 ? $year + 1 : $year;
    }
}
