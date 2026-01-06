<template>
  <q-inner-loading
    :showing="showing"
    class="penny-loading"
    transition-show="fade"
    transition-hide="fade"
  >
    <div class="loading-content">
      <!-- Breathing Logo -->
      <div class="logo-container">
        <img
          src="/logo.png"
          alt="PennyPilot"
          class="loading-logo"
        />
      </div>

      <!-- Cycling Messages -->
      <transition name="fade" mode="out-in">
        <div :key="currentMessageIndex" class="loading-message">
          {{ currentMessage }}
        </div>
      </transition>

      <!-- Progress Dots -->
      <div class="loading-dots">
        <span
          v-for="(_, index) in messages"
          :key="index"
          class="dot"
          :class="{ active: index === currentMessageIndex }"
        />
      </div>
    </div>
  </q-inner-loading>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';

const props = withDefaults(
  defineProps<{
    showing: boolean;
  }>(),
  {
    showing: false,
  }
);

// Warm, fuzzy loading messages
const messages = [
  'Penny is waking up...',
  'Checking your Financial GPS...',
  'Calculating your path to growth...',
];

const currentMessageIndex = ref(0);
let messageInterval: ReturnType<typeof setInterval> | null = null;

const currentMessage = computed(() => messages[currentMessageIndex.value]);

// Cycle through messages when showing
watch(
  () => props.showing,
  (isShowing) => {
    if (isShowing) {
      currentMessageIndex.value = 0;
      messageInterval = setInterval(() => {
        currentMessageIndex.value = (currentMessageIndex.value + 1) % messages.length;
      }, 2000);
    } else {
      if (messageInterval) {
        clearInterval(messageInterval);
        messageInterval = null;
      }
    }
  },
  { immediate: true }
);

onUnmounted(() => {
  if (messageInterval) {
    clearInterval(messageInterval);
  }
});
</script>

<style scoped>
.penny-loading {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(4px);
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

/* Logo Container with Pulse Animation */
.logo-container {
  margin-bottom: 24px;
}

.loading-logo {
  width: 80px;
  height: 80px;
  object-fit: contain;
  animation: breathe 2s ease-in-out infinite;
}

/* Breathing/Pulse Animation */
@keyframes breathe {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.9;
  }
}

/* Loading Message - Deep Teal per CLAUDE.md */
.loading-message {
  color: #004D40;
  font-size: 14px;
  font-weight: 500;
  text-align: center;
  min-height: 20px;
  letter-spacing: 0.3px;
}

/* Message Fade Transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Progress Dots */
.loading-dots {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #B2DFDB;
  transition: all 0.3s ease;
}

.dot.active {
  background-color: #004D40;
  transform: scale(1.2);
}
</style>
