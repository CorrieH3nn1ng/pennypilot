<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
  >
    <q-card class="celebration-card bg-gradient-success">
      <q-card-section class="column items-center justify-center full-height text-center text-white q-pa-xl">
        <!-- Trophy Icon with Animation -->
        <div class="trophy-container q-mb-lg">
          <q-icon name="emoji_events" size="120px" color="amber" class="trophy-icon" />
        </div>

        <!-- Title -->
        <div class="text-h3 text-weight-bold q-mb-sm">
          {{ celebration?.title || 'Goal Reached!' }}
        </div>

        <!-- Main Message -->
        <div class="text-h4 q-mb-md">
          {{ celebration?.message || 'Liability Paid Off' }}
        </div>

        <!-- Subtitle with Amount Freed -->
        <div class="text-h5 text-amber q-mb-xl">
          {{ celebration?.subtitle || 'Congratulations!' }}
        </div>

        <!-- Celebration Details -->
        <q-card flat class="bg-white-10 q-pa-md rounded-borders q-mb-xl" style="min-width: 300px;">
          <div class="row justify-between text-body1">
            <span>Amount Freed:</span>
            <span class="text-weight-bold text-amber">
              R {{ formatMoney(celebration?.amount_freed || 0) }}/month
            </span>
          </div>
          <q-separator dark class="q-my-sm" />
          <div class="text-caption text-center" style="opacity: 0.8;">
            This amount is now available in your monthly budget!
          </div>
        </q-card>

        <!-- Decorative Stars -->
        <div class="stars-container">
          <q-icon name="star" color="amber" size="sm" class="star star-1" />
          <q-icon name="star" color="amber" size="md" class="star star-2" />
          <q-icon name="star" color="amber" size="sm" class="star star-3" />
          <q-icon name="star" color="amber" size="xs" class="star star-4" />
          <q-icon name="star" color="amber" size="md" class="star star-5" />
        </div>

        <!-- Continue Button -->
        <q-btn
          color="amber"
          text-color="dark"
          size="lg"
          rounded
          label="Celebrate & Continue"
          icon-right="celebration"
          class="q-px-xl"
          @click="close"
        />
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { LiabilityCelebration } from '@/types';
import { formatNumber } from '@/utils/currency';

defineProps<{
  modelValue: boolean;
  celebration: LiabilityCelebration | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'close'): void;
}>();

function formatMoney(amount: number): string {
  return formatNumber(amount);
}

function close() {
  emit('update:modelValue', false);
  emit('close');
}
</script>

<style scoped>
.celebration-card {
  background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 50%, #43A047 100%);
}

.bg-white-10 {
  background: rgba(255, 255, 255, 0.1);
}

.trophy-container {
  animation: bounce 1s ease-in-out infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.trophy-icon {
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.stars-container {
  position: absolute;
  width: 100%;
  height: 100%;
  pointer-events: none;
  overflow: hidden;
}

.star {
  position: absolute;
  animation: twinkle 1.5s ease-in-out infinite;
}

.star-1 { top: 15%; left: 10%; animation-delay: 0s; }
.star-2 { top: 20%; right: 15%; animation-delay: 0.3s; }
.star-3 { top: 30%; left: 20%; animation-delay: 0.6s; }
.star-4 { top: 10%; right: 25%; animation-delay: 0.9s; }
.star-5 { top: 25%; left: 5%; animation-delay: 1.2s; }

@keyframes twinkle {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}

.full-height {
  min-height: 100vh;
}
</style>
