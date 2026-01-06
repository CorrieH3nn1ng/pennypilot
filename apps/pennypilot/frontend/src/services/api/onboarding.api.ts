import { n8nApi, type N8nExtractedInvoice } from './n8n.api';

/**
 * Onboarding API Service
 *
 * Handles AI-powered document extraction for onboarding wizard
 * Uses n8n webhook pipeline for Gemini extraction
 */

export interface TaxPreview {
  gross_amount: number;
  tax_provision: number;
  net_after_tax: number;
  effective_rate: number | null;
  budget_preview: {
    needs: number;
    wants: number;
    savings: number;
  };
}

export interface DocumentExtractionResult {
  document_type: 'invoice' | 'payslip';
  is_payslip: boolean;
  entity_name: string | null;
  document_date: string | null;
  total_amount: number | null;
  net_amount: number | null;
  vat_amount: number | null;
  tax_deducted: number | null;
  monthly_base_amount: number;
  confidence: number | null;
  notes: string | null;
  suggested_income_type: 'self_employed' | 'salaried';
  tax_preview: TaxPreview | null;
}

export interface ExtractionResponse {
  success: boolean;
  data?: DocumentExtractionResult;
  error?: string;
}

// SARS 2024/2025 Tax Brackets for local calculation
const TAX_BRACKETS = [
  { min: 0, max: 237100, rate: 18, base: 0 },
  { min: 237101, max: 370500, rate: 26, base: 42678 },
  { min: 370501, max: 512800, rate: 31, base: 77362 },
  { min: 512801, max: 673000, rate: 36, base: 121475 },
  { min: 673001, max: 857900, rate: 39, base: 179147 },
  { min: 857901, max: 1817000, rate: 41, base: 251258 },
  { min: 1817001, max: Infinity, rate: 45, base: 644489 },
];
const PRIMARY_REBATE = 17235;

/**
 * Estimate monthly tax provision using SARS brackets
 */
function estimateMonthlyTax(monthlyIncome: number): number {
  const annualIncome = monthlyIncome * 12;
  let annualTax = 0;

  for (const bracket of TAX_BRACKETS) {
    if (annualIncome <= bracket.max) {
      annualTax = bracket.base + (annualIncome - bracket.min + 1) * (bracket.rate / 100);
      break;
    }
  }

  // Apply primary rebate
  annualTax = Math.max(0, annualTax - PRIMARY_REBATE);
  return Math.round((annualTax / 12) * 100) / 100;
}

/**
 * Convert n8n invoice response to onboarding format
 */
function convertN8nToOnboarding(invoice: N8nExtractedInvoice): DocumentExtractionResult {
  const totalAmount = invoice.amount ?? 0;
  const vatAmount = invoice.vat_amount ?? 0;
  const netAmount = totalAmount - vatAmount;

  // For invoices: use the net amount (excl VAT) as monthly base
  const monthlyBaseAmount = netAmount > 0 ? netAmount : totalAmount;

  // Calculate tax preview
  const taxProvision = estimateMonthlyTax(monthlyBaseAmount);
  const netAfterTax = monthlyBaseAmount - taxProvision;
  const effectiveRate = monthlyBaseAmount > 0
    ? Math.round((taxProvision / monthlyBaseAmount) * 1000) / 10
    : 0;

  return {
    document_type: 'invoice',
    is_payslip: false,
    entity_name: invoice.supplier_details?.name ?? invoice.client_name ?? null,
    document_date: invoice.invoice_date,
    total_amount: totalAmount,
    net_amount: netAmount,
    vat_amount: vatAmount,
    tax_deducted: null,
    monthly_base_amount: monthlyBaseAmount,
    confidence: invoice.confidence ?? 80,
    notes: invoice.description,
    suggested_income_type: 'self_employed',
    tax_preview: {
      gross_amount: monthlyBaseAmount,
      tax_provision: taxProvision,
      net_after_tax: netAfterTax,
      effective_rate: effectiveRate,
      budget_preview: {
        needs: Math.round(netAfterTax * 0.5 * 100) / 100,
        wants: Math.round(netAfterTax * 0.3 * 100) / 100,
        savings: Math.round(netAfterTax * 0.2 * 100) / 100,
      },
    },
  };
}

/**
 * Extract income data from an uploaded invoice or payslip
 * Uses n8n webhook pipeline with Gemini Vision
 *
 * @param file - The document file (PDF, PNG, JPG)
 * @returns Extracted data with tax preview
 */
export async function extractOnboardingDocument(file: File): Promise<ExtractionResponse> {
  // Check if n8n is configured
  if (!n8nApi.isConfigured()) {
    return {
      success: false,
      error: 'AI extraction is not configured. Please enter your income manually.',
    };
  }

  try {
    // Use n8n webhook for extraction
    const n8nResponse = await n8nApi.uploadInvoice(file);

    if (!n8nResponse.success) {
      return {
        success: false,
        error: n8nResponse.error || 'Failed to extract document data',
      };
    }

    // Convert n8n response to onboarding format
    const data = convertN8nToOnboarding(n8nResponse.data);

    return {
      success: true,
      data,
    };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error.message : 'Failed to process document',
    };
  }
}
