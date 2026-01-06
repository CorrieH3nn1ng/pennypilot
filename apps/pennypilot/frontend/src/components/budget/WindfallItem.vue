<template>
  <q-item class="q-py-md">
    <q-item-section avatar>
      <q-avatar color="info" text-color="white">
        <q-icon name="celebration" />
      </q-avatar>
    </q-item-section>

    <q-item-section>
      <q-item-label class="text-weight-medium">
        {{ transaction?.description || 'Unknown Transaction' }}
      </q-item-label>
      <q-item-label caption>
        {{ transaction?.transaction_date }}
        <q-badge color="info" class="q-ml-xs">windfall</q-badge>
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-item-label class="text-weight-bold text-positive">
        +R {{ formatMoney(Math.abs(Number(transaction?.amount || 0))) }}
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-btn
        flat
        round
        icon="add_circle"
        color="primary"
        size="sm"
        @click="$emit('add-to-blueprint', audit)"
      >
        <q-tooltip>Add as recurring income</q-tooltip>
      </q-btn>
    </q-item-section>
  </q-item>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { TransactionAudit } from '@/types';
import { formatNumber } from '@/utils/currency';

const props = defineProps<{
  audit: TransactionAudit;
}>();

defineEmits<{
  (e: 'add-to-blueprint', audit: TransactionAudit): void;
}>();

const transaction = computed(() => props.audit.transaction);

function formatMoney(amount: number): string {
  return formatNumber(amount);
}
</script>
