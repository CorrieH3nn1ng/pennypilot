<?php

namespace App\Models;

use App\Services\FinancialCalculator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calculate amount from quantity and unit price using BCMath
     *
     * Returns a rounded value (2 decimal places) for storage
     */
    public function calculateAmount(): string
    {
        $amount = FinancialCalculator::calculateLineItemAmount(
            FinancialCalculator::toScale($this->quantity),
            FinancialCalculator::toScale($this->unit_price)
        );

        return FinancialCalculator::round($amount);
    }

    /**
     * Boot method to auto-calculate amount
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->amount = $item->calculateAmount();
        });

        static::saved(function ($item) {
            // Recalculate invoice totals when item is saved
            $item->invoice->recalculateTotals();
        });

        static::deleted(function ($item) {
            // Recalculate invoice totals when item is deleted
            $item->invoice->recalculateTotals();
        });
    }
}
