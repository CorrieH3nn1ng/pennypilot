<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class InvoiceMatchingService
{
    // Tolerance for amount matching (e.g., 0.01 = 1 cent difference allowed)
    private const AMOUNT_TOLERANCE = 0.01;

    // Maximum days after invoice sent to look for payment
    private const MAX_DAYS_AFTER_SENT = 90;

    /**
     * Find potential transaction matches for an invoice
     */
    public function findPotentialMatches(Invoice $invoice): Collection
    {
        // Only match sent or overdue invoices
        if (!in_array($invoice->status, ['sent', 'overdue'])) {
            return collect();
        }

        $userId = $invoice->user_id;
        $invoiceTotal = (float) $invoice->total;

        // Get potential matching transactions
        $query = Transaction::where('user_id', $userId)
            ->where('amount', '>', 0) // Only income (positive amounts)
            ->whereNull('deleted_at');

        // Transaction should be after invoice date
        $query->where('transaction_date', '>=', $invoice->invoice_date);

        // And within reasonable time frame
        $query->where('transaction_date', '<=', Carbon::now()->addDays(7));

        // Get transactions and calculate match scores
        return $query->get()->map(function ($transaction) use ($invoice, $invoiceTotal) {
            $score = $this->calculateMatchScore($transaction, $invoice, $invoiceTotal);
            return [
                'transaction' => $transaction,
                'score' => $score,
                'reasons' => $this->getMatchReasons($transaction, $invoice, $invoiceTotal),
            ];
        })->filter(function ($match) {
            // Only return matches with score > 0
            return $match['score'] > 0;
        })->sortByDesc('score')->values();
    }

    /**
     * Calculate match score (0-100)
     */
    private function calculateMatchScore(Transaction $transaction, Invoice $invoice, float $invoiceTotal): float
    {
        $score = 0;

        // Amount matching (up to 50 points)
        $amountDiff = abs((float) $transaction->amount - $invoiceTotal);
        if ($amountDiff <= self::AMOUNT_TOLERANCE) {
            $score += 50; // Exact match
        } elseif ($amountDiff <= 1.00) {
            $score += 40; // Very close
        } elseif ($amountDiff <= 10.00) {
            $score += 20; // Close
        } elseif ($amountDiff / $invoiceTotal <= 0.01) {
            $score += 30; // Within 1%
        }

        // Reference matching (up to 30 points)
        $invoiceNumber = strtolower($invoice->invoice_number);
        $description = strtolower($transaction->description ?? '');
        $rawDescription = strtolower($transaction->raw_description ?? '');
        $bankRef = strtolower($transaction->bank_reference ?? '');

        if (
            str_contains($description, $invoiceNumber) ||
            str_contains($rawDescription, $invoiceNumber) ||
            str_contains($bankRef, $invoiceNumber)
        ) {
            $score += 30; // Invoice number found in transaction
        } elseif (
            str_contains($description, $invoice->client->name ?? '') ||
            str_contains($rawDescription, $invoice->client->name ?? '')
        ) {
            $score += 15; // Client name found
        }

        // Date proximity (up to 20 points)
        // Transactions closer to due date get higher scores
        $dueDate = Carbon::parse($invoice->due_date);
        $transactionDate = Carbon::parse($transaction->transaction_date);
        $daysDiff = abs($dueDate->diffInDays($transactionDate));

        if ($daysDiff <= 3) {
            $score += 20;
        } elseif ($daysDiff <= 7) {
            $score += 15;
        } elseif ($daysDiff <= 14) {
            $score += 10;
        } elseif ($daysDiff <= 30) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Get human-readable match reasons
     */
    private function getMatchReasons(Transaction $transaction, Invoice $invoice, float $invoiceTotal): array
    {
        $reasons = [];

        $amountDiff = abs((float) $transaction->amount - $invoiceTotal);
        if ($amountDiff <= self::AMOUNT_TOLERANCE) {
            $reasons[] = 'Exact amount match';
        } elseif ($amountDiff <= 1.00) {
            $reasons[] = 'Amount within R1.00';
        } elseif ($amountDiff / $invoiceTotal <= 0.01) {
            $reasons[] = 'Amount within 1%';
        }

        $invoiceNumber = strtolower($invoice->invoice_number);
        $description = strtolower($transaction->description ?? '');
        $rawDescription = strtolower($transaction->raw_description ?? '');
        $bankRef = strtolower($transaction->bank_reference ?? '');

        if (
            str_contains($description, $invoiceNumber) ||
            str_contains($rawDescription, $invoiceNumber) ||
            str_contains($bankRef, $invoiceNumber)
        ) {
            $reasons[] = 'Invoice number in reference';
        }

        if (
            str_contains($description, strtolower($invoice->client->name ?? '')) ||
            str_contains($rawDescription, strtolower($invoice->client->name ?? ''))
        ) {
            $reasons[] = 'Client name in description';
        }

        $dueDate = Carbon::parse($invoice->due_date);
        $transactionDate = Carbon::parse($transaction->transaction_date);
        $daysDiff = $dueDate->diffInDays($transactionDate, false);

        if ($daysDiff >= 0 && $daysDiff <= 7) {
            $reasons[] = 'Paid within a week of due date';
        } elseif ($daysDiff < 0 && abs($daysDiff) <= 7) {
            $reasons[] = 'Paid early (before due date)';
        }

        return $reasons;
    }

    /**
     * Auto-match a single invoice to best transaction
     */
    public function autoMatch(Invoice $invoice, float $minConfidence = 70): ?array
    {
        $matches = $this->findPotentialMatches($invoice);

        if ($matches->isEmpty()) {
            return null;
        }

        $bestMatch = $matches->first();

        if ($bestMatch['score'] >= $minConfidence) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Auto-match all unmatched invoices for a user
     */
    public function autoMatchAllForUser(string $userId, float $minConfidence = 70): array
    {
        $invoices = Invoice::where('user_id', $userId)
            ->whereIn('status', ['sent', 'overdue'])
            ->whereNull('transaction_id')
            ->with('client')
            ->get();

        $matched = [];
        $failed = [];

        foreach ($invoices as $invoice) {
            $result = $this->autoMatch($invoice, $minConfidence);

            if ($result) {
                $this->linkTransactionToInvoice(
                    $invoice,
                    $result['transaction'],
                    'auto',
                    $result['score']
                );
                $matched[] = [
                    'invoice' => $invoice,
                    'transaction' => $result['transaction'],
                    'score' => $result['score'],
                ];
            } else {
                $failed[] = $invoice;
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $failed,
        ];
    }

    /**
     * Match invoices against newly imported transactions
     */
    public function matchAgainstNewTransactions(string $userId, array $transactionIds, float $minConfidence = 80): array
    {
        $transactions = Transaction::whereIn('id', $transactionIds)
            ->where('user_id', $userId)
            ->where('amount', '>', 0) // Only income
            ->get();

        $invoices = Invoice::where('user_id', $userId)
            ->whereIn('status', ['sent', 'overdue'])
            ->whereNull('transaction_id')
            ->with('client')
            ->get();

        $matched = [];

        foreach ($invoices as $invoice) {
            $invoiceTotal = (float) $invoice->total;

            foreach ($transactions as $transaction) {
                $score = $this->calculateMatchScore($transaction, $invoice, $invoiceTotal);

                if ($score >= $minConfidence) {
                    $this->linkTransactionToInvoice($invoice, $transaction, 'auto', $score);
                    $matched[] = [
                        'invoice' => $invoice,
                        'transaction' => $transaction,
                        'score' => $score,
                    ];
                    break; // Move to next invoice
                }
            }
        }

        return $matched;
    }

    /**
     * Manually link a transaction to an invoice
     */
    public function manualMatch(Invoice $invoice, Transaction $transaction): Invoice
    {
        return $this->linkTransactionToInvoice($invoice, $transaction, 'manual', 100);
    }

    /**
     * Unlink a transaction from an invoice
     */
    public function unmatch(Invoice $invoice): Invoice
    {
        $invoice->update([
            'transaction_id' => null,
            'matched_at' => null,
            'match_method' => null,
            'match_confidence' => null,
        ]);

        return $invoice->fresh();
    }

    /**
     * Link a transaction to an invoice and optionally mark as paid
     */
    private function linkTransactionToInvoice(
        Invoice $invoice,
        Transaction $transaction,
        string $method,
        float $confidence
    ): Invoice {
        $invoice->update([
            'transaction_id' => $transaction->id,
            'matched_at' => now(),
            'match_method' => $method,
            'match_confidence' => $confidence,
        ]);

        // Auto-mark as paid if not already
        if (in_array($invoice->status, ['sent', 'overdue'])) {
            $invoice->markAsPaid($transaction->transaction_date, true);
        }

        return $invoice->fresh(['transaction', 'client', 'incomeSource']);
    }

    /**
     * Get match statistics for a user
     */
    public function getMatchStats(string $userId): array
    {
        $total = Invoice::where('user_id', $userId)->count();
        $matched = Invoice::where('user_id', $userId)
            ->whereNotNull('transaction_id')
            ->count();
        $autoMatched = Invoice::where('user_id', $userId)
            ->where('match_method', 'auto')
            ->count();
        $manualMatched = Invoice::where('user_id', $userId)
            ->where('match_method', 'manual')
            ->count();
        $unmatched = Invoice::where('user_id', $userId)
            ->whereIn('status', ['sent', 'overdue'])
            ->whereNull('transaction_id')
            ->count();

        return [
            'total_invoices' => $total,
            'matched' => $matched,
            'auto_matched' => $autoMatched,
            'manual_matched' => $manualMatched,
            'unmatched_pending' => $unmatched,
        ];
    }
}
