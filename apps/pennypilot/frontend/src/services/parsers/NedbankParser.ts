import { BaseBankParser } from './BaseBankParser';
import type { ParsedTransaction } from '@/types';

/**
 * Nedbank CSV/Excel Parser
 *
 * Handles various Nedbank export formats:
 * - Standard CSV exports
 * - Excel statement downloads
 * - Different column variations
 *
 * Typical columns (may vary):
 * - Date | Transaction Date | Trans Date
 * - Description | Transaction Description | Narrative
 * - Amount | Transaction Amount | Debit/Credit
 * - Balance | Running Balance
 * - Reference (optional)
 */
export class NedbankParser extends BaseBankParser {
  get bankName(): string {
    return 'Nedbank';
  }

  get expectedColumns(): string[] {
    return ['date', 'description', 'amount'];
  }

  // Column name variations Nedbank might use
  private readonly columnMappings = {
    date: ['date', 'transaction date', 'trans date', 'value date', 'posting date'],
    description: ['description', 'transaction description', 'narrative', 'details', 'particulars'],
    amount: ['amount', 'transaction amount', 'value', 'money in/out'],
    debit: ['debit', 'debit amount', 'dr', 'money out', 'withdrawal'],
    credit: ['credit', 'credit amount', 'cr', 'money in', 'deposit'],
    balance: ['balance', 'running balance', 'available balance', 'closing balance'],
    reference: ['reference', 'ref', 'transaction reference', 'bank reference', 'statement number'],
  };

  mapRow(row: Record<string, string>, rowIndex: number): ParsedTransaction | null {
    // Normalize column names to lowercase
    const normalizedRow: Record<string, string> = {};
    for (const [key, value] of Object.entries(row)) {
      normalizedRow[key.toLowerCase().trim()] = value?.toString() || '';
    }

    // Find the correct column for each field
    const dateValue = this.findColumn(normalizedRow, this.columnMappings.date);
    const descValue = this.findColumn(normalizedRow, this.columnMappings.description);
    const balanceValue = this.findColumn(normalizedRow, this.columnMappings.balance);
    const refValue = this.findColumn(normalizedRow, this.columnMappings.reference);

    // Handle amount (might be single column or separate debit/credit)
    let amount: number;
    const amountValue = this.findColumn(normalizedRow, this.columnMappings.amount);
    const debitValue = this.findColumn(normalizedRow, this.columnMappings.debit);
    const creditValue = this.findColumn(normalizedRow, this.columnMappings.credit);

    if (amountValue && amountValue.trim() !== '') {
      amount = this.parseAmount(amountValue);
    } else if (debitValue || creditValue) {
      const debit = debitValue && debitValue.trim() !== '' ? Math.abs(this.parseAmount(debitValue)) : 0;
      const credit = creditValue && creditValue.trim() !== '' ? Math.abs(this.parseAmount(creditValue)) : 0;
      amount = credit - debit; // Credits positive, debits negative
    } else {
      this.errors.push({
        row: rowIndex,
        field: 'amount',
        message: 'No amount column found',
        rawValue: row,
      });
      return null;
    }

    // Validate required fields
    if (!dateValue || dateValue.trim() === '') {
      // Skip rows without dates (likely headers or empty rows)
      return null;
    }

    if (!descValue || descValue.trim() === '') {
      // Skip rows without description
      return null;
    }

    // Skip rows that look like headers or summaries
    if (this.isHeaderRow(descValue) || this.isSummaryRow(descValue)) {
      return null;
    }

    try {
      return {
        transactionDate: this.parseDate(dateValue),
        description: this.cleanDescription(descValue),
        amount: amount,
        balanceAfter: balanceValue && balanceValue.trim() !== '' ? this.parseAmount(balanceValue) : null,
        bankReference: refValue || this.extractReference(descValue),
        rawDescription: descValue,
        importSource: 'csv',
      };
    } catch (error) {
      this.errors.push({
        row: rowIndex,
        field: 'parsing',
        message: error instanceof Error ? error.message : 'Parse error',
        rawValue: row,
      });
      return null;
    }
  }

  private findColumn(row: Record<string, string>, possibleNames: string[]): string | null {
    for (const name of possibleNames) {
      if (row[name] !== undefined && row[name] !== '') {
        return row[name];
      }
    }
    return null;
  }

  private isHeaderRow(description: string): boolean {
    const headerIndicators = ['description', 'transaction', 'details', 'narrative', 'particulars'];
    const lower = description.toLowerCase().trim();
    return headerIndicators.some((h) => lower === h);
  }

  private isSummaryRow(description: string): boolean {
    const summaryIndicators = [
      'opening balance',
      'closing balance',
      'total',
      'subtotal',
      'balance brought forward',
      'balance carried forward',
      'statement',
    ];
    const lower = description.toLowerCase();
    return summaryIndicators.some((s) => lower.includes(s));
  }

  private extractReference(description: string): string | null {
    // Try to extract reference numbers from description
    // Common patterns: REF:123456, *123456*, #123456
    const patterns = [/REF[:\s]*(\w+)/i, /\*(\d+)\*/, /#(\d+)/, /CARD\s+(\d{4})/i, /(\d{6,})/];

    for (const pattern of patterns) {
      const match = pattern.exec(description);
      if (match) {
        return match[1];
      }
    }
    return null;
  }
}
