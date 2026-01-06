<template>
  <div class="goal-step-content">
    <!-- Timeframe Header -->
    <div class="q-mb-lg">
      <div class="text-h6">{{ timeframeLabel.title }}</div>
      <div class="text-grey-6">{{ timeframeLabel.subtitle }}</div>
    </div>

    <!-- Goal Prompt -->
    <p class="text-body1 q-mb-md">
      What's your main {{ timeframe }}-term financial goal?
    </p>

    <!-- Goal Options -->
    <q-list>
      <q-item
        v-for="option in goalOptions"
        :key="option.id"
        tag="label"
        clickable
        @click="selectGoal(option.id)"
      >
        <q-item-section side>
          <q-radio
            :model-value="goal.category"
            :val="option.id"
            color="primary"
            @update:model-value="selectGoal($event)"
          />
        </q-item-section>
        <q-item-section avatar>
          <q-icon :name="option.icon" color="primary" />
        </q-item-section>
        <q-item-section>
          <q-item-label>{{ option.label }}</q-item-label>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Custom Goal Input -->
    <q-slide-transition>
      <div v-if="goal.category === 'custom'" class="q-mt-md">
        <q-input
          :model-value="goal.customDescription"
          @update:model-value="updateField('customDescription', $event)"
          outlined
          label="Describe your goal"
          placeholder="e.g., Save for a new laptop"
          :rules="[(v) => !!v?.trim() || 'Please describe your goal']"
        />
      </div>
    </q-slide-transition>

    <!-- Optional Target Amount -->
    <div class="q-mt-lg">
      <div class="text-caption text-grey-7 q-mb-sm">
        Target amount (optional)
      </div>
      <q-input
        :model-value="goal.targetAmount"
        @update:model-value="updateField('targetAmount', $event ? Number($event) : undefined)"
        outlined
        type="number"
        :prefix="configStore.currencySymbol"
        placeholder="0.00"
        :input-style="{ textAlign: 'right' }"
      />
    </div>

    <!-- Optional Target Date -->
    <div class="q-mt-md">
      <div class="text-caption text-grey-7 q-mb-sm">
        Target date (optional)
      </div>
      <q-input
        :model-value="goal.targetDate"
        @update:model-value="updateField('targetDate', $event)"
        outlined
        type="date"
        :min="minDate"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { GoalTimeframe, FinancialGoal } from '../types/quest.types';
import { getGoalOptions, TIMEFRAME_LABELS } from '../data/goals';
import { useConfigStore } from '@/stores/config.store';

const configStore = useConfigStore();

const props = defineProps<{
  timeframe: GoalTimeframe;
  goal: Partial<FinancialGoal>;
}>();

const emit = defineEmits<{
  (e: 'update:goal', value: Partial<FinancialGoal>): void;
}>();

const goalOptions = computed(() => getGoalOptions(props.timeframe));
const timeframeLabel = computed(() => TIMEFRAME_LABELS[props.timeframe]);

const minDate = computed(() => {
  const today = new Date();
  return today.toISOString().split('T')[0];
});

function selectGoal(categoryId: string): void {
  emit('update:goal', {
    ...props.goal,
    category: categoryId,
    // Clear custom description if not custom
    customDescription:
      categoryId === 'custom' ? props.goal.customDescription : undefined,
  });
}

function updateField(
  field: keyof FinancialGoal,
  value: string | number | undefined
): void {
  emit('update:goal', {
    ...props.goal,
    [field]: value,
  });
}
</script>

<style scoped>
.goal-step-content {
  max-width: 500px;
  margin: 0 auto;
}
</style>
