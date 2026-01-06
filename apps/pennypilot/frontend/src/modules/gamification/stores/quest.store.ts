/**
 * Quest Store
 * Manages quest state, user persona, and gamification progress
 * Part of Directive #5: Gamification
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  Quest,
  UserPersona,
  FinancialGoal,
  GoalTimeframe,
  Badge,
  QuestReward,
} from '../types/quest.types';
import { DEFAULT_USER_PERSONA, BRONZE_PILOT_QUEST } from '../types/quest.types';
import { questController } from '../services/QuestController';
import { localBaseService } from '@/services/storage/LocalBaseService';

export const useQuestStore = defineStore('quest', () => {
  // ============================================
  // State
  // ============================================

  const persona = ref<UserPersona | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const showCompletionModal = ref(false);
  const lastReward = ref<QuestReward | null>(null);

  // ============================================
  // Computed
  // ============================================

  const financialGoals = computed(() => persona.value?.financialGoals || []);

  const badges = computed(() => persona.value?.badges || []);

  const xp = computed(() => persona.value?.xp || 0);

  const level = computed(() => persona.value?.level || 1);

  const activeQuests = computed(() => persona.value?.activeQuests || []);

  const completedQuests = computed(() => persona.value?.completedQuests || []);

  // Bronze Pilot specific
  const bronzePilotQuest = computed<Quest>(() => {
    return questController.getBronzePilotQuest(financialGoals.value);
  });

  const bronzePilotProgress = computed(() =>
    questController.checkBronzePilotProgress(financialGoals.value)
  );

  const hasBronzePilot = computed(() =>
    questController.hasBadge(persona.value, 'bronze_pilot')
  );

  const isBronzePilotActive = computed(
    () => activeQuests.value.includes('bronze_pilot') && !hasBronzePilot.value
  );

  // Feature unlocks
  const canUseTaxProvisioning = computed(() =>
    questController.isFeatureUnlocked(persona.value, 'tax_provisioning')
  );

  // ============================================
  // Actions
  // ============================================

  /**
   * Initialize persona from local storage
   */
  async function loadPersona(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const stored = await localBaseService.getPersona();
      if (stored) {
        persona.value = stored;
      } else {
        // Create default persona for new users
        persona.value = { ...DEFAULT_USER_PERSONA };
        // Try to save, but don't fail if collection was just wiped
        try {
          await localBaseService.savePersona(persona.value);
        } catch {
          // Collection may need a page refresh after wipe - that's OK
          console.log('Persona will be saved on next interaction');
        }
      }
    } catch (e) {
      // This is expected after a data wipe - just use default
      console.log('No stored persona found, using default');
      persona.value = { ...DEFAULT_USER_PERSONA };
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Save persona to local storage
   */
  async function savePersona(): Promise<void> {
    if (!persona.value) return;

    try {
      await localBaseService.savePersona(persona.value);
    } catch (e) {
      console.error('Error saving persona:', e);
      error.value = 'Failed to save progress';
    }
  }

  /**
   * Add or update a financial goal
   */
  async function setGoal(
    timeframe: GoalTimeframe,
    category: string,
    customDescription?: string,
    targetAmount?: number,
    targetDate?: string
  ): Promise<void> {
    if (!persona.value) return;

    const newGoal = questController.createGoal(
      timeframe,
      category,
      customDescription,
      targetAmount,
      targetDate
    );

    const updatedGoals = questController.updateGoalForTimeframe(
      persona.value.financialGoals,
      newGoal
    );

    persona.value = {
      ...persona.value,
      financialGoals: updatedGoals,
      updatedAt: new Date().toISOString(),
    };

    await savePersona();

    // Check if Bronze Pilot quest is now complete
    if (
      isBronzePilotActive.value &&
      questController.isBronzePilotComplete(updatedGoals)
    ) {
      await completeBronzePilotQuest();
    }
  }

  /**
   * Get goal for a specific timeframe
   */
  function getGoalForTimeframe(timeframe: GoalTimeframe): FinancialGoal | undefined {
    return financialGoals.value.find((g) => g.timeframe === timeframe);
  }

  /**
   * Complete the Bronze Pilot quest
   */
  async function completeBronzePilotQuest(): Promise<QuestReward | null> {
    if (!persona.value) return null;

    const reward = questController.completeQuest('bronze_pilot');
    if (!reward) return null;

    // Award badge
    persona.value = questController.awardBadge(persona.value, reward.badge);

    // Add XP
    persona.value = questController.addXp(persona.value, reward.xp);

    // Mark quest completed
    persona.value = questController.markQuestCompleted(
      persona.value,
      'bronze_pilot'
    );

    await savePersona();

    // Show completion modal
    lastReward.value = reward;
    showCompletionModal.value = true;

    return reward;
  }

  /**
   * Dismiss completion modal
   */
  function dismissCompletionModal(): void {
    showCompletionModal.value = false;
    lastReward.value = null;
  }

  /**
   * Award a badge manually (for testing or admin)
   */
  async function awardBadge(badge: Badge): Promise<void> {
    if (!persona.value) return;

    persona.value = questController.awardBadge(persona.value, badge);
    await savePersona();
  }

  /**
   * Add XP manually
   */
  async function addXp(amount: number): Promise<void> {
    if (!persona.value) return;

    persona.value = questController.addXp(persona.value, amount);
    await savePersona();
  }

  /**
   * Reset persona (for testing)
   */
  async function resetPersona(): Promise<void> {
    persona.value = { ...DEFAULT_USER_PERSONA };
    await savePersona();
  }

  // ============================================
  // Return
  // ============================================

  return {
    // State
    persona,
    isLoading,
    error,
    showCompletionModal,
    lastReward,

    // Computed
    financialGoals,
    badges,
    xp,
    level,
    activeQuests,
    completedQuests,
    bronzePilotQuest,
    bronzePilotProgress,
    hasBronzePilot,
    isBronzePilotActive,
    canUseTaxProvisioning,

    // Actions
    loadPersona,
    savePersona,
    setGoal,
    getGoalForTimeframe,
    completeBronzePilotQuest,
    dismissCompletionModal,
    awardBadge,
    addXp,
    resetPersona,
  };
});
