<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="column full-height">
      <!-- Header -->
      <q-toolbar class="bg-primary text-white">
        <q-avatar color="white" text-color="primary" size="36px">
          <q-icon name="flight" />
        </q-avatar>
        <q-toolbar-title>
          <div class="text-weight-bold">The Bronze Pilot</div>
          <div class="text-caption">Financial Health Checkup</div>
        </q-toolbar-title>
        <q-btn flat round icon="close" @click="handleClose" />
      </q-toolbar>

      <!-- Stepper -->
      <q-stepper
        v-model="currentStep"
        ref="stepper"
        color="primary"
        animated
        flat
        class="col"
      >
        <!-- Step 1: Short-term Goal -->
        <q-step
          :name="1"
          title="Short-Term Goal"
          icon="schedule"
          :done="currentStep > 1"
        >
          <GoalStepContent
            timeframe="short"
            :goal="shortTermGoal"
            @update:goal="updateGoal('short', $event)"
          />
        </q-step>

        <!-- Step 2: Medium-term Goal -->
        <q-step
          :name="2"
          title="Medium-Term Goal"
          icon="event"
          :done="currentStep > 2"
        >
          <GoalStepContent
            timeframe="medium"
            :goal="mediumTermGoal"
            @update:goal="updateGoal('medium', $event)"
          />
        </q-step>

        <!-- Step 3: Long-term Goal -->
        <q-step
          :name="3"
          title="Long-Term Goal"
          icon="trending_up"
          :done="currentStep > 3"
        >
          <GoalStepContent
            timeframe="long"
            :goal="longTermGoal"
            @update:goal="updateGoal('long', $event)"
          />
        </q-step>

        <!-- Navigation -->
        <template v-slot:navigation>
          <q-stepper-navigation class="q-pa-md">
            <div class="row q-gutter-sm">
              <q-btn
                v-if="currentStep > 1"
                flat
                color="primary"
                label="Back"
                @click="goBack"
                class="col"
              />
              <q-btn
                v-if="currentStep < 3"
                color="primary"
                label="Next Step"
                @click="goNext"
                :disable="!canProceed"
                class="col"
              />
              <q-btn
                v-else
                color="positive"
                label="Complete Quest"
                icon-right="emoji_events"
                @click="completeQuest"
                :disable="!canProceed"
                :loading="isCompleting"
                class="col"
              />
            </div>
          </q-stepper-navigation>
        </template>
      </q-stepper>
    </q-card>
  </q-dialog>

  <!-- Completion Dialog -->
  <q-dialog v-model="showCompletion" persistent>
    <q-card class="text-center" style="min-width: 320px">
      <q-card-section class="q-pt-xl">
        <div class="completion-animation q-mb-lg">
          <q-avatar
            color="#CD7F32"
            text-color="white"
            size="80px"
          >
            <q-icon name="flight" size="40px" />
          </q-avatar>
        </div>

        <div class="text-h5 text-weight-bold q-mb-sm">Quest Complete!</div>
        <div class="text-h6 text-primary q-mb-md">Bronze Pilot</div>

        <p class="text-grey-7">
          You've completed your Financial Health Checkup
          and earned the Bronze Pilot badge!
        </p>

        <q-chip color="positive" text-color="white" class="q-mb-md">
          +100 XP Earned
        </q-chip>

        <div class="q-pa-md bg-grey-2 rounded-borders q-mb-md">
          <q-icon name="lock_open" color="positive" size="sm" class="q-mr-sm" />
          <span class="text-weight-medium">UNLOCKED: Tax Provisioning Tool</span>
        </div>
      </q-card-section>

      <q-card-actions class="q-pa-md">
        <q-btn
          flat
          label="View My Goals"
          color="primary"
          @click="viewGoals"
          class="col"
        />
        <q-btn
          color="primary"
          label="Set Up Tax"
          icon-right="arrow_forward"
          @click="goToTax"
          class="col"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useQuestStore } from '../stores/quest.store';
import type { GoalTimeframe, FinancialGoal } from '../types/quest.types';
import GoalStepContent from './GoalStepContent.vue';

const props = defineProps<{
  modelValue: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
}>();

const $q = useQuasar();
const router = useRouter();
const questStore = useQuestStore();

const currentStep = ref(1);
const isCompleting = ref(false);
const showCompletion = ref(false);

// Temporary goal state for each step
const shortTermGoal = ref<Partial<FinancialGoal>>({});
const mediumTermGoal = ref<Partial<FinancialGoal>>({});
const longTermGoal = ref<Partial<FinancialGoal>>({});

// Initialize from store when dialog opens
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      const existing = {
        short: questStore.getGoalForTimeframe('short'),
        medium: questStore.getGoalForTimeframe('medium'),
        long: questStore.getGoalForTimeframe('long'),
      };
      shortTermGoal.value = existing.short || {};
      mediumTermGoal.value = existing.medium || {};
      longTermGoal.value = existing.long || {};

      // Start at first incomplete step
      if (existing.short) currentStep.value = 2;
      if (existing.medium) currentStep.value = 3;
      if (!existing.short) currentStep.value = 1;
    }
  }
);

const currentGoal = computed(() => {
  switch (currentStep.value) {
    case 1:
      return shortTermGoal.value;
    case 2:
      return mediumTermGoal.value;
    case 3:
      return longTermGoal.value;
    default:
      return {};
  }
});

const canProceed = computed(() => {
  const goal = currentGoal.value;
  if (!goal.category) return false;
  if (goal.category === 'custom' && !goal.customDescription?.trim()) return false;
  return true;
});

function updateGoal(
  timeframe: GoalTimeframe,
  updates: Partial<FinancialGoal>
): void {
  switch (timeframe) {
    case 'short':
      shortTermGoal.value = { ...shortTermGoal.value, ...updates };
      break;
    case 'medium':
      mediumTermGoal.value = { ...mediumTermGoal.value, ...updates };
      break;
    case 'long':
      longTermGoal.value = { ...longTermGoal.value, ...updates };
      break;
  }
}

async function saveCurrentStep(): Promise<void> {
  const timeframes: GoalTimeframe[] = ['short', 'medium', 'long'];
  const timeframe = timeframes[currentStep.value - 1];
  const goal = currentGoal.value;

  if (goal.category) {
    await questStore.setGoal(
      timeframe,
      goal.category,
      goal.customDescription,
      goal.targetAmount,
      goal.targetDate
    );
  }
}

async function goNext(): Promise<void> {
  await saveCurrentStep();
  currentStep.value++;
}

function goBack(): void {
  currentStep.value--;
}

async function completeQuest(): Promise<void> {
  isCompleting.value = true;

  try {
    await saveCurrentStep();

    // Check if quest is complete
    if (questStore.bronzePilotProgress === 100) {
      showCompletion.value = true;
      emit('update:modelValue', false);
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to complete quest. Please try again.',
    });
  } finally {
    isCompleting.value = false;
  }
}

function handleClose(): void {
  $q.dialog({
    title: 'Leave Quest?',
    message: 'Your progress will be saved. You can continue later.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    await saveCurrentStep();
    emit('update:modelValue', false);
  });
}

function viewGoals(): void {
  showCompletion.value = false;
  router.push('/goals');
}

function goToTax(): void {
  showCompletion.value = false;
  router.push('/tax');
}
</script>

<style scoped>
.completion-animation {
  animation: badge-pop 0.5s ease-out;
}

@keyframes badge-pop {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  50% {
    transform: scale(1.2);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
