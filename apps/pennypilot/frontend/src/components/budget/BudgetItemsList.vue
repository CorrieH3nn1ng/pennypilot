<template>
  <div>
    <!-- Empty State -->
    <div v-if="items.length === 0" class="text-center q-pa-lg">
      <q-icon name="inbox" size="48px" color="grey-4" />
      <div class="text-body2 text-grey q-mt-sm">No {{ bucket }} items yet</div>
      <q-btn flat color="primary" icon="add" label="Add Item" @click="$emit('add')" class="q-mt-sm" />
    </div>

    <!-- Items List -->
    <q-list v-else separator>
      <q-item v-for="item in items" :key="item.id">
        <q-item-section avatar>
          <q-avatar
            v-if="item.category"
            :style="{ backgroundColor: item.category.color }"
            size="36px"
          >
            <q-icon :name="item.category.icon" color="white" size="18px" />
          </q-avatar>
          <q-avatar v-else color="grey-4" size="36px">
            <q-icon name="label" color="white" size="18px" />
          </q-avatar>
        </q-item-section>

        <q-item-section>
          <q-item-label>{{ item.name || item.category?.name || 'Unnamed' }}</q-item-label>
          <q-item-label caption>
            <q-badge v-if="item.is_fixed" color="blue-grey" class="q-mr-xs">Fixed</q-badge>
            <span :class="getVarianceClass(item)">
              {{ getVarianceText(item) }}
            </span>
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <div class="text-right">
            <div class="text-weight-medium">
              R {{ formatMoney(item.actual_amount) }}
            </div>
            <div class="text-caption text-grey">
              of R {{ formatMoney(item.planned_amount) }}
            </div>
          </div>
        </q-item-section>

        <q-item-section side style="width: 80px">
          <q-linear-progress
            :value="getProgress(item)"
            :color="getProgressColor(item)"
            size="8px"
            rounded
          />
          <div class="text-caption text-right q-mt-xs">
            {{ Math.round(item.percent_spent || 0) }}%
          </div>
        </q-item-section>
      </q-item>

      <!-- Add Button -->
      <q-item clickable @click="$emit('add')">
        <q-item-section avatar>
          <q-avatar color="primary" text-color="white" size="36px">
            <q-icon name="add" size="18px" />
          </q-avatar>
        </q-item-section>
        <q-item-section>
          <q-item-label class="text-primary">Add {{ bucket }} item</q-item-label>
        </q-item-section>
      </q-item>
    </q-list>
  </div>
</template>

<script setup lang="ts">
import type { BudgetItem, BudgetBucket } from '@/types';

interface Props {
  items: BudgetItem[];
  bucket: BudgetBucket;
}

defineProps<Props>();
defineEmits<{
  (e: 'add'): void;
}>();

function formatMoney(amount: number): string {
  return amount.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getProgress(item: BudgetItem): number {
  if (item.planned_amount === 0) return 0;
  return Math.min(1, item.actual_amount / item.planned_amount);
}

function getProgressColor(item: BudgetItem): string {
  const percent = (item.actual_amount / item.planned_amount) * 100;
  if (percent >= 100) return 'negative';
  if (percent >= 80) return 'warning';
  return 'positive';
}

function getVarianceClass(item: BudgetItem): string {
  const variance = item.variance || (item.planned_amount - item.actual_amount);
  if (variance < 0) return 'text-negative';
  return 'text-grey';
}

function getVarianceText(item: BudgetItem): string {
  const variance = item.variance || (item.planned_amount - item.actual_amount);
  if (variance > 0) return `R ${formatMoney(variance)} under`;
  if (variance < 0) return `R ${formatMoney(Math.abs(variance))} over`;
  return 'On budget';
}
</script>
