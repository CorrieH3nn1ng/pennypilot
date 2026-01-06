/**
 * Universal Statement Parser
 *
 * Format-blind parsing for any bank statement format:
 * - CSV: Nedbank format with running balance
 * - OFX: Open Financial Exchange (XML) with FITID and ledger balance
 * - QIF: Quicken Interchange Format
 * - XLSX: Excel spreadsheet
 *
 * The parser extracts:
 * - Transactions with date, description, amount
 * - Running balance (where available)
 * - Official ledger balance (from OFX/PDF)
 * - Unique transaction IDs (for deduplication)
 */

import type { ParsedTransaction, ParseResult } from '@/types';
import { NedbankParser } from './NedbankParser';

export type StatementFormat = 'csv' | 'ofx' | 'ofc' | 'qif' | 'xlsx' | 'pdf' | 'unknown';

export interface UniversalParseResult extends ParseResult {
  format: StatementFormat;
  bankName: string | null;
  accountNumber: string | null;
  openingBalance: number | null;
  ledgerBalance: number | null;
  ledgerBalanceDate: string | null;
  dateRange: { start: string; end: string } | null;
  currency: string;
}

export interface OFXTransaction {
  date: string;
  amount: number;
  fitId: string;
  name: string;
  type: string;
}

// Noise patterns to filter out
const NOISE_PATTERNS = [
  /^CARRIED FORWARD$/i,
  /^BROUGHT FORWARD$/i,
  /^PROVISIONAL STATEMENT$/i,
  /^VAT \d+\/\d+-\d+\/\d+ = R[\d.]+$/i,
  /^Opening Balance$/i,
  /^Closing Balance$/i,
];

export class UniversalStatementParser {
  /**
   * Detect format from file extension and content
   */
  static detectFormat(file: File): StatementFormat {
    const ext = file.name.split('.').pop()?.toLowerCase();

    switch (ext) {
      case 'csv':
        return 'csv';
      case 'ofx':
        return 'ofx';
      case 'ofc':
        return 'ofc';
      case 'qif':
        return 'qif';
      case 'xlsx':
      case 'xls':
        return 'xlsx';
      case 'pdf':
        return 'pdf';
      default:
        return 'unknown';
    }
  }

  /**
   * Parse any statement format
   */
  static async parse(file: File): Promise<UniversalParseResult> {
    const format = this.detectFormat(file);

    switch (format) {
      case 'csv':
        return this.parseCSV(file);
      case 'ofx':
        return this.parseOFX(file);
      case 'ofc':
        return this.parseOFC(file);
      case 'qif':
        return this.parseQIF(file);
      case 'xlsx':
        return this.parseXLSX(file);
      case 'pdf':
        return this.parsePDF(file);
      default:
        return {
          success: false,
          format: 'unknown',
          bankName: null,
          accountNumber: null,
          openingBalance: null,
          ledgerBalance: null,
          ledgerBalanceDate: null,
          dateRange: null,
          currency: 'ZAR',
          transactions: [],
          errors: [{ row: 0, field: 'format', message: `Unsupported file format: ${file.name}`, rawValue: null }],
          warnings: [],
          stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
        };
    }
  }

  /**
   * Parse CSV (delegate to NedbankParser)
   */
  private static async parseCSV(file: File): Promise<UniversalParseResult> {
    const parser = new NedbankParser();
    const result = await parser.parseFile(file);

    // Filter noise and extract ledger balance from last transaction
    const filtered = result.transactions.filter(tx => !this.isNoiseTransaction(tx.description));
    const firstTx = filtered[0];
    const lastTx = filtered[filtered.length - 1];

    // Calculate opening balance: first transaction's balance minus its amount
    let openingBalance: number | null = null;
    if (firstTx && firstTx.balanceAfter !== null) {
      openingBalance = firstTx.balanceAfter - firstTx.amount;
    }

    return {
      ...result,
      transactions: filtered,
      format: 'csv',
      bankName: 'Nedbank',
      accountNumber: null, // Could extract from header
      openingBalance,
      ledgerBalance: lastTx?.balanceAfter ?? null,
      ledgerBalanceDate: lastTx?.transactionDate ?? null,
      dateRange: result.stats.dateRange,
      currency: 'ZAR',
    };
  }

  /**
   * Parse OFX (Open Financial Exchange XML)
   */
  private static async parseOFX(file: File): Promise<UniversalParseResult> {
    const text = await file.text();
    const transactions: ParsedTransaction[] = [];
    const errors: any[] = [];

    try {
      // Extract account info
      const bankIdMatch = text.match(/<BANKID>([^<]+)/);
      const acctIdMatch = text.match(/<ACCTID>([^<]+)/);
      const currencyMatch = text.match(/<CURDEF>([^<]+)/);

      // Extract ledger balance
      const ledgerAmtMatch = text.match(/<LEDGERBAL>[\s\S]*?<BALAMT>([^<]+)/);
      const ledgerDateMatch = text.match(/<LEDGERBAL>[\s\S]*?<DTASOF>([^<]+)/);

      // Parse all transactions
      const txRegex = /<STMTTRN>([\s\S]*?)<\/STMTTRN>/g;
      let match;
      let rowIndex = 0;

      while ((match = txRegex.exec(text)) !== null) {
        rowIndex++;
        const txBlock = match[1];

        const dateMatch = txBlock.match(/<DTPOSTED>(\d{8})/);
        const amountMatch = txBlock.match(/<TRNAMT>([^<]+)/);
        const fitIdMatch = txBlock.match(/<FITID>([^<]+)/);
        const nameMatch = txBlock.match(/<NAME>([^<]+)/);

        if (!dateMatch || !amountMatch || !nameMatch) continue;

        const description = nameMatch[1].trim();

        // Skip noise
        if (this.isNoiseTransaction(description)) continue;

        // Parse date: YYYYMMDD -> YYYY-MM-DD
        const dateStr = dateMatch[1];
        const transactionDate = `${dateStr.slice(0, 4)}-${dateStr.slice(4, 6)}-${dateStr.slice(6, 8)}`;

        transactions.push({
          transactionDate,
          description,
          amount: parseFloat(amountMatch[1]),
          balanceAfter: null, // OFX doesn't have per-transaction balance
          bankReference: fitIdMatch?.[1] ?? null,
          rawDescription: description,
          importSource: 'ofx',
        });
      }

      // Calculate date range
      let dateRange: { start: string; end: string } | null = null;
      if (transactions.length > 0) {
        const dates = transactions.map(t => t.transactionDate).sort();
        dateRange = { start: dates[0], end: dates[dates.length - 1] };
      }

      // Parse ledger balance date
      let ledgerBalanceDate: string | null = null;
      if (ledgerDateMatch) {
        const d = ledgerDateMatch[1];
        ledgerBalanceDate = `${d.slice(0, 4)}-${d.slice(4, 6)}-${d.slice(6, 8)}`;
      }

      return {
        success: true,
        format: 'ofx',
        bankName: bankIdMatch?.[1] ?? 'Unknown',
        accountNumber: acctIdMatch?.[1] ?? null,
        openingBalance: null, // OFX doesn't have per-transaction balance
        ledgerBalance: ledgerAmtMatch ? parseFloat(ledgerAmtMatch[1]) : null,
        ledgerBalanceDate,
        dateRange,
        currency: currencyMatch?.[1] ?? 'ZAR',
        transactions,
        errors,
        warnings: [],
        stats: {
          totalRows: rowIndex,
          parsedRows: transactions.length,
          skippedRows: rowIndex - transactions.length,
          dateRange,
        },
      };
    } catch (error) {
      return {
        success: false,
        format: 'ofx',
        bankName: null,
        accountNumber: null,
        openingBalance: null,
        ledgerBalance: null,
        ledgerBalanceDate: null,
        dateRange: null,
        currency: 'ZAR',
        transactions: [],
        errors: [{ row: 0, field: 'parsing', message: error instanceof Error ? error.message : 'OFX parse error', rawValue: null }],
        warnings: [],
        stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
      };
    }
  }

  /**
   * Parse OFC (older Microsoft Money format)
   */
  private static async parseOFC(file: File): Promise<UniversalParseResult> {
    const text = await file.text();
    const transactions: ParsedTransaction[] = [];

    try {
      // Extract account info
      const bankIdMatch = text.match(/<BANKID>([^\n<]+)/);
      const acctIdMatch = text.match(/<ACCTID>([^\n<]+)/);
      const ledgerMatch = text.match(/<LEDGER>([^\n<]+)/);

      // Parse transactions (similar to OFX but slightly different structure)
      const txRegex = /<STMTTRN>([\s\S]*?)<\/STMTTRN>/g;
      let match;

      while ((match = txRegex.exec(text)) !== null) {
        const txBlock = match[1];

        const dateMatch = txBlock.match(/<DTPOSTED>(\d{8})/);
        const amountMatch = txBlock.match(/<TRNAMT>([^\n<]+)/);
        const nameMatch = txBlock.match(/<NAME>([^\n<]+)/);
        const fitIdMatch = txBlock.match(/<FITID>([^\n<]+)/);

        if (!dateMatch || !amountMatch || !nameMatch) continue;

        const description = nameMatch[1].trim();
        if (this.isNoiseTransaction(description)) continue;

        const dateStr = dateMatch[1];
        const transactionDate = `${dateStr.slice(0, 4)}-${dateStr.slice(4, 6)}-${dateStr.slice(6, 8)}`;

        transactions.push({
          transactionDate,
          description,
          amount: parseFloat(amountMatch[1]),
          balanceAfter: null,
          bankReference: fitIdMatch?.[1] ?? null,
          rawDescription: description,
          importSource: 'ofc',
        });
      }

      const dateRange = transactions.length > 0
        ? { start: transactions[0].transactionDate, end: transactions[transactions.length - 1].transactionDate }
        : null;

      return {
        success: true,
        format: 'ofc',
        bankName: bankIdMatch?.[1]?.trim() ?? 'Unknown',
        accountNumber: acctIdMatch?.[1]?.trim() ?? null,
        openingBalance: null, // OFC doesn't have per-transaction balance
        ledgerBalance: ledgerMatch ? parseFloat(ledgerMatch[1]) : null,
        ledgerBalanceDate: null,
        dateRange,
        currency: 'ZAR',
        transactions,
        errors: [],
        warnings: [],
        stats: { totalRows: transactions.length, parsedRows: transactions.length, skippedRows: 0, dateRange },
      };
    } catch (error) {
      return {
        success: false,
        format: 'ofc',
        bankName: null,
        accountNumber: null,
        openingBalance: null,
        ledgerBalance: null,
        ledgerBalanceDate: null,
        dateRange: null,
        currency: 'ZAR',
        transactions: [],
        errors: [{ row: 0, field: 'parsing', message: 'OFC parse error', rawValue: null }],
        warnings: [],
        stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
      };
    }
  }

  /**
   * Parse QIF (Quicken Interchange Format)
   */
  private static async parseQIF(file: File): Promise<UniversalParseResult> {
    const text = await file.text();
    const transactions: ParsedTransaction[] = [];
    const lines = text.split('\n');

    let currentTx: Partial<ParsedTransaction> = {};

    for (const line of lines) {
      const code = line[0];
      const value = line.slice(1).trim();

      switch (code) {
        case 'D': // Date (MM/DD/YY format)
          const dateParts = value.split('/');
          if (dateParts.length === 3) {
            let [month, day, year] = dateParts;
            // Handle 2-digit year
            if (year.length === 2) {
              year = year.startsWith('9') ? `19${year}` : `20${year}`;
            }
            currentTx.transactionDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
          }
          break;
        case 'T': // Amount
          currentTx.amount = parseFloat(value.replace(/,/g, ''));
          break;
        case 'M': // Memo/Description
          currentTx.description = value;
          currentTx.rawDescription = value;
          break;
        case '^': // End of transaction
          if (currentTx.transactionDate && currentTx.amount !== undefined && currentTx.description) {
            if (!this.isNoiseTransaction(currentTx.description)) {
              transactions.push({
                transactionDate: currentTx.transactionDate,
                description: currentTx.description,
                amount: currentTx.amount,
                balanceAfter: null, // QIF doesn't have balance
                bankReference: null,
                rawDescription: currentTx.rawDescription || currentTx.description,
                importSource: 'qif',
              });
            }
          }
          currentTx = {};
          break;
      }
    }

    const dateRange = transactions.length > 0
      ? {
          start: transactions.map(t => t.transactionDate).sort()[0],
          end: transactions.map(t => t.transactionDate).sort().pop()!,
        }
      : null;

    return {
      success: true,
      format: 'qif',
      bankName: null,
      accountNumber: null,
      openingBalance: null, // QIF doesn't provide balance
      ledgerBalance: null, // QIF doesn't provide ledger balance
      ledgerBalanceDate: null,
      dateRange,
      currency: 'ZAR',
      transactions,
      errors: [],
      warnings: ['QIF format does not include running balance - verification not possible'],
      stats: { totalRows: transactions.length, parsedRows: transactions.length, skippedRows: 0, dateRange },
    };
  }

  /**
   * Parse XLSX (Excel) - Nedbank format (headerless, same as CSV)
   */
  private static async parseXLSX(file: File): Promise<UniversalParseResult> {
    try {
      // Dynamic import xlsx library
      const XLSX = await import('xlsx');
      const arrayBuffer = await file.arrayBuffer();
      const workbook = XLSX.read(arrayBuffer, { type: 'array' });
      const sheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[sheetName];
      const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 }) as any[][];

      // DEBUG: Log what we're getting from the xlsx library
      console.log('XLSX Parser Debug:');
      console.log('  Total rows from xlsx:', rows.length);
      console.log('  First 10 rows:', rows.slice(0, 10));

      const transactions: ParsedTransaction[] = [];

      // Nedbank date format: 25Jan2025
      const nedbankDatePattern = /^(\d{1,2})([A-Za-z]{3})(\d{4})$/;
      const months: Record<string, string> = {
        jan: '01', feb: '02', mar: '03', apr: '04', may: '05', jun: '06',
        jul: '07', aug: '08', sep: '09', oct: '10', nov: '11', dec: '12',
      };

      // Metadata rows to skip
      const metadataPatterns = [
        /statement\s*enquiry/i,
        /account\s*number/i,
        /account\s*description/i,
        /statement\s*number/i,
        /^date$/i,
      ];

      let skippedReasons: Record<string, number> = {};

      for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (!row || row.length < 3) {
          skippedReasons['row too short or empty'] = (skippedReasons['row too short or empty'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (too short) - length=${row?.length}, data=`, row);
          continue;
        }

        const [dateVal, description, amount, balance] = row;
        const dateStr = String(dateVal || '').trim();
        const descStr = String(description || '').trim();

        // Skip metadata/header rows
        const combined = `${dateStr} ${descStr}`.toLowerCase();
        if (metadataPatterns.some(p => p.test(combined))) {
          skippedReasons['metadata/header'] = (skippedReasons['metadata/header'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (metadata/header) - "${combined}"`);
          continue;
        }

        // Skip noise rows (CARRIED FORWARD, etc.)
        if (this.isNoiseTransaction(descStr)) {
          skippedReasons['noise transaction'] = (skippedReasons['noise transaction'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (noise) - "${descStr}"`);
          continue;
        }

        // Skip rows without amount
        if (amount === undefined || amount === null || amount === '') {
          skippedReasons['no amount'] = (skippedReasons['no amount'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (no amount) - date="${dateVal}", desc="${descStr}", amount="${amount}"`);
          continue;
        }

        // Parse date - try Nedbank format first (25Jan2025)
        let transactionDate: string | null = null;

        if (typeof dateVal === 'number') {
          // Excel serial date to ISO string
          // Excel epoch: day 1 = Jan 1, 1900, stored as serial number
          // Use UTC to avoid timezone issues: (serial - 25569) days from Unix epoch
          const utcMs = (dateVal - 25569) * 86400000;
          const date = new Date(utcMs);
          const year = date.getUTCFullYear();
          const month = String(date.getUTCMonth() + 1).padStart(2, '0');
          const day = String(date.getUTCDate()).padStart(2, '0');
          transactionDate = `${year}-${month}-${day}`;
          console.log(`  Row ${i}: Serial date ${dateVal} -> ${transactionDate}`);
        } else {
          // Try Nedbank format: 25Jan2025
          const match = dateStr.match(nedbankDatePattern);
          if (match) {
            const [, day, monthStr, year] = match;
            const month = months[monthStr.toLowerCase()];
            if (month) {
              transactionDate = `${year}-${month}-${day.padStart(2, '0')}`;
            }
          }
        }

        // Skip if no valid date
        if (!transactionDate) {
          skippedReasons['invalid date'] = (skippedReasons['invalid date'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (invalid date) - dateVal="${dateVal}" (type: ${typeof dateVal})`);
          continue;
        }

        // Skip empty descriptions
        if (!descStr) {
          skippedReasons['empty description'] = (skippedReasons['empty description'] || 0) + 1;
          console.log(`  Row ${i}: SKIPPED (empty description)`);
          continue;
        }

        // Parse amount
        let parsedAmount: number;
        if (typeof amount === 'number') {
          parsedAmount = amount;
        } else {
          const amountStr = String(amount).replace(/[^\d.-]/g, '');
          parsedAmount = parseFloat(amountStr);
          if (isNaN(parsedAmount)) continue;
        }

        // Parse balance
        let parsedBalance: number | null = null;
        if (balance !== undefined && balance !== null && balance !== '') {
          if (typeof balance === 'number') {
            parsedBalance = balance;
          } else {
            const balStr = String(balance).replace(/[^\d.-]/g, '');
            parsedBalance = parseFloat(balStr);
            if (isNaN(parsedBalance)) parsedBalance = null;
          }
        }

        console.log(`  Row ${i}: PARSED - date=${transactionDate}, amount=${parsedAmount}, balance=${parsedBalance}, desc="${descStr.substring(0, 30)}..."`);

        transactions.push({
          transactionDate,
          description: descStr,
          amount: parsedAmount,
          balanceAfter: parsedBalance,
          bankReference: null,
          rawDescription: descStr,
          importSource: 'xlsx',
        });
      }

      // Log skip summary
      console.log('XLSX Parser Summary:');
      console.log('  Total rows:', rows.length);
      console.log('  Parsed transactions:', transactions.length);
      console.log('  Skip reasons:', skippedReasons);

      // Sort transactions by date to ensure correct first/last
      transactions.sort((a, b) => a.transactionDate.localeCompare(b.transactionDate));

      const firstTx = transactions[0];
      const lastTx = transactions[transactions.length - 1];
      const dateRange = transactions.length > 0
        ? { start: firstTx.transactionDate, end: lastTx.transactionDate }
        : null;

      // Log for debugging
      console.log('Excel Parser - First TX:', firstTx?.transactionDate, firstTx?.amount, firstTx?.balanceAfter);
      console.log('Excel Parser - Last TX:', lastTx?.transactionDate, lastTx?.amount, lastTx?.balanceAfter);

      // Calculate opening balance: first transaction's balance minus its amount
      // Formula: If first TX shows balance R5000 after -R100 debit, opening was R5100
      let openingBalance: number | null = null;
      if (firstTx && firstTx.balanceAfter !== null) {
        openingBalance = firstTx.balanceAfter - firstTx.amount;
        console.log('Opening balance calculation:', firstTx.balanceAfter, '-', firstTx.amount, '=', openingBalance);
      }
      console.log('Excel Parser - Final: openingBalance=', openingBalance, 'ledgerBalance=', lastTx?.balanceAfter);

      return {
        success: true,
        format: 'xlsx',
        bankName: 'Nedbank',
        accountNumber: null,
        openingBalance,
        ledgerBalance: lastTx?.balanceAfter ?? null,
        ledgerBalanceDate: lastTx?.transactionDate ?? null,
        dateRange,
        currency: 'ZAR',
        transactions,
        errors: [],
        warnings: [],
        stats: { totalRows: rows.length, parsedRows: transactions.length, skippedRows: rows.length - transactions.length, dateRange },
      };
    } catch (error) {
      return {
        success: false,
        format: 'xlsx',
        bankName: null,
        accountNumber: null,
        openingBalance: null,
        ledgerBalance: null,
        ledgerBalanceDate: null,
        dateRange: null,
        currency: 'ZAR',
        transactions: [],
        errors: [{ row: 0, field: 'parsing', message: error instanceof Error ? error.message : 'XLSX parse error', rawValue: null }],
        warnings: [],
        stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
      };
    }
  }

  /**
   * Parse PDF using pdf.js
   * Extracts text and parses Nedbank statement transaction patterns
   */
  private static async parsePDF(file: File): Promise<UniversalParseResult> {
    try {
      // Dynamic import pdfjs-dist
      const pdfjsLib = await import('pdfjs-dist');

      // Set worker source (use CDN for simplicity)
      pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${pdfjsLib.version}/pdf.worker.min.js`;

      const arrayBuffer = await file.arrayBuffer();
      const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

      let fullText = '';
      const transactions: ParsedTransaction[] = [];

      // Extract text from all pages
      for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();
        const pageText = textContent.items
          .map((item: any) => item.str)
          .join(' ');
        fullText += pageText + '\n';
      }

      // Extract account info
      const accountMatch = fullText.match(/Account\s*(?:number|no)?[:\s]*(\d{10,})/i);
      const closingBalanceMatch = fullText.match(/Closing\s*Balance[:\s]*R?\s*([\d,]+\.?\d*)/i);

      // Parse Nedbank PDF statement patterns
      // Pattern: DD Mon YYYY or DDMonYYYY followed by description and amounts
      // Example: "22 Nov 2025 PnP Crp Mounta4606390202677923 -1,066.71 36,375.28"

      // Split by lines for better parsing
      const lines = fullText.split(/\n/);

      for (const line of lines) {
        // Match date patterns
        const datePatterns = [
          // "22 Nov 2025" or "22Nov2025"
          /(\d{1,2})\s*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*(\d{4})/i,
          // "22/11/2025"
          /(\d{1,2})\/(\d{1,2})\/(\d{4})/,
        ];

        let transactionDate: string | null = null;
        let remainingText = line;

        for (const pattern of datePatterns) {
          const match = line.match(pattern);
          if (match) {
            if (match[2] && isNaN(parseInt(match[2]))) {
              // Month name format
              const monthMap: Record<string, string> = {
                jan: '01', feb: '02', mar: '03', apr: '04', may: '05', jun: '06',
                jul: '07', aug: '08', sep: '09', oct: '10', nov: '11', dec: '12',
              };
              const day = match[1].padStart(2, '0');
              const month = monthMap[match[2].toLowerCase()];
              const year = match[3];
              transactionDate = `${year}-${month}-${day}`;
            } else {
              // DD/MM/YYYY format
              const day = match[1].padStart(2, '0');
              const month = match[2].padStart(2, '0');
              const year = match[3];
              transactionDate = `${year}-${month}-${day}`;
            }
            remainingText = line.slice(match.index! + match[0].length).trim();
            break;
          }
        }

        if (!transactionDate) continue;

        // Extract amounts from the line (look for -X,XXX.XX or X,XXX.XX patterns)
        const amountPattern = /(-?[\d,]+\.\d{2})/g;
        const amounts: number[] = [];
        let amountMatch;
        while ((amountMatch = amountPattern.exec(remainingText)) !== null) {
          amounts.push(parseFloat(amountMatch[1].replace(/,/g, '')));
        }

        if (amounts.length === 0) continue;

        // Last amount is usually balance, second to last (or only) is transaction amount
        const amount = amounts.length > 1 ? amounts[amounts.length - 2] : amounts[0];
        const balance = amounts.length > 1 ? amounts[amounts.length - 1] : null;

        // Extract description (everything between date and first amount)
        const firstAmountIndex = remainingText.search(/(-?[\d,]+\.\d{2})/);
        let description = firstAmountIndex > 0
          ? remainingText.slice(0, firstAmountIndex).trim()
          : remainingText.trim();

        // Clean up description
        description = description.replace(/\s+/g, ' ').trim();

        if (!description || description.length < 3) continue;
        if (this.isNoiseTransaction(description)) continue;

        transactions.push({
          transactionDate,
          description,
          amount,
          balanceAfter: balance,
          bankReference: null,
          rawDescription: description,
          importSource: 'pdf',
        });
      }

      // Deduplicate (PDF can have repeated content)
      const seen = new Set<string>();
      const uniqueTransactions = transactions.filter(tx => {
        const key = `${tx.transactionDate}|${tx.amount}|${tx.description.substring(0, 15)}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      });

      // Sort by date
      uniqueTransactions.sort((a, b) => a.transactionDate.localeCompare(b.transactionDate));

      const dateRange = uniqueTransactions.length > 0
        ? {
            start: uniqueTransactions[0].transactionDate,
            end: uniqueTransactions[uniqueTransactions.length - 1].transactionDate,
          }
        : null;

      // Get closing balance
      let ledgerBalance: number | null = null;
      if (closingBalanceMatch) {
        ledgerBalance = parseFloat(closingBalanceMatch[1].replace(/,/g, ''));
      } else if (uniqueTransactions.length > 0) {
        const lastTx = uniqueTransactions[uniqueTransactions.length - 1];
        ledgerBalance = lastTx.balanceAfter;
      }

      // Calculate opening balance from first transaction
      let openingBalance: number | null = null;
      if (uniqueTransactions.length > 0) {
        const firstTx = uniqueTransactions[0];
        if (firstTx.balanceAfter !== null) {
          openingBalance = firstTx.balanceAfter - firstTx.amount;
        }
      }

      return {
        success: uniqueTransactions.length > 0,
        format: 'pdf',
        bankName: 'Nedbank',
        accountNumber: accountMatch?.[1] ?? null,
        openingBalance,
        ledgerBalance,
        ledgerBalanceDate: dateRange?.end ?? null,
        dateRange,
        currency: 'ZAR',
        transactions: uniqueTransactions,
        errors: uniqueTransactions.length === 0
          ? [{ row: 0, field: 'parsing', message: 'No transactions found in PDF. Try CSV or OFX export.', rawValue: null }]
          : [],
        warnings: ['PDF parsing may miss some transactions. Verify against CSV export.'],
        stats: {
          totalRows: transactions.length,
          parsedRows: uniqueTransactions.length,
          skippedRows: transactions.length - uniqueTransactions.length,
          dateRange,
        },
      };
    } catch (error) {
      console.error('PDF parsing error:', error);
      return {
        success: false,
        format: 'pdf',
        bankName: null,
        accountNumber: null,
        openingBalance: null,
        ledgerBalance: null,
        ledgerBalanceDate: null,
        dateRange: null,
        currency: 'ZAR',
        transactions: [],
        errors: [{
          row: 0,
          field: 'parsing',
          message: error instanceof Error ? error.message : 'PDF parse error. Try CSV or OFX export.',
          rawValue: null,
        }],
        warnings: [],
        stats: { totalRows: 0, parsedRows: 0, skippedRows: 0, dateRange: null },
      };
    }
  }

  /**
   * Check if transaction is noise (should be filtered)
   */
  private static isNoiseTransaction(description: string): boolean {
    return NOISE_PATTERNS.some(pattern => pattern.test(description));
  }

  /**
   * Merge results from multiple formats (CSV + OFX)
   * Uses OFX for official balance, CSV for running balance verification
   */
  static mergeResults(primary: UniversalParseResult, secondary: UniversalParseResult): UniversalParseResult {
    // Use secondary's balances if primary doesn't have them
    const openingBalance = primary.openingBalance ?? secondary.openingBalance;
    const ledgerBalance = primary.ledgerBalance ?? secondary.ledgerBalance;
    const ledgerBalanceDate = primary.ledgerBalanceDate ?? secondary.ledgerBalanceDate;

    // Deduplicate transactions by date + amount + description similarity
    const seen = new Set<string>();
    const merged: ParsedTransaction[] = [];

    for (const tx of [...primary.transactions, ...secondary.transactions]) {
      const key = `${tx.transactionDate}|${tx.amount}|${tx.description.substring(0, 20)}`;
      if (!seen.has(key)) {
        seen.add(key);
        merged.push(tx);
      }
    }

    // Sort by date
    merged.sort((a, b) => a.transactionDate.localeCompare(b.transactionDate));

    return {
      ...primary,
      transactions: merged,
      openingBalance,
      ledgerBalance,
      ledgerBalanceDate,
    };
  }
}
