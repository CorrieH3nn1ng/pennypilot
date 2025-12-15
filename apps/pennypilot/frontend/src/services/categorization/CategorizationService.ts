import type { Transaction, Category } from '@/types';

interface CategoryRule {
  categoryName: string;
  keywords: string[];
  isIncome?: boolean;
}

// South African merchant keywords and patterns
const CATEGORY_RULES: CategoryRule[] = [
  // Expenses
  {
    categoryName: 'Groceries',
    keywords: [
      'CHECKERS', 'PICK N PAY', 'PICK-N-PAY', 'PNP', 'SHOPRITE', 'WOOLWORTHS',
      'SPAR', 'FOOD LOVERS', 'FOODLOVERS', 'MAKRO', 'FRUIT & VEG', 'FRUIT AND VEG',
      'BOXER', 'USAVE', 'OK FOODS', 'CAMBRIDGE FOOD', 'MASSMART',
    ],
  },
  {
    categoryName: 'Transport',
    keywords: [
      'UBER TRIP', 'BOLT RIDE', 'GAUTRAIN', 'MBT', 'METRORAIL', 'MYCITI',
      'GOLDEN ARROW', 'INTERCAPE', 'GREYHOUND', 'TRANSLUX', 'E-TOLL', 'ETOLL',
      'SANRAL', 'PARKING', 'AVIS', 'HERTZ', 'EUROPCAR', 'BUDGET CAR',
    ],
  },
  {
    categoryName: 'Fuel',
    keywords: [
      'ENGEN', 'CALTEX', 'SHELL', 'SASOL', 'BP ', 'TOTAL GARAGE', 'TOTALENERGIES',
      'PETROL', 'DIESEL', 'SERVICE STATION', 'FUEL', 'ASTRON ENERGY',
    ],
  },
  {
    categoryName: 'Utilities',
    keywords: [
      'ESKOM', 'CITY POWER', 'CITY OF JOHANNESBURG', 'CITY OF CAPE TOWN',
      'CITY OF TSHWANE', 'ELECTRICITY', 'PREPAID ELEC', 'WATER BILL',
      'MUNICIPALITY', 'TELKOM', 'FIBRE', 'VODACOM', 'MTN', 'CELL C',
      'RAIN MOBILE', 'AFRIHOST', 'WEBAFRICA', 'COOL IDEAS', 'VUMATEL',
    ],
  },
  {
    categoryName: 'Entertainment',
    keywords: [
      'NETFLIX', 'SHOWMAX', 'DSTV', 'MULTICHOICE', 'STER KINEKOR', 'STERKINEKOR',
      'NU METRO', 'CINEMA', 'COMPUTICKET', 'WEBTICKETS', 'TICKETPRO',
      'DISNEY+', 'DISNEY PLUS', 'AMAZON PRIME', 'YOUTUBE', 'APPLE TV',
    ],
  },
  {
    categoryName: 'Dining Out',
    keywords: [
      'UBER EATS', 'UBEREATS', 'MR DELIVERY', 'MR D', 'BOLT FOOD',
      'NANDOS', 'NANDO\'S', 'KFC', 'MCDONALDS', 'MCDONALD\'S', 'BURGER KING',
      'WIMPY', 'STEERS', 'SPUR', 'OCEAN BASKET', 'FISHAWAYS', 'DEBONAIRS',
      'ROMANS PIZZA', 'DOMINOS', 'PIZZA HUT', 'TASHAS', 'VIDA E CAFFE',
      'MUGG & BEAN', 'MUGG AND BEAN', 'SEATTLE', 'STARBUCKS', 'BOOTLEGGER',
      'RESTAURANT', 'CAFE', 'COFFEE', 'ROCOMAMAS', 'HUSSAR GRILL',
    ],
  },
  {
    categoryName: 'Healthcare',
    keywords: [
      'DISCHEM', 'DIS-CHEM', 'CLICKS PHARM', 'PHARMACY', 'DOCTOR', 'DR ',
      'MEDICAL', 'PATHCARE', 'LANCET', 'AMPATH', 'NETCARE', 'MEDICLINIC',
      'LIFE HOSPITAL', 'DENTIST', 'OPTOMETRIST', 'SPEC-SAVERS', 'SPECSAVERS',
    ],
  },
  {
    categoryName: 'Shopping',
    keywords: [
      'TAKEALOT', 'AMAZON.', 'GAME STORES', 'INCREDIBLE CONNECTION',
      'INCREDIBLE CONNECT', 'BASH', 'SUPERBALIST', 'ZANDO', 'MR PRICE',
      'MRPRICE', 'EDGARS', 'JET STORES', 'FOSCHINI', 'TRUWORTHS', 'ACKERMANS',
      'PEP STORES', 'COTTON ON', 'H&M', 'ZARA', 'WOOLWORTHS CLOTH',
      'BUILDERS WAREHOUSE', 'BUILDERS EXPRESS', 'CASHBUILD', 'LEROY MERLIN',
      'CTM', 'TILE AFRICA', 'HIRSCHS', 'TAFELBERG', 'CNA', 'EXCLUSIVE BOOKS',
    ],
  },
  {
    categoryName: 'Insurance',
    keywords: [
      'OLD MUTUAL', 'SANLAM', 'DISCOVERY INSURE', 'MOMENTUM', 'OUTSURANCE',
      'SANTAM', 'HOLLARD', 'BUDGET INSURANCE', 'FIRST FOR WOMEN', 'DIALDIRECT',
      'MIWAY', 'KING PRICE', 'AUTO & GENERAL', 'TELESURE', 'INSURANCE',
    ],
  },
  {
    categoryName: 'Bank Fees',
    keywords: [
      'MONTHLY FEE', 'SERVICE FEE', 'CASH HANDLING', 'ATM FEE', 'LEDGER FEE',
      'ADMIN FEE', 'ACCOUNT FEE', 'MAINTENANCE FEE', 'CARD FEE', 'SMS NOTIFICATION',
      'NOTIFICATION FEE', 'STOP ORDER FEE', 'DEBIT ORDER FEE', 'UNPAID FEE',
      'DEBIT CARD REPL', 'CHEQUE BOOK', 'STATEMENT FEE',
    ],
  },
  {
    categoryName: 'Subscriptions',
    keywords: [
      'SPOTIFY', 'APPLE.COM', 'APPLE MUSIC', 'GOOGLE PLAY', 'MICROSOFT',
      'ADOBE', 'CANVA', 'DROPBOX', 'ICLOUD', 'OPENAI', 'CHATGPT',
      'LINKEDIN PREMIUM', 'AUDIBLE', 'KINDLE',
    ],
  },
  {
    categoryName: 'Medical Aid',
    keywords: [
      'DISCOVERY HEALTH', 'BONITAS', 'GEMS', 'FEDHEALTH', 'MEDIHELP',
      'BESTMED', 'MOMENTUM HEALTH', 'MEDSHIELD', 'PROFMED', 'POLMED',
      'MEDICAL SCHEME', 'MED AID', 'MEDICAL AID',
    ],
  },
  {
    categoryName: 'Pension',
    keywords: [
      'PENSION', 'RETIREMENT', 'PROVIDENT FUND', 'RA CONTRIB', 'RETIREMENT ANNUITY',
      'SANLAM RA', 'OLD MUTUAL RA', 'ALLAN GRAY', 'CORONATION', '10X INVEST',
    ],
  },
  {
    categoryName: 'Domestic Help',
    keywords: [
      'DOMESTIC', 'GARDENER', 'SWEEPSA', 'CLEANING SERVICE', 'MAID',
    ],
  },
  {
    categoryName: 'Rates & Taxes',
    keywords: [
      'RATES AND TAXES', 'RATES & TAXES', 'MUNICIPAL RATES', 'PROPERTY RATES',
      'LEVIES', 'BODY CORPORATE', 'HOA', 'HOME OWNERS',
    ],
  },
  {
    categoryName: 'Gambling/Lotto',
    keywords: [
      'LOTTO', 'ITHUBA', 'HOLLYWOODBETS', 'SUPABETS', 'BETWAY', 'SUNBET',
      'SPORTINGBET', 'LOTTOSTAR', 'PLAYABETS', 'GBETS', 'CASINO',
    ],
  },

  // Income categories
  {
    categoryName: 'Salary',
    keywords: [
      'SALARY', 'WAGES', 'NETT PAY', 'NET PAY', 'PAYROLL', 'REMUNERATION',
    ],
    isIncome: true,
  },
  {
    categoryName: 'Interest',
    keywords: [
      'INTEREST CREDIT', 'INT CREDIT', 'INTEREST EARNED', 'CREDIT INTEREST',
    ],
    isIncome: true,
  },
  {
    categoryName: 'Refund',
    keywords: [
      'REFUND', 'REVERSAL', 'CASHBACK', 'EBUCKS', 'UCOUNT', 'REWARDS',
    ],
    isIncome: true,
  },
  {
    categoryName: 'Investment',
    keywords: [
      'DIVIDEND', 'UNIT TRUST', 'ETF', 'EASY EQUITIES', 'EASYEQUITIES',
    ],
    isIncome: true,
  },
];

export interface CategorizationResult {
  categoryId: string | null;
  categoryName: string | null;
  confidence: 'high' | 'medium' | 'low' | null;
  matchedKeyword: string | null;
}

class CategorizationService {
  private categories: Category[] = [];
  private categoryNameToId: Map<string, string> = new Map();

  /**
   * Set available categories from the store
   */
  setCategories(categories: Category[]): void {
    this.categories = categories;
    this.categoryNameToId.clear();
    categories.forEach((cat) => {
      this.categoryNameToId.set(cat.name.toLowerCase(), cat.id);
    });
  }

  /**
   * Auto-categorize a single transaction based on description
   */
  categorize(transaction: Transaction): CategorizationResult {
    const description = (transaction.description || '').toUpperCase();
    const rawDescription = (transaction.raw_description || '').toUpperCase();
    const searchText = `${description} ${rawDescription}`;

    // Determine if this is income or expense
    const isIncome = transaction.amount > 0;

    // Find matching rule
    for (const rule of CATEGORY_RULES) {
      // Skip income rules for expenses and vice versa
      if (rule.isIncome !== undefined && rule.isIncome !== isIncome) {
        continue;
      }

      for (const keyword of rule.keywords) {
        if (searchText.includes(keyword.toUpperCase())) {
          const categoryId = this.categoryNameToId.get(rule.categoryName.toLowerCase());

          if (categoryId) {
            return {
              categoryId,
              categoryName: rule.categoryName,
              confidence: keyword.length > 5 ? 'high' : 'medium',
              matchedKeyword: keyword,
            };
          }
        }
      }
    }

    return {
      categoryId: null,
      categoryName: null,
      confidence: null,
      matchedKeyword: null,
    };
  }

  /**
   * Auto-categorize multiple transactions
   */
  categorizeMany(transactions: Transaction[]): Map<string, CategorizationResult> {
    const results = new Map<string, CategorizationResult>();

    for (const tx of transactions) {
      if (tx.local_id) {
        results.set(tx.local_id, this.categorize(tx));
      }
    }

    return results;
  }

  /**
   * Get statistics about categorization results
   */
  getStats(results: Map<string, CategorizationResult>): {
    total: number;
    categorized: number;
    highConfidence: number;
    mediumConfidence: number;
    uncategorized: number;
  } {
    let categorized = 0;
    let highConfidence = 0;
    let mediumConfidence = 0;

    results.forEach((result) => {
      if (result.categoryId) {
        categorized++;
        if (result.confidence === 'high') highConfidence++;
        if (result.confidence === 'medium') mediumConfidence++;
      }
    });

    return {
      total: results.size,
      categorized,
      highConfidence,
      mediumConfidence,
      uncategorized: results.size - categorized,
    };
  }
}

export const categorizationService = new CategorizationService();
