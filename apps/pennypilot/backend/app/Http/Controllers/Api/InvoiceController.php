<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Services\FinancialCalculator;
use App\Services\InvoiceMatchingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    /**
     * List all invoices for the user
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::where('user_id', Auth::id())
            ->with(['client:id,name,client_code', 'items'])
            ->orderBy('invoice_date', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by client
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('invoice_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('invoice_date', '<=', $request->to_date);
        }

        $invoices = $query->get();

        // Calculate summary
        $summary = [
            'total_count' => $invoices->count(),
            'draft_count' => $invoices->where('status', 'draft')->count(),
            'sent_count' => $invoices->where('status', 'sent')->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
            'overdue_count' => $invoices->where('status', 'overdue')->count(),
            'total_outstanding' => $invoices->whereIn('status', ['sent', 'overdue'])->sum('total'),
            'total_paid' => $invoices->where('status', 'paid')->sum('total'),
        ];

        // Calculate Tax Year Summary (SA: March to February)
        // Current tax year: March 2025 to Feb 2026
        $now = now();
        $taxYearStart = $now->month >= 3
            ? $now->copy()->setMonth(3)->setDay(1)->startOfDay()
            : $now->copy()->subYear()->setMonth(3)->setDay(1)->startOfDay();
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay()->endOfDay();

        $taxYearInvoices = $invoices->filter(function ($inv) use ($taxYearStart, $taxYearEnd) {
            $paidDate = $inv->paid_date ? \Carbon\Carbon::parse($inv->paid_date) : null;
            return $inv->status === 'paid'
                && $paidDate
                && $paidDate->gte($taxYearStart)
                && $paidDate->lte($taxYearEnd);
        });

        // Business invoices only for tax calculation
        $businessPaidTotal = $taxYearInvoices
            ->where('nature', 'business')
            ->sum('total');

        // Calculate tax set-aside: 39.1% Income Tax ONLY (not VAT-registered)
        // TODO: Subtract business expenses when that feature is implemented
        $incomeTaxAmount = bcmul((string) $businessPaidTotal, '0.391', 2);

        $summary['tax_year'] = [
            'label' => $taxYearStart->format('Y') . '/' . $taxYearEnd->format('y'),
            'start' => $taxYearStart->toDateString(),
            'end' => $taxYearEnd->toDateString(),
            'business_paid_total' => (float) $businessPaidTotal,
            'private_paid_total' => (float) $taxYearInvoices->where('nature', 'private')->sum('total'),
            'income_tax_rate' => 39.1,
            'income_tax_amount' => (float) $incomeTaxAmount,
            'total_tax_set_aside' => (float) $incomeTaxAmount,
            'net_after_tax' => (float) bcsub((string) $businessPaidTotal, $incomeTaxAmount, 2),
        ];

        return response()->json([
            'data' => $invoices,
            'summary' => $summary,
        ]);
    }

    /**
     * Create a new invoice
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid|exists:clients,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'numeric|min:0|max:100',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Verify client belongs to user
        $client = Client::where('user_id', Auth::id())
            ->findOrFail($validated['client_id']);

        // Generate invoice number
        $dueDate = Carbon::parse($validated['due_date']);
        $invoiceData = Invoice::generateInvoiceNumber(
            Auth::id(),
            $client->client_code,
            $dueDate
        );

        DB::beginTransaction();
        try {
            // Create invoice
            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'client_id' => $client->id,
                'invoice_number' => $invoiceData['invoice_number'],
                'sequence_number' => $invoiceData['sequence_number'],
                'status' => 'draft',
                'invoice_type' => 'regular',
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'title' => $validated['title'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create items using BCMath for precision
            foreach ($validated['items'] as $index => $itemData) {
                $amount = FinancialCalculator::round(
                    FinancialCalculator::calculateLineItemAmount(
                        FinancialCalculator::toScale($itemData['quantity']),
                        FinancialCalculator::toScale($itemData['unit_price'])
                    )
                );

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? null,
                    'unit_price' => $itemData['unit_price'],
                    'amount' => $amount,
                    'sort_order' => $index,
                ]);
            }

            // Recalculate totals
            $invoice->recalculateTotals();

            DB::commit();

            return response()->json([
                'data' => $invoice->fresh(['client', 'items']),
                'message' => 'Invoice created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get a single invoice
     */
    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->with(['client', 'items', 'incomeSource', 'transaction'])
            ->findOrFail($id);

        return response()->json(['data' => $invoice]);
    }

    /**
     * Update an invoice
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        // Can only update draft invoices (or historical)
        if (!in_array($invoice->status, ['draft']) && $invoice->invoice_type !== 'historical') {
            return response()->json([
                'message' => 'Can only edit draft invoices',
            ], 422);
        }

        $validated = $request->validate([
            'invoice_date' => 'sometimes|date',
            'due_date' => 'sometimes|date',
            'tax_rate' => 'numeric|min:0|max:100',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|uuid',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Update invoice
            $invoice->update([
                'invoice_date' => $validated['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $validated['due_date'] ?? $invoice->due_date,
                'tax_rate' => $validated['tax_rate'] ?? $invoice->tax_rate,
                'title' => $validated['title'] ?? $invoice->title,
                'notes' => $validated['notes'] ?? $invoice->notes,
            ]);

            // Update items if provided
            if (isset($validated['items'])) {
                // Get existing item IDs
                $existingIds = $invoice->items()->pluck('id')->toArray();
                $updatedIds = [];

                foreach ($validated['items'] as $index => $itemData) {
                    // Calculate amount using BCMath for precision
                    $amount = FinancialCalculator::round(
                        FinancialCalculator::calculateLineItemAmount(
                            FinancialCalculator::toScale($itemData['quantity']),
                            FinancialCalculator::toScale($itemData['unit_price'])
                        )
                    );

                    if (isset($itemData['id']) && in_array($itemData['id'], $existingIds)) {
                        // Update existing item
                        InvoiceItem::where('id', $itemData['id'])->update([
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'] ?? null,
                            'unit_price' => $itemData['unit_price'],
                            'amount' => $amount,
                            'sort_order' => $index,
                        ]);
                        $updatedIds[] = $itemData['id'];
                    } else {
                        // Create new item
                        $item = InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'] ?? null,
                            'unit_price' => $itemData['unit_price'],
                            'amount' => $amount,
                            'sort_order' => $index,
                        ]);
                        $updatedIds[] = $item->id;
                    }
                }

                // Delete removed items
                $toDelete = array_diff($existingIds, $updatedIds);
                InvoiceItem::whereIn('id', $toDelete)->delete();

                // Recalculate totals
                $invoice->recalculateTotals();
            }

            DB::commit();

            return response()->json([
                'data' => $invoice->fresh(['client', 'items']),
                'message' => 'Invoice updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete an invoice
     */
    public function destroy(string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        // Delete associated items
        $invoice->items()->delete();

        // Delete uploaded file if exists
        if ($invoice->uploaded_file_path) {
            Storage::disk('private')->delete($invoice->uploaded_file_path);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Bulk delete invoices before a specific date
     */
    public function bulkDeleteBefore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'before_date' => 'required|date',
        ]);

        $beforeDate = Carbon::parse($validated['before_date']);

        // Get invoices to delete
        $invoices = Invoice::where('user_id', Auth::id())
            ->where('invoice_date', '<', $beforeDate)
            ->get();

        $count = $invoices->count();

        if ($count === 0) {
            return response()->json([
                'deleted_count' => 0,
                'message' => 'No invoices found before ' . $beforeDate->format('Y-m-d'),
            ]);
        }

        DB::beginTransaction();
        try {
            foreach ($invoices as $invoice) {
                // Delete associated items
                $invoice->items()->delete();

                // Delete uploaded file if exists
                if ($invoice->uploaded_file_path) {
                    Storage::disk('private')->delete($invoice->uploaded_file_path);
                }

                $invoice->delete();
            }

            DB::commit();

            return response()->json([
                'deleted_count' => $count,
                'message' => "Deleted {$count} invoices dated before " . $beforeDate->format('Y-m-d'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark invoice as sent
     */
    public function markSent(string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Can only send draft invoices',
            ], 422);
        }

        $invoice->update(['status' => 'sent']);

        return response()->json([
            'data' => $invoice->fresh(['client', 'items']),
            'message' => 'Invoice marked as sent',
        ]);
    }

    /**
     * Mark invoice as paid
     */
    public function markPaid(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'paid_date' => 'required|date',
            'create_income' => 'boolean',
        ]);

        $invoice = Invoice::where('user_id', Auth::id())
            ->with('client')
            ->findOrFail($id);

        if (!in_array($invoice->status, ['sent', 'overdue'])) {
            return response()->json([
                'message' => 'Can only mark sent or overdue invoices as paid',
            ], 422);
        }

        $invoice->markAsPaid(
            Carbon::parse($validated['paid_date']),
            $validated['create_income'] ?? true
        );

        return response()->json([
            'data' => $invoice->fresh(['client', 'items', 'incomeSource']),
            'message' => 'Invoice marked as paid',
        ]);
    }

    /**
     * Cancel an invoice
     */
    public function cancel(string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Cannot cancel a paid invoice',
            ], 422);
        }

        $invoice->update(['status' => 'cancelled']);

        return response()->json([
            'data' => $invoice->fresh(['client', 'items']),
            'message' => 'Invoice cancelled',
        ]);
    }

    /**
     * Upload a historical invoice
     */
    public function uploadHistorical(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid|exists:clients,id',
            'invoice_number' => 'required|string|max:20|unique:invoices,invoice_number',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'paid_date' => 'nullable|date',
            'total' => 'required|numeric|min:0',
            'nature' => 'nullable|in:business,private',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        // Verify client belongs to user
        $client = Client::where('user_id', Auth::id())
            ->findOrFail($validated['client_id']);

        // Store the file
        $path = $request->file('file')->store('invoices/' . Auth::id(), 'private');

        // Create the invoice
        $paidDate = $validated['paid_date'] ?? null;
        $invoice = Invoice::create([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
            'invoice_number' => $validated['invoice_number'],
            'sequence_number' => 0, // Historical invoices don't use sequence
            'status' => $paidDate ? 'paid' : 'sent',
            'invoice_type' => 'historical',
            'nature' => $validated['nature'] ?? 'business', // Default to business for invoices
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'paid_date' => $paidDate,
            'subtotal' => $validated['total'],
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => $validated['total'],
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'uploaded_file_path' => $path,
        ]);

        return response()->json([
            'data' => $invoice->fresh(['client']),
            'message' => 'Historical invoice uploaded successfully',
        ], 201);
    }

    /**
     * Download invoice PDF (for historical uploads)
     */
    public function download(string $id)
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        if (!$invoice->uploaded_file_path) {
            return response()->json([
                'message' => 'No file attached to this invoice',
            ], 404);
        }

        return Storage::disk('private')->download(
            $invoice->uploaded_file_path,
            "invoice-{$invoice->invoice_number}.pdf"
        );
    }

    /**
     * Get next invoice number preview
     */
    public function previewNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid|exists:clients,id',
            'due_date' => 'required|date',
        ]);

        $client = Client::where('user_id', Auth::id())
            ->findOrFail($validated['client_id']);

        $dueDate = Carbon::parse($validated['due_date']);
        $invoiceData = Invoice::generateInvoiceNumber(
            Auth::id(),
            $client->client_code,
            $dueDate
        );

        return response()->json([
            'invoice_number' => $invoiceData['invoice_number'],
            'sequence_number' => $invoiceData['sequence_number'],
        ]);
    }

    /**
     * Update overdue status for all invoices
     */
    public function updateOverdueStatus(): JsonResponse
    {
        $updated = Invoice::where('user_id', Auth::id())
            ->where('status', 'sent')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        return response()->json([
            'updated_count' => $updated,
            'message' => "Updated {$updated} invoices to overdue status",
        ]);
    }

    /**
     * Find potential transaction matches for an invoice
     */
    public function findMatches(string $id, InvoiceMatchingService $matchingService): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->with('client')
            ->findOrFail($id);

        $matches = $matchingService->findPotentialMatches($invoice);

        return response()->json([
            'data' => $matches->map(fn($match) => [
                'transaction' => $match['transaction'],
                'score' => $match['score'],
                'reasons' => $match['reasons'],
            ]),
            'invoice' => $invoice,
        ]);
    }

    /**
     * Manually match an invoice to a transaction
     */
    public function match(Request $request, string $id, InvoiceMatchingService $matchingService): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|uuid|exists:transactions,id',
        ]);

        $invoice = Invoice::where('user_id', Auth::id())
            ->with('client')
            ->findOrFail($id);

        // Verify transaction belongs to user
        $transaction = Transaction::where('user_id', Auth::id())
            ->findOrFail($validated['transaction_id']);

        // Can only match sent or overdue invoices
        if (!in_array($invoice->status, ['sent', 'overdue'])) {
            return response()->json([
                'message' => 'Can only match sent or overdue invoices',
            ], 422);
        }

        $invoice = $matchingService->manualMatch($invoice, $transaction);

        return response()->json([
            'data' => $invoice,
            'message' => 'Invoice matched to transaction and marked as paid',
        ]);
    }

    /**
     * Remove match from an invoice
     */
    public function unmatch(string $id, InvoiceMatchingService $matchingService): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())
            ->findOrFail($id);

        if (!$invoice->transaction_id) {
            return response()->json([
                'message' => 'Invoice is not matched to any transaction',
            ], 422);
        }

        $invoice = $matchingService->unmatch($invoice);

        return response()->json([
            'data' => $invoice,
            'message' => 'Invoice unmatched from transaction',
        ]);
    }

    /**
     * Unmatch ALL invoices for the user (for data cleanup before re-import)
     */
    public function unmatchAll(): JsonResponse
    {
        $updated = Invoice::where('user_id', Auth::id())
            ->whereNotNull('transaction_id')
            ->update([
                'transaction_id' => null,
                'matched_at' => null,
                'match_method' => null,
                'match_confidence' => null,
            ]);

        return response()->json([
            'unmatched_count' => $updated,
            'message' => "Unmatched {$updated} invoices from transactions",
        ]);
    }

    /**
     * Auto-match all unmatched invoices for the user
     */
    public function autoMatchAll(Request $request, InvoiceMatchingService $matchingService): JsonResponse
    {
        $minConfidence = $request->input('min_confidence', 70);

        $result = $matchingService->autoMatchAllForUser(Auth::id(), $minConfidence);

        return response()->json([
            'matched' => collect($result['matched'])->map(fn($m) => [
                'invoice_id' => $m['invoice']->id,
                'invoice_number' => $m['invoice']->invoice_number,
                'transaction_id' => $m['transaction']->id,
                'score' => $m['score'],
            ]),
            'unmatched_count' => count($result['unmatched']),
            'message' => sprintf(
                'Matched %d invoices, %d remaining unmatched',
                count($result['matched']),
                count($result['unmatched'])
            ),
        ]);
    }

    /**
     * Get match statistics for the user
     */
    public function matchStats(InvoiceMatchingService $matchingService): JsonResponse
    {
        $stats = $matchingService->getMatchStats(Auth::id());

        return response()->json(['data' => $stats]);
    }
}
