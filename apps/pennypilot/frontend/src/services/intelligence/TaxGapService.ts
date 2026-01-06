/**
 * TaxGapService
 * Calculates South African income tax using SARS 2025/2026 tax tables.
 * Part of Directive #4: Penny's Soul (Intelligence Layer)
 *
 * Reference: SARS Tax Tables 1 March 2025 - 28 February 2026
 * https://www.sars.gov.za/tax-rates/income-tax/rates-of-tax-for-individuals/
 */

// ============================================
// Types
// ============================================

export interface TaxBracket {
  min: number;
  max: number;
  rate: number;
  baseTax: number;
}

export interface TaxRebate {
  primary: number;    // Everyone under 65
  secondary: number;  // 65 and older
  tertiary: number;   // 75 and older
}

export interface TaxThreshold {
  under65: number;
  age65to74: number;
  age75plus: number;
}

export interface TaxCalculationInput {
  annualIncome: number;
  annualDeductions?: number;
  age?: number;
  medicalAidMembers?: number;
  medicalAidContributions?: number;
}

export interface TaxCalculationResult {
  grossIncome: number;
  taxableIncome: number;
  deductions: number;
  taxBeforeRebates: number;
  primaryRebate: number;
  secondaryRebate: number;
  tertiaryRebate: number;
  totalRebates: number;
  taxPayable: number;
  effectiveRate: number;
  marginalRate: number;
  monthlyTax: number;
  monthlyNetIncome: number;
}

export interface TaxGapAnalysis {
  currentProvision: number;
  estimatedTax: number;
  gap: number;
  gapPercentage: number;
  status: 'under' | 'over' | 'on-track';
  recommendation: string;
  confidence: number;
}

// ============================================
// SARS 2025/2026 Tax Tables
// ============================================

/**
 * Tax brackets for 2025/2026 tax year (1 March 2025 - 28 February 2026)
 * Updated with inflation adjustments from 2025 Budget
 */
export const TAX_BRACKETS_2025_2026: TaxBracket[] = [
  { min: 1, max: 247500, rate: 0.18, baseTax: 0 },
  { min: 247501, max: 386500, rate: 0.26, baseTax: 44550 },
  { min: 386501, max: 535000, rate: 0.31, baseTax: 80690 },
  { min: 535001, max: 702000, rate: 0.36, baseTax: 126727 },
  { min: 702001, max: 895000, rate: 0.39, baseTax: 186847 },
  { min: 895001, max: 1895000, rate: 0.41, baseTax: 262117 },
  { min: 1895001, max: Infinity, rate: 0.45, baseTax: 672117 },
];

/**
 * Tax rebates for 2025/2026
 * Primary Rebate: R18,833 (as per SARS 2025/26)
 */
export const TAX_REBATES_2025_2026: TaxRebate = {
  primary: 18833,   // All natural persons
  secondary: 10323, // Persons 65 and older
  tertiary: 3439,   // Persons 75 and older
};

/**
 * Tax thresholds (below which no tax is payable) for 2025/2026
 * Calculated from rebates: primary rebate / 18% = threshold
 */
export const TAX_THRESHOLDS_2025_2026: TaxThreshold = {
  under65: 104628,  // R18,833 / 0.18
  age65to74: 161961, // (R18,833 + R10,323) / 0.18
  age75plus: 181072, // (R18,833 + R10,323 + R3,439) / 0.18
};

/**
 * Medical Scheme Fees Tax Credit for 2025/2026 (monthly amounts)
 * This is a direct tax offset (not a deduction) - non-refundable
 */
export const MEDICAL_TAX_CREDITS_2025_2026 = {
  mainMember: 364,        // R364/month for main member
  firstDependant: 364,    // R364/month for first dependent
  additionalDependants: 246, // R246/month for each subsequent dependent
};

// Legacy exports for backwards compatibility
export const TAX_BRACKETS_2024_2025 = TAX_BRACKETS_2025_2026;
export const TAX_REBATES_2024_2025 = TAX_REBATES_2025_2026;
export const TAX_THRESHOLDS_2024_2025 = TAX_THRESHOLDS_2025_2026;
export const MEDICAL_TAX_CREDITS_2024_2025 = MEDICAL_TAX_CREDITS_2025_2026;

// ============================================
// TaxGapService Class
// ============================================

export class TaxGapService {
  private brackets: TaxBracket[];
  private rebates: TaxRebate;
  private thresholds: TaxThreshold;
  public readonly taxYear = '2025/2026';
  public readonly effectiveDate = '1 March 2025';

  constructor() {
    this.brackets = TAX_BRACKETS_2025_2026;
    this.rebates = TAX_REBATES_2025_2026;
    this.thresholds = TAX_THRESHOLDS_2025_2026;
  }

  /**
   * Get formatted tax brackets for display
   */
  getFormattedBrackets(): { description: string; rate: number }[] {
    return this.brackets.map((bracket, index) => {
      const minFormatted = bracket.min.toLocaleString('en-ZA');
      const maxFormatted = bracket.max === Infinity
        ? 'and above'
        : bracket.max.toLocaleString('en-ZA');
      const ratePercent = (bracket.rate * 100).toFixed(0);

      if (index === 0) {
        return {
          description: `R1 – R${maxFormatted}: ${ratePercent}% of taxable income`,
          rate: bracket.rate,
        };
      } else if (bracket.max === Infinity) {
        return {
          description: `R${minFormatted} ${maxFormatted}: R${bracket.baseTax.toLocaleString('en-ZA')} + ${ratePercent}% above R${(bracket.min - 1).toLocaleString('en-ZA')}`,
          rate: bracket.rate,
        };
      } else {
        return {
          description: `R${minFormatted} – R${maxFormatted}: R${bracket.baseTax.toLocaleString('en-ZA')} + ${ratePercent}% above R${(bracket.min - 1).toLocaleString('en-ZA')}`,
          rate: bracket.rate,
        };
      }
    });
  }

  /**
   * Get the primary rebate amount
   */
  getPrimaryRebate(): number {
    return this.rebates.primary;
  }

  /**
   * Get medical credit rates for display
   */
  getMedicalCreditRates(): typeof MEDICAL_TAX_CREDITS_2025_2026 {
    return MEDICAL_TAX_CREDITS_2025_2026;
  }

  /**
   * Calculate annual medical tax credit based on dependents
   * Main member is always included (you must have medical aid to claim)
   * @param dependents - Number of dependents (excluding main member)
   */
  calculateMedicalTaxCredit(dependents: number): {
    monthlyCredit: number;
    annualCredit: number;
    breakdown: { mainMember: number; firstDep: number; additionalDeps: number };
  } {
    const credits = MEDICAL_TAX_CREDITS_2025_2026;
    let monthlyCredit = credits.mainMember; // Main member always included

    let firstDep = 0;
    let additionalDeps = 0;

    if (dependents >= 1) {
      firstDep = credits.firstDependant;
      monthlyCredit += firstDep;
    }
    if (dependents > 1) {
      additionalDeps = (dependents - 1) * credits.additionalDependants;
      monthlyCredit += additionalDeps;
    }

    return {
      monthlyCredit,
      annualCredit: monthlyCredit * 12,
      breakdown: {
        mainMember: credits.mainMember,
        firstDep,
        additionalDeps,
      },
    };
  }

  /**
   * Calculate annual income tax based on SARS tables
   */
  calculateTax(input: TaxCalculationInput): TaxCalculationResult {
    const {
      annualIncome,
      annualDeductions = 0,
      age = 30,
      medicalAidMembers = 0,
      medicalAidContributions = 0,
    } = input;

    // Calculate taxable income
    const taxableIncome = Math.max(0, annualIncome - annualDeductions);

    // Calculate tax before rebates
    const taxBeforeRebates = this.calculateTaxFromBrackets(taxableIncome);

    // Calculate rebates based on age
    const primaryRebate = this.rebates.primary;
    const secondaryRebate = age >= 65 ? this.rebates.secondary : 0;
    const tertiaryRebate = age >= 75 ? this.rebates.tertiary : 0;
    const totalRebates = primaryRebate + secondaryRebate + tertiaryRebate;

    // Calculate medical tax credits
    const medicalCredits = this.calculateMedicalCredits(
      medicalAidMembers,
      medicalAidContributions
    );

    // Calculate final tax payable
    let taxPayable = taxBeforeRebates - totalRebates - medicalCredits;
    taxPayable = Math.max(0, taxPayable); // Tax cannot be negative

    // Calculate rates
    const effectiveRate = taxableIncome > 0 ? taxPayable / taxableIncome : 0;
    const marginalRate = this.getMarginalRate(taxableIncome);

    // Monthly figures
    const monthlyTax = taxPayable / 12;
    const monthlyNetIncome = (annualIncome / 12) - monthlyTax;

    return {
      grossIncome: annualIncome,
      taxableIncome,
      deductions: annualDeductions,
      taxBeforeRebates,
      primaryRebate,
      secondaryRebate,
      tertiaryRebate,
      totalRebates,
      taxPayable,
      effectiveRate,
      marginalRate,
      monthlyTax,
      monthlyNetIncome,
    };
  }

  /**
   * Calculate tax from brackets (before rebates)
   */
  calculateTaxFromBrackets(taxableIncome: number): number {
    if (taxableIncome <= 0) return 0;

    for (const bracket of this.brackets) {
      if (taxableIncome >= bracket.min && taxableIncome <= bracket.max) {
        const excessAmount = taxableIncome - bracket.min + 1;
        return bracket.baseTax + (excessAmount * bracket.rate);
      }
    }

    // Should not reach here, but handle edge case
    const lastBracket = this.brackets[this.brackets.length - 1];
    const excessAmount = taxableIncome - lastBracket.min + 1;
    return lastBracket.baseTax + (excessAmount * lastBracket.rate);
  }

  /**
   * Get marginal tax rate for a given income
   */
  getMarginalRate(taxableIncome: number): number {
    for (const bracket of this.brackets) {
      if (taxableIncome >= bracket.min && taxableIncome <= bracket.max) {
        return bracket.rate;
      }
    }
    return this.brackets[this.brackets.length - 1].rate;
  }

  /**
   * Calculate medical tax credits
   */
  calculateMedicalCredits(
    members: number,
    _contributions: number
  ): number {
    if (members <= 0) return 0;

    const credits = MEDICAL_TAX_CREDITS_2025_2026;
    let monthlyCredit = 0;

    if (members >= 1) monthlyCredit += credits.mainMember;
    if (members >= 2) monthlyCredit += credits.firstDependant;
    if (members > 2) monthlyCredit += (members - 2) * credits.additionalDependants;

    return monthlyCredit * 12; // Annual credit
  }

  /**
   * Check if income is below tax threshold
   */
  isBelowThreshold(annualIncome: number, age: number = 30): boolean {
    if (age >= 75) return annualIncome < this.thresholds.age75plus;
    if (age >= 65) return annualIncome < this.thresholds.age65to74;
    return annualIncome < this.thresholds.under65;
  }

  /**
   * Analyze the gap between current tax provision and estimated tax
   */
  analyzeTaxGap(
    estimatedAnnualTax: number,
    currentMonthlyProvision: number,
    monthsRemaining: number = 12
  ): TaxGapAnalysis {
    const currentAnnualProvision = currentMonthlyProvision * 12;
    const projectedProvision = currentMonthlyProvision * monthsRemaining;
    const gap = estimatedAnnualTax - currentAnnualProvision;
    const gapPercentage = currentAnnualProvision > 0
      ? (gap / currentAnnualProvision) * 100
      : (estimatedAnnualTax > 0 ? 100 : 0);

    let status: 'under' | 'over' | 'on-track';
    let recommendation: string;
    let confidence: number;

    const tolerance = 0.05; // 5% tolerance

    if (Math.abs(gapPercentage) <= tolerance * 100) {
      status = 'on-track';
      recommendation = "Your tax provision looks good! You're on track for the year.";
      confidence = 0.9;
    } else if (gap > 0) {
      status = 'under';
      const monthlyShortfall = gap / Math.max(1, monthsRemaining);
      recommendation = `Consider increasing your monthly provision by R${monthlyShortfall.toFixed(0)} to avoid a surprise tax bill.`;
      confidence = 0.85;
    } else {
      status = 'over';
      const monthlyExcess = Math.abs(gap) / Math.max(1, monthsRemaining);
      recommendation = `Great news! You're over-provisioned by about R${monthlyExcess.toFixed(0)}/month. Consider adjusting or saving the extra.`;
      confidence = 0.85;
    }

    return {
      currentProvision: currentAnnualProvision,
      estimatedTax: estimatedAnnualTax,
      gap,
      gapPercentage,
      status,
      recommendation,
      confidence,
    };
  }

  /**
   * Get a friendly, fuzzy insight message about tax status
   */
  getFuzzyInsight(analysis: TaxGapAnalysis): string {
    const { status, gap, gapPercentage } = analysis;

    if (status === 'on-track') {
      return "You're flying steady! Your tax provision is right on target. Keep doing what you're doing.";
    }

    if (status === 'under') {
      if (gapPercentage > 50) {
        return `Heads up, Captain! There's a significant gap in your tax provision. Let's work together to close it before year-end.`;
      } else if (gapPercentage > 20) {
        return `I noticed your tax provision might be a bit light. A small adjustment now could save you stress later.`;
      } else {
        return `Just a gentle nudge—your tax provision is slightly under. Nothing major, but worth keeping an eye on.`;
      }
    }

    if (status === 'over') {
      if (Math.abs(gapPercentage) > 30) {
        return `You're well ahead on your tax! That extra R${Math.abs(gap).toFixed(0)} could be working harder for you elsewhere.`;
      } else {
        return `Nice work! You're slightly over-provisioned for tax. That's a comfortable cushion to have.`;
      }
    }

    return "Let me take a closer look at your tax situation...";
  }
}

// Singleton instance
export const taxGapService = new TaxGapService();
