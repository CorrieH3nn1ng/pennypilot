<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxProvision extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'tax_year',
        'year',
        'month',
        'income_amount',
        'provision_amount',
        'provision_rate',
        'is_paid',
        'payment_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'income_amount' => 'decimal:2',
            'provision_amount' => 'decimal:2',
            'provision_rate' => 'decimal:2',
            'is_paid' => 'boolean',
            'payment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get period display name
     */
    public function getPeriodNameAttribute(): string
    {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar',
            4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        return $months[$this->month] . ' ' . $this->year;
    }
}
