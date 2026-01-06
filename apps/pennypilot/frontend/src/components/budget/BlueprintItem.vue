<template>
  <q-item class="q-py-md" :class="{ 'bg-grey-2': !item.is_active }">
    <q-item-section avatar>
      <q-avatar
        :color="item.is_active ? (item.item_type === 'income' ? 'positive' : getBucketColor(item.bucket)) : 'grey'"
        text-color="white"
      >
        <q-icon :name="getItemIcon()" />
      </q-avatar>
    </q-item-section>

    <q-item-section>
      <q-item-label class="text-weight-medium" :class="{ 'text-grey': !item.is_active }">
        {{ item.name }}
        <q-icon v-if="item.is_liability_item" name="account_balance_wallet" size="xs" color="warning" class="q-ml-xs">
          <q-tooltip>Linked to debt recovery</q-tooltip>
        </q-icon>
      </q-item-label>
      <q-item-label caption>
        <q-badge
          v-if="item.item_type === 'expense'"
          :color="getBucketColor(item.bucket)"
          class="q-mr-xs"
        >
          {{ item.bucket }}
        </q-badge>
        <q-badge v-else color="positive" class="q-mr-xs">income</q-badge>
        <q-badge
          v-if="item.item_type === 'expense' && item.nature"
          :color="item.nature === 'business' ? 'blue-8' : 'purple-6'"
          outline
          class="q-mr-xs"
        >
          {{ item.nature === 'business' ? 'BIZ' : 'PVT' }}
        </q-badge>
        <span v-if="item.due_day" class="q-mr-sm">
          <q-icon name="event" size="xs" /> {{ getOrdinal(item.due_day) }}
        </span>
        <span v-if="item.match_pattern" class="text-grey-6">
          <q-icon name="search" size="xs" /> {{ item.match_pattern }}
        </span>
      </q-item-label>
      <!-- Liability Progress Row -->
      <q-item-label v-if="item.is_liability_item && item.liability" caption class="q-mt-xs">
        <div class="row items-center q-gutter-xs">
          <q-linear-progress
            :value="item.liability.progress_percent / 100"
            color="warning"
            class="col"
            style="max-width: 100px;"
            rounded
          />
          <span class="text-warning text-weight-medium">
            {{ Math.round(item.liability.progress_percent) }}%
          </span>
          <span class="text-grey-6">
            R {{ formatMoney(item.liability.current_balance) }} remaining
          </span>
        </div>
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-item-label
        class="text-weight-bold"
        :class="item.item_type === 'income' ? 'text-positive' : 'text-primary'"
      >
        R {{ formatMoney(item.expected_amount) }}/mo
      </q-item-label>
      <q-item-label caption v-if="item.frequency !== 'monthly'">
        {{ item.frequency }}
      </q-item-label>
      <q-item-label v-if="item.is_liability_item && item.liability" caption class="text-warning">
        {{ item.liability.installment_label }}
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-btn flat round icon="more_vert" size="sm">
        <q-menu>
          <q-list dense>
            <q-item clickable v-close-popup @click="$emit('edit', item)">
              <q-item-section side><q-icon name="edit" size="xs" /></q-item-section>
              <q-item-section>Edit</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="$emit('toggle', item)">
              <q-item-section side>
                <q-icon :name="item.is_active ? 'visibility_off' : 'visibility'" size="xs" />
              </q-item-section>
              <q-item-section>{{ item.is_active ? 'Deactivate' : 'Activate' }}</q-item-section>
            </q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="$emit('delete', item)">
              <q-item-section side><q-icon name="delete" size="xs" color="negative" /></q-item-section>
              <q-item-section class="text-negative">Delete</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </q-item-section>
  </q-item>
</template>

<script setup lang="ts">
import type { Blueprint, BudgetBucket } from '@/types';
import { formatNumber } from '@/utils/currency';

const props = defineProps<{
  item: Blueprint;
}>();

defineEmits<{
  (e: 'edit', item: Blueprint): void;
  (e: 'toggle', item: Blueprint): void;
  (e: 'delete', item: Blueprint): void;
}>();

function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function getBucketColor(bucket: BudgetBucket): string {
  switch (bucket) {
    case 'needs': return 'blue';
    case 'wants': return 'pink';
    case 'savings': return 'green';
    default: return 'grey';
  }
}

function getItemIcon(): string {
  // Liability items get a special icon
  if (props.item.is_liability_item) {
    return 'account_balance_wallet';
  }
  // Use category icon if available
  if (props.item.category?.icon) {
    return props.item.category.icon;
  }
  // Default icons
  return props.item.item_type === 'income' ? 'trending_up' : 'repeat';
}

function getOrdinal(n: number): string {
  const s = ['th', 'st', 'nd', 'rd'];
  const v = n % 100;
  return n + (s[(v - 20) % 10] || s[v] || s[0]);
}
</script>
