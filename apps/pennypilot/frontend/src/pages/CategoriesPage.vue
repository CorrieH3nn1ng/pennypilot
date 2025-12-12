<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Categories</div>

    <q-tabs v-model="tab" class="q-mb-md" align="left">
      <q-tab name="expenses" label="Expenses" />
      <q-tab name="income" label="Income" />
    </q-tabs>

    <q-tab-panels v-model="tab" animated>
      <q-tab-panel name="expenses">
        <q-list separator>
          <q-item v-for="cat in expenseCategories" :key="cat.id">
            <q-item-section avatar>
              <q-avatar :style="{ backgroundColor: cat.color }">
                <q-icon :name="cat.icon" color="white" />
              </q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ cat.name }}</q-item-label>
              <q-item-label caption v-if="cat.is_system">System Default</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>

      <q-tab-panel name="income">
        <q-list separator>
          <q-item v-for="cat in incomeCategories" :key="cat.id">
            <q-item-section avatar>
              <q-avatar :style="{ backgroundColor: cat.color }">
                <q-icon :name="cat.icon" color="white" />
              </q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ cat.name }}</q-item-label>
              <q-item-label caption v-if="cat.is_system">System Default</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useCategoriesStore } from '@/stores/categories.store';

const categoriesStore = useCategoriesStore();
const tab = ref('expenses');

const expenseCategories = computed(() => categoriesStore.expenseCategories);
const incomeCategories = computed(() => categoriesStore.incomeCategories);
</script>
