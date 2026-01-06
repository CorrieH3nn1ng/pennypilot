<template>
  <q-item class="q-py-md" :class="{ 'bg-grey-2': audit.is_silenced }">
    <q-item-section avatar>
      <q-avatar color="warning" text-color="white">
        <q-icon name="warning" />
      </q-avatar>
    </q-item-section>

    <q-item-section>
      <q-item-label class="text-weight-medium" :class="{ 'text-grey': audit.is_silenced }">
        {{ transaction?.description || 'Unknown Transaction' }}
      </q-item-label>
      <q-item-label caption>
        {{ transaction?.transaction_date }}
        <q-badge v-if="audit.is_silenced" color="grey" class="q-ml-xs">silenced</q-badge>
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-item-label class="text-weight-bold text-negative">
        R {{ formatMoney(Math.abs(Number(transaction?.amount || 0))) }}
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-btn flat round icon="more_vert" size="sm">
        <q-menu>
          <q-list dense>
            <q-item clickable v-close-popup @click="$emit('silence', audit)">
              <q-item-section side>
                <q-icon :name="audit.is_silenced ? 'volume_up' : 'volume_off'" size="xs" />
              </q-item-section>
              <q-item-section>{{ audit.is_silenced ? 'Unsilence' : 'Silence' }}</q-item-section>
            </q-item>
            <q-separator />
            <q-item-label header>Add to Blueprint as:</q-item-label>
            <q-item clickable v-close-popup @click="$emit('add-to-blueprint', audit, 'needs')">
              <q-item-section side><q-icon name="home" size="xs" color="blue" /></q-item-section>
              <q-item-section>Needs (Essential)</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="$emit('add-to-blueprint', audit, 'wants')">
              <q-item-section side><q-icon name="favorite" size="xs" color="pink" /></q-item-section>
              <q-item-section>Wants (Discretionary)</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="$emit('add-to-blueprint', audit, 'savings')">
              <q-item-section side><q-icon name="savings" size="xs" color="green" /></q-item-section>
              <q-item-section>Savings/Debt</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </q-item-section>
  </q-item>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { TransactionAudit, BudgetBucket } from '@/types';
import { formatNumber } from '@/utils/currency';

const props = defineProps<{
  audit: TransactionAudit;
}>();

defineEmits<{
  (e: 'silence', audit: TransactionAudit): void;
  (e: 'add-to-blueprint', audit: TransactionAudit, bucket: BudgetBucket): void;
}>();

const transaction = computed(() => props.audit.transaction);

function formatMoney(amount: number): string {
  return formatNumber(amount);
}
</script>
