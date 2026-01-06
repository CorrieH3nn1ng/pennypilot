/**
 * Invoice Ingestion API Service
 *
 * Handles n8n/Gemini extracted invoice data with:
 * - Schema mapping to Gemini prompt keys
 * - Auto-reconciliation with bank deposits
 * - SARS 2025/26 tax set-aside calculations
 */

import { apiClient } from './client';
import type { Invoice, Client } from '@/types';

// ============================================
// Gemini Extraction Schema (exact keys from prompt)
// ============================================

export interface GeminiLineItem {
  description: string;
  quantity: number;
  unit_price: number;
  unit?: string | null;
}

export interface GeminiExtractedInvoice {
  invoice_number: string;
  invoice_date: string;        // YYYY-MM-DD
  due_date: string | null;     // YYYY-MM-DD
  client_name: string;
  client_email: string | null;
  amount: number;              // Total as decimal
  description: string | null;
  items: GeminiLineItem[];
  vat_rate: number | null;
  vat_amount: number | null;
  subtotal: number | null;
  confidence: number;
}

// ============================================
// Tax Set-Aside Response
// ============================================

export interface TaxSetAside {
  amount: number;
  bracket_rate: number;
  tax_before_rebate: number;
  rebate_applied: number;
  tax_set_aside: number;
  suggested_savings: number;
  effective_rate: number;
  tax_free_threshold: number;
}

// ============================================
// Ingestion Response Types
// ============================================

export interface IngestionSuccessResponse {
  data: Invoice;
  tax_set_aside: TaxSetAside;
  client_matched: true;
  auto_reconciled: boolean;
  matched_transaction_id?: string;
  message: string;
}

export interface IngestionStagedResponse {
  staged_id: string;
  client_matched: false;
  suggested_clients: Array<{
    id: string;
    name: string;
    client_code: string;
    email: string | null;
  }>;
  message: string;
}

export type IngestionResponse = IngestionSuccessResponse | IngestionStagedResponse;

export interface StagedInvoice {
  id: string;
  user_id: string;
  invoice_number: string;
  invoice_date: string;
  due_date: string;
  client_name: string;
  client_email: string | null;
  amount: number;
  description: string | null;
  source: string;
  file_path: string | null;
  raw_data: string;
  status: 'pending' | 'approved' | 'rejected';
  created_at: string;
}

export interface ApproveStaagedData {
  client_id: string;
  create_client?: boolean;
  client_name?: string;
}

// ============================================
// SARS 2025/26 Tax Calculator (Frontend)
// ============================================

/**
 * SARS 2025/26 Tax Configuration
 *
 * Primary Rebate: R17,235
 * Tax-Free Threshold: R95,750 (R17,235 / 0.18)
 *
 * The first R95,750 of annual income is effectively tax-free
 * because the tax on that amount equals the rebate.
 */
export const SARS_2025_26 = {
  PRIMARY_REBATE: 17235,
  TAX_FREE_THRESHOLD: 95750, // R17,235 / 0.18 = R95,750
  BRACKETS: [
    { min: 1, max: 237100, rate: 0.18, base: 0 },
    { min: 237101, max: 370500, rate: 0.26, base: 42678 },
    { min: 370501, max: 512800, rate: 0.31, base: 77362 },
    { min: 512801, max: 673000, rate: 0.36, base: 121475 },
    { min: 673001, max: 857900, rate: 0.39, base: 179147 },
    { min: 857901, max: 1817000, rate: 0.41, base: 251258 },
    { min: 1817001, max: Infinity, rate: 0.45, base: 644489 },
  ],
};

/**
 * Calculate tax set-aside for invoice amount
 * SARS 2025/26: First R95,750 is tax-free, then 18% bracket applies
 */
export function calculateTaxSetAside(invoiceAmount: number): TaxSetAside {
  // Annualize (assume monthly invoice)
  const annualizedIncome = invoiceAmount * 12;

  // Find applicable bracket
  let bracket = SARS_2025_26.BRACKETS[0];
  for (const b of SARS_2025_26.BRACKETS) {
    if (annualizedIncome >= b.min && annualizedIncome <= b.max) {
      bracket = b;
      break;
    }
  }

  // Calculate annual tax before rebate
  const taxableAboveMin = Math.max(0, annualizedIncome - bracket.min + 1);
  const annualTaxBeforeRebate = bracket.base + (taxableAboveMin * bracket.rate);

  // Apply rebate
  const annualTaxAfterRebate = Math.max(0, annualTaxBeforeRebate - SARS_2025_26.PRIMARY_REBATE);

  // Monthly provision (this invoice's share)
  const monthlyTax = annualTaxAfterRebate / 12;

  // Tax on this specific invoice amount
  const invoiceTaxBeforeRebate = invoiceAmount * bracket.rate;
  const proRatedRebate = SARS_2025_26.PRIMARY_REBATE / 12;
  const invoiceTaxSetAside = Math.max(0, invoiceTaxBeforeRebate - proRatedRebate);

  return {
    amount: Math.round(invoiceAmount * 100) / 100,
    bracket_rate: bracket.rate * 100,
    tax_before_rebate: Math.round(invoiceTaxBeforeRebate * 100) / 100,
    rebate_applied: Math.round(proRatedRebate * 100) / 100,
    tax_set_aside: Math.round(invoiceTaxSetAside * 100) / 100,
    suggested_savings: Math.round(invoiceTaxSetAside * 100) / 100,
    effective_rate: invoiceAmount > 0
      ? Math.round((invoiceTaxSetAside / invoiceAmount) * 10000) / 100
      : 0,
    tax_free_threshold: SARS_2025_26.TAX_FREE_THRESHOLD,
  };
}

// ============================================
// Ingestion API Methods
// ============================================

export const ingestionApi = {
  /**
   * Ingest invoice data from n8n/Gemini
   * Uses exact schema from Gemini prompt
   */
  async ingest(data: GeminiExtractedInvoice): Promise<IngestionResponse> {
    // Map Gemini schema to API schema
    const payload = {
      invoice_number: data.invoice_number,
      invoice_date: data.invoice_date,
      due_date: data.due_date,
      client_name: data.client_name,
      client_email: data.client_email,
      amount: data.amount,
      description: data.description,
      items: data.items?.map(item => ({
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        unit: item.unit,
      })),
      source: 'n8n',
    };

    return apiClient.postRaw<IngestionResponse>('/invoice-ingestion/ingest', payload);
  },

  /**
   * Get all staged invoices awaiting review
   */
  async getStaged(): Promise<{ data: StagedInvoice[]; count: number }> {
    return apiClient.getRaw('/invoice-ingestion/staged');
  },

  /**
   * Approve staged invoice with client assignment
   */
  async approveStaged(id: string, data: ApproveStaagedData): Promise<IngestionSuccessResponse> {
    return apiClient.postRaw(`/invoice-ingestion/staged/${id}/approve`, data);
  },

  /**
   * Reject staged invoice
   */
  async rejectStaged(id: string): Promise<{ message: string }> {
    return apiClient.postRaw(`/invoice-ingestion/staged/${id}/reject`, {});
  },

  /**
   * Get tax set-aside calculation for an invoice
   */
  async getTaxSetAside(invoiceId: string): Promise<{
    invoice_id: string;
    invoice_number: string;
    invoice_total: number;
    tax_info: TaxSetAside;
    sars_year: string;
  }> {
    return apiClient.getRaw(`/invoices/${invoiceId}/tax-set-aside`);
  },

  /**
   * Calculate tax set-aside locally (no API call)
   */
  calculateTaxSetAside,
};

export default ingestionApi;
