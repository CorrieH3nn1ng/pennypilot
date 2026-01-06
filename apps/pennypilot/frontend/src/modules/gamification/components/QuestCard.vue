<template>
  <q-card class="quest-card" v-if="showCard">
    <!-- Header -->
    <q-card-section class="bg-primary text-white q-py-sm">
      <div class="row items-center">
        <q-icon name="emoji_events" size="sm" class="q-mr-sm" />
        <span class="text-weight-medium">YOUR ACTIVE QUEST</span>
      </div>
    </q-card-section>

    <!-- Quest Content -->
    <q-card-section>
      <div class="row items-center q-mb-md">
        <q-avatar
          :color="quest.reward.badge.color"
          text-color="white"
          size="48px"
          class="q-mr-md"
        >
          <q-icon :name="quest.reward.badge.icon" size="24px" />
        </q-avatar>
        <div>
          <div class="text-h6">{{ quest.name }}</div>
          <div class="text-grey-7">{{ quest.description }}</div>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="q-mb-md">
        <div class="row justify-between q-mb-xs">
          <span class="text-caption text-grey-7">Progress</span>
          <span class="text-caption text-weight-medium">{{ quest.progress }}%</span>
        </div>
        <q-linear-progress
          :value="quest.progress / 100"
          color="primary"
          size="12px"
          rounded
        />
      </div>

      <!-- Steps Preview -->
      <div class="q-mb-md">
        <div
          v-for="step in quest.steps"
          :key="step.id"
          class="row items-center q-mb-xs"
        >
          <q-icon
            :name="step.isCompleted ? 'check_circle' : 'radio_button_unchecked'"
            :color="step.isCompleted ? 'positive' : 'grey-5'"
            size="xs"
            class="q-mr-sm"
          />
          <span
            :class="step.isCompleted ? 'text-grey-6' : 'text-grey-8'"
          >
            {{ step.title }}
          </span>
        </div>
      </div>

      <!-- Reward Preview -->
      <q-separator class="q-mb-md" />
      <div class="row items-center text-grey-7">
        <q-icon name="card_giftcard" size="sm" class="q-mr-sm" />
        <span class="text-caption">
          Reward: <strong>{{ quest.reward.badge.name }}</strong> Badge
          <span v-if="quest.reward.featureUnlock">
            + Unlock Tax Provisioning
          </span>
        </span>
      </div>
    </q-card-section>

    <!-- Action Button -->
    <q-card-actions>
      <q-btn
        color="primary"
        label="Continue Quest"
        class="full-width"
        @click="$emit('open-quest')"
      />
    </q-card-actions>
  </q-card>

  <!-- Badge Earned Card (shown when quest completed) -->
  <q-card class="quest-card" v-else-if="hasBadge">
    <q-card-section class="text-center q-py-lg">
      <q-avatar
        :color="completedBadge?.color"
        text-color="white"
        size="64px"
        class="q-mb-md"
      >
        <q-icon :name="completedBadge?.icon" size="32px" />
      </q-avatar>
      <div class="text-h6 q-mb-xs">{{ completedBadge?.name }}</div>
      <div class="text-grey-6 text-caption">
        {{ completedBadge?.description }}
      </div>
      <q-chip color="positive" text-color="white" class="q-mt-md">
        <q-icon name="verified" size="xs" class="q-mr-xs" />
        Quest Complete
      </q-chip>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useQuestStore } from '../stores/quest.store';

defineEmits<{
  (e: 'open-quest'): void;
}>();

const questStore = useQuestStore();

const quest = computed(() => questStore.bronzePilotQuest);
const hasBadge = computed(() => questStore.hasBronzePilot);

const completedBadge = computed(() =>
  questStore.badges.find((b) => b.id === 'bronze_pilot')
);

const showCard = computed(
  () => questStore.isBronzePilotActive && !hasBadge.value
);
</script>

<style scoped>
.quest-card {
  border-left: 4px solid var(--q-primary);
}
</style>
