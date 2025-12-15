import type { ParsedTransaction, ParseResult, ParseError } from '@/types';
import Papa from 'papaparse';

export abstract class BaseBankParser {
  protected errors: ParseError[] = [];
  protected warnings: string[] = [];

  abstract get bankName(): string;
  abstract get expectedColumns(): string[];
  abstract mapRow(row: Record<string, string>, rowIndex: number): ParsedTransaction | null;

  /**
   * Parse a file - subclasses can override for custom formats
   */
  async parseFile(file: File): Promise<ParseResult> {
    return new Promise((resolve) => {
      Papa.parse(file, {
        header: true,
        skipEmptyLines: true,
        complete: (results) => {
          const rows = results.data as Record<string, string>[];
          resolve(this.parse(rows));
        },
        error: (error) => {
          resolve({
            success: false,
            transactions: [],
            errors: [{ row: 0, field: 'file', message: error.message, rawValue: null }],
            warnings: [],
            stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
          });
        },
      });
    });
  }

  parse(rows: Record<string, string>[]): ParseResult {
    this.errors = [];
    this.warnings = [];

    const transactions: ParsedTransaction[] = [];
    let skippedRows = 0;

    for (let i = 0; i < rows.length; i++) {
      try {
        const transaction = this.mapRow(rows[i], i + 1);
        if (transaction) {
          transactions.push(transaction);
        } else {
          skippedRows++;
        }
      } catch (error) {
        skippedRows++;
        this.errors.push({
          row: i + 1,
          field: 'unknown',
          message: error instanceof Error ? error.message : 'Unknown parsing error',
          rawValue: rows[i],
        });
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

    return {
      success: this.errors.length === 0,
      transactions,
      errors: this.errors,
      warnings: this.warnings,
      stats: {
        totalRows: rows.length,
        parsedRows: transactions.length,
        skippedRows,
        dateRange,
      },
    };
  }

  protected parseAmount(value: string | number): number {
    if (typeof value === 'number') return value;

    // Handle South African number format (spaces as thousand separators)
    const cleaned = value
      .replace(/\s/g, '') // Remove spaces
      .replace(/R/gi, '') // Remove Rand symbol
      .replace(/,/g, '') // Remove commas (thousand separators)
      .trim();

    const amount = parseFloat(cleaned);
    if (isNaN(amount)) {
      throw new Error(`Invalid amount: ${value}`);
    }
    return amount;
  }

  protected parseDate(value: string): string {
    // Common South African date formats
    const formats = [
      /^(\d{4})-(\d{2})-(\d{2})$/, // 2024-01-15
      /^(\d{4})\/(\d{2})\/(\d{2})$/, // 2024/01/15
      /^(\d{2})\/(\d{2})\/(\d{4})$/, // 15/01/2024
      /^(\d{2})-(\d{2})-(\d{4})$/, // 15-01-2024
      /^(\d{2}) (\w{3}) (\d{4})$/, // 15 Jan 2024
    ];

    // Try ISO format first (YYYY-MM-DD)
    if (formats[0].test(value)) {
      return value;
    }

    // Try YYYY/MM/DD
    const yyyymmdd = formats[1].exec(value);
    if (yyyymmdd) {
      const [, year, month, day] = yyyymmdd;
      return `${year}-${month}-${day}`;
    }

    // Try DD/MM/YYYY
    const ddmmyyyy = formats[2].exec(value);
    if (ddmmyyyy) {
      const [, day, month, year] = ddmmyyyy;
      return `${year}-${month}-${day}`;
    }

    // Try DD-MM-YYYY
    const ddmmyyyy2 = formats[3].exec(value);
    if (ddmmyyyy2) {
      const [, day, month, year] = ddmmyyyy2;
      return `${year}-${month}-${day}`;
    }

    // Try DD Mon YYYY
    const ddMonyyyy = formats[4].exec(value);
    if (ddMonyyyy) {
      const [, day, monthStr, year] = ddMonyyyy;
      const monthMap: Record<string, string> = {
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
      const month = monthMap[monthStr.toLowerCase()];
      if (month) {
        return `${year}-${month}-${day.padStart(2, '0')}`;
      }
    }

    throw new Error(`Unrecognized date format: ${value}`);
  }

  protected cleanDescription(value: string): string {
    return value
      .replace(/\s+/g, ' ') // Normalize whitespace
      .trim()
      .substring(0, 500); // Limit length
  }
}
