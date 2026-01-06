<template>
  <div class="badge-display">
    <!-- Single Badge View -->
    <div v-if="badge" class="badge-single text-center">
      <q-avatar
        :color="badge.color"
        text-color="white"
        :size="size"
        class="badge-avatar"
      >
        <q-icon :name="badge.icon" :size="iconSize" />
      </q-avatar>
      <div v-if="showName" class="text-weight-medium q-mt-sm">
        {{ badge.name }}
      </div>
      <div v-if="showDescription" class="text-caption text-grey-6">
        {{ badge.description }}
      </div>
      <div v-if="showEarnedDate && badge.earnedAt" class="text-caption text-grey-5 q-mt-xs">
        Earned {{ formatDate(badge.earnedAt) }}
      </div>
    </div>

    <!-- Multiple Badges Grid -->
    <div v-else-if="badges && badges.length > 0" class="badges-grid">
      <div
        v-for="b in badges"
        :key="b.id"
        class="badge-item text-center"
        @click="$emit('select', b)"
      >
        <q-avatar
          :color="b.color"
          text-color="white"
          :size="size"
          class="badge-avatar"
        >
          <q-icon :name="b.icon" :size="iconSize" />
        </q-avatar>
        <div v-if="showName" class="text-caption text-weight-medium q-mt-xs">
          {{ b.name }}
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state text-center q-pa-md">
      <q-icon name="emoji_events" size="48px" color="grey-4" />
      <div class="text-grey-6 q-mt-sm">No badges earned yet</div>
      <div class="text-caption text-grey-5">
        Complete quests to earn badges
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Badge } from '../types/quest.types';

const props = withDefaults(
  defineProps<{
    badge?: Badge;
    badges?: Badge[];
    size?: string;
    showName?: boolean;
    showDescription?: boolean;
    showEarnedDate?: boolean;
  }>(),
  {
    size: '64px',
    showName: true,
    showDescription: false,
    showEarnedDate: false,
  }
);

defineEmits<{
  (e: 'select', badge: Badge): void;
}>();

const iconSize = computed(() => {
  const sizeNum = parseInt(props.size);
  return `${Math.round(sizeNum * 0.5)}px`;
});

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
</script>

<style scoped>
.badge-avatar {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.badges-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 16px;
}

.badge-item {
  cursor: pointer;
  transition: transform 0.2s ease;
}

.badge-item:hover {
  transform: scale(1.05);
}

.empty-state {
  background: rgba(0, 0, 0, 0.02);
  border-radius: 8px;
}
</style>
