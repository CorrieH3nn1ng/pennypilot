<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StagedInvoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Invoice Ingestion Controller
 *
 * Handles incoming Gemini-extracted invoice data from n8n pipeline.
 * Auto-links to existing clients, stages unmatched for manual review.
 *
 * SARS 2025/26 Tax Integration:
 * - Calculates provisional tax buffer on 'Sent' invoices
 * - Uses 18%-45% bracket rate minus pro-rated R17,235 rebate
 */
class InvoiceIngestionController extends Controller
{
    /**
     * SARS 2025/26 Tax Configuration
     *
     * Primary Rebate: R17,235
     * Tax-Free Threshold: R95,750 (R17,235 / 0.18)
     *
     * The first R95,750 of annual income is effectively tax-free
     * because the tax on that amount (R95,750 × 18% = R17,235) equals the rebate.
     */
    private const PRIMARY_REBATE_2025_26 = 17235;
    private const TAX_FREE_THRESHOLD = 95750; // R17,235 / 0.18
    private const TAX_BRACKETS_2025_26 = [
        ['min' => 1, 'max' => 237100, 'rate' => 0.18, 'base' => 0],
        ['min' => 237101, 'max' => 370500, 'rate' => 0.26, 'base' => 42678],
        ['min' => 370501, 'max' => 512800, 'rate' => 0.31, 'base' => 77362],
        ['min' => 512801, 'max' => 673000, 'rate' => 0.36, 'base' => 121475],
        ['min' => 673001, 'max' => 857900, 'rate' => 0.39, 'base' => 179147],
        ['min' => 857901, 'max' => 1817000, 'rate' => 0.41, 'base' => 251258],
        ['min' => 1817001, 'max' => PHP_INT_MAX, 'rate' => 0.45, 'base' => 644489],
    ];

    /**
     * Receive and process Gemini-extracted invoice data from n8n
     *
     * Expected JSON format:
     * {
     *   "invoice_number": "INV-001",
     *   "invoice_date": "2025-12-01" or "01/12/2025" or "1 Dec 2025",
     *   "due_date": "2025-12-31",
     *   "client_name": "Acme Corp",
     *   "client_email": "billing@acme.com",
     *   "amount": 15000.00 or "R 15 000,00" or "15000",
     *   "description": "Consulting services December 2025",
     *   "source": "email" | "upload" | "scan",
     *   "items": [
     *     { "description": "...", "quantity": 1, "unit_price": 15000 }
     *   ]
     * }
     */
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:50',
            'invoice_date' => 'required|string',
            'due_date' => 'nullable|string',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'amount' => 'required',
            'description' => 'nullable|string',
            'source' => 'nullable|string|in:email,upload,scan,api',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'file_path' => 'nullable|string',
        ]);

        // Standardize date to YYYY-MM-DD
        $invoiceDate = $this->standardizeDate($validated['invoice_date']);
        $dueDate = $validated['due_date']
            ? $this->standardizeDate($validated['due_date'])
            : Carbon::parse($invoiceDate)->addDays(30)->format('Y-m-d');

        // Standardize amount to decimal
        $amount = $this->standardizeAmount($validated['amount']);

        // Try to auto-link to existing client
        $client = $this->findMatchingClient(
            $validated['client_name'],
            $validated['client_email'] ?? null
        );

        if ($client) {
            // Direct import - client found
            return $this->createInvoice($client, [
                'invoice_number' => $validated['invoice_number'],
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'description' => $validated['description'] ?? null,
                'source' => $validated['source'] ?? 'api',
                'items' => $validated['items'] ?? null,
                'file_path' => $validated['file_path'] ?? null,
            ]);
        }

        // No match - stage for manual review
        return $this->stageForReview([
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'client_name' => $validated['client_name'],
            'client_email' => $validated['client_email'] ?? null,
            'amount' => $amount,
            'description' => $validated['description'] ?? null,
            'source' => $validated['source'] ?? 'api',
            'items' => $validated['items'] ?? null,
            'file_path' => $validated['file_path'] ?? null,
            'raw_data' => $request->all(),
        ]);
    }

    /**
     * Get all staged invoices awaiting review
     */
    public function staged(): JsonResponse
    {
        $staged = DB::table('staged_invoices')
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $staged,
            'count' => $staged->count(),
        ]);
    }

    /**
     * Approve a staged invoice with client assignment
     */
    public function approveStaged(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid|exists:clients,id',
            'create_client' => 'boolean',
            'client_name' => 'required_if:create_client,true|string|max:255',
        ]);

        $staged = DB::table('staged_invoices')
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$staged) {
            return response()->json(['message' => 'Staged invoice not found'], 404);
        }

        // Get or create client
        if ($validated['create_client'] ?? false) {
            $client = Client::create([
                'user_id' => Auth::id(),
                'client_code' => Client::generateNextCode(Auth::id()),
                'name' => $validated['client_name'],
                'is_active' => true,
            ]);
        } else {
            $client = Client::where('user_id', Auth::id())
                ->findOrFail($validated['client_id']);
        }

        // Create the invoice
        $data = json_decode($staged->raw_data, true);
        $result = $this->createInvoice($client, [
            'invoice_number' => $staged->invoice_number,
            'invoice_date' => $staged->invoice_date,
            'due_date' => $staged->due_date,
            'amount' => $staged->amount,
            'description' => $staged->description,
            'source' => $staged->source,
            'items' => $data['items'] ?? null,
            'file_path' => $staged->file_path,
        ]);

        // Mark staged as processed
        DB::table('staged_invoices')
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'processed_at' => now(),
            ]);

        return $result;
    }

    /**
     * Reject a staged invoice
     */
    public function rejectStaged(string $id): JsonResponse
    {
        $updated = DB::table('staged_invoices')
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'processed_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['message' => 'Staged invoice not found'], 404);
        }

        return response()->json(['message' => 'Staged invoice rejected']);
    }

    /**
     * Calculate tax set-aside for an invoice amount
     *
     * SARS 2025/26 Logic:
     * - First R95,750 annual income is TAX-FREE (rebate covers the 18% tax)
     * - Above R95,750: Apply 18% bracket rate
     * - Pro-rate monthly: R95,750/12 = R7,979.17 tax-free per month
     */
    public function calculateTaxSetAside(float $amount): array
    {
        // Annualize the amount (assuming monthly)
        $annualizedAmount = $amount * 12;

        // Find applicable bracket
        $bracket = self::TAX_BRACKETS_2025_26[0];
        foreach (self::TAX_BRACKETS_2025_26 as $b) {
            if ($annualizedAmount >= $b['min'] && $annualizedAmount <= $b['max']) {
                $bracket = $b;
                break;
            }
        }

        // Calculate annual tax using bracket formula
        $taxableAboveMin = max(0, $annualizedAmount - $bracket['min'] + 1);
        $annualTaxBeforeRebate = $bracket['base'] + ($taxableAboveMin * $bracket['rate']);

        // Apply rebate - this is where R95,750 becomes tax-free
        $annualTaxAfterRebate = max(0, $annualTaxBeforeRebate - self::PRIMARY_REBATE_2025_26);

        // Monthly tax provision
        $monthlyTax = $annualTaxAfterRebate / 12;

        // For this specific invoice: pro-rate the tax-free threshold
        $monthlyTaxFreeThreshold = self::TAX_FREE_THRESHOLD / 12; // R7,979.17
        $proRatedRebate = self::PRIMARY_REBATE_2025_26 / 12;

        // Taxable portion of this invoice (above monthly tax-free threshold)
        $taxableAmount = max(0, $amount - $monthlyTaxFreeThreshold);
        $invoiceTaxSetAside = $taxableAmount * $bracket['rate'];

        // Simplified approach: use pro-rated rebate
        $taxBeforeRebate = $amount * $bracket['rate'];
        $taxSetAside = max(0, $taxBeforeRebate - $proRatedRebate);

        return [
            'amount' => round($amount, 2),
            'bracket_rate' => $bracket['rate'] * 100,
            'tax_before_rebate' => round($taxBeforeRebate, 2),
            'rebate_applied' => round($proRatedRebate, 2),
            'tax_set_aside' => round($taxSetAside, 2),
            'suggested_savings' => round($taxSetAside, 2),
            'effective_rate' => $amount > 0 ? round(($taxSetAside / $amount) * 100, 2) : 0,
            'tax_free_threshold' => self::TAX_FREE_THRESHOLD,
            'monthly_tax_free' => round($monthlyTaxFreeThreshold, 2),
        ];
    }

    /**
     * Get tax set-aside calculation for an invoice
     */
    public function getTaxSetAside(string $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', Auth::id())->findOrFail($id);

        $taxInfo = $this->calculateTaxSetAside($invoice->total);

        return response()->json([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => $invoice->total,
            'tax_info' => $taxInfo,
            'sars_year' => '2025/26',
        ]);
    }

    // =========================================
    // Private Helper Methods
    // =========================================

    /**
     * Standardize various date formats to YYYY-MM-DD
     */
    private function standardizeDate(string $dateStr): string
    {
        // Already in YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        // Try to parse with Carbon
        try {
            // Handle DD/MM/YYYY
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
                return Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
            }

            // Handle DD-MM-YYYY
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateStr, $matches)) {
                return Carbon::createFromFormat('d-m-Y', $dateStr)->format('Y-m-d');
            }

            // Handle "1 Dec 2025" or "1 December 2025"
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateStr}", ['error' => $e->getMessage()]);
            return now()->format('Y-m-d');
        }
    }

    /**
     * Standardize various amount formats to decimal
     * Handles: "R 15 000,00", "15000", "15,000.00", etc.
     */
    private function standardizeAmount($amount): float
    {
        if (is_numeric($amount)) {
            return (float) $amount;
        }

        // Remove currency symbol and whitespace
        $cleaned = preg_replace('/[R\s]/', '', (string) $amount);

        // Handle SA format: "15 000,00" -> "15000.00"
        if (preg_match('/^[\d\s]+,\d{2}$/', $cleaned)) {
            $cleaned = str_replace(' ', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }

        // Handle US format with commas: "15,000.00" -> "15000.00"
        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d{2})?$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Find matching client by name or email
     * Improved algorithm: stricter matching to avoid false positives
     */
    private function findMatchingClient(string $name, ?string $email): ?Client
    {
        $query = Client::where('user_id', Auth::id());
        $nameLower = strtolower(trim($name));

        // Strategy 1: EXACT email match only (most reliable)
        if ($email) {
            $client = (clone $query)->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if ($client) return $client;
        }

        // Strategy 2: Exact name match
        $client = (clone $query)->whereRaw('LOWER(name) = ?', [$nameLower])->first();
        if ($client) return $client;

        // Strategy 3: Contains match - only if name is at least 5 characters
        if (strlen($nameLower) >= 5) {
            // Check if extracted name contains a client name
            $clients = $query->get();
            foreach ($clients as $c) {
                $clientNameLower = strtolower($c->name);
                // Require at least 5 chars and full containment
                if (strlen($clientNameLower) >= 5 &&
                    (str_contains($nameLower, $clientNameLower) || str_contains($clientNameLower, $nameLower))) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * Create invoice from ingested data
     */
    private function createInvoice(Client $client, array $data): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Check for duplicate invoice number
            $existing = Invoice::where('user_id', Auth::id())
                ->where('invoice_number', $data['invoice_number'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Invoice with this number already exists',
                    'existing_invoice' => $existing->id,
                ], 409);
            }

            // Create invoice
            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'client_id' => $client->id,
                'invoice_number' => $data['invoice_number'],
                'sequence_number' => 0, // Imported invoices don't use sequence
                'status' => 'sent', // Imported invoices are assumed sent
                'invoice_type' => 'historical',
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $data['amount'],
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => $data['amount'],
                'title' => $data['description'],
                'uploaded_file_path' => $data['file_path'] ?? null,
            ]);

            // Create items if provided
            if (!empty($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? null,
                        'unit_price' => $item['unit_price'],
                        'amount' => $item['quantity'] * $item['unit_price'],
                        'sort_order' => $index,
                    ]);
                }
                $invoice->recalculateTotals();
            }

            // Calculate tax set-aside
            $taxInfo = $this->calculateTaxSetAside($invoice->total);

            DB::commit();

            return response()->json([
                'data' => $invoice->fresh(['client', 'items']),
                'tax_set_aside' => $taxInfo,
                'client_matched' => true,
                'message' => 'Invoice imported successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice ingestion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Stage invoice for manual review
     */
    private function stageForReview(array $data): JsonResponse
    {
        $id = DB::table('staged_invoices')->insertGetId([
            'id' => Str::uuid(),
            'user_id' => Auth::id(),
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'source' => $data['source'],
            'file_path' => $data['file_path'],
            'raw_data' => json_encode($data['raw_data']),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'staged_id' => $id,
            'client_matched' => false,
            'suggested_clients' => $this->getSuggestedClients($data['client_name']),
            'message' => 'Invoice staged for manual review - client not found',
        ], 202);
    }

    /**
     * Get suggested clients for manual matching
     */
    private function getSuggestedClients(string $name): array
    {
        return Client::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'client_code', 'email'])
            ->toArray();
    }
}
