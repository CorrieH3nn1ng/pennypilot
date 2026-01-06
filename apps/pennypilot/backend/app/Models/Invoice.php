<?php

namespace App\Models;

use App\Services\FinancialCalculator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Transaction|null $transaction
 */
class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'client_id',
        'invoice_number',
        'sequence_number',
        'status',
        'invoice_type',
        'nature',  // 'business' or 'private' for tax classification
        'invoice_date',
        'due_date',
        'paid_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'title',
        'notes',
        'uploaded_file_path',
        'income_source_id',
        'transaction_id',
        'matched_at',
        'match_method',
        'match_confidence',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'matched_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'match_confidence' => 'decimal:2',
            'sequence_number' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function incomeSource(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Generate invoice number: YYMMDDCCC-NNN
     * YY = year, MM = month, DD = day payable (due date), CCC = client code, NNN = sequence
     */
    public static function generateInvoiceNumber(
        string $userId,
        string $clientCode,
        \DateTimeInterface $dueDate
    ): array {
        $prefix = $dueDate->format('ymd') . $clientCode;

        // Find the next sequence number for this month/client
        $lastInvoice = static::where('user_id', $userId)
            ->where('invoice_number', 'like', $prefix . '-%')
            ->orderBy('sequence_number', 'desc')
            ->first();

        $sequence = $lastInvoice ? $lastInvoice->sequence_number + 1 : 1;
        $sequenceStr = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return [
            'invoice_number' => $prefix . '-' . $sequenceStr,
            'sequence_number' => $sequence,
        ];
    }

    /**
     * Recalculate totals from items using BCMath for precision
     *
     * SARS-Compliant:
     * - Intermediate calculations at 4 decimal places
     * - Final amounts rounded to 2 decimal places
     * - VAT uses Round Half Up
     */
    public function recalculateTotals(): void
    {
        // Get all line items and calculate totals using BCMath
        $items = $this->items()->get()->map(function ($item) {
            return [
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        })->toArray();

        $totals = FinancialCalculator::calculateInvoiceTotals(
            $items,
            FinancialCalculator::toScale($this->tax_rate ?? 0)
        );

        $this->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
        ]);
    }

    /**
     * Validate invoice integrity
     * Ensures: Sum(Line_Item_Net) + VAT == Total
     */
    public function validateIntegrity(): bool
    {
        return FinancialCalculator::validateInvoiceIntegrity(
            FinancialCalculator::toScale($this->subtotal),
            FinancialCalculator::toScale($this->tax_amount),
            FinancialCalculator::toScale($this->total)
        );
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Mark invoice as paid and optionally create income entry + transaction
     *
     * Creates both:
     * - IncomeSource for tracking expected income
     * - Transaction for Dashboard Total Income calculation
     */
    public function markAsPaid(\DateTimeInterface $paidDate, bool $createIncome = true): void
    {
        $this->update([
            'status' => 'paid',
            'paid_date' => $paidDate,
        ]);

        if ($createIncome && !$this->income_source_id) {
            // Create income source for income tracking
            $income = IncomeSource::create([
                'user_id' => $this->user_id,
                'client_id' => $this->client_id,
                'name' => "Invoice {$this->invoice_number}",
                'type' => 'freelance',
                'gross_amount' => $this->total,
                'net_amount' => $this->total, // Will be adjusted by tax provisions
                'tax_amount' => 0,
                'frequency' => 'irregular',
                'is_active' => true,
                'notes' => "Payment for invoice {$this->invoice_number} from {$this->client->name}",
            ]);

            $this->update(['income_source_id' => $income->id]);

            // Create transaction for Dashboard Total Income (if not already matched to a transaction)
            if (!$this->transaction_id) {
                // Find the "Invoice Income" category or use null
                $incomeCategory = Category::where('user_id', $this->user_id)
                    ->where('is_income', true)
                    ->where('name', 'like', '%invoice%')
                    ->first();

                // Fallback to any income category
                if (!$incomeCategory) {
                    $incomeCategory = Category::where('user_id', $this->user_id)
                        ->where('is_income', true)
                        ->first();
                }

                // Fallback to system income category
                if (!$incomeCategory) {
                    $incomeCategory = Category::whereNull('user_id')
                        ->where('is_income', true)
                        ->first();
                }

                $transaction = Transaction::create([
                    'user_id' => $this->user_id,
                    'category_id' => $incomeCategory?->id,
                    'transaction_date' => $paidDate,
                    'description' => "Invoice Payment: {$this->invoice_number} - {$this->client->name}",
                    'amount' => $this->total, // Positive for income
                    'balance_after' => null,
                    'bank_reference' => "INV-{$this->invoice_number}",
                    'import_source' => 'api',
                    'raw_description' => "Invoice {$this->invoice_number} payment from {$this->client->name}",
                    'is_categorized' => $incomeCategory !== null,
                    'is_transfer' => false,
                    'categorized_by' => $incomeCategory ? 'system' : null,
                    'confidence_score' => 100,
                    'notes' => "Auto-created from invoice {$this->invoice_number}",
                    'tags' => json_encode(['invoice', 'income']),
                    'local_id' => (string) \Illuminate\Support\Str::uuid(),
                    'sync_status' => 'synced',
                ]);

                // Link invoice to the transaction
                $this->update(['transaction_id' => $transaction->id]);
            }
        }
    }
}
