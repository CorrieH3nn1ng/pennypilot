<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title> PennyPilot </q-toolbar-title>

        <q-btn flat round icon="sync" @click="syncData" :loading="isSyncing">
          <q-tooltip>Sync with server</q-tooltip>
        </q-btn>

        <q-btn flat round icon="logout" @click="logout">
          <q-tooltip>Logout</q-tooltip>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered>
      <q-list>
        <q-item-label header> Navigation </q-item-label>

        <q-item clickable v-ripple to="/" exact>
          <q-item-section avatar>
            <q-icon name="dashboard" />
          </q-item-section>
          <q-item-section> Dashboard </q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/transactions">
          <q-item-section avatar>
            <q-icon name="receipt_long" />
          </q-item-section>
          <q-item-section> Transactions </q-item-section>
          <q-item-section side>
            <q-badge v-if="uncategorizedCount > 0" color="warning">
              {{ uncategorizedCount }}
            </q-badge>
          </q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/import">
          <q-item-section avatar>
            <q-icon name="upload_file" />
          </q-item-section>
          <q-item-section> Import CSV </q-item-section>
        </q-item>

        <q-separator />

        <q-item-label header> Settings </q-item-label>

        <q-item clickable v-ripple to="/categories">
          <q-item-section avatar>
            <q-icon name="category" />
          </q-item-section>
          <q-item-section> Categories </q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/settings">
          <q-item-section avatar>
            <q-icon name="settings" />
          </q-item-section>
          <q-item-section> Settings </q-item-section>
        </q-item>
      </q-list>

      <q-separator />

      <div class="q-pa-md text-caption text-grey">
        <div>{{ userName }}</div>
        <div v-if="!isOnline" class="text-warning">
          <q-icon name="cloud_off" /> Offline Mode
        </div>
      </div>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useUserStore } from '@/stores/user.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';

const $q = useQuasar();
const router = useRouter();
const userStore = useUserStore();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

const leftDrawerOpen = ref(false);
const isSyncing = ref(false);
const isOnline = ref(navigator.onLine);

const userName = computed(() => userStore.userName);
const uncategorizedCount = computed(() => transactionsStore.uncategorizedCount);

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value;
}

async function syncData() {
  if (!isOnline.value) {
    $q.notify({
      type: 'warning',
      message: 'Cannot sync while offline',
    });
    return;
  }

  isSyncing.value = true;

  try {
    const result = await transactionsStore.syncToServer();
    $q.notify({
      type: 'positive',
      message: `Synced ${result.pushed} transactions`,
    });
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Sync failed',
    });
  } finally {
    isSyncing.value = false;
  }
}

async function logout() {
  await userStore.logout();
  router.push('/login');
}

onMounted(async () => {
  // Load data
  await Promise.all([
    transactionsStore.loadFromLocal(),
    categoriesStore.loadFromServer(),
  ]);

  // Listen for online/offline events
  window.addEventListener('online', () => {
    isOnline.value = true;
  });
  window.addEventListener('offline', () => {
    isOnline.value = false;
  });
});
</script>
