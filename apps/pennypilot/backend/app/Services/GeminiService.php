<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private string $model = 'gemini-2.0-flash';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Get HTTP client with SSL verification disabled in local environment
     */
    private function getHttpClient()
    {
        $http = Http::timeout(60);

        // Disable SSL verification in local environment (Windows SSL cert issue)
        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /**
     * Extract invoice data from a PDF file
     */
    public function extractInvoiceFromPdf(string $pdfPath): array
    {
        if (!$this->apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        // Read PDF file and encode as base64
        $pdfContent = file_get_contents($pdfPath);
        $base64Pdf = base64_encode($pdfContent);

        $prompt = $this->getInvoiceExtractionPrompt();

        $response = $this->getHttpClient()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => 'application/pdf',
                                    'data' => $base64Pdf,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.8,
                    'maxOutputTokens' => 2048,
                ],
            ]
        );

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to process PDF with Gemini: ' . $response->body());
        }

        $result = $response->json();

        return $this->parseInvoiceResponse($result);
    }

    /**
     * Extract invoice data from an image file
     */
    public function extractInvoiceFromImage(string $imagePath): array
    {
        if (!$this->apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        // Read image and encode as base64
        $imageContent = file_get_contents($imagePath);
        $base64Image = base64_encode($imageContent);
        $mimeType = mime_content_type($imagePath);

        $prompt = $this->getInvoiceExtractionPrompt();

        $response = $this->getHttpClient()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.8,
                    'maxOutputTokens' => 2048,
                ],
            ]
        );

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to process image with Gemini: ' . $response->body());
        }

        $result = $response->json();

        return $this->parseInvoiceResponse($result);
    }

    /**
     * Get the prompt for invoice extraction
     */
    private function getInvoiceExtractionPrompt(): string
    {
        return <<<'PROMPT'
Analyze this invoice document and extract the following information. Return ONLY valid JSON with no markdown formatting or code blocks.

Extract these fields:
- invoice_number: The invoice number/reference
- invoice_date: Date the invoice was issued (format: YYYY-MM-DD)
- due_date: Payment due date (format: YYYY-MM-DD)
- client_name: Name of the RECIPIENT/customer being billed (the "Bill To" or "Invoice To" party, NOT the sender/issuer of the invoice)
- client_email: Email of the recipient/customer (from "Bill To" section)
- subtotal: Amount before tax
- tax_rate: Tax percentage if applicable (just the number, e.g., 15 for 15%)
- tax_amount: Tax amount
- total: Total amount including tax
- currency: Currency code (e.g., ZAR, USD)
- payment_reference: Any payment reference or banking details
- line_items: Array of items with description, quantity, unit_price, amount

If a field is not found or unclear, use null.
For amounts, use numbers only (no currency symbols).
For dates, if only partial date is visible, make reasonable assumptions for the year.

Return JSON in this exact format:
{
    "invoice_number": "string or null",
    "invoice_date": "YYYY-MM-DD or null",
    "due_date": "YYYY-MM-DD or null",
    "client_name": "string or null",
    "client_email": "string or null",
    "subtotal": number or null,
    "tax_rate": number or null,
    "tax_amount": number or null,
    "total": number or null,
    "currency": "string or null",
    "payment_reference": "string or null",
    "line_items": [
        {
            "description": "string",
            "quantity": number,
            "unit_price": number,
            "amount": number
        }
    ],
    "confidence": number between 0 and 100,
    "notes": "any relevant notes about extraction quality"
}
PROMPT;
    }

    /**
     * Parse the Gemini response into structured invoice data
     */
    private function parseInvoiceResponse(array $response): array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Clean up the response - remove markdown code blocks if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        // Try to parse as JSON
        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Gemini response as JSON', [
                'text' => $text,
                'error' => json_last_error_msg(),
            ]);

            // Return empty structure with error
            return [
                'success' => false,
                'error' => 'Failed to parse invoice data',
                'raw_response' => $text,
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'client_email' => $data['client_email'] ?? null,
                'subtotal' => $data['subtotal'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? null,
                'total' => $data['total'] ?? null,
                'currency' => $data['currency'] ?? 'ZAR',
                'payment_reference' => $data['payment_reference'] ?? null,
                'line_items' => $data['line_items'] ?? [],
                'confidence' => $data['confidence'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
        ];
    }

    /**
     * Check if Gemini service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Extract financial document data for onboarding (invoice or payslip)
     * Designed to populate the onboarding wizard with income information
     */
    public function extractOnboardingDocument(string $filePath): array
    {
        if (!$this->apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        // Read file and encode as base64
        $fileContent = file_get_contents($filePath);
        $base64Content = base64_encode($fileContent);
        $mimeType = mime_content_type($filePath);

        $prompt = $this->getOnboardingExtractionPrompt();

        $response = $this->getHttpClient()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Content,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.8,
                    'maxOutputTokens' => 1024,
                ],
            ]
        );

        if (!$response->successful()) {
            Log::error('Gemini API error (onboarding)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to process document with Gemini');
        }

        $result = $response->json();

        return $this->parseOnboardingResponse($result);
    }

    /**
     * Get the prompt for onboarding document extraction
     * Designed for SA accountant expertise
     */
    private function getOnboardingExtractionPrompt(): string
    {
        return <<<'PROMPT'
Act as an expert South African accountant. Analyze this financial document (invoice or payslip) and extract key information for budgeting purposes.

First, determine the DOCUMENT TYPE:
- "invoice" = A tax invoice, quote, or billing document (shows sales/revenue)
- "payslip" = A salary slip, pay advice, or remuneration statement (shows employment income)

Then extract the following based on document type:

FOR INVOICES:
- total_amount: The total/gross amount (including VAT if applicable)
- vat_amount: The VAT amount (null if not VAT registered or no VAT shown)
- subtotal: Amount before VAT (null if not shown)
- document_date: Date on the document (format: YYYY-MM-DD)
- entity_name: The business/person name who ISSUED the invoice (the freelancer/business)

FOR PAYSLIPS:
- gross_pay: Total earnings before deductions
- net_pay: Take-home pay after all deductions (PAYE, UIF, pension, medical aid)
- paye_tax: PAYE tax deducted
- document_date: Pay period end date or payment date (format: YYYY-MM-DD)
- entity_name: The employer name

Return ONLY valid JSON with no markdown formatting:
{
    "document_type": "invoice" or "payslip",
    "total_amount": number (gross amount for invoice, gross pay for payslip),
    "net_amount": number (subtotal for invoice, net pay for payslip),
    "vat_amount": number or null,
    "tax_deducted": number or null (PAYE for payslip),
    "document_date": "YYYY-MM-DD" or null,
    "entity_name": "string" or null,
    "currency": "ZAR",
    "confidence": number 0-100,
    "notes": "any relevant extraction notes"
}

IMPORTANT:
- For South African documents, assume ZAR currency unless stated otherwise
- VAT is typically 15% in South Africa
- If it's a payslip, net_amount should be the NET PAY (what the employee actually receives)
- If it's an invoice, net_amount should be the amount BEFORE VAT
PROMPT;
    }

    /**
     * Parse the onboarding document response
     */
    private function parseOnboardingResponse(array $response): array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Clean up markdown code blocks
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse onboarding Gemini response', [
                'text' => $text,
                'error' => json_last_error_msg(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to parse document data',
                'raw_response' => $text,
            ];
        }

        // Normalize the response
        $documentType = $data['document_type'] ?? 'invoice';
        $isPayslip = $documentType === 'payslip';

        return [
            'success' => true,
            'data' => [
                'document_type' => $documentType,
                'is_payslip' => $isPayslip,
                'total_amount' => $this->parseAmount($data['total_amount'] ?? null),
                'net_amount' => $this->parseAmount($data['net_amount'] ?? null),
                'vat_amount' => $this->parseAmount($data['vat_amount'] ?? null),
                'tax_deducted' => $this->parseAmount($data['tax_deducted'] ?? null),
                'document_date' => $data['document_date'] ?? null,
                'entity_name' => $data['entity_name'] ?? null,
                'currency' => $data['currency'] ?? 'ZAR',
                'confidence' => $data['confidence'] ?? null,
                'notes' => $data['notes'] ?? null,
                // Calculated field for onboarding
                'monthly_base_amount' => $isPayslip
                    ? $this->parseAmount($data['net_amount'] ?? $data['total_amount'] ?? null)
                    : $this->parseAmount($data['total_amount'] ?? null),
            ],
        ];
    }

    /**
     * Parse amount to float, using BCMath for precision
     */
    private function parseAmount($value): ?float
    {
        if ($value === null) {
            return null;
        }

        // Handle string amounts (remove currency symbols, spaces, etc.)
        if (is_string($value)) {
            $value = preg_replace('/[^0-9.-]/', '', $value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        // Use bcmul for precision then convert to float for JSON
        return (float) bcadd((string) $value, '0', 2);
    }
}

