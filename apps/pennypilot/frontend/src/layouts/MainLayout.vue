<template>
  <q-layout view="hHh Lpr fFf">
    <!-- Header -->
    <q-header elevated class="header-brand">
      <q-toolbar>
        <q-btn
          flat
          round
          icon="menu"
          aria-label="Menu"
          @click="toggleDrawer"
        />

        <q-toolbar-title class="row items-center no-wrap">
          <q-avatar size="28px" class="q-mr-sm">
            <img src="/logo.png" alt="PennyPilot" />
          </q-avatar>
          <span class="brand-text">PennyPilot</span>
        </q-toolbar-title>

        <q-space />

        <!-- Sync Status Cloud -->
        <q-btn
          flat
          round
          size="sm"
          :icon="syncIcon"
          :class="syncCloudClass"
          @click="handleSyncClick"
        >
          <q-badge
            v-if="pendingSyncCount > 0"
            :label="pendingSyncCount > 9 ? '9+' : pendingSyncCount.toString()"
            color="negative"
            floating
            rounded
          />
          <q-tooltip>{{ syncTooltip }}</q-tooltip>
        </q-btn>

        <!-- Profile -->
        <q-btn flat round icon="account_circle" size="sm">
          <q-tooltip>{{ userName }}</q-tooltip>
          <q-menu>
            <q-list dense style="min-width: 180px">
              <q-item-label header class="q-pb-none">
                <div class="text-weight-bold">{{ userName }}</div>
                <div class="text-caption text-grey">{{ userEmail }}</div>
              </q-item-label>
              <q-separator spaced />
              <q-item clickable v-close-popup to="/settings">
                <q-item-section avatar>
                  <q-icon name="settings" size="sm" />
                </q-item-section>
                <q-item-section>Settings</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar>
                  <q-icon name="logout" size="sm" />
                </q-item-section>
                <q-item-section>Logout</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <!-- Side Drawer (Back-Office) -->
    <q-drawer
      v-model="drawerOpen"
      bordered
      :width="280"
      class="drawer-backoffice"
    >
      <!-- Pro Account Banner -->
      <div class="pro-banner" :class="isPremium ? 'pro-active' : 'pro-inactive'">
        <q-icon :name="isPremium ? 'workspace_premium' : 'star_border'" size="24px" />
        <div class="pro-text">
          <div class="pro-title">{{ isPremium ? 'Pro Account' : 'Free Account' }}</div>
          <div class="pro-subtitle">
            {{ isPremium ? 'Cloud sync enabled' : 'Upgrade for cloud sync' }}
          </div>
        </div>
        <q-btn
          v-if="!isPremium"
          flat
          dense
          size="sm"
          label="Upgrade"
          class="pro-upgrade"
          @click="showUpgradeDialog = true"
        />
      </div>

      <q-scroll-area class="drawer-scroll">
        <q-list dense>
          <!-- Business Section (Self-Employed Only) -->
          <template v-if="showInvoicing">
            <q-item-label header class="section-header">
              <q-icon name="business_center" size="16px" class="q-mr-sm" />
              Business
            </q-item-label>

            <q-item
              clickable
              v-ripple
              to="/invoices"
              active-class="item-active"
              :inset-level="0.3"
            >
              <q-item-section avatar>
                <q-icon name="description" />
              </q-item-section>
              <q-item-section>Invoices</q-item-section>
            </q-item>

            <q-item
              v-if="showClients"
              clickable
              v-ripple
              to="/clients"
              active-class="item-active"
              :inset-level="0.3"
            >
              <q-item-section avatar>
                <q-icon name="people" />
              </q-item-section>
              <q-item-section>Clients</q-item-section>
            </q-item>

            <q-item
              clickable
              v-ripple
              to="/business-profile"
              active-class="item-active"
              :inset-level="0.3"
            >
              <q-item-section avatar>
                <q-icon name="storefront" />
              </q-item-section>
              <q-item-section>Business Profile</q-item-section>
            </q-item>

            <q-item
              clickable
              v-ripple
              to="/income"
              active-class="item-active"
              :inset-level="0.3"
            >
              <q-item-section avatar>
                <q-icon name="payments" />
              </q-item-section>
              <q-item-section>Income Sources</q-item-section>
            </q-item>

            <q-separator spaced />
          </template>

          <!-- Salaried User: Payslip Upload Section -->
          <template v-if="isSalaried">
            <q-item-label header class="section-header">
              <q-icon name="account_balance_wallet" size="16px" class="q-mr-sm" />
              Income
            </q-item-label>

            <q-item
              clickable
              v-ripple
              to="/income"
              active-class="item-active"
              :inset-level="0.3"
            >
              <q-item-section avatar>
                <q-icon name="payments" />
              </q-item-section>
              <q-item-section>Payslip / Net Income</q-item-section>
            </q-item>

            <q-separator spaced />
          </template>

          <!-- Strategic Audit Section -->
          <q-item-label header class="section-header">
            <q-icon name="account_balance" size="16px" class="q-mr-sm" />
            Strategic Audit
          </q-item-label>

          <q-item
            clickable
            v-ripple
            to="/blueprint"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="architecture" />
            </q-item-section>
            <q-item-section>Blueprint</q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/budget-card"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="credit_card" />
            </q-item-section>
            <q-item-section>Budget Card</q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/audit"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="fact_check" />
            </q-item-section>
            <q-item-section>Audit</q-item-section>
          </q-item>

          <q-separator spaced />

          <!-- Setup Section -->
          <q-item-label header class="section-header">
            <q-icon name="tune" size="16px" class="q-mr-sm" />
            Setup
          </q-item-label>

          <q-item
            clickable
            v-ripple
            to="/tax"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="receipt" />
            </q-item-section>
            <q-item-section>Tax Settings</q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/fixed-expenses"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="repeat" />
            </q-item-section>
            <q-item-section>Fixed Expenses</q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/categories"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="category" />
            </q-item-section>
            <q-item-section>Categories</q-item-section>
          </q-item>

          <q-separator spaced />

          <!-- System Section -->
          <q-item-label header class="section-header">
            <q-icon name="settings" size="16px" class="q-mr-sm" />
            System
          </q-item-label>

          <q-item
            clickable
            v-ripple
            to="/import"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="upload_file" />
            </q-item-section>
            <q-item-section>Import Statements</q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/settings"
            active-class="item-active"
            :inset-level="0.3"
          >
            <q-item-section avatar>
              <q-icon name="settings" />
            </q-item-section>
            <q-item-section>Settings</q-item-section>
          </q-item>
        </q-list>
      </q-scroll-area>

      <!-- Drawer Footer -->
      <div class="drawer-footer">
        <div class="text-caption text-grey-6">
          <q-icon
            :name="isOnline ? 'wifi' : 'wifi_off'"
            :color="isOnline ? 'positive' : 'warning'"
            size="12px"
            class="q-mr-xs"
          />
          {{ isOnline ? 'Online' : 'Offline' }}
        </div>
        <div class="text-caption text-grey-5">v1.0.0</div>
      </div>
    </q-drawer>

    <!-- Page Container -->
    <q-page-container>
      <router-view />
    </q-page-container>

    <!-- Footer Navigation -->
    <AppFooter @toggle-drawer="toggleDrawer" />

    <!-- Upgrade Dialog -->
    <q-dialog v-model="showUpgradeDialog">
      <q-card class="upgrade-dialog">
        <q-card-section class="row items-center q-pb-none">
          <q-icon name="workspace_premium" color="purple" size="md" class="q-mr-sm" />
          <div class="text-h6">Upgrade to Pro</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="upgrade-features">
            <div class="feature-item">
              <q-icon name="cloud_sync" color="positive" />
              <span>Cloud backup & sync</span>
            </div>
            <div class="feature-item">
              <q-icon name="devices" color="positive" />
              <span>Access across devices</span>
            </div>
            <div class="feature-item">
              <q-icon name="security" color="positive" />
              <span>Secure data protection</span>
            </div>
            <div class="feature-item">
              <q-icon name="support_agent" color="positive" />
              <span>Priority support</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Maybe Later" v-close-popup />
          <q-btn color="purple" label="Subscribe" @click="handleSubscribe" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useUserStore } from '@/stores/user.store';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';
import AppFooter from '@/components/AppFooter.vue';

const $q = useQuasar();
const router = useRouter();
const userStore = useUserStore();
const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

// State
const drawerOpen = ref(false);
const isOnline = ref(navigator.onLine);
const isSyncing = ref(false);
const showUpgradeDialog = ref(false);

// User info
const userName = computed(() => userStore.userName);
const userEmail = computed(() => userStore.user?.email || '');
const isPremium = computed(() => userStore.isPremium);
const pendingSyncCount = computed(() => transactionsStore.pendingSyncCount);

// Persona-based feature visibility
const showInvoicing = computed(() => userStore.showInvoicing);
const showClients = computed(() => userStore.showClients);
const isSalaried = computed(() => userStore.isSalaried);

// Sync status
const syncIcon = computed(() => {
  if (isSyncing.value) return 'sync';
  if (pendingSyncCount.value > 0) return 'cloud_upload';
  return 'cloud_done';
});

const syncCloudClass = computed(() => {
  if (isSyncing.value) return 'sync-active';
  if (pendingSyncCount.value > 0) return 'sync-pending';
  return 'sync-ok';
});

const syncTooltip = computed(() => {
  if (!isPremium.value) return 'Upgrade to enable sync';
  if (isSyncing.value) return 'Syncing...';
  if (pendingSyncCount.value > 0) return `${pendingSyncCount.value} pending changes`;
  return 'All synced';
});

// Actions
function toggleDrawer() {
  drawerOpen.value = !drawerOpen.value;
}

async function handleSyncClick() {
  if (!isPremium.value) {
    showUpgradeDialog.value = true;
    return;
  }
  if (!isOnline.value) {
    $q.notify({ type: 'warning', message: 'Offline', position: 'bottom', timeout: 1500 });
    return;
  }
  await performSync();
}

async function performSync() {
  if (isSyncing.value) return;
  isSyncing.value = true;

  try {
    const result = await transactionsStore.syncToServer();
    if (result.pushed > 0 || result.updated > 0) {
      $q.notify({
        type: 'positive',
        message: `Synced ${result.pushed + result.updated} items`,
        position: 'bottom',
        timeout: 2000,
      });
    }
  } catch {
    $q.notify({ type: 'negative', message: 'Sync failed', position: 'bottom' });
  } finally {
    isSyncing.value = false;
  }
}

function handleSubscribe() {
  showUpgradeDialog.value = false;
  $q.notify({
    type: 'info',
    message: 'Subscription coming soon!',
    icon: 'star',
    position: 'bottom',
  });
}

async function logout() {
  await userStore.logout();
  router.push('/login');
}

// Network handlers
function handleOnline() {
  isOnline.value = true;
  if (isPremium.value && pendingSyncCount.value > 0) {
    performSync();
  }
}

function handleOffline() {
  isOnline.value = false;
}

// Lifecycle
onMounted(async () => {
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  await userStore.fetchUser();
  await Promise.all([
    transactionsStore.loadFromLocal(),
    categoriesStore.loadFromServer(),
  ]);
});

onUnmounted(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
});
</script>

<style scoped>
/* Header */
.header-brand {
  background-color: #004D40;
}

.brand-text {
  font-weight: 600;
  font-size: 18px;
}

/* Sync Cloud Indicator */
.sync-ok {
  color: rgba(255, 255, 255, 0.8);
}

.sync-pending {
  color: #FFB74D;
}

.sync-active {
  color: #4FC3F7;
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Drawer */
.drawer-backoffice {
  background: #FAFAFA;
}

.drawer-scroll {
  height: calc(100% - 120px);
}

/* Pro Banner */
.pro-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  margin: 12px;
  border-radius: 12px;
}

.pro-active {
  background: linear-gradient(135deg, #7B1FA2 0%, #9C27B0 100%);
  color: white;
}

.pro-inactive {
  background: #ECEFF1;
  color: #546E7A;
}

.pro-text {
  flex: 1;
}

.pro-title {
  font-size: 14px;
  font-weight: 600;
}

.pro-subtitle {
  font-size: 11px;
  opacity: 0.8;
}

.pro-upgrade {
  color: #004D40;
  background: white;
}

/* Section Headers */
.section-header {
  display: flex;
  align-items: center;
  font-size: 11px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 12px 16px 8px;
}

/* List Items */
.drawer-backoffice :deep(.q-item) {
  min-height: 44px;
  border-radius: 8px;
  margin: 2px 8px;
}

.drawer-backoffice :deep(.q-item__section--avatar) {
  min-width: 40px;
}

.drawer-backoffice :deep(.q-icon) {
  color: #004D40;
}

.item-active {
  background: #E0F2F1;
  color: #004D40;
}

/* Drawer Footer */
.drawer-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #E0E0E0;
  background: white;
}

/* Page Container */
.q-page-container {
  padding-bottom: 56px;
}

/* Upgrade Dialog */
.upgrade-dialog {
  min-width: 300px;
  border-radius: 12px;
}

.upgrade-features {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
}
</style>
