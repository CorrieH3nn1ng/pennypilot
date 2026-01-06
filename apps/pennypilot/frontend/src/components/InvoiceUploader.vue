<template>
  <div>
    <!-- Upload Section -->
    <div class="text-subtitle2 text-grey-7 q-mb-xs">
      <q-icon name="auto_awesome" color="primary" size="xs" class="q-mr-xs" />
      AI Invoice Scanner
    </div>
    <div class="text-caption text-grey q-mb-sm">
      Upload PDF or photo - processed via n8n + Gemini
    </div>

    <q-file
      v-model="file"
      label="Tap to upload invoice"
      accept=".pdf,.jpg,.jpeg,.png"
      filled
      dense
      :loading="isProcessing"
      :disable="isProcessing"
      @update:model-value="handleFileSelected"
    >
      <template v-slot:prepend>
        <q-icon name="cloud_upload" />
      </template>
      <template v-slot:append v-if="file && !isProcessing">
        <q-icon name="close" class="cursor-pointer" @click.stop="clearFile" />
      </template>
    </q-file>

    <!-- Processing Status -->
    <div v-if="isProcessing" class="q-mt-md">
      <q-linear-progress
        :value="progressValue"
        :indeterminate="progressIndeterminate"
        color="primary"
        rounded
        size="8px"
        class="q-mb-sm"
      />
      <div class="text-caption text-grey text-center">
        <q-icon name="smart_toy" class="q-mr-xs" />
        {{ statusMessage }}
      </div>
    </div>

    <!-- Success Banner -->
    <q-banner
      v-if="extractedData"
      class="bg-positive text-white q-mt-sm"
      rounded
      dense
    >
      <template v-slot:avatar>
        <q-icon name="check_circle" />
      </template>
      <div>
        Extracted: {{ extractedData.invoice_number || 'Invoice' }}
        <div class="text-caption">
          R{{ (extractedData.amount || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
          <span v-if="extractedData.confidence">
            | {{ extractedData.confidence }}% confidence
          </span>
        </div>
      </div>
    </q-banner>

    <!-- Note: Client matching happens in parent component via dropdown -->

    <!-- Error Banner -->
    <q-banner
      v-if="error"
      class="bg-negative text-white q-mt-sm"
      rounded
      dense
    >
      <template v-slot:avatar>
        <q-icon name="error" />
      </template>
      {{ error }}
    </q-banner>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useQuasar } from 'quasar';
import { n8nApi, type N8nExtractedInvoice, type ExpectedBanking } from '@/services/api/n8n.api';
import { useBusinessProfileStore } from '@/stores/business-profile.store';

const $q = useQuasar();
const businessProfileStore = useBusinessProfileStore();

// Emits
const emit = defineEmits<{
  (e: 'extracted', data: N8nExtractedInvoice): void;
  (e: 'file-selected', file: File): void;
  (e: 'cleared'): void;
  (e: 'error', message: string): void;
}>();

// State
const file = ref<File | null>(null);
const isProcessing = ref(false);
const extractedData = ref<N8nExtractedInvoice | null>(null);
const error = ref<string | null>(null);
const statusMessage = ref('Preparing upload...');
const progressValue = ref(0);
const progressIndeterminate = ref(true);

// Methods
async function handleFileSelected(selectedFile: File | null) {
  if (!selectedFile) return;

  emit('file-selected', selectedFile);

  // Check if n8n is configured
  if (!n8nApi.isConfigured()) {
    error.value = 'n8n webhook not configured';
    $q.notify({
      type: 'negative',
      message: 'n8n webhook not configured',
      caption: 'Set VITE_N8N_WEBHOOK_URL in environment',
    });
    return;
  }

  // Reset state
  isProcessing.value = true;
  extractedData.value = null;
  error.value = null;
  progressValue.value = 0;
  progressIndeterminate.value = false;

  try {
    // Stage 1: Preparing (0-10%)
    statusMessage.value = 'Preparing upload...';
    progressValue.value = 0.1;

    // Stage 2: Uploading (10-30%)
    statusMessage.value = 'Uploading to AI pipeline...';
    progressValue.value = 0.2;
    await new Promise(resolve => setTimeout(resolve, 200));

    // Stage 3: AI Processing (30-90%)
    statusMessage.value = 'Gemini analyzing invoice...';
    progressValue.value = 0.3;
    progressIndeterminate.value = true;

    // Get user's banking details for verification (from business profile)
    const profile = businessProfileStore.profile;
    const expectedBanking: ExpectedBanking | null = profile?.bank_name && profile?.account_number
      ? { bank_name: profile.bank_name, account_number: profile.account_number }
      : null;

    // Call n8n webhook with banking verification
    const result = await n8nApi.uploadInvoice(selectedFile, expectedBanking);

    // Stage 4: Processing result
    progressIndeterminate.value = false;
    progressValue.value = 0.95;
    statusMessage.value = 'Processing result...';

    if (result.success) {
      progressValue.value = 1;
      extractedData.value = result.data;
      statusMessage.value = 'Extraction complete!';

      // Emit extracted data
      emit('extracted', result.data);

      $q.notify({
        type: 'positive',
        message: `Extracted: ${result.data.invoice_number || 'Invoice'}`,
        caption: `R${(result.data.amount || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}`,
        icon: 'check_circle',
      });
    } else {
      error.value = result.error;
      emit('error', result.error);
      $q.notify({
        type: 'negative',
        message: 'Extraction failed',
        caption: result.error,
        icon: 'error',
        timeout: 5000,
      });
    }
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Processing failed';
    error.value = message;
    emit('error', message);
    $q.notify({
      type: 'negative',
      message,
      icon: 'error',
    });
  } finally {
    isProcessing.value = false;
    progressIndeterminate.value = false;
  }
}

function clearFile() {
  file.value = null;
  extractedData.value = null;
  error.value = null;
  statusMessage.value = 'Preparing upload...';
  progressValue.value = 0;
  emit('cleared');
}

// Expose for parent access
defineExpose({
  file,
  clearFile,
  extractedData,
  error,
  isProcessing,
});
</script>
