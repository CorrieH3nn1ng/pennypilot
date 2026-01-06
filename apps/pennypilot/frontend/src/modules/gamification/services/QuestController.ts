/**
 * Quest Controller
 * Handles quest logic, progression tracking, and reward distribution
 * Part of Directive #5: Gamification
 */

import { v4 as uuidv4 } from 'uuid';
import type {
  Quest,
  QuestReward,
  FinancialGoal,
  GoalTimeframe,
  Badge,
  UserPersona,
  QuestStep,
} from '../types/quest.types';
import { BRONZE_PILOT_QUEST } from '../types/quest.types';

export class QuestController {
  /**
   * Check Bronze Pilot quest progress based on user's financial goals
   * Returns progress percentage (0-100)
   */
  checkBronzePilotProgress(goals: FinancialGoal[]): number {
    const hasShort = goals.some((g) => g.timeframe === 'short');
    const hasMedium = goals.some((g) => g.timeframe === 'medium');
    const hasLong = goals.some((g) => g.timeframe === 'long');

    const completed = [hasShort, hasMedium, hasLong].filter(Boolean).length;
    return Math.round((completed / 3) * 100);
  }

  /**
   * Check if Bronze Pilot quest is complete
   */
  isBronzePilotComplete(goals: FinancialGoal[]): boolean {
    return this.checkBronzePilotProgress(goals) === 100;
  }

  /**
   * Get updated quest steps based on goals
   */
  getUpdatedQuestSteps(goals: FinancialGoal[]): QuestStep[] {
    const hasShort = goals.some((g) => g.timeframe === 'short');
    const hasMedium = goals.some((g) => g.timeframe === 'medium');
    const hasLong = goals.some((g) => g.timeframe === 'long');

    return [
      {
        id: 'short_term_goal',
        title: 'Short-Term Goal',
        description: 'Set your goal for the next 0-12 months',
        isCompleted: hasShort,
      },
      {
        id: 'medium_term_goal',
        title: 'Medium-Term Goal',
        description: 'Set your goal for the next 1-5 years',
        isCompleted: hasMedium,
      },
      {
        id: 'long_term_goal',
        title: 'Long-Term Goal',
        description: 'Set your goal for 5+ years ahead',
        isCompleted: hasLong,
      },
    ];
  }

  /**
   * Get current state of Bronze Pilot quest
   */
  getBronzePilotQuest(goals: FinancialGoal[]): Quest {
    const progress = this.checkBronzePilotProgress(goals);
    const isComplete = progress === 100;

    return {
      ...BRONZE_PILOT_QUEST,
      status: isComplete ? 'completed' : 'active',
      progress,
      steps: this.getUpdatedQuestSteps(goals),
      completedAt: isComplete ? new Date().toISOString() : undefined,
    };
  }

  /**
   * Create a new financial goal
   */
  createGoal(
    timeframe: GoalTimeframe,
    category: string,
    customDescription?: string,
    targetAmount?: number,
    targetDate?: string
  ): FinancialGoal {
    return {
      id: uuidv4(),
      timeframe,
      category,
      customDescription,
      targetAmount,
      targetDate,
      createdAt: new Date().toISOString(),
    };
  }

  /**
   * Update or replace a goal for a specific timeframe
   */
  updateGoalForTimeframe(
    existingGoals: FinancialGoal[],
    newGoal: FinancialGoal
  ): FinancialGoal[] {
    // Remove existing goal for this timeframe
    const filtered = existingGoals.filter(
      (g) => g.timeframe !== newGoal.timeframe
    );
    // Add the new goal
    return [...filtered, newGoal];
  }

  /**
   * Complete a quest and return the reward
   */
  completeQuest(questId: string): QuestReward | null {
    if (questId === 'bronze_pilot') {
      return {
        badge: {
          id: 'bronze_pilot',
          name: 'Bronze Pilot',
          icon: 'flight',
          color: '#CD7F32',
          description: 'Completed the Financial Health Checkup',
          earnedAt: new Date().toISOString(),
        },
        featureUnlock: 'tax_provisioning',
        xp: 100,
      };
    }
    return null;
  }

  /**
   * Award badge to user persona
   */
  awardBadge(persona: UserPersona, badge: Badge): UserPersona {
    // Check if badge already earned
    if (persona.badges.some((b) => b.id === badge.id)) {
      return persona;
    }

    return {
      ...persona,
      badges: [...persona.badges, badge],
      updatedAt: new Date().toISOString(),
    };
  }

  /**
   * Add XP to user persona and check for level up
   */
  addXp(persona: UserPersona, xp: number): UserPersona {
    const newXp = persona.xp + xp;
    const newLevel = this.calculateLevel(newXp);

    return {
      ...persona,
      xp: newXp,
      level: newLevel,
      updatedAt: new Date().toISOString(),
    };
  }

  /**
   * Calculate level based on XP
   * Simple formula: Level up every 500 XP
   */
  calculateLevel(xp: number): number {
    return Math.floor(xp / 500) + 1;
  }

  /**
   * Mark quest as completed in persona
   */
  markQuestCompleted(persona: UserPersona, questId: string): UserPersona {
    return {
      ...persona,
      activeQuests: persona.activeQuests.filter((q) => q !== questId),
      completedQuests: [...persona.completedQuests, questId],
      updatedAt: new Date().toISOString(),
    };
  }

  /**
   * Check if user has a specific badge
   */
  hasBadge(persona: UserPersona | null, badgeId: string): boolean {
    if (!persona) return false;
    return persona.badges.some((b) => b.id === badgeId);
  }

  /**
   * Check if user has completed a specific quest
   */
  hasCompletedQuest(persona: UserPersona | null, questId: string): boolean {
    if (!persona) return false;
    return persona.completedQuests.includes(questId);
  }

  /**
   * Check if a feature is unlocked based on badges
   */
  isFeatureUnlocked(
    persona: UserPersona | null,
    featureId: string
  ): boolean {
    if (!persona) return false;

    // Map features to required badges
    const featureBadgeMap: Record<string, string> = {
      tax_provisioning: 'bronze_pilot',
    };

    const requiredBadge = featureBadgeMap[featureId];
    if (!requiredBadge) return true; // Feature not gated

    return this.hasBadge(persona, requiredBadge);
  }
}

// Singleton instance
export const questController = new QuestController();
