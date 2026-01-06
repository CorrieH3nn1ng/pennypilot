/**
 * Quest System Type Definitions
 * Part of Directive #5: Gamification
 */

// ============================================
// Goal Types
// ============================================

export type GoalTimeframe = 'short' | 'medium' | 'long';

export interface FinancialGoal {
  id: string;
  timeframe: GoalTimeframe;
  category: string; // e.g., 'emergency_fund', 'property', 'custom'
  customDescription?: string; // For custom goals
  targetAmount?: number;
  targetDate?: string;
  createdAt: string;
}

export interface GoalOption {
  id: string;
  label: string;
  icon: string;
}

// ============================================
// Quest Types
// ============================================

export type QuestStatus = 'locked' | 'active' | 'completed';

export interface QuestStep {
  id: string;
  title: string;
  description: string;
  isCompleted: boolean;
}

export interface QuestReward {
  badge: Badge;
  featureUnlock?: string; // e.g., 'tax_provisioning'
  xp: number;
}

export interface Quest {
  id: string;
  name: string;
  description: string;
  status: QuestStatus;
  reward: QuestReward;
  progress: number; // 0-100
  steps: QuestStep[];
  startedAt?: string;
  completedAt?: string;
}

// ============================================
// Badge Types
// ============================================

export interface Badge {
  id: string;
  name: string;
  icon: string;
  color: string;
  description: string;
  earnedAt?: string;
}

// ============================================
// User Persona Types
// ============================================

export interface UserPersona {
  badges: Badge[];
  xp: number;
  level: number;
  activeQuests: string[]; // Quest IDs
  completedQuests: string[];
  financialGoals: FinancialGoal[];
  createdAt: string;
  updatedAt: string;
}

// ============================================
// Quest Definitions
// ============================================

export const BRONZE_PILOT_QUEST: Quest = {
  id: 'bronze_pilot',
  name: 'The Bronze Pilot',
  description: 'Complete your Financial Health Checkup to earn your wings!',
  status: 'active',
  progress: 0,
  reward: {
    badge: {
      id: 'bronze_pilot',
      name: 'Bronze Pilot',
      icon: 'flight',
      color: '#CD7F32', // Bronze color
      description: 'Completed the Financial Health Checkup',
    },
    featureUnlock: 'tax_provisioning',
    xp: 100,
  },
  steps: [
    {
      id: 'short_term_goal',
      title: 'Short-Term Goal',
      description: 'Set your goal for the next 0-12 months',
      isCompleted: false,
    },
    {
      id: 'medium_term_goal',
      title: 'Medium-Term Goal',
      description: 'Set your goal for the next 1-5 years',
      isCompleted: false,
    },
    {
      id: 'long_term_goal',
      title: 'Long-Term Goal',
      description: 'Set your goal for 5+ years ahead',
      isCompleted: false,
    },
  ],
};

// ============================================
// Default User Persona
// ============================================

export const DEFAULT_USER_PERSONA: UserPersona = {
  badges: [],
  xp: 0,
  level: 1,
  activeQuests: ['bronze_pilot'],
  completedQuests: [],
  financialGoals: [],
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
};
