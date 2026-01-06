<template>
  <q-item class="q-py-md">
    <q-item-section avatar>
      <q-avatar
        :color="item.is_liability_item ? 'warning' : (item.item_type === 'income' ? 'positive' : getBucketColor(item.bucket))"
        text-color="white"
      >
        <q-icon :name="getIcon()" />
      </q-avatar>
    </q-item-section>

    <q-item-section>
      <q-item-label class="text-weight-medium">
        {{ item.name }}
        <q-badge v-if="item.is_one_off" color="orange" class="q-ml-xs">one-off</q-badge>
        <q-icon v-if="item.is_liability_item" name="account_balance_wallet" size="xs" color="warning" class="q-ml-xs">
          <q-tooltip>Debt repayment</q-tooltip>
        </q-icon>
      </q-item-label>
      <q-item-label caption>
        <q-badge
          :color="item.item_type === 'income' ? 'positive' : getBucketColor(item.bucket)"
          class="q-mr-xs"
        >
          {{ item.item_type === 'income' ? 'income' : item.bucket }}
        </q-badge>
        <span v-if="item.expected_day" class="q-mr-sm">
          <q-icon name="event" size="xs" /> {{ getOrdinal(item.expected_day) }}
        </span>
        <q-badge v-if="item.audit_status === 'matched'" color="positive" outline>
          <q-icon name="check" size="xs" class="q-mr-xs" />
          matched
        </q-badge>
        <q-badge v-else-if="item.audit_status === 'missing'" color="negative" outline>
          <q-icon name="warning" size="xs" class="q-mr-xs" />
          missing
        </q-badge>
      </q-item-label>
      <!-- Liability Progress Row -->
      <q-item-label v-if="item.is_liability_item" caption class="q-mt-xs">
        <div class="row items-center q-gutter-xs">
          <q-badge color="warning" outline class="q-mr-xs">
            <q-icon name="payments" size="xs" class="q-mr-xs" />
            {{ item.installment_label || 'Installment' }}
          </q-badge>
          <span v-if="remainingBalance !== null" class="text-grey-6">
            R {{ formatMoney(remainingBalance) }} remaining
          </span>
        </div>
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-item-label class="text-weight-bold" :class="item.item_type === 'income' ? 'text-positive' : 'text-primary'">
        R {{ formatMoney(item.expected_amount) }}
      </q-item-label>
      <q-item-label v-if="showMatched && item.matched_amount" caption class="text-positive">
        Actual: R {{ formatMoney(item.matched_amount) }}
      </q-item-label>
    </q-item-section>

    <q-item-section side v-if="!showMatched">
      <q-btn
        flat
        round
        icon="link"
        color="primary"
        size="sm"
        @click="$emit('match', item)"
      >
        <q-tooltip>Match to transaction</q-tooltip>
      </q-btn>
    </q-item-section>

    <q-item-section side v-else>
      <div class="column items-end">
        <q-badge
          v-if="item.match_confidence"
          :color="getConfidenceColor(item.match_confidence)"
        >
          {{ Math.round(item.match_confidence) }}%
        </q-badge>
        <q-btn
          flat
          dense
          size="sm"
          icon="link_off"
          color="grey"
          @click="$emit('unmatch', item)"
        >
          <q-tooltip>Unmatch</q-tooltip>
        </q-btn>
      </div>
    </q-item-section>
  </q-item>

  <!-- Matched Transaction Details -->
  <q-item v-if="showMatched && item.matchedTransaction" class="bg-grey-1 q-pl-xl">
    <q-item-section avatar>
      <q-icon name="subdirectory_arrow_right" color="grey" />
    </q-item-section>
    <q-item-section>
      <q-item-label caption>{{ item.matchedTransaction.description }}</q-item-label>
      <q-item-label caption class="text-grey">{{ item.matched_date }}</q-item-label>
    </q-item-section>
  </q-item>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { BudgetCardItem, BudgetBucket } from '@/types';
import { formatNumber } from '@/utils/currency';

const props = defineProps<{
  item: BudgetCardItem;
  showMatched?: boolean;
}>();

defineEmits<{
  (e: 'match', item: BudgetCardItem): void;
  (e: 'unmatch', item: BudgetCardItem): void;
}>();

// Computed for liability remaining balance
const remainingBalance = computed((): number | null => {
  if (!props.item.is_liability_item) return null;
  // Use matched remaining balance if available, otherwise use liability's current balance
  if (props.item.remaining_balance_at_match !== null) {
    return props.item.remaining_balance_at_match;
  }
  if (props.item.remaining_balance !== null) {
    return props.item.remaining_balance;
  }
  if (props.item.liability?.current_balance !== undefined) {
    return props.item.liability.current_balance;
  }
  return null;
});

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

function getIcon(): string {
  // Liability items get special icon
  if (props.item.is_liability_item) {
    return 'account_balance_wallet';
  }
  if (props.item.category?.icon) return props.item.category.icon;
  return props.item.item_type === 'income' ? 'trending_up' : 'repeat';
}

function getOrdinal(n: number): string {
  const s = ['th', 'st', 'nd', 'rd'];
  const v = n % 100;
  return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

function getConfidenceColor(confidence: number): string {
  if (confidence >= 90) return 'positive';
  if (confidence >= 75) return 'info';
  if (confidence >= 50) return 'warning';
  return 'negative';
}
</script>
