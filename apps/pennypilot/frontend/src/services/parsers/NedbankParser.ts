import { BaseBankParser } from './BaseBankParser';
import type { ParsedTransaction, ParseResult } from '@/types';
import Papa from 'papaparse';

/**
 * Nedbank CSV Parser
 *
 * Handles Nedbank statement exports with format:
 * - Header rows with account info (skipped)
 * - No column headers for transaction data
 * - Columns: Date, Description, Amount, Balance
 * - Date format: 25Jan2025 (ddMMMyyyy)
 */
export class NedbankParser extends BaseBankParser {
  get bankName(): string {
    return 'Nedbank';
  }

  get expectedColumns(): string[] {
    return ['date', 'description', 'amount', 'balance'];
  }

  /**
   * Override the base parseFile method to handle Nedbank's headerless format
   */
  async parseFile(file: File): Promise<ParseResult> {
    return new Promise((resolve) => {
      Papa.parse(file, {
        header: false, // Nedbank CSV has no headers
        skipEmptyLines: true,
        complete: (results) => {
          const transactions: ParsedTransaction[] = [];
          this.errors = [];

          const rows = results.data as string[][];

          for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const transaction = this.mapRowArray(row, i);
            if (transaction) {
              transactions.push(transaction);
            }
          }

          // Calculate date range
          let dateRange: { start: string; end: string } | null = null;
          if (transactions.length > 0) {
            const dates = transactions.map((t) => t.transactionDate).sort();
            dateRange = {
              start: dates[0],
              end: dates[dates.length - 1],
            };
          }

          resolve({
            success: this.errors.length === 0,
            transactions,
            errors: this.errors,
            warnings: [],
            stats: {
              totalRows: rows.length,
              parsedRows: transactions.length,
              skippedRows: rows.length - transactions.length - this.errors.length,
              dateRange,
            },
          });
        },
        error: (error) => {
          resolve({
            success: false,
            transactions: [],
            errors: [
              {
                row: 0,
                field: 'file',
                message: error.message,
                rawValue: null,
              },
            ],
            warnings: [],
            stats: {
              totalRows: 0,
              parsedRows: 0,
              skippedRows: 0,
              dateRange: null,
            },
          });
        },
      });
    });
  }

  /**
   * Map a row array (no headers) to a transaction
   */
  private mapRowArray(row: string[], rowIndex: number): ParsedTransaction | null {
    // Skip rows with less than 3 columns
    if (row.length < 3) {
      return null;
    }

    const [dateStr, description, amountStr, balanceStr] = row.map((v) => v?.trim() || '');

    // Skip metadata/header rows
    if (this.isMetadataRow(dateStr, description)) {
      return null;
    }

    // Skip summary rows
    if (this.isSummaryRow(description)) {
      return null;
    }

    // Validate date format (should be like 25Jan2025)
    if (!this.isValidNedbankDate(dateStr)) {
      return null;
    }

    // Skip rows without amount (info rows like VAT notices)
    if (!amountStr || amountStr === '') {
      return null;
    }

    try {
      const amount = this.parseAmount(amountStr);
      const balance = balanceStr ? this.parseAmount(balanceStr) : null;
      const transactionDate = this.parseNedbankDate(dateStr);

      return {
        transactionDate,
        description: this.cleanDescription(description),
        amount,
        balanceAfter: balance,
        bankReference: this.extractReference(description),
        rawDescription: description,
        importSource: 'csv',
      };
    } catch (error) {
      this.errors.push({
        row: rowIndex,
        field: 'parsing',
        message: error instanceof Error ? error.message : 'Parse error',
        rawValue: row.join(','),
      });
      return null;
    }
  }

  /**
   * Check if row is metadata (account info headers)
   */
  private isMetadataRow(dateStr: string, description: string): boolean {
    const metadataIndicators = [
      'statement enquiry',
      'account number',
      'account description',
      'statement number',
    ];
    const combined = `${dateStr} ${description}`.toLowerCase();
    return metadataIndicators.some((m) => combined.includes(m));
  }

  /**
   * Check if this is a summary/info row to skip
   */
  private isSummaryRow(description: string): boolean {
    const summaryIndicators = [
      'opening balance',
      'closing balance',
      'carried forward',
      'brought forward',
      'balance brought',
      'balance carried',
      'total',
      'subtotal',
    ];
    const lower = description.toLowerCase();
    return summaryIndicators.some((s) => lower.includes(s));
  }

  /**
   * Validate Nedbank date format (25Jan2025)
   */
  private isValidNedbankDate(dateStr: string): boolean {
    // Pattern: 1-2 digits + 3 letter month + 4 digit year
    const pattern = /^\d{1,2}[A-Za-z]{3}\d{4}$/;
    return pattern.test(dateStr);
  }

  /**
   * Parse Nedbank date format (25Jan2025 -> Date)
   */
  private parseNedbankDate(dateStr: string): string {
    const months: Record<string, string> = {
      jan: '01',
      feb: '02',
      mar: '03',
      apr: '04',
      may: '05',
      jun: '06',
      jul: '07',
      aug: '08',
      sep: '09',
      oct: '10',
      nov: '11',
      dec: '12',
    };

    // Extract day, month, year from format like "25Jan2025"
    const match = dateStr.match(/^(\d{1,2})([A-Za-z]{3})(\d{4})$/);
    if (!match) {
      throw new Error(`Invalid date format: ${dateStr}`);
    }

    const [, day, monthStr, year] = match;
    const month = months[monthStr.toLowerCase()];
    if (!month) {
      throw new Error(`Invalid month: ${monthStr}`);
    }

    // Return ISO format: YYYY-MM-DD
    return `${year}-${month}-${day.padStart(2, '0')}`;
  }

  /**
   * Extract reference numbers from description
   */
  private extractReference(description: string): string | null {
    // Common patterns in Nedbank descriptions
    const patterns = [
      /\*(\w+)\*/, // *reference*
      /(\d{10,})/, // Long number sequences
      /CARD\s+(\d{4})/i, // Card last 4 digits
    ];

    for (const pattern of patterns) {
      const match = pattern.exec(description);
      if (match) {
        return match[1];
      }
    }
    return null;
  }

  // Override base mapRow - not used but required by interface
  mapRow(row: Record<string, string>, rowIndex: number): ParsedTransaction | null {
    // Convert to array format
    const values = Object.values(row);
    return this.mapRowArray(values, rowIndex);
  }
}
