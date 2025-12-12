<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Settings</div>

    <q-list>
      <q-item-label header>Account</q-item-label>
      <q-item>
        <q-item-section>
          <q-item-label>Profile</q-item-label>
          <q-item-label caption>Manage your account details</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="chevron_right" />
        </q-item-section>
      </q-item>

      <q-separator />

      <q-item-label header>Data</q-item-label>
      <q-item clickable @click="exportData">
        <q-item-section>
          <q-item-label>Export Data</q-item-label>
          <q-item-label caption>Download all your transactions</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="download" />
        </q-item-section>
      </q-item>

      <q-item clickable @click="clearLocalData">
        <q-item-section>
          <q-item-label class="text-negative">Clear Local Data</q-item-label>
          <q-item-label caption>Remove offline cached data</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-icon name="delete" color="negative" />
        </q-item-section>
      </q-item>

      <q-separator />

      <q-item-label header>About</q-item-label>
      <q-item>
        <q-item-section>
          <q-item-label>Version</q-item-label>
          <q-item-label caption>0.1.0 (MVP)</q-item-label>
        </q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup lang="ts">
import { useQuasar } from 'quasar';
import { localBaseService } from '@/services/storage/LocalBaseService';

const $q = useQuasar();

function exportData() {
  $q.notify({
    type: 'info',
    message: 'Export feature coming soon',
  });
}

async function clearLocalData() {
  $q.dialog({
    title: 'Clear Local Data',
    message: 'This will remove all cached transactions. Data synced to server will not be affected.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    await localBaseService.clearTransactions();
    $q.notify({
      type: 'positive',
      message: 'Local data cleared',
    });
    window.location.reload();
  });
}
</script>
