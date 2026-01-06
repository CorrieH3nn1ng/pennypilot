<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'client_code',
        'name',
        'email',
        'contact_person',
        'phone',
        'address',
        'notes',
        'billing_period_start',
        'billing_period_end',
        'payment_day',
        'payment_terms',
        'default_rate',
        'rate_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'billing_period_start' => 'integer',
            'billing_period_end' => 'integer',
            'payment_day' => 'integer',
            'payment_terms' => 'integer',
            'default_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    /**
     * Generate the next available client code for a user
     */
    public static function generateNextCode(string $userId): string
    {
        $driver = \DB::connection()->getDriverName();

        // Use database-appropriate casting for ordering
        $orderBy = $driver === 'pgsql'
            ? 'CAST(client_code AS INTEGER) DESC'
            : 'CAST(client_code AS UNSIGNED) DESC';

        $lastClient = static::where('user_id', $userId)
            ->orderByRaw($orderBy)
            ->first();

        if (!$lastClient) {
            return '001';
        }

        $nextNumber = (int) $lastClient->client_code + 1;
        return str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the calculated due date based on client payment settings
     */
    public function calculateDueDate(\DateTime $invoiceDate): \DateTime
    {
        $dueDate = clone $invoiceDate;

        if ($this->payment_terms) {
            // Use payment terms (days from invoice date)
            $dueDate->modify("+{$this->payment_terms} days");
        } elseif ($this->payment_day) {
            // Use specific payment day of month
            $dueDate->setDate(
                (int) $dueDate->format('Y'),
                (int) $dueDate->format('m'),
                min($this->payment_day, (int) $dueDate->format('t'))
            );
            // If payment day has passed, move to next month
            if ($dueDate <= $invoiceDate) {
                $dueDate->modify('+1 month');
                $dueDate->setDate(
                    (int) $dueDate->format('Y'),
                    (int) $dueDate->format('m'),
                    min($this->payment_day, (int) $dueDate->format('t'))
                );
            }
        } else {
            // Default: 30 days from invoice
            $dueDate->modify('+30 days');
        }

        return $dueDate;
    }
}
