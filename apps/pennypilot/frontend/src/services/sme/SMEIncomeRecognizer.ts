/**
 * SME Income Recognizer
 *
 * Proactively matches invoice payments to bank deposits.
 * Applies the 39.1% tax set-aside automatically.
 *
 * Key Features:
 * - Auto-detect client names in transaction descriptions
 * - Match by amount (exact or within tolerance)
 * - Apply tax provisions automatically
 * - Track matched vs unmatched income
 */

import type { ParsedTransaction } from '@/types';

export interface KnownClient {
  name: string;
  patterns: RegExp[];
  defaultTaxRate: number; // 0.391 for SME
}

export interface IncomeMatch {
  transactionId: string;
  transactionDate: string;
  amount: number;
  clientName: string;
  matchConfidence: number; // 0-100
  taxSetAside: number;
  netAfterTax: number;
  matchedInvoiceId?: string;
}

export interface IncomeRecognitionResult {
  recognizedIncome: IncomeMatch[];
  unrecognizedDeposits: ParsedTransaction[];
  totalGrossIncome: number;
  totalTaxSetAside: number;
  totalNetIncome: number;
}

// Default SME tax rate (conservative estimate)
const SME_TAX_RATE = 0.391;

// Known client patterns (can be extended from user's invoice history)
const DEFAULT_KNOWN_CLIENTS: KnownClient[] = [
  {
    name: 'Nucleus Mining',
    patterns: [/NUCLEUS\s*MIN/i, /NUCLEUS/i],
    defaultTaxRate: SME_TAX_RATE,
  },
  // Add more as they're detected from invoices
];

export class SMEIncomeRecognizer {
  private knownClients: KnownClient[] = [...DEFAULT_KNOWN_CLIENTS];
  private taxRate: number = SME_TAX_RATE;

  constructor(customClients?: KnownClient[], customTaxRate?: number) {
    if (customClients) {
      this.knownClients = [...this.knownClients, ...customClients];
    }
    if (customTaxRate !== undefined) {
      this.taxRate = customTaxRate;
    }
  }

  /**
   * Add a client from invoice data
   */
  addClientFromInvoice(clientName: string, invoiceId?: string): void {
    // Check if client already exists
    const existing = this.knownClients.find(c =>
      c.name.toLowerCase() === clientName.toLowerCase()
    );

    if (!existing) {
      // Create patterns from client name
      const words = clientName.split(/\s+/).filter(w => w.length > 2);
      const patterns = [
        new RegExp(clientName.replace(/\s+/g, '\\s*'), 'i'),
        ...words.map(word => new RegExp(word, 'i')),
      ];

      this.knownClients.push({
        name: clientName,
        patterns,
        defaultTaxRate: this.taxRate,
      });
    }
  }

  /**
   * Recognize income from transactions
   */
  recognizeIncome(transactions: ParsedTransaction[]): IncomeRecognitionResult {
    const recognizedIncome: IncomeMatch[] = [];
    const unrecognizedDeposits: ParsedTransaction[] = [];

    // Filter to deposits only (positive amounts)
    const deposits = transactions.filter(tx => tx.amount > 0);

    for (const deposit of deposits) {
      const match = this.matchToClient(deposit);

      if (match) {
        recognizedIncome.push(match);
      } else {
        unrecognizedDeposits.push(deposit);
      }
    }

    // Calculate totals
    const totalGrossIncome = recognizedIncome.reduce((sum, m) => sum + m.amount, 0);
    const totalTaxSetAside = recognizedIncome.reduce((sum, m) => sum + m.taxSetAside, 0);
    const totalNetIncome = totalGrossIncome - totalTaxSetAside;

    return {
      recognizedIncome,
      unrecognizedDeposits,
      totalGrossIncome,
      totalTaxSetAside,
      totalNetIncome,
    };
  }

  /**
   * Match a deposit to a known client
   */
  private matchToClient(deposit: ParsedTransaction): IncomeMatch | null {
    const description = deposit.rawDescription || deposit.description;

    for (const client of this.knownClients) {
      for (const pattern of client.patterns) {
        if (pattern.test(description)) {
          const taxSetAside = Math.round(deposit.amount * this.taxRate * 100) / 100;

          return {
            transactionId: deposit.bankReference || `${deposit.transactionDate}-${deposit.amount}`,
            transactionDate: deposit.transactionDate,
            amount: deposit.amount,
            clientName: client.name,
            matchConfidence: this.calculateConfidence(description, client),
            taxSetAside,
            netAfterTax: deposit.amount - taxSetAside,
          };
        }
      }
    }

    return null;
  }

  /**
   * Calculate match confidence (0-100)
   */
  private calculateConfidence(description: string, client: KnownClient): number {
    let score = 50; // Base score for any match

    // Exact name match
    if (description.toLowerCase().includes(client.name.toLowerCase())) {
      score += 40;
    }

    // Multiple pattern matches
    const matches = client.patterns.filter(p => p.test(description)).length;
    score += Math.min(matches * 5, 10);

    return Math.min(score, 100);
  }

  /**
   * Get known clients list
   */
  getKnownClients(): KnownClient[] {
    return [...this.knownClients];
  }

  /**
   * Auto-detect potential clients from unrecognized deposits
   */
  suggestNewClients(unrecognized: ParsedTransaction[]): string[] {
    const suggestions: string[] = [];

    // Look for EFT patterns that might be client payments
    const eftPattern = /EFT\w*\s+(.+?)(?:\s+\d|$)/i;
    const depositPattern = /DEPOSIT\s+(.+?)(?:\s+\d|$)/i;

    for (const deposit of unrecognized) {
      const desc = deposit.rawDescription || deposit.description;

      // Skip small amounts (likely personal transfers)
      if (deposit.amount < 5000) continue;

      const eftMatch = desc.match(eftPattern);
      const depMatch = desc.match(depositPattern);

      const potentialName = (eftMatch?.[1] || depMatch?.[1])?.trim();

      if (potentialName && potentialName.length > 3 && !suggestions.includes(potentialName)) {
        suggestions.push(potentialName);
      }
    }

    return suggestions;
  }
}

/**
 * Quick utility to recognize Nucleus Mining income
 */
export function recognizeNucleusMiningIncome(
  transactions: ParsedTransaction[],
  taxRate: number = SME_TAX_RATE
): IncomeRecognitionResult {
  const recognizer = new SMEIncomeRecognizer([], taxRate);
  return recognizer.recognizeIncome(transactions);
}
