<template>
  <q-footer class="app-footer" bordered>
    <q-tabs
      v-model="currentTab"
      class="nav-tabs"
      active-color="teal-9"
      indicator-color="transparent"
      dense
      no-caps
      switch-indicator
    >
      <!-- Home Tab (toggles drawer when already on dashboard) -->
      <q-tab
        name="home"
        icon="home"
        label="Home"
        class="nav-tab"
        @click="handleHomeClick"
      />

      <!-- Wallet/Transactions Tab -->
      <q-route-tab
        name="wallet"
        to="/transactions"
        icon="account_balance_wallet"
        label="Wallet"
        class="nav-tab"
      >
        <q-badge
          v-if="uncategorizedCount > 0"
          color="warning"
          text-color="dark"
          floating
          rounded
        >
          {{ uncategorizedCount > 9 ? '9+' : uncategorizedCount }}
        </q-badge>
      </q-route-tab>

      <!-- Intelligence Tab (Tax Gap, Trends) -->
      <q-route-tab
        name="intelligence"
        to="/intelligence"
        icon="insights"
        label="Insights"
        class="nav-tab"
      />

      <!-- Quests/Goals Tab -->
      <q-route-tab
        name="quests"
        to="/goals"
        icon="emoji_events"
        label="Quests"
        class="nav-tab"
      />
    </q-tabs>
  </q-footer>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTransactionsStore } from '@/stores/transactions.store';

const emit = defineEmits<{
  (e: 'toggle-drawer'): void;
}>();

const route = useRoute();
const router = useRouter();
const transactionsStore = useTransactionsStore();

// Current tab based on route
const currentTab = computed(() => {
  const path = route.path;
  if (path === '/') return 'home';
  if (path.startsWith('/transactions') || path.startsWith('/import') || path.startsWith('/budget')) return 'wallet';
  if (path.startsWith('/intelligence') || path.startsWith('/tax')) return 'intelligence';
  if (path.startsWith('/goals')) return 'quests';
  return 'home';
});

// Uncategorized badge
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);

// Home tab logic: toggle drawer if already on dashboard, else navigate
function handleHomeClick() {
  if (route.path === '/') {
    emit('toggle-drawer');
  } else {
    router.push('/');
  }
}
</script>

<style scoped>
.app-footer {
  background: white;
  border-top: 1px solid #E0E0E0;
  height: 56px;
  z-index: 1000;
}

.nav-tabs {
  height: 56px;
}

/* Tab styling - 44px+ touch targets per CLAUDE.md */
.nav-tab {
  min-height: 56px;
  min-width: 72px;
  padding: 4px 12px;
}

.nav-tab :deep(.q-tab__icon) {
  font-size: 22px;
  color: #90A4AE;
}

.nav-tab :deep(.q-tab__label) {
  font-size: 10px;
  font-weight: 500;
  color: #90A4AE;
  margin-top: 2px;
  letter-spacing: 0.2px;
}

/* Active state - Deep Teal */
.nav-tab.q-tab--active :deep(.q-tab__icon),
.nav-tab.q-tab--active :deep(.q-tab__label) {
  color: #004D40;
}

/* Ripple color */
.nav-tab :deep(.q-ripple) {
  color: rgba(0, 77, 64, 0.1);
}

/* Badge positioning */
.nav-tab :deep(.q-badge) {
  top: 6px;
  right: 14px;
  font-size: 9px;
  min-height: 14px;
  padding: 0 4px;
}
</style>
