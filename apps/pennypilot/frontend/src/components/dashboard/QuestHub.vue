<template>
  <q-card v-if="showHub" class="quest-hub" flat bordered>
    <!-- Quest Header -->
    <div class="hub-header">
      <div class="hub-title">
        <q-icon name="emoji_events" size="18px" />
        <span>{{ headerTitle }}</span>
      </div>
      <q-chip
        v-if="hasActiveQuest"
        color="teal-8"
        text-color="white"
        size="sm"
        dense
      >
        {{ quest.progress }}%
      </q-chip>
      <q-chip
        v-else-if="hasBadge"
        color="positive"
        text-color="white"
        size="sm"
        dense
        icon="verified"
      >
        Complete
      </q-chip>
    </div>

    <!-- Quest Content -->
    <q-card-section class="q-pt-sm q-pb-md">
      <!-- Active Quest -->
      <div v-if="hasActiveQuest" class="quest-content">
        <div class="quest-info">
          <q-avatar
            :color="quest.reward.badge.color"
            text-color="white"
            size="36px"
          >
            <q-icon :name="quest.reward.badge.icon" size="18px" />
          </q-avatar>
          <div class="quest-details">
            <div class="quest-name">{{ quest.name }}</div>
            <div class="quest-desc">{{ quest.description }}</div>
          </div>
        </div>

        <!-- Progress Bar -->
        <q-linear-progress
          :value="quest.progress / 100"
          color="teal-8"
          track-color="teal-2"
          size="6px"
          rounded
          class="q-my-sm"
        />

        <!-- Steps as Sub-tasks (including uncategorized) -->
        <div class="quest-steps">
          <!-- Uncategorized Alert (nested as sub-task) -->
          <div
            v-if="uncategorizedCount > 0"
            class="step-item alert-step"
          >
            <q-icon name="warning" color="warning" size="14px" />
            <span class="step-text">
              Categorize {{ uncategorizedCount }} transaction{{ uncategorizedCount > 1 ? 's' : '' }}
            </span>
            <q-btn
              flat
              dense
              size="xs"
              color="warning"
              icon="auto_fix_high"
              :loading="isAutoCategorizing"
              @click.stop="$emit('auto-categorize')"
            />
          </div>

          <!-- Unassigned Bucket Alert -->
          <div
            v-if="unassignedBucketCount > 0"
            class="step-item discovery-step"
          >
            <q-icon name="psychology" color="teal" size="14px" />
            <span class="step-text">
              Assign {{ unassignedBucketCount }} to budget buckets
            </span>
            <q-btn
              flat
              dense
              size="xs"
              color="teal"
              icon="play_arrow"
              @click.stop="$emit('open-interrogator')"
            />
          </div>

          <!-- Quest Steps -->
          <div
            v-for="step in quest.steps"
            :key="step.id"
            class="step-item"
          >
            <q-icon
              :name="step.isCompleted ? 'check_circle' : 'radio_button_unchecked'"
              :color="step.isCompleted ? 'positive' : 'grey-5'"
              size="14px"
            />
            <span class="step-text" :class="{ completed: step.isCompleted }">
              {{ step.title }}
            </span>
          </div>
        </div>

        <!-- Continue Button -->
        <q-btn
          unelevated
          color="teal-9"
          label="Continue Quest"
          icon="play_arrow"
          class="full-width q-mt-sm"
          size="sm"
          @click="$emit('open-quest')"
        />
      </div>

      <!-- Badge Earned State -->
      <div v-else-if="hasBadge" class="badge-earned">
        <q-avatar
          :color="completedBadge?.color"
          text-color="white"
          size="48px"
        >
          <q-icon :name="completedBadge?.icon" size="24px" />
        </q-avatar>
        <div class="badge-info">
          <div class="badge-name">{{ completedBadge?.name }}</div>
          <div class="badge-desc">Quest completed!</div>
        </div>

        <!-- Still show uncategorized if present -->
        <div v-if="uncategorizedCount > 0" class="uncategorized-inline">
          <q-separator class="q-my-sm" />
          <div class="step-item alert-step">
            <q-icon name="category" color="warning" size="14px" />
            <span class="step-text">
              {{ uncategorizedCount }} uncategorized
            </span>
            <q-btn
              flat
              dense
              size="xs"
              color="teal"
              label="Fix"
              @click.stop="$emit('auto-categorize')"
              :loading="isAutoCategorizing"
            />
          </div>
        </div>
      </div>

      <!-- No Quest (just show uncategorized or unassigned) -->
      <div v-else-if="uncategorizedCount > 0 || unassignedBucketCount > 0" class="no-quest">
        <!-- Uncategorized alert -->
        <div v-if="uncategorizedCount > 0" class="step-item alert-step">
          <q-icon name="category" color="warning" size="16px" />
          <span class="step-text">
            {{ uncategorizedCount }} transactions need categories
          </span>
        </div>

        <!-- Unassigned bucket alert -->
        <div v-if="unassignedBucketCount > 0" class="step-item discovery-step q-mt-sm">
          <q-icon name="psychology" color="teal" size="16px" />
          <span class="step-text">
            {{ unassignedBucketCount }} need budget buckets
          </span>
        </div>

        <div class="row q-gutter-sm q-mt-sm">
          <q-btn
            v-if="uncategorizedCount > 0"
            flat
            dense
            size="sm"
            color="warning"
            icon="auto_fix_high"
            label="Auto-fix"
            :loading="isAutoCategorizing"
            @click="$emit('auto-categorize')"
          />
          <q-btn
            v-if="unassignedBucketCount > 0"
            unelevated
            dense
            size="sm"
            color="teal"
            icon="psychology"
            label="Assign Buckets"
            @click="$emit('open-interrogator')"
          />
        </div>
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useQuestStore } from '@/modules/gamification/stores/quest.store';
import { useDiscoveryStore } from '@/stores/discovery.store';

const props = defineProps<{
  isAutoCategorizing?: boolean;
}>();

const emit = defineEmits<{
  (e: 'auto-categorize'): void;
  (e: 'open-quest'): void;
  (e: 'open-interrogator'): void;
}>();

const transactionsStore = useTransactionsStore();
const questStore = useQuestStore();
const discoveryStore = useDiscoveryStore();

// Load discovery stats on mount
onMounted(async () => {
  await discoveryStore.loadStats();
});

// Quest state
const quest = computed(() => questStore.bronzePilotQuest);
const hasBadge = computed(() => questStore.hasBronzePilot);
const hasActiveQuest = computed(
  () => questStore.isBronzePilotActive && !hasBadge.value
);
const completedBadge = computed(() =>
  questStore.badges.find((b) => b.id === 'bronze_pilot')
);

// Uncategorized count
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);

// Unassigned bucket count
const unassignedBucketCount = computed(() => discoveryStore.stats?.unassigned_count || 0);

// Show hub if there's a quest, badge, uncategorized, or unassigned bucket transactions
const showHub = computed(() => {
  return hasActiveQuest.value || hasBadge.value || uncategorizedCount.value > 0 || unassignedBucketCount.value > 0;
});

// Header title
const headerTitle = computed(() => {
  if (hasActiveQuest.value) return 'Active Quest';
  if (hasBadge.value) return 'Achievement';
  return 'Action Required';
});
</script>

<style scoped>
.quest-hub {
  border-radius: 12px;
  border-color: #B2DFDB;
  overflow: hidden;
}

.hub-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #E0F2F1;
  border-bottom: 1px solid #B2DFDB;
}

.hub-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #004D40;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Quest Content */
.quest-content {
  display: flex;
  flex-direction: column;
}

.quest-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.quest-details {
  flex: 1;
}

.quest-name {
  font-size: 14px;
  font-weight: 600;
  color: #263238;
}

.quest-desc {
  font-size: 12px;
  color: #78909C;
}

/* Steps */
.quest-steps {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  padding: 4px 0;
}

.step-text {
  flex: 1;
  color: #546E7A;
}

.step-text.completed {
  color: #90A4AE;
  text-decoration: line-through;
}

.alert-step {
  background: #FFF8E1;
  padding: 6px 8px;
  border-radius: 6px;
  margin: 4px 0;
}

.alert-step .step-text {
  color: #E65100;
  font-weight: 500;
}

.discovery-step {
  background: #E0F2F1;
  padding: 6px 8px;
  border-radius: 6px;
  margin: 4px 0;
}

.discovery-step .step-text {
  color: #004D40;
  font-weight: 500;
}

/* Badge Earned */
.badge-earned {
  display: flex;
  align-items: center;
  gap: 12px;
}

.badge-info {
  flex: 1;
}

.badge-name {
  font-size: 14px;
  font-weight: 600;
  color: #263238;
}

.badge-desc {
  font-size: 12px;
  color: #4CAF50;
}

.uncategorized-inline {
  width: 100%;
}

/* No Quest */
.no-quest {
  text-align: center;
}

.no-quest .step-item {
  justify-content: center;
}
</style>
