import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  budgetCardApi,
  type GenerateCardParams,
  type AddItemData,
  type UpdateItemData,
  type FinalizeData,
  type CardListItem,
} from '@/services/api/budget-card.api';
import { auditApi, type MatchData, type SilenceData, type LeakToBlueprintData } from '@/services/api/audit.api';
import type {
  BudgetCard,
  BudgetCardItem,
  BudgetCardDetails,
  AuditStats,
  SuccessGrade,
  TransactionAudit,
  AuditResult,
} from '@/types';

export const useBudgetCardStore = defineStore('budgetCard', () => {
  // State
  const cards = ref<CardListItem[]>([]);
  const currentCard = ref<BudgetCardDetails | null>(null);
  const selectedCard = ref<BudgetCardDetails | null>(null);
  const isLoading = ref(false);
  const isAuditing = ref(false);
  const error = ref<string | null>(null);
  const lastAuditResult = ref<AuditResult | null>(null);

  // Getters
  const hasCurrentCard = computed(() => currentCard.value !== null);

  const currentCardStatus = computed(() => currentCard.value?.card.status ?? null);

  const currentStats = computed(() => currentCard.value?.stats ?? null);

  const pendingItems = computed(() => currentCard.value?.items.grouped.pending ?? []);

  const matchedItems = computed(() => currentCard.value?.items.grouped.matched ?? []);

  const missingItems = computed(() => currentCard.value?.items.grouped.missing ?? []);

  const leaks = computed(() => currentCard.value?.audits.leaks ?? []);

  const windfalls = computed(() => currentCard.value?.audits.windfalls ?? []);

  const transfers = computed(() => currentCard.value?.audits.transfers ?? []);

  const matchRate = computed(() => {
    const stats = currentStats.value;
    if (!stats || stats.total_items === 0) return 0;
    return Math.round((stats.matched_items / stats.total_items) * 100);
  });

  const incomeItems = computed(() => {
    const items = currentCard.value?.items.all ?? [];
    return items.filter((i) => i.item_type === 'income');
  });

  const expenseItems = computed(() => {
    const items = currentCard.value?.items.all ?? [];
    return items.filter((i) => i.item_type === 'expense');
  });

  // Actions
  async function fetchCards() {
    isLoading.value = true;
    error.value = null;
    try {
      cards.value = await budgetCardApi.list();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch budget cards';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchCurrentCard() {
    isLoading.value = true;
    error.value = null;
    try {
      currentCard.value = await budgetCardApi.current();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch current card';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchCard(id: string) {
    isLoading.value = true;
    error.value = null;
    try {
      selectedCard.value = await budgetCardApi.get(id);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch budget card';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function generateCard(params: GenerateCardParams) {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await budgetCardApi.generate(params);
      await fetchCurrentCard();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to generate budget card';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function renewCard() {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await budgetCardApi.renew();
      await fetchCards();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to renew budget card';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function addItem(cardId: string, data: AddItemData): Promise<BudgetCardItem> {
    isLoading.value = true;
    error.value = null;
    try {
      const item = await budgetCardApi.addItem(cardId, data);
      await fetchCurrentCard();
      return item;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to add item';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateItem(cardId: string, itemId: string, data: UpdateItemData): Promise<BudgetCardItem> {
    error.value = null;
    try {
      const item = await budgetCardApi.updateItem(cardId, itemId, data);
      await fetchCurrentCard();
      return item;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to update item';
      throw e;
    }
  }

  async function removeItem(cardId: string, itemId: string): Promise<void> {
    error.value = null;
    try {
      await budgetCardApi.removeItem(cardId, itemId);
      await fetchCurrentCard();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to remove item';
      throw e;
    }
  }

  async function activateCard(id: string): Promise<void> {
    error.value = null;
    try {
      await budgetCardApi.activate(id);
      await fetchCurrentCard();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to activate card';
      throw e;
    }
  }

  async function finalizeCard(id: string, data: FinalizeData) {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await budgetCardApi.finalize(id, data);
      await fetchCurrentCard();
      await fetchCards();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to finalize card';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function getGrade(id: string): Promise<SuccessGrade> {
    error.value = null;
    try {
      return await budgetCardApi.getGrade(id);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to get grade';
      throw e;
    }
  }

  // Audit Actions
  async function runAudit(cardId?: string): Promise<AuditResult> {
    isAuditing.value = true;
    error.value = null;
    try {
      const result = cardId ? await auditApi.run(cardId) : await auditApi.runCurrent();
      lastAuditResult.value = result;
      await fetchCurrentCard();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to run audit';
      throw e;
    } finally {
      isAuditing.value = false;
    }
  }

  async function matchTransaction(data: MatchData): Promise<BudgetCardItem> {
    error.value = null;
    try {
      const item = await auditApi.match(data);
      await fetchCurrentCard();
      return item;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to match transaction';
      throw e;
    }
  }

  async function unmatchTransaction(itemId: string): Promise<BudgetCardItem> {
    error.value = null;
    try {
      const item = await auditApi.unmatch({ budget_card_item_id: itemId });
      await fetchCurrentCard();
      return item;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to unmatch transaction';
      throw e;
    }
  }

  async function silenceLeak(auditId: string): Promise<TransactionAudit> {
    error.value = null;
    try {
      const audit = await auditApi.silence({ transaction_audit_id: auditId });
      await fetchCurrentCard();
      return audit;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to silence leak';
      throw e;
    }
  }

  async function unsilenceLeak(auditId: string): Promise<TransactionAudit> {
    error.value = null;
    try {
      const audit = await auditApi.unsilence({ transaction_audit_id: auditId });
      await fetchCurrentCard();
      return audit;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to unsilence leak';
      throw e;
    }
  }

  async function convertLeakToBlueprint(data: LeakToBlueprintData): Promise<{ blueprint_id: string }> {
    error.value = null;
    try {
      const result = await auditApi.leakToBlueprint(data);
      await fetchCurrentCard();
      return result;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to convert leak to blueprint';
      throw e;
    }
  }

  function clearError() {
    error.value = null;
  }

  function getItemById(itemId: string): BudgetCardItem | undefined {
    return currentCard.value?.items.all.find((i) => i.id === itemId);
  }

  return {
    // State
    cards,
    currentCard,
    selectedCard,
    isLoading,
    isAuditing,
    error,
    lastAuditResult,
    // Getters
    hasCurrentCard,
    currentCardStatus,
    currentStats,
    pendingItems,
    matchedItems,
    missingItems,
    leaks,
    windfalls,
    transfers,
    matchRate,
    incomeItems,
    expenseItems,
    // Actions
    fetchCards,
    fetchCurrentCard,
    fetchCard,
    generateCard,
    renewCard,
    addItem,
    updateItem,
    removeItem,
    activateCard,
    finalizeCard,
    getGrade,
    // Audit Actions
    runAudit,
    matchTransaction,
    unmatchTransaction,
    silenceLeak,
    unsilenceLeak,
    convertLeakToBlueprint,
    clearError,
    getItemById,
  };
});
