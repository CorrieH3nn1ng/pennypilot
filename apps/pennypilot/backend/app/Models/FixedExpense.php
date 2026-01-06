<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedExpense extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'amount',
        'due_day',
        'bucket',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Check if due soon (within 5 days)
     */
    public function isDueSoon(): bool
    {
        if (!$this->due_day) {
            return false;
        }

        $today = (int) date('d');
        $daysUntilDue = $this->due_day - $today;

        // Handle month wrap
        if ($daysUntilDue < 0) {
            $daysUntilDue += (int) date('t'); // days in current month
        }

        return $daysUntilDue <= 5;
    }
}
