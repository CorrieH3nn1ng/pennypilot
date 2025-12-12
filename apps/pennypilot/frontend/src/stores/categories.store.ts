import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { localBaseService } from '@/services/storage/LocalBaseService';
import { categoriesApi } from '@/services/api/categories.api';
import type { Category } from '@/types';

export const useCategoriesStore = defineStore('categories', () => {
  // State
  const categories = ref<Category[]>([]);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // Getters
  const expenseCategories = computed(() =>
    categories.value.filter((c) => !c.is_income).sort((a, b) => a.sort_order - b.sort_order)
  );

  const incomeCategories = computed(() =>
    categories.value.filter((c) => c.is_income).sort((a, b) => a.sort_order - b.sort_order)
  );

  const categoryMap = computed(() => {
    const map = new Map<string, Category>();
    categories.value.forEach((c) => map.set(c.id, c));
    return map;
  });

  // Actions
  async function loadFromLocal(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      categories.value = await localBaseService.getAllCategories();
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load categories';
    } finally {
      isLoading.value = false;
    }
  }

  async function loadFromServer(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const serverCategories = await categoriesApi.list();
      categories.value = serverCategories;

      // Cache locally
      await localBaseService.setCategories(serverCategories);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load categories from server';

      // Fall back to local if available
      const local = await localBaseService.getAllCategories();
      if (local.length > 0) {
        categories.value = local;
      }
    } finally {
      isLoading.value = false;
    }
  }

  async function createCategory(data: {
    name: string;
    icon?: string;
    color?: string;
    is_income?: boolean;
  }): Promise<Category | null> {
    try {
      const created = await categoriesApi.create(data);
      categories.value.push(created);
      await localBaseService.addCategory(created);
      return created;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to create category';
      return null;
    }
  }

  function getCategoryById(id: string | null): Category | undefined {
    if (!id) return undefined;
    return categoryMap.value.get(id);
  }

  function getCategoryColor(id: string | null): string {
    const category = getCategoryById(id);
    return category?.color || '#9E9E9E';
  }

  function getCategoryIcon(id: string | null): string {
    const category = getCategoryById(id);
    return category?.icon || 'category';
  }

  function getCategoryName(id: string | null): string {
    const category = getCategoryById(id);
    return category?.name || 'Uncategorized';
  }

  return {
    // State
    categories,
    isLoading,
    error,
    // Getters
    expenseCategories,
    incomeCategories,
    categoryMap,
    // Actions
    loadFromLocal,
    loadFromServer,
    createCategory,
    getCategoryById,
    getCategoryColor,
    getCategoryIcon,
    getCategoryName,
  };
});
