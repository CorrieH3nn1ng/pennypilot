/**
 * n8n Webhook API Service
 *
 * Handles communication with the PennyPilot AI Invoice Pipeline.
 * Dedicated n8n workflow for invoice extraction via Gemini.
 *
 * Webhook URL: http://ezepeze.dedicated.co.za:5678/webhook/pennypilot-invoice
 */

import axios, { AxiosError } from 'axios';

// ============================================
// Configuration
// ============================================

const N8N_WEBHOOK_URL = import.meta.env.VITE_N8N_WEBHOOK_URL
  || 'http://ezepeze.dedicated.co.za:5678/webhook/pennypilot-invoice';

// ============================================
// Extraction Prompt (SA Invoice Specialist)
// ============================================

const INVOICE_EXTRACTION_PROMPT = `You are a South African invoice data extraction specialist. Analyze this invoice document and extract data for a financial system.

CRITICAL: The "client" is the RECIPIENT being billed (the "Bill To" / "Invoice To" party), NOT the sender/issuer.

Extract and return ONLY valid JSON (no markdown, no code blocks):

{
  "invoice_number": "string",
  "invoice_date": "YYYY-MM-DD",
  "due_date": "YYYY-MM-DD",
  "client_name": "string",
  "client_email": "string or null",
  "amount": number,
  "subtotal": number,
  "vat_rate": 15,
  "vat_amount": number,
  "description": "string",
  "items": [
    { "description": "string", "quantity": number, "unit_price": number, "amount": number }
  ],
  "supplier_details": {
    "name": "string",
    "vat_number": "10-digit string or null",
    "reg_number": "CIPC number e.g. 2024/123456/07",
    "address": "string or null"
  },
  "recipient_details": {
    "vat_number": "10-digit string or null",
    "address": "string or null"
  },
  "banking": {
    "bank": "string",
    "account": "string",
    "reference": "string"
  },
  "compliance": {
    "is_labeled_tax_invoice": boolean,
    "has_supplier_vat": boolean,
    "has_recipient_vat": boolean,
    "has_physical_addresses": boolean
  },
  "confidence": number
}

RULES:
1. Amounts must be numbers only. Convert "R 15 000,00" to 15000.00.
2. Dates: YYYY-MM-DD. Assume current year 2025 if missing.
3. SA VAT is 15%. If the document says 14%, flag it in description.
4. If "Total Amount" > 5000, "recipient_details.vat_number" is legally required by SARS.
5. Return null for fields that cannot be determined.`;

// ============================================
// n8n Request/Response Types
// ============================================

export interface ExpectedBanking {
  bank_name: string;
  account_number: string;
}

export interface N8nUploadPayload {
  file: string;           // base64 encoded
  prompt: string;         // extraction instructions
  document_type: string;  // e.g., "invoice"
  filename: string;
  expected_banking?: ExpectedBanking | null;
}

// Extracted invoice data from Gemini (SARS compliant)
export interface N8nExtractedInvoice {
  invoice_number: string | null;
  invoice_date: string | null;
  due_date: string | null;
  client_name: string | null;
  client_email: string | null;
  amount: number | null;
  subtotal: number | null;
  vat_rate: number | null;
  vat_amount: number | null;
  description: string | null;
  items: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
  }>;
  supplier_details: {
    name: string | null;
    vat_number: string | null;
    reg_number: string | null;
    address: string | null;
  } | null;
  recipient_details: {
    vat_number: string | null;
    address: string | null;
  } | null;
  banking: {
    bank: string | null;
    account: string | null;
    reference: string | null;
  } | null;
  compliance: {
    is_labeled_tax_invoice: boolean;
    has_supplier_vat: boolean;
    has_recipient_vat: boolean;
    has_physical_addresses: boolean;
  } | null;
  confidence: number;
}

// n8n workflow success response
export interface N8nSuccessResponse {
  success: true;
  document_type: string;
  extracted_at: string;
  data: N8nExtractedInvoice;
}

// n8n workflow error response
export interface N8nErrorResponse {
  success: false;
  error: string;
  extracted_at?: string;
}

export type N8nResponse = N8nSuccessResponse | N8nErrorResponse;

// ============================================
// Helper Functions
// ============================================

/**
 * Convert a File to base64 string
 */
async function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result as string;
      // Remove the data URL prefix (e.g., "data:application/pdf;base64,")
      const base64 = result.split(',')[1];
      resolve(base64);
    };
    reader.onerror = () => reject(new Error('Failed to read file'));
    reader.readAsDataURL(file);
  });
}

/**
 * Get the default invoice extraction prompt
 */
export function getInvoiceExtractionPrompt(): string {
  return INVOICE_EXTRACTION_PROMPT;
}

// ============================================
// n8n API Methods
// ============================================

export const n8nApi = {
  /**
   * Get the configured webhook URL
   */
  getWebhookUrl(): string {
    return N8N_WEBHOOK_URL;
  },

  /**
   * Check if n8n webhook is configured
   */
  isConfigured(): boolean {
    return !!N8N_WEBHOOK_URL && N8N_WEBHOOK_URL.length > 0;
  },

  /**
   * Upload invoice to n8n pipeline for AI extraction
   *
   * Flow:
   * 1. Convert file to base64
   * 2. POST to n8n webhook with file + prompt + user's banking
   * 3. n8n sends to Gemini for analysis
   * 4. n8n validates and returns extracted data
   */
  async uploadInvoice(
    file: File,
    expectedBanking?: ExpectedBanking | null,
    customPrompt?: string
  ): Promise<N8nResponse> {
    if (!this.isConfigured()) {
      return {
        success: false,
        error: 'n8n webhook is not configured. Set VITE_N8N_WEBHOOK_URL in your environment.',
      };
    }

    try {
      // Convert file to base64
      const base64File = await fileToBase64(file);

      // Prepare payload matching n8n workflow expectations
      const payload: N8nUploadPayload = {
        file: base64File,
        prompt: customPrompt || INVOICE_EXTRACTION_PROMPT,
        document_type: 'invoice',
        filename: file.name,
        expected_banking: expectedBanking || null,
      };

      // Send to n8n webhook
      const response = await axios.post<N8nResponse>(
        N8N_WEBHOOK_URL,
        payload,
        {
          headers: {
            'Content-Type': 'application/json',
          },
          timeout: 120000, // 2 minute timeout for AI processing
        }
      );

      return response.data;
    } catch (error) {
      // Handle axios errors
      if (error instanceof AxiosError) {
        if (error.response) {
          // Server responded with error
          const data = error.response.data;
          return {
            success: false,
            error: data?.error || data?.message || `Server error: ${error.response.status}`,
          };
        } else if (error.request) {
          // No response received
          return {
            success: false,
            error: 'No response from n8n server. Check if n8n is running.',
          };
        }
      }

      // Generic error
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error occurred',
      };
    }
  },

  /**
   * Test the n8n webhook connection
   */
  async testConnection(): Promise<{ connected: boolean; message: string }> {
    if (!this.isConfigured()) {
      return {
        connected: false,
        message: 'n8n webhook URL not configured',
      };
    }

    try {
      // Send a minimal test payload
      // n8n should reject it but we'll know it's reachable
      const response = await axios.post(
        N8N_WEBHOOK_URL,
        { test: true },
        {
          headers: { 'Content-Type': 'application/json' },
          timeout: 10000,
          validateStatus: () => true, // Accept any status
        }
      );

      // Any response means it's reachable
      return {
        connected: true,
        message: `n8n webhook reachable (status: ${response.status})`,
      };
    } catch (error) {
      return {
        connected: false,
        message: error instanceof Error ? error.message : 'Connection failed',
      };
    }
  },
};

export default n8nApi;
