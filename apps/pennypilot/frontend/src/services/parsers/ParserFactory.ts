import { NedbankParser } from './NedbankParser';
import type { BaseBankParser } from './BaseBankParser';
import type { BankFormat } from '@/types';

export class ParserFactory {
  private static parsers: Map<BankFormat, new () => BaseBankParser> = new Map([
    ['nedbank', NedbankParser],
    // Future parsers:
    // ['fnb', FnbParser],
    // ['absa', AbsaParser],
    // ['standard', StandardBankParser],
    // ['capitec', CapitecParser],
  ]);

  static create(format: BankFormat): BaseBankParser {
    const ParserClass = this.parsers.get(format);
    if (!ParserClass) {
      throw new Error(`No parser available for bank format: ${format}`);
    }
    return new ParserClass();
  }

  static detectFormat(headers: string[]): BankFormat {
    const normalized = headers.map((h) => h.toLowerCase().trim());

    // Nedbank detection heuristics
    if (
      normalized.some((h) => h.includes('nedbank')) ||
      (normalized.includes('narrative') && normalized.includes('balance')) ||
      normalized.includes('money in') ||
      normalized.includes('money out')
    ) {
      return 'nedbank';
    }

    // FNB detection (future)
    if (normalized.some((h) => h.includes('fnb')) || normalized.includes('first national')) {
      return 'fnb';
    }

    // ABSA detection (future)
    if (normalized.some((h) => h.includes('absa'))) {
      return 'absa';
    }

    // Standard Bank detection (future)
    if (normalized.some((h) => h.includes('standard bank'))) {
      return 'standard';
    }

    // Capitec detection (future)
    if (normalized.some((h) => h.includes('capitec'))) {
      return 'capitec';
    }

    // Default to nedbank for now (most common)
    return 'nedbank';
  }

  static getSupportedFormats(): BankFormat[] {
    return Array.from(this.parsers.keys());
  }
}
