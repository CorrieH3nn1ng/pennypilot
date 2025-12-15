<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5">Categories</div>
      <q-space />
      <q-btn color="primary" icon="add" label="Add Category" @click="openAddDialog" />
    </div>

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
              <q-item-label caption v-else class="text-primary">Custom</q-item-label>
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
              <q-item-label caption v-else class="text-primary">Custom</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>
    </q-tab-panels>

    <!-- Add Category Dialog -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Add Custom Category</div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="newCategory.name"
            label="Category Name"
            outlined
            autofocus
            :rules="[(v) => !!v || 'Name is required']"
          />

          <q-select
            v-model="newCategory.is_income"
            :options="[
              { label: 'Expense', value: false },
              { label: 'Income', value: true },
            ]"
            label="Type"
            outlined
            emit-value
            map-options
          />

          <div>
            <div class="text-caption text-grey q-mb-xs">Color</div>
            <div class="row q-gutter-sm">
              <q-avatar
                v-for="color in colorOptions"
                :key="color"
                :style="{ backgroundColor: color, cursor: 'pointer' }"
                size="32px"
                @click="newCategory.color = color"
              >
                <q-icon
                  v-if="newCategory.color === color"
                  name="check"
                  color="white"
                  size="18px"
                />
              </q-avatar>
            </div>
          </div>

          <div>
            <div class="text-caption text-grey q-mb-xs">Icon</div>
            <div class="row q-gutter-sm">
              <q-avatar
                v-for="icon in iconOptions"
                :key="icon"
                :style="{
                  backgroundColor: newCategory.icon === icon ? newCategory.color : '#E0E0E0',
                  cursor: 'pointer',
                }"
                size="32px"
                @click="newCategory.icon = icon"
              >
                <q-icon :name="icon" color="white" size="18px" />
              </q-avatar>
            </div>
          </div>

          <!-- Preview -->
          <div class="q-mt-md">
            <div class="text-caption text-grey q-mb-xs">Preview</div>
            <q-item dense>
              <q-item-section avatar>
                <q-avatar :style="{ backgroundColor: newCategory.color }" size="40px">
                  <q-icon :name="newCategory.icon" color="white" />
                </q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ newCategory.name || 'Category Name' }}</q-item-label>
                <q-item-label caption>{{ newCategory.is_income ? 'Income' : 'Expense' }}</q-item-label>
              </q-item-section>
            </q-item>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Create"
            :loading="isCreating"
            :disable="!newCategory.name"
            @click="createCategory"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useQuasar } from 'quasar';
import { useCategoriesStore } from '@/stores/categories.store';

const $q = useQuasar();
const categoriesStore = useCategoriesStore();
const tab = ref('expenses');

const expenseCategories = computed(() => categoriesStore.expenseCategories);
const incomeCategories = computed(() => categoriesStore.incomeCategories);

// Add dialog state
const showAddDialog = ref(false);
const isCreating = ref(false);
const newCategory = ref({
  name: '',
  icon: 'label',
  color: '#1976D2',
  is_income: false,
});

const colorOptions = [
  '#F44336', '#E91E63', '#9C27B0', '#673AB7',
  '#3F51B5', '#1976D2', '#0288D1', '#00BCD4',
  '#009688', '#4CAF50', '#8BC34A', '#CDDC39',
  '#FFC107', '#FF9800', '#FF5722', '#795548',
];

const iconOptions = [
  'label', 'work', 'business', 'store', 'shopping_bag',
  'computer', 'phone_android', 'directions_car', 'flight',
  'home', 'school', 'fitness_center', 'pets', 'child_care',
  'local_cafe', 'local_bar', 'build', 'handyman',
];

function openAddDialog() {
  newCategory.value = {
    name: '',
    icon: 'label',
    color: '#1976D2',
    is_income: tab.value === 'income',
  };
  showAddDialog.value = true;
}

async function createCategory() {
  if (!newCategory.value.name) return;

  isCreating.value = true;

  try {
    const created = await categoriesStore.createCategory({
      name: newCategory.value.name,
      icon: newCategory.value.icon,
      color: newCategory.value.color,
      is_income: newCategory.value.is_income,
    });

    if (created) {
      $q.notify({
        type: 'positive',
        message: `Category "${created.name}" created`,
      });
      showAddDialog.value = false;

      // Switch to the appropriate tab
      tab.value = newCategory.value.is_income ? 'income' : 'expenses';
    } else {
      $q.notify({
        type: 'negative',
        message: categoriesStore.error || 'Failed to create category',
      });
    }
  } finally {
    isCreating.value = false;
  }
}
</script>
