<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Transaction;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiExtractionController extends Controller
{
    /**
     * Extract invoice data from a PDF using Gemini Vision AI
     */
    public function extractInvoice(Request $request, GeminiService $geminiService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if (!$geminiService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'AI extraction is not configured. Please add your GEMINI_API_KEY to .env',
            ], 503);
        }

        try {
            $file = $request->file('file');
            $mimeType = $file->getMimeType();
            $tempPath = $file->getRealPath();

            if (str_contains($mimeType, 'pdf')) {
                $result = $geminiService->extractInvoiceFromPdf($tempPath);
            } else {
                $result = $geminiService->extractInvoiceFromImage($tempPath);
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to extract invoice data',
                    'raw_response' => $result['raw_response'] ?? null,
                ], 422);
            }

            $extractedData = $result['data'];

            // Log extracted data for debugging
            Log::info('AI Extraction result', [
                'client_name' => $extractedData['client_name'] ?? 'NOT FOUND',
                'client_email' => $extractedData['client_email'] ?? 'NOT FOUND',
                'invoice_number' => $extractedData['invoice_number'] ?? 'NOT FOUND',
                'total' => $extractedData['total'] ?? 'NOT FOUND',
            ]);

            // Try to match client
            $matchedClient = $this->findMatchingClient($extractedData);

            // Apply client defaults if matched
            if ($matchedClient) {
                Log::info('Client matched', ['client_id' => $matchedClient->id, 'name' => $matchedClient->name]);
                $extractedData = $this->applyClientDefaults($extractedData, $matchedClient);
            } else {
                Log::info('No client match found');
            }

            // Find potential transaction matches
            $potentialMatches = $this->findPotentialTransactions($extractedData);

            return response()->json([
                'success' => true,
                'data' => $extractedData,
                'matched_client' => $matchedClient ? [
                    'id' => $matchedClient->id,
                    'name' => $matchedClient->name,
                    'client_code' => $matchedClient->client_code,
                    'billing_period_start' => $matchedClient->billing_period_start,
                    'billing_period_end' => $matchedClient->billing_period_end,
                    'payment_day' => $matchedClient->payment_day,
                    'payment_terms' => $matchedClient->payment_terms,
                    'default_rate' => $matchedClient->default_rate,
                    'rate_type' => $matchedClient->rate_type,
                ] : null,
                'potential_transactions' => $potentialMatches,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to extract invoice data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find matching client using multiple strategies
     * Improved algorithm: prioritizes exact matches, requires higher confidence for fuzzy matching
     */
    private function findMatchingClient(array $extractedData): ?Client
    {
        $userId = Auth::id();
        $clients = Client::where('user_id', $userId)->where('is_active', true)->get();

        if ($clients->isEmpty()) {
            return null;
        }

        $clientName = strtolower(trim($extractedData['client_name'] ?? ''));
        $clientEmail = strtolower(trim($extractedData['client_email'] ?? ''));

        // Strategy 1: EXACT email match only (no partial matching)
        if ($clientEmail) {
            $match = $clients->first(function ($client) use ($clientEmail) {
                return strtolower($client->email ?? '') === $clientEmail;
            });
            if ($match) return $match;
        }

        // Strategy 2: Exact name match (case insensitive)
        if ($clientName) {
            $match = $clients->first(function ($client) use ($clientName) {
                return strtolower($client->name) === $clientName;
            });
            if ($match) return $match;
        }

        // Strategy 3: Check client_code match in invoice number
        $invoiceNumber = strtolower(trim($extractedData['invoice_number'] ?? ''));
        if ($invoiceNumber) {
            $match = $clients->first(function ($client) use ($invoiceNumber) {
                $code = strtolower($client->client_code ?? '');
                return $code && str_contains($invoiceNumber, $code);
            });
            if ($match) return $match;
        }

        // Strategy 4: Name contains match - but require MINIMUM 5 characters to avoid false positives
        if ($clientName && strlen($clientName) >= 5) {
            // Check if extracted name contains a client name (e.g., "Nucleus Mining Logistics CC" contains "Nucleus Mining Logistics")
            $match = $clients->first(function ($client) use ($clientName) {
                $dbName = strtolower($client->name);
                // Only match if one fully contains the other
                return str_contains($clientName, $dbName) || str_contains($dbName, $clientName);
            });
            if ($match) return $match;
        }

        // Strategy 5: Word-based matching - require at least 50% word match for business names
        if ($clientName) {
            $extractedWords = $this->getSignificantWords($clientName);
            $extractedWordCount = count($extractedWords);

            if ($extractedWordCount > 0) {
                $bestMatch = null;
                $bestScore = 0;
                $bestRatio = 0;

                foreach ($clients as $client) {
                    $clientWords = $this->getSignificantWords(strtolower($client->name));
                    $clientWordCount = count($clientWords);

                    if ($clientWordCount === 0) continue;

                    $matchingWords = array_intersect($extractedWords, $clientWords);
                    $matchCount = count($matchingWords);

                    // Calculate match ratio (percentage of client words that match)
                    $ratio = $matchCount / $clientWordCount;

                    // Require at least 2 matching words OR 50% match ratio
                    if ($matchCount >= 2 || ($matchCount >= 1 && $ratio >= 0.5)) {
                        if ($matchCount > $bestScore || ($matchCount === $bestScore && $ratio > $bestRatio)) {
                            $bestScore = $matchCount;
                            $bestRatio = $ratio;
                            $bestMatch = $client;
                        }
                    }
                }

                if ($bestMatch) {
                    return $bestMatch;
                }
            }
        }

        return null;
    }

    /**
     * Get significant words from a name (filter out common words)
     */
    private function getSignificantWords(string $name): array
    {
        $words = preg_split('/[\s,.-]+/', $name);
        $stopWords = ['pty', 'ltd', 'limited', 'inc', 'incorporated', 'cc', 'the', 'and', 'of', 'for', 'a', 'an'];

        return array_values(array_filter($words, function ($word) use ($stopWords) {
            $word = strtolower(trim($word));
            return strlen($word) >= 2 && !in_array($word, $stopWords);
        }));
    }

    /**
     * Find potential transaction matches for this invoice
     */
    private function findPotentialTransactions(array $extractedData): array
    {
        if (empty($extractedData['total']) || empty($extractedData['invoice_date'])) {
            return [];
        }

        $amount = (float) $extractedData['total'];
        $tolerance = $amount * 0.01;

        return Transaction::where('user_id', Auth::id())
            ->where('amount', '>', 0)
            ->whereBetween('transaction_date', [
                Carbon::parse($extractedData['invoice_date'])->subDays(7)->toDateString(),
                Carbon::parse($extractedData['invoice_date'])->addDays(90)->toDateString(),
            ])
            ->whereBetween('amount', [$amount - $tolerance, $amount + $tolerance])
            ->orderBy('transaction_date')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'date' => $t->transaction_date,
                'amount' => $t->amount,
                'description' => $t->description,
            ])
            ->toArray();
    }

    /**
     * Apply client default settings to fill in missing extracted data
     */
    private function applyClientDefaults(array $data, Client $client): array
    {
        // Calculate invoice date if missing
        if (empty($data['invoice_date'])) {
            $now = Carbon::now();
            $periodEnd = $client->billing_period_end ?? $now->daysInMonth;

            if ($now->day > $periodEnd) {
                $invoiceDate = $now->copy()->day(min($periodEnd, $now->daysInMonth));
            } else {
                $invoiceDate = $now->copy()->subMonth();
                $invoiceDate->day(min($periodEnd, $invoiceDate->daysInMonth));
            }
            $data['invoice_date'] = $invoiceDate->toDateString();
        }

        // Calculate due date if missing
        if (empty($data['due_date']) && !empty($data['invoice_date'])) {
            $invoiceDate = Carbon::parse($data['invoice_date']);

            if ($client->payment_terms) {
                $dueDate = $invoiceDate->copy()->addDays($client->payment_terms);
            } elseif ($client->payment_day) {
                $dueDate = $invoiceDate->copy();
                $dueDate->day(min($client->payment_day, $dueDate->daysInMonth));

                if ($dueDate->lessThanOrEqualTo($invoiceDate)) {
                    $dueDate->addMonth();
                    $dueDate->day(min($client->payment_day, $dueDate->daysInMonth));
                }
            } else {
                $dueDate = $invoiceDate->copy()->addDays(30);
            }

            $data['due_date'] = $dueDate->toDateString();
        }

        // Fill in client email if missing
        if (empty($data['client_email']) && !empty($client->email)) {
            $data['client_email'] = $client->email;
        }

        return $data;
    }

    /**
     * Check if AI extraction is available
     */
    public function status(GeminiService $geminiService): JsonResponse
    {
        return response()->json([
            'available' => $geminiService->isConfigured(),
            'provider' => 'gemini',
        ]);
    }

    /**
     * Get the extraction prompt for n8n pipeline
     * This allows centralized prompt management from the database
     */
    public function getExtractionPrompt(): JsonResponse
    {
        // In the future, this could be stored in a settings table
        // For now, return the standardized prompt
        $prompt = <<<'PROMPT'
You are a South African invoice data extraction specialist. Analyze this invoice document and extract data for a financial system.

CRITICAL: The "client" is the RECIPIENT being billed (the "Bill To" / "Invoice To" party), NOT the sender/issuer.

Extract and return ONLY valid JSON (no markdown, no code blocks):

{
  "invoice_number": "string - the invoice reference number",
  "invoice_date": "YYYY-MM-DD format",
  "due_date": "YYYY-MM-DD format (default: invoice_date + 30 days if missing)",
  "client_name": "string - company/person being BILLED",
  "client_email": "string or null - from Bill To section",
  "amount": number - total amount as decimal (e.g., 15000.00),
  "subtotal": number - amount before VAT,
  "vat_rate": number or null - VAT percentage (15 for standard SA VAT),
  "vat_amount": number or null,
  "description": "string - brief description of services/goods",
  "items": [
    {
      "description": "line item description",
      "quantity": number,
      "unit_price": number,
      "unit": "hrs/units/days or null"
    }
  ],
  "bank_name": "string or null - bank name from payment details",
  "account_number": "string or null - account number from payment details",
  "bank_reference": "string or null - payment reference if visible",
  "confidence": number 0-100
}

RULES:
1. Amounts must be numbers only (no "R" or currency symbols)
2. Convert "R 15 000,00" format to 15000.00
3. Dates must be YYYY-MM-DD
4. If year is missing, assume current year (2025)
5. Return null for fields that cannot be determined
6. Extract ALL line items visible on the invoice
PROMPT;

        return response()->json([
            'prompt' => $prompt,
            'version' => '1.0.0',
            'updated_at' => '2025-12-23',
        ]);
    }
}
