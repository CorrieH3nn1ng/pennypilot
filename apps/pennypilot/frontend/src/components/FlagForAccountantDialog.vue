<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="column no-wrap">
      <!-- Header -->
      <q-toolbar class="bg-deep-orange text-white">
        <q-btn flat round dense icon="close" @click="cancel" />
        <q-toolbar-title>Flag for Accountant</q-toolbar-title>
        <q-btn flat label="Flag" :loading="isSaving" @click="save" :disable="!isValid" />
      </q-toolbar>

      <!-- Transaction Info -->
      <q-card-section class="bg-grey-1">
        <div v-if="transaction" class="row items-center q-gutter-md">
          <q-avatar :color="transaction.amount < 0 ? 'negative' : 'positive'" text-color="white" size="48px">
            <q-icon :name="transaction.amount < 0 ? 'remove' : 'add'" />
          </q-avatar>
          <div class="col">
            <div class="text-subtitle1 text-weight-medium">{{ transaction.description }}</div>
            <div class="text-caption text-grey-7">
              {{ formatDate(transaction.transaction_date) }}
              <q-badge v-if="transaction.category" :color="transaction.category.color" class="q-ml-sm">
                {{ transaction.category.name }}
              </q-badge>
            </div>
          </div>
          <div class="text-right">
            <div :class="['text-h6', 'text-weight-bold', transaction.amount < 0 ? 'text-negative' : 'text-positive']">
              R {{ formatMoney(Math.abs(transaction.amount)) }}
            </div>
            <div class="text-caption text-grey-6">
              Potential tax saving: R {{ formatMoney(Math.abs(transaction.amount) * 0.391) }}
            </div>
          </div>
        </div>
      </q-card-section>

      <q-separator />

      <!-- Question Input -->
      <q-card-section class="col q-pa-lg">
        <div class="text-subtitle1 text-weight-medium q-mb-sm">
          <q-icon name="help_outline" class="q-mr-xs" color="deep-orange" />
          Draft Question for Accountant
        </div>
        <div class="text-caption text-grey-7 q-mb-md">
          Capture your question while the context is fresh. This will appear on the PDF report.
        </div>

        <q-input
          v-model="question"
          type="textarea"
          outlined
          autogrow
          :rows="4"
          placeholder="e.g., Is this home office expense deductible at 100% or do I need to calculate the m2 ratio?"
          class="q-mb-md"
          counter
          maxlength="1000"
        />

        <!-- Quick Suggestions -->
        <div class="text-caption text-grey-7 q-mb-sm">Quick suggestions:</div>
        <div class="row q-gutter-sm">
          <q-chip
            v-for="suggestion in suggestions"
            :key="suggestion"
            clickable
            outline
            color="deep-orange"
            @click="question = suggestion"
          >
            {{ suggestion }}
          </q-chip>
        </div>
      </q-card-section>

      <!-- Info Banner -->
      <q-card-section class="bg-amber-1 q-py-sm">
        <div class="row items-center q-gutter-sm">
          <q-icon name="info" color="amber-9" />
          <span class="text-caption text-amber-10">
            Flagged items can be exported as a PDF report for your accountant.
            If approved as a business expense, your tax reserve will automatically update.
          </span>
        </div>
      </q-card-section>

      <!-- Action Bar (sticky bottom for mobile) -->
      <q-card-actions class="bg-white q-pa-md" style="border-top: 1px solid #e0e0e0;">
        <q-btn
          flat
          label="Cancel"
          color="grey-7"
          class="col"
          @click="cancel"
        />
        <q-btn
          unelevated
          label="Flag for Review"
          color="deep-orange"
          icon="flag"
          class="col"
          :loading="isSaving"
          @click="save"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useAccountantStore } from '@/stores/accountant.store';
import { formatNumber } from '@/utils/currency';
import type { Transaction } from '@/types';

const props = defineProps<{
  modelValue: boolean;
  transaction: Transaction | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'flagged', transaction: Transaction): void;
}>();

const accountantStore = useAccountantStore();

const question = ref('');
const isSaving = ref(false);

const isValid = computed(() => true); // Question is optional

const suggestions = [
  'Is this 100% deductible as a business expense?',
  'Does this count as capital expenditure?',
  'Should this be apportioned between business and personal use?',
  'What documentation do I need to keep for this expense?',
];

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    question.value = '';
  }
});

function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function cancel(): void {
  emit('update:modelValue', false);
}

async function save(): Promise<void> {
  if (!props.transaction?.id) return;

  isSaving.value = true;
  try {
    const flagged = await accountantStore.flagTransaction(
      props.transaction.id,
      question.value.trim() || undefined
    );
    if (flagged) {
      emit('flagged', flagged);
      emit('update:modelValue', false);
    }
  } finally {
    isSaving.value = false;
  }
}
</script>
