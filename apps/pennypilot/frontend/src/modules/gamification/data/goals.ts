/**
 * Predefined Financial Goal Options
 * South African focused goal categories
 */

import type { GoalOption, GoalTimeframe } from '../types/quest.types';

export interface GoalOptionsMap {
  short: GoalOption[];
  medium: GoalOption[];
  long: GoalOption[];
}

export const GOAL_OPTIONS: GoalOptionsMap = {
  // Short-term goals (0-12 months)
  short: [
    {
      id: 'emergency_fund',
      label: 'Build emergency fund (3-6 months expenses)',
      icon: 'savings',
    },
    {
      id: 'pay_debt',
      label: 'Pay off debt (credit card, store accounts)',
      icon: 'money_off',
    },
    {
      id: 'save_vacation',
      label: 'Save for a holiday',
      icon: 'flight',
    },
    {
      id: 'new_phone',
      label: 'Buy a new phone/gadget',
      icon: 'smartphone',
    },
    {
      id: 'custom',
      label: 'Other (specify your goal)',
      icon: 'edit',
    },
  ],

  // Medium-term goals (1-5 years)
  medium: [
    {
      id: 'car',
      label: 'Buy a car',
      icon: 'directions_car',
    },
    {
      id: 'education',
      label: 'Education or upskilling',
      icon: 'school',
    },
    {
      id: 'wedding',
      label: 'Wedding fund',
      icon: 'favorite',
    },
    {
      id: 'investment',
      label: 'Start investing (ETFs, Unit Trusts)',
      icon: 'trending_up',
    },
    {
      id: 'home_deposit',
      label: 'Save for home deposit',
      icon: 'home',
    },
    {
      id: 'custom',
      label: 'Other (specify your goal)',
      icon: 'edit',
    },
  ],

  // Long-term goals (5+ years)
  long: [
    {
      id: 'property',
      label: 'Buy property',
      icon: 'home',
    },
    {
      id: 'retirement',
      label: 'Retirement savings (RA, Pension)',
      icon: 'elderly',
    },
    {
      id: 'children_education',
      label: "Children's education fund",
      icon: 'child_care',
    },
    {
      id: 'financial_freedom',
      label: 'Financial independence (FIRE)',
      icon: 'star',
    },
    {
      id: 'business',
      label: 'Start a business',
      icon: 'business',
    },
    {
      id: 'custom',
      label: 'Other (specify your goal)',
      icon: 'edit',
    },
  ],
};

/**
 * Get goal options for a specific timeframe
 */
export function getGoalOptions(timeframe: GoalTimeframe): GoalOption[] {
  return GOAL_OPTIONS[timeframe];
}

/**
 * Get goal label by ID and timeframe
 */
export function getGoalLabel(
  timeframe: GoalTimeframe,
  goalId: string
): string | undefined {
  const option = GOAL_OPTIONS[timeframe].find((g) => g.id === goalId);
  return option?.label;
}

/**
 * Timeframe display labels
 */
export const TIMEFRAME_LABELS: Record<GoalTimeframe, { title: string; subtitle: string }> = {
  short: {
    title: 'Short-Term Goal',
    subtitle: '0-12 months',
  },
  medium: {
    title: 'Medium-Term Goal',
    subtitle: '1-5 years',
  },
  long: {
    title: 'Long-Term Goal',
    subtitle: '5+ years',
  },
};
