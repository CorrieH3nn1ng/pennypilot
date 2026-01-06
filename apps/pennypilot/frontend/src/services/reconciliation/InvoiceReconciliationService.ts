/**
 * Invoice Reconciliation Service
 *
 * Auto-matches incoming n8n invoices with bank deposits in IndexedDB.
 * When a match is found:
 * 1. Marks invoice as 'Paid'
 * 2. Updates the Tax Gap immediately
 * 3. Links the transaction to the invoice
 */

import { localBaseService } from '@/services/storage/LocalBaseService';
import { invoiceApi } from '@/services/api/invoice.api';
import { calculateTaxSetAside, type GeminiExtractedInvoice, type TaxSetAside } from '@/services/api/ingestion.api';
import type { Transaction, Invoice } from '@/types';

// ============================================
// Match Result Types
// ============================================

export interface ReconciliationMatch {
  transaction: Transaction;
  score: number;
  reasons: string[];
}

export interface ReconciliationResult {
  invoice_number: string;
  matched: boolean;
  transaction?: Transaction;
  match_score?: number;
  match_reasons?: string[];
  tax_set_aside?: TaxSetAside;
  auto_paid: boolean;
}

// ============================================
// Matching Configuration
// ============================================

const MATCH_CONFIG = {
  // Minimum score to auto-reconcile (out of 100)
  AUTO_MATCH_THRESHOLD: 75,

  // Score weights
  WEIGHTS: {
    EXACT_AMOUNT: 50,      // Exact amount match
    CLOSE_AMOUNT: 30,      // Within 1% tolerance
    DATE_PROXIMITY: 20,    // Within 7 days of invoice date
    CLIENT_NAME: 20,       // Client name in description
    INVOICE_NUMBER: 30,    // Invoice number in description
  },

  // Amount tolerance for "close" matches (1%)
  AMOUNT_TOLERANCE: 0.01,

  // Days window for date matching
  DATE_WINDOW_DAYS: 14,
};

// ============================================
// Reconciliation Service
// ============================================

class InvoiceReconciliationService {
  /**
   * Process incoming invoice from n8n and attempt auto-reconciliation
   */
  async processIncomingInvoice(
    extractedData: GeminiExtractedInvoice,
    invoiceId: string
  ): Promise<ReconciliationResult> {
    const result: ReconciliationResult = {
      invoice_number: extractedData.invoice_number,
      matched: false,
      auto_paid: false,
    };

    try {
      // Find potential deposit matches in IndexedDB
      const potentialMatches = await this.findMatchingDeposits(extractedData);

      if (potentialMatches.length > 0) {
        const bestMatch = potentialMatches[0];

        // Check if score meets auto-match threshold
        if (bestMatch.score >= MATCH_CONFIG.AUTO_MATCH_THRESHOLD) {
          // Auto-reconcile: Mark invoice as paid
          await this.reconcileInvoice(invoiceId, bestMatch.transaction);

          result.matched = true;
          result.transaction = bestMatch.transaction;
          result.match_score = bestMatch.score;
          result.match_reasons = bestMatch.reasons;
          result.auto_paid = true;

          // Calculate tax set-aside
          result.tax_set_aside = calculateTaxSetAside(extractedData.amount);

          console.log('[Reconciliation] Auto-matched invoice', {
            invoice: extractedData.invoice_number,
            transaction: bestMatch.transaction.description,
            score: bestMatch.score,
          });
        }
      }

      return result;
    } catch (error) {
      console.error('[Reconciliation] Error processing invoice:', error);
      return result;
    }
  }

  /**
   * Find deposits in IndexedDB that match the invoice
   */
  async findMatchingDeposits(invoice: GeminiExtractedInvoice): Promise<ReconciliationMatch[]> {
    // Get all transactions from IndexedDB
    const transactions = await localBaseService.getAllTransactions();

    // Filter to deposits only (positive amounts, not transfers)
    const deposits = transactions.filter(tx =>
      Number(tx.amount) > 0 &&
      !tx.is_transfer
    );

    // Score each deposit
    const matches: ReconciliationMatch[] = [];

    for (const deposit of deposits) {
      const { score, reasons } = this.scoreMatch(invoice, deposit);

      if (score > 0) {
        matches.push({
          transaction: deposit,
          score,
          reasons,
        });
      }
    }

    // Sort by score descending
    return matches.sort((a, b) => b.score - a.score);
  }

  /**
   * Score how well a transaction matches an invoice
   */
  private scoreMatch(
    invoice: GeminiExtractedInvoice,
    transaction: Transaction
  ): { score: number; reasons: string[] } {
    let score = 0;
    const reasons: string[] = [];

    const invoiceAmount = invoice.amount;
    const txAmount = Number(transaction.amount);
    const txDescription = (transaction.description || '').toLowerCase();
    const txRawDescription = (transaction.raw_description || '').toLowerCase();

    // 1. Amount matching
    if (Math.abs(invoiceAmount - txAmount) < 0.01) {
      score += MATCH_CONFIG.WEIGHTS.EXACT_AMOUNT;
      reasons.push('Exact amount match');
    } else {
      const tolerance = invoiceAmount * MATCH_CONFIG.AMOUNT_TOLERANCE;
      if (Math.abs(invoiceAmount - txAmount) <= tolerance) {
        score += MATCH_CONFIG.WEIGHTS.CLOSE_AMOUNT;
        reasons.push('Amount within 1%');
      }
    }

    // 2. Date proximity
    const invoiceDate = new Date(invoice.invoice_date);
    const txDate = new Date(transaction.transaction_date);
    const daysDiff = Math.abs(
      (txDate.getTime() - invoiceDate.getTime()) / (1000 * 60 * 60 * 24)
    );

    if (daysDiff <= MATCH_CONFIG.DATE_WINDOW_DAYS) {
      const dateScore = MATCH_CONFIG.WEIGHTS.DATE_PROXIMITY *
        (1 - daysDiff / MATCH_CONFIG.DATE_WINDOW_DAYS);
      score += dateScore;
      reasons.push(`Within ${Math.round(daysDiff)} days`);
    }

    // 3. Invoice number in description
    const invoiceNum = invoice.invoice_number.toLowerCase();
    if (txDescription.includes(invoiceNum) || txRawDescription.includes(invoiceNum)) {
      score += MATCH_CONFIG.WEIGHTS.INVOICE_NUMBER;
      reasons.push('Invoice # in description');
    }

    // Also check for partial invoice number (without prefix)
    const numericPart = invoiceNum.replace(/[^0-9]/g, '');
    if (numericPart.length >= 3 &&
        (txDescription.includes(numericPart) || txRawDescription.includes(numericPart))) {
      score += MATCH_CONFIG.WEIGHTS.INVOICE_NUMBER * 0.5;
      reasons.push('Invoice # (partial) in description');
    }

    // 4. Client name in description
    const clientName = invoice.client_name.toLowerCase();
    const clientWords = clientName.split(/\s+/).filter(w => w.length > 2);

    for (const word of clientWords) {
      if (txDescription.includes(word) || txRawDescription.includes(word)) {
        score += MATCH_CONFIG.WEIGHTS.CLIENT_NAME / clientWords.length;
        reasons.push(`Client name match: "${word}"`);
        break;
      }
    }

    return { score: Math.min(100, Math.round(score)), reasons };
  }

  /**
   * Reconcile invoice with transaction
   * Marks invoice as paid and links to transaction
   */
  private async reconcileInvoice(
    invoiceId: string,
    transaction: Transaction
  ): Promise<void> {
    // Call API to match and mark as paid
    await invoiceApi.match(invoiceId, transaction.id!);
  }

  /**
   * Manually reconcile an invoice with a selected transaction
   */
  async manualReconcile(
    invoiceId: string,
    transactionId: string
  ): Promise<Invoice> {
    return invoiceApi.match(invoiceId, transactionId);
  }

  /**
   * Find all unreconciled invoices and attempt auto-matching
   */
  async reconcileAllPending(): Promise<{
    matched: number;
    unmatched: number;
    results: ReconciliationResult[];
  }> {
    const results: ReconciliationResult[] = [];
    let matched = 0;
    let unmatched = 0;

    try {
      // Use the backend auto-match endpoint
      const response = await invoiceApi.autoMatchAll(MATCH_CONFIG.AUTO_MATCH_THRESHOLD);

      matched = response.matched.length;
      unmatched = response.unmatched_count;

      for (const match of response.matched) {
        results.push({
          invoice_number: match.invoice_number,
          matched: true,
          match_score: match.score,
          auto_paid: true,
        });
      }
    } catch (error) {
      console.error('[Reconciliation] Bulk reconciliation failed:', error);
    }

    return { matched, unmatched, results };
  }

  /**
   * Get reconciliation statistics
   */
  async getStats(): Promise<{
    total_invoices: number;
    matched: number;
    unmatched: number;
    auto_matched: number;
    manual_matched: number;
  }> {
    const stats = await invoiceApi.getMatchStats();
    return {
      total_invoices: stats.total_invoices,
      matched: stats.matched,
      unmatched: stats.unmatched_pending,
      auto_matched: stats.auto_matched,
      manual_matched: stats.manual_matched,
    };
  }
}

// Export singleton
export const reconciliationService = new InvoiceReconciliationService();
export default reconciliationService;
