/**
 * Intelligent Categorizer
 *
 * Auto-categorizes transactions into SME expense buckets:
 * - Essential: Bond, Rates, Utilities, Insurance, Medical
 * - Repairs/Maintenance: Home repairs, vehicle service, equipment maintenance
 * - New/Capital: New purchases, equipment, furniture
 * - Fun/Lifestyle: Entertainment, dining, hobbies
 *
 * Only triggers interrogation for truly unknown items.
 */

import type { ParsedTransaction, BudgetBucket } from '@/types';

export type SMEExpenseCategory =
  | 'essential'      // Non-negotiable: Bond, Rates, Insurance
  | 'repairs'        // Repairs & Maintenance
  | 'new_capital'    // New purchases, equipment
  | 'fun'            // Lifestyle, entertainment
  | 'transfer'       // Internal transfers (exclude from budget)
  | 'business'       // Tax-deductible business expenses
  | 'tax_paid'       // SARS payments (statutory debt, not expense)
  | 'income'         // Income (handled separately)
  | 'unknown';       // Needs interrogation

export interface CategorizationRule {
  patterns: RegExp[];
  category: SMEExpenseCategory;
  budgetBucket: BudgetBucket;
  description: string;
}

export interface CategorizedTransaction extends ParsedTransaction {
  smeCategory: SMEExpenseCategory;
  budgetBucket: BudgetBucket;
  autoMatched: boolean;
  matchedRule?: string;
  manualOverride?: boolean;        // True if user manually changed category
  overrideType?: 'once' | 'always'; // One-time exception vs permanent rule
}

/**
 * Category override request - when user manually changes a category
 */
export interface CategoryOverrideRequest {
  transactionId: string;
  originalCategory: SMEExpenseCategory;
  newCategory: SMEExpenseCategory;
  merchantPattern: string;        // Extracted merchant name for rule matching
  overrideType: 'once' | 'always';
}

/**
 * User-defined category rule (persisted to IndexedDB)
 */
export interface UserCategoryRule {
  id: string;
  merchantPattern: string;        // e.g., "SPAR", "CHECKERS"
  category: SMEExpenseCategory;
  budgetBucket: BudgetBucket;
  createdAt: string;
  note?: string;                  // e.g., "Special treats at grocery stores"
}

export interface CategorizationResult {
  categorized: CategorizedTransaction[];
  needsInterrogation: ParsedTransaction[];
  summary: {
    essential: number;
    repairs: number;
    newCapital: number;
    fun: number;
    transfers: number;
    business: number;      // TAX-DEDUCTIBLE - reduces taxable income
    taxPaid: number;       // SARS payments (statutory debt, not expense)
    unknown: number;
  };
}

// South African Essential expense patterns
const ESSENTIAL_RULES: CategorizationRule[] = [
  // Housing
  {
    patterns: [/BOND\s*PAYMENT/i, /MORTGAGE/i, /HOME\s*LOAN/i, /ABSA\s*HOME/i, /NEDBANK\s*HOME/i, /FNB\s*HOME/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Bond/Mortgage Payment',
  },
  {
    patterns: [/RATES?\s*(AND|&)?\s*TAXES/i, /MUNICIPAL/i, /CITY\s*OF/i, /CITY\s*POWER/i, /JOBURG/i, /TSHWANE/i, /CAPE\s*TOWN/i, /EKURHULENI/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Municipal Rates & Taxes',
  },
  // Body Corporate / Levies / HOA (hard-coded - no interrogation)
  {
    patterns: [/LEVIES?/i, /LEVY/i, /BODY\s*CORP/i, /HOA/i, /HOME\s*OWNERS/i, /THE\s*ISLANDS/i, /ISLANDS\s*DE/i, /SECTIONAL\s*TITLE/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Levies/Body Corporate',
  },
  // Utilities
  {
    patterns: [/ESKOM/i, /PREPAID\s*ELECTRICITY/i, /CITY\s*POWER/i, /ELECTRICITY/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Electricity',
  },
  {
    patterns: [/WATER\s*(AND|&)?\s*SAN/i, /RAND\s*WATER/i, /WATER\s*BOARD/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Water',
  },
  // Insurance
  {
    patterns: [/INSURANCE/i, /SANTAM/i, /OUTSURANCE/i, /DISCOVERY\s*INSURE/i, /OLD\s*MUTUAL/i, /SANLAM/i, /OMLAC/i, /LIBERTY/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Insurance',
  },
  // Medical
  {
    patterns: [/MEDICAL\s*AID/i, /DISCOVERY\s*HEALTH/i, /BONITAS/i, /MEDSHIELD/i, /GEMS/i, /MOMENTUM\s*HEALTH/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Medical Aid',
  },
  // Telecoms (personal - business internet is in BUSINESS_RULES)
  {
    patterns: [/TELKOM/i, /MTN\s*SP/i, /VODACOM/i, /CELL\s*C/i, /RAIN/i, /FIBRE/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Phone/Mobile',
  },
  // Schools
  {
    patterns: [/SCHOOL/i, /ACADEMY/i, /COLLEGE/i, /CRECHE/i, /DAYCARE/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Education',
  },
  // Security
  {
    patterns: [/ADT/i, /FIDELITY/i, /CHUBB/i, /G4S/i, /SECURITY/i],
    category: 'essential',
    budgetBucket: 'needs',
    description: 'Security',
  },
];

// Repairs & Maintenance patterns
const REPAIRS_RULES: CategorizationRule[] = [
  {
    patterns: [/PLUMBER/i, /PLUMBING/i, /ELECTRICIAN/i, /HANDYMAN/i, /REPAIRS?/i, /FIX/i],
    category: 'repairs',
    budgetBucket: 'needs',
    description: 'Home Repairs',
  },
  {
    patterns: [/SERVICE\s*PLAN/i, /CAR\s*SERVICE/i, /VEHICLE\s*SERVICE/i, /WORKSHOP/i, /MECHANIC/i],
    category: 'repairs',
    budgetBucket: 'needs',
    description: 'Vehicle Maintenance',
  },
  {
    patterns: [/BUILD\s*IT/i, /BUILDERS/i, /HARDWARE/i, /CASHBUILD/i, /CTM/i, /TILE/i],
    category: 'repairs',
    budgetBucket: 'needs',
    description: 'Hardware/Building Materials',
  },
];

// Fun/Lifestyle patterns (WANTS bucket - no interrogation for known merchants)
const FUN_RULES: CategorizationRule[] = [
  // Dining / Fast Food (hard-coded - auto-categorize to Wants)
  {
    patterns: [
      /RESTAURANT/i, /MUGG\s*(AND|&)?\s*BEAN/i, /WIMPY/i, /SPUR/i, /NANDO/i, /KFC/i, /MCDONALD/i, /STEERS/i, /OCEAN\s*BASKET/i,
      /DEBONAIRS/i, /ROMANS?\s*PIZZA/i, /DOMINOS/i, /PIZZA\s*HUT/i, /BURGER\s*KING/i, /FISHAWAYS/i, /GALITOS/i,
      /ROCOMAMAS/i, /HUSSAR/i, /CAPPUCCINOS/i, /PANAROTTIS/i, /SIMPLY\s*ASIA/i, /KUNG\s*FU/i, /JOHN\s*DORY/i,
      /CAFE/i, /BISTRO/i, /DINER/i, /GRILL/i, /EATERY/i,
    ],
    category: 'fun',
    budgetBucket: 'wants',
    description: 'Dining Out / Fast Food',
  },
  // Entertainment
  {
    patterns: [/NETFLIX/i, /SHOWMAX/i, /DSTV/i, /MULTICHOICE/i, /SPOTIFY/i, /YOUTUBE/i, /GOOGLE\s*TV/i, /GOOGLE\s*PLAY/i],
    category: 'fun',
    budgetBucket: 'wants',
    description: 'Streaming/Entertainment',
  },
  // Shopping
  {
    patterns: [/TAKEALOT/i, /AMAZON/i, /TEMU/i, /SHEIN/i, /ALIEXPRESS/i, /WISH/i],
    category: 'fun',
    budgetBucket: 'wants',
    description: 'Online Shopping',
  },
  // Coffee
  {
    patterns: [/VIDA/i, /STARBUCKS/i, /SEATTLE/i, /COFFEE/i],
    category: 'fun',
    budgetBucket: 'wants',
    description: 'Coffee',
  },
  // Pets
  {
    patterns: [/PET/i, /PETZ/i, /VET/i, /ANIMAL/i],
    category: 'fun',
    budgetBucket: 'wants',
    description: 'Pets',
  },
];

// New/Capital expense patterns
const NEW_CAPITAL_RULES: CategorizationRule[] = [
  {
    patterns: [/INCREDIBLE\s*CONNECTION/i, /ISTORE/i, /APPLE/i, /SAMSUNG\s*STORE/i, /LAPTOP/i, /COMPUTER/i],
    category: 'new_capital',
    budgetBucket: 'savings',
    description: 'Electronics',
  },
  {
    patterns: [/FURNITURE/i, /@HOME/i, /MR\s*PRICE\s*HOME/i, /SHEET\s*STREET/i, /CORICRAFT/i],
    category: 'new_capital',
    budgetBucket: 'savings',
    description: 'Furniture/Home',
  },
];

// Transfer patterns (exclude from budget)
const TRANSFER_RULES: CategorizationRule[] = [
  {
    patterns: [/^TRANSFER$/i, /INTERNAL\s*TRANSFER/i, /BETWEEN\s*ACCOUNTS/i, /SAVINGS\s*TRANSFER/i],
    category: 'transfer',
    budgetBucket: 'savings',
    description: 'Internal Transfer',
  },
  {
    patterns: [/ATM\s*CASH/i, /CASH\s*WITHDRAWAL/i, /ATM\s*WITH/i],
    category: 'transfer',
    budgetBucket: 'needs', // Cash could be for anything
    description: 'Cash Withdrawal',
  },
  // Pocket account / savings transfers (hard-coded - no interrogation)
  {
    patterns: [
      /^BONUS\s+(DECEMBER|NOVEMBER|OCTOBER|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER)\s*$/i,
      /^BONUS\s+DEC\s*$/i, /^BONUS\s+NOV\s*$/i, /^BONUS\s+OCT\s*$/i,
      /^NOVEMBER\s*$/i, /^DECEMBER\s*$/i, /^JANUARY\s*$/i, /^FEBRUARY\s*$/i, /^MARCH\s*$/i,
      /^JAN\d{0,2}\s*$/i, /^FEB\d{0,2}\s*$/i, /^MAR\d{0,2}\s*$/i, /^APR\d{0,2}\s*$/i, /^MAY\d{0,2}\s*$/i, /^JUN\d{0,2}\s*$/i,
      /^JUL\d{0,2}\s*$/i, /^AUG\d{0,2}\s*$/i, /^SEP\d{0,2}\s*$/i, /^OCT\d{0,2}\s*$/i, /^NOV\d{0,2}\s*$/i, /^DEC\d{0,2}\s*$/i,
      /^MONTHEND\s*$/i, /^MONTH\s*END\s*$/i,
      /POCKET/i, /SAVINGS\s*POCKET/i,
      /^EXTRA\s+WORK\s+BONUS\s*$/i,
    ],
    category: 'transfer',
    budgetBucket: 'savings',
    description: 'Pocket/Savings Transfer',
  },
];

// Business Expense patterns (TAX-DEDUCTIBLE - reduces taxable income)
const BUSINESS_RULES: CategorizationRule[] = [
  // Cloud & Hosting & Business Internet
  {
    patterns: [
      /DIGITALOCEAN/i, /AWS/i, /AMAZON\s*WEB/i, /AZURE/i, /GOOGLE\s*CLOUD/i, /HEROKU/i,
      /VERCEL/i, /NETLIFY/i, /CLOUDFLARE/i, /HETZNER/i, /LINODE/i,
      /AFRIHOST/i, /WEBAFRICA/i, /COOL\s*IDEAS/i, /VUMATEL/i, // Business ISP
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Cloud/Hosting/Internet',
  },
  // Software & SaaS
  {
    patterns: [
      /CLAUDE\.?AI/i, /ANTHROPIC/i, /OPENAI/i, /CHATGPT/i,
      /GITHUB/i, /GITLAB/i, /BITBUCKET/i,
      /SLACK/i, /ZOOM/i, /NOTION/i, /TRELLO/i, /ASANA/i, /JIRA/i, /ATLASSIAN/i,
      /FIGMA/i, /SKETCH/i, /MIRO/i, /LOOM/i, /CALENDLY/i,
      /ADOBE/i, /CANVA/i, /DROPBOX/i,
      /MICROSOFT\s*(365|OFFICE|ISO)/i, /OFFICE\s*365/i,
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Software/SaaS',
  },
  // Domain & Email
  {
    patterns: [
      /NAMECHEAP/i, /GODADDY/i, /GOOGLE\s*WORKSPACE/i, /GSUITE/i,
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Domain/Email',
  },
  // Payment Processing
  {
    patterns: [
      /PAYFAST/i, /YOCO/i, /PAYSTACK/i, /STRIPE/i, /PAYPAL\s*FEE/i,
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Payment Processing',
  },
  // Professional Services
  {
    patterns: [
      /ACCOUNTANT/i, /BOOKKEEP/i, /LEGAL\s*FEE/i, /ATTORNEY/i,
      /CONSULTANT/i, /CONTRACTOR/i,
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Professional Services',
  },
  // Marketing & Advertising
  {
    patterns: [
      /GOOGLE\s*ADS/i, /FACEBOOK\s*ADS/i, /META\s*ADS/i, /LINKEDIN\s*ADS/i,
      /MAILCHIMP/i, /SENDGRID/i, /SENDINBLUE/i,
    ],
    category: 'business',
    budgetBucket: 'needs',
    description: 'Marketing',
  },
];

// Grocery patterns (between essential and fun depending on spend)
const GROCERY_PATTERNS = [
  /SPAR/i, /SUPERSPAR/i, /PNP/i, /PICK\s*N\s*PAY/i, /CHECKERS/i, /SHOPRITE/i, /WOOLWORTHS/i, /FOOD\s*LOVER/i,
];

// Fuel patterns
const FUEL_PATTERNS = [
  /ENGEN/i, /SHELL/i, /BP\s/i, /CALTEX/i, /SASOL/i, /TOTAL\s/i, /PETROL/i, /FUEL/i,
];

export class IntelligentCategorizer {
  private allRules: CategorizationRule[];

  constructor() {
    this.allRules = [
      ...BUSINESS_RULES,    // Check business FIRST (tax-deductible)
      ...ESSENTIAL_RULES,
      ...REPAIRS_RULES,
      ...FUN_RULES,
      ...NEW_CAPITAL_RULES,
      ...TRANSFER_RULES,
    ];
  }

  /**
   * Categorize transactions - auto-match known patterns
   */
  categorize(transactions: ParsedTransaction[]): CategorizationResult {
    const categorized: CategorizedTransaction[] = [];
    const needsInterrogation: ParsedTransaction[] = [];

    const summary = {
      essential: 0,
      repairs: 0,
      newCapital: 0,
      fun: 0,
      transfers: 0,
      business: 0,
      taxPaid: 0,
      unknown: 0,
    };

    for (const tx of transactions) {
      // Skip income (positive amounts)
      if (tx.amount > 0) {
        categorized.push({
          ...tx,
          smeCategory: 'income',
          budgetBucket: 'savings', // Income goes to savings bucket for budgeting
          autoMatched: true,
          matchedRule: 'Income',
        });
        continue;
      }

      // Try to match against rules
      const match = this.matchTransaction(tx);

      if (match) {
        categorized.push({
          ...tx,
          smeCategory: match.category,
          budgetBucket: match.budgetBucket,
          autoMatched: true,
          matchedRule: match.description,
        });

        // Update summary
        switch (match.category) {
          case 'essential': summary.essential += Math.abs(tx.amount); break;
          case 'repairs': summary.repairs += Math.abs(tx.amount); break;
          case 'new_capital': summary.newCapital += Math.abs(tx.amount); break;
          case 'fun': summary.fun += Math.abs(tx.amount); break;
          case 'transfer': summary.transfers += Math.abs(tx.amount); break;
          case 'business': summary.business += Math.abs(tx.amount); break;
          case 'tax_paid': summary.taxPaid += Math.abs(tx.amount); break;
        }
      } else {
        // Use both description and rawDescription for matching
        const searchText = tx.rawDescription || tx.description;

        // Check for groceries (essential need - no interrogation)
        if (this.isGrocery(searchText)) {
          categorized.push({
            ...tx,
            smeCategory: 'essential',
            budgetBucket: 'needs',
            autoMatched: true,
            matchedRule: 'Groceries',
          });
          summary.essential += Math.abs(tx.amount);
        }
        // Check for fuel (essential for SME - no interrogation)
        else if (this.isFuel(searchText)) {
          categorized.push({
            ...tx,
            smeCategory: 'essential',
            budgetBucket: 'needs',
            autoMatched: true,
            matchedRule: 'Fuel',
          });
          summary.essential += Math.abs(tx.amount);
        }
        // Unknown - needs interrogation
        else {
          needsInterrogation.push(tx);
          summary.unknown += Math.abs(tx.amount);
        }
      }
    }

    return {
      categorized,
      needsInterrogation,
      summary,
    };
  }

  /**
   * Match transaction against all rules
   */
  private matchTransaction(tx: ParsedTransaction): CategorizationRule | null {
    const description = tx.rawDescription || tx.description;

    for (const rule of this.allRules) {
      for (const pattern of rule.patterns) {
        if (pattern.test(description)) {
          return rule;
        }
      }
    }

    return null;
  }

  /**
   * Check if transaction is grocery shopping
   */
  private isGrocery(description: string): boolean {
    return GROCERY_PATTERNS.some(p => p.test(description));
  }

  /**
   * Check if transaction is fuel
   */
  private isFuel(description: string): boolean {
    return FUEL_PATTERNS.some(p => p.test(description));
  }

  /**
   * Get interrogation question for unknown expense
   */
  getInterrogationQuestion(tx: ParsedTransaction): {
    question: string;
    options: { label: string; category: SMEExpenseCategory; bucket: BudgetBucket }[];
  } {
    const amount = Math.abs(tx.amount);
    const desc = tx.description.substring(0, 30);

    return {
      question: `"${desc}" - R${amount.toFixed(2)} - What type of expense?`,
      options: [
        { label: 'Business (Tax-Deductible)', category: 'business', bucket: 'needs' },
        { label: 'Transfer/Savings', category: 'transfer', bucket: 'savings' },
        { label: 'Essential (Needs)', category: 'essential', bucket: 'needs' },
        { label: 'Fun/Lifestyle (Wants)', category: 'fun', bucket: 'wants' },
        { label: 'Repair/Maintenance', category: 'repairs', bucket: 'needs' },
        { label: 'New Purchase', category: 'new_capital', bucket: 'savings' },
      ],
    };
  }

  /**
   * Extract merchant pattern from description for rule creation
   * e.g., "SPAR DAMDORYN 4606390202677923" → "SPAR"
   */
  extractMerchantPattern(description: string): string {
    // Remove card numbers, dates, reference numbers
    let pattern = description
      .replace(/\d{6,}/g, '')                    // Long numbers
      .replace(/\d{1,2}(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\d{2,4}/gi, '')
      .replace(/[*#_\-\.\/\\|]+/g, ' ')          // Separators
      .replace(/\s{2,}/g, ' ')                   // Multiple spaces
      .trim();

    // Take first 1-2 meaningful words (the merchant name)
    const words = pattern.split(' ').filter(w => w.length > 2);
    return words.slice(0, 2).join(' ').toUpperCase();
  }

  /**
   * Get override confirmation question when user manually changes category
   */
  getOverrideConfirmation(
    tx: CategorizedTransaction,
    newCategory: SMEExpenseCategory
  ): {
    question: string;
    merchantPattern: string;
    options: { label: string; type: 'once' | 'always'; description: string }[];
  } {
    const merchantPattern = this.extractMerchantPattern(tx.rawDescription || tx.description);
    const categoryLabels: Record<SMEExpenseCategory, string> = {
      essential: 'Essential (Needs)',
      repairs: 'Repairs',
      new_capital: 'New Purchase',
      fun: 'Fun/Lifestyle (Wants)',
      transfer: 'Transfer',
      business: 'Business (Tax-Deductible)',
      tax_paid: 'Tax Paid (SARS)',
      income: 'Income',
      unknown: 'Unknown',
    };

    return {
      question: `Change "${merchantPattern}" from ${categoryLabels[tx.smeCategory]} to ${categoryLabels[newCategory]}?`,
      merchantPattern,
      options: [
        {
          label: 'Just this once',
          type: 'once',
          description: 'One-time exception (e.g., special treat)',
        },
        {
          label: `Always for ${merchantPattern}`,
          type: 'always',
          description: 'Update permanent rule for this merchant',
        },
      ],
    };
  }

  /**
   * Get bucket for a category
   */
  getBucketForCategory(category: SMEExpenseCategory): BudgetBucket {
    const bucketMap: Record<SMEExpenseCategory, BudgetBucket> = {
      essential: 'needs',
      repairs: 'needs',
      business: 'business',
      fun: 'wants',
      new_capital: 'savings',
      transfer: 'transfer',
      tax_paid: 'tax_paid',
      income: 'savings',
      unknown: 'needs',
    };
    return bucketMap[category];
  }
}

/**
 * Quick categorization utility
 */
export function quickCategorize(transactions: ParsedTransaction[]): CategorizationResult {
  const categorizer = new IntelligentCategorizer();
  return categorizer.categorize(transactions);
}
