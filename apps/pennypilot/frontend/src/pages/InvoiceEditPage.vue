<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <q-btn flat round icon="arrow_back" :to="{ name: 'invoices' }" />
      <span class="text-h6 q-ml-sm">{{ isEdit ? 'Edit Invoice' : 'New Invoice' }}</span>
    </div>

    <!-- AI Upload Section - Only for new invoices -->
    <q-card v-if="!isEdit" class="q-mb-md">
      <q-card-section>
        <InvoiceUploader
          ref="uploaderRef"
          @extracted="handleExtracted"
        />
      </q-card-section>
    </q-card>

    <q-form @submit="handleSubmit">
      <!-- Client & Dates -->
      <q-card class="q-mb-md">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle2 text-grey-7 q-mb-sm">Invoice Details</div>
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="formData.client_id"
            :options="clientOptions"
            label="Client *"
            emit-value
            map-options
            filled
            dense
            :rules="[val => !!val || 'Client is required']"
            @update:model-value="onClientChange"
          >
            <template v-slot:after>
              <q-btn flat round dense icon="add" @click="showClientDialog = true" />
            </template>
          </q-select>

          <q-input
            v-model="previewNumber"
            label="Invoice Number"
            filled
            dense
            readonly
            class="q-mt-sm"
          />

          <div class="row q-col-gutter-sm q-mt-sm">
            <div class="col-6">
              <q-input
                v-model="formData.invoice_date"
                label="Invoice Date *"
                type="date"
                filled
                dense
                :rules="[val => !!val || 'Required']"
              />
            </div>
            <div class="col-6">
              <q-input
                v-model="formData.due_date"
                label="Due Date *"
                type="date"
                filled
                dense
                :rules="[val => !!val || 'Required']"
                @update:model-value="updatePreviewNumber"
              />
            </div>
          </div>

          <div class="row q-col-gutter-sm q-mt-sm">
            <div class="col-6">
              <q-input
                v-model.number="formData.tax_rate"
                label="VAT %"
                type="number"
                filled
                dense
                min="0"
                max="100"
              />
            </div>
            <div class="col-6">
              <!-- Placeholder -->
            </div>
          </div>

          <q-input
            v-model="formData.title"
            label="Description"
            filled
            dense
            class="q-mt-sm"
            placeholder="e.g., December 2025 Services"
          />
        </q-card-section>
      </q-card>

      <!-- Line Items -->
      <q-card class="q-mb-md">
        <q-card-section class="q-pb-none">
          <div class="row items-center justify-between">
            <span class="text-subtitle2 text-grey-7">Line Items</span>
            <q-btn flat dense size="sm" icon="add" label="Add" color="primary" @click="addItem" />
          </div>
        </q-card-section>
        <q-card-section>
          <div v-for="(item, index) in formData.items" :key="index" class="q-mb-md">
            <q-input
              v-model="item.description"
              label="Description *"
              filled
              dense
              :rules="[val => !!val || 'Required']"
            />
            <div class="row q-col-gutter-sm q-mt-xs">
              <div class="col-3">
                <q-input
                  v-model.number="item.quantity"
                  label="Qty"
                  type="number"
                  filled
                  dense
                  min="0.01"
                  step="0.01"
                />
              </div>
              <div class="col-3">
                <q-input
                  v-model="item.unit"
                  label="Unit"
                  filled
                  dense
                  placeholder="hrs"
                />
              </div>
              <div class="col-4">
                <CurrencyInput
                  v-model="item.unit_price"
                  label="Rate"
                  filled
                  dense
                />
              </div>
              <div class="col-2 flex items-center justify-center">
                <q-btn
                  flat
                  round
                  dense
                  icon="delete"
                  color="negative"
                  size="sm"
                  :disable="formData.items.length === 1"
                  @click="removeItem(index)"
                />
              </div>
            </div>
            <div class="text-right text-caption text-grey q-mt-xs">
              = R {{ formatMoney(item.quantity * item.unit_price) }}
            </div>
            <q-separator v-if="index < formData.items.length - 1" class="q-mt-md" />
          </div>
        </q-card-section>
      </q-card>

      <!-- Totals -->
      <q-card class="q-mb-md">
        <q-card-section>
          <div class="row justify-between q-mb-xs">
            <span class="text-grey-7">Subtotal</span>
            <span>R {{ formatMoney(subtotal) }}</span>
          </div>
          <div v-if="formData.tax_rate > 0" class="row justify-between q-mb-xs">
            <span class="text-grey-7">VAT ({{ formData.tax_rate }}%)</span>
            <span>R {{ formatMoney(taxAmount) }}</span>
          </div>
          <q-separator class="q-my-sm" />
          <div class="row justify-between text-subtitle1 text-weight-bold">
            <span>Total</span>
            <span>R {{ formatMoney(total) }}</span>
          </div>
        </q-card-section>
      </q-card>

      <!-- Notes -->
      <q-card class="q-mb-md">
        <q-card-section>
          <q-input
            v-model="formData.notes"
            label="Notes"
            type="textarea"
            filled
            dense
            rows="2"
          />
        </q-card-section>
      </q-card>

      <!-- Actions -->
      <div class="row q-gutter-sm q-mb-xl">
        <q-btn
          outline
          color="grey"
          label="Cancel"
          class="col"
          :to="{ name: 'invoices' }"
        />
        <q-btn
          type="submit"
          color="primary"
          label="Save"
          class="col"
          :loading="isLoading"
        />
      </div>
    </q-form>

    <!-- Add Client Dialog -->
    <q-dialog v-model="showClientDialog" :maximized="$q.screen.lt.sm" transition-show="slide-up">
      <q-card :style="$q.screen.lt.sm ? '' : 'width: 400px'">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat round dense icon="close" v-close-popup />
          <q-toolbar-title>Add Client</q-toolbar-title>
          <q-btn flat label="Save" @click="handleAddClient" />
        </q-toolbar>

        <q-scroll-area :style="$q.screen.lt.sm ? 'height: calc(100vh - 50px)' : 'max-height: 70vh'">
          <div class="q-pa-md">
            <q-input v-model="newClient.name" label="Client Name *" filled dense class="q-mb-sm" />
            <q-input v-model="newClient.email" label="Email" type="email" filled dense class="q-mb-sm" />
            <q-input v-model="newClient.contact_person" label="Contact Person" filled dense class="q-mb-sm" />
            <q-input v-model="newClient.phone" label="Phone" filled dense class="q-mb-sm" />
            <q-input v-model="newClient.address" label="Address" type="textarea" filled dense rows="2" />
          </div>
        </q-scroll-area>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useInvoiceStore } from '@/stores/invoice.store';
import { useClientStore } from '@/stores/client.store';
import { useBusinessProfileStore } from '@/stores/business-profile.store';
import InvoiceUploader from '@/components/InvoiceUploader.vue';
import CurrencyInput from '@/components/CurrencyInput.vue';
import { formatNumber } from '@/utils/currency';
import type { ExtractedInvoiceData, MatchedClient } from '@/services/api/ai.api';

const $q = useQuasar();
const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const clientStore = useClientStore();
const profileStore = useBusinessProfileStore();

// Refs
const uploaderRef = ref<InstanceType<typeof InvoiceUploader> | null>(null);

// State
const isEdit = computed(() => !!route.params.id);
const showClientDialog = ref(false);
const previewNumber = ref('');

const formData = ref({
  client_id: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  tax_rate: 0,
  title: '',
  notes: '',
  items: [
    { description: '', quantity: 1, unit: '', unit_price: 0 },
  ],
});

const newClient = ref({
  name: '',
  email: '',
  contact_person: '',
  phone: '',
  address: '',
});

// Computed
const isLoading = computed(() => invoiceStore.isLoading);
const clientOptions = computed(() => clientStore.clientOptions);

const subtotal = computed(() => {
  return formData.value.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
});

const taxAmount = computed(() => subtotal.value * (formData.value.tax_rate / 100));
const total = computed(() => subtotal.value + taxAmount.value);

// Methods - use centralized currency utilities
function formatMoney(amount: number | undefined | null): string {
  return formatNumber(amount);
}

function addItem() {
  formData.value.items.push({ description: '', quantity: 1, unit: '', unit_price: 0 });
}

function removeItem(index: number) {
  formData.value.items.splice(index, 1);
}

// Handle extracted data from uploader
function handleExtracted(data: ExtractedInvoiceData, matchedClient: MatchedClient | null) {
  // Auto-select matched client
  if (matchedClient) {
    formData.value.client_id = matchedClient.id;
  }

  // Fill dates
  if (data.invoice_date) formData.value.invoice_date = data.invoice_date;
  if (data.due_date) formData.value.due_date = data.due_date;

  // Fill tax
  if (data.tax_rate) formData.value.tax_rate = data.tax_rate;

  // Fill line items
  if (data.line_items && data.line_items.length > 0) {
    formData.value.items = data.line_items.map(item => ({
      description: item.description,
      quantity: item.quantity,
      unit: '',
      unit_price: item.unit_price,
    }));
  }

  // Update invoice number preview
  updatePreviewNumber();
}

async function updatePreviewNumber() {
  if (formData.value.client_id && formData.value.due_date) {
    const result = await invoiceStore.previewInvoiceNumber(formData.value.client_id, formData.value.due_date);
    if (result) previewNumber.value = result.invoice_number;
  }
}

async function onClientChange() {
  await updatePreviewNumber();
}

async function handleAddClient() {
  if (!newClient.value.name) {
    $q.notify({ type: 'negative', message: 'Client name is required' });
    return;
  }

  const created = await clientStore.createClient(newClient.value);
  if (created) {
    formData.value.client_id = created.id;
    showClientDialog.value = false;
    newClient.value = { name: '', email: '', contact_person: '', phone: '', address: '' };
    $q.notify({ type: 'positive', message: 'Client added' });
    await updatePreviewNumber();
  }
}

async function handleSubmit() {
  const validItems = formData.value.items.filter(item => item.description && item.quantity > 0);

  if (validItems.length === 0) {
    $q.notify({ type: 'negative', message: 'Add at least one line item' });
    return;
  }

  const data = {
    client_id: formData.value.client_id,
    invoice_date: formData.value.invoice_date,
    due_date: formData.value.due_date,
    tax_rate: formData.value.tax_rate,
    title: formData.value.title || undefined,
    notes: formData.value.notes || undefined,
    items: validItems.map(item => ({
      description: item.description,
      quantity: item.quantity,
      unit: item.unit || undefined,
      unit_price: item.unit_price,
    })),
  };

  let result;
  if (isEdit.value) {
    result = await invoiceStore.updateInvoice(route.params.id as string, data);
  } else {
    result = await invoiceStore.createInvoice(data);
  }

  if (result) {
    $q.notify({ type: 'positive', message: isEdit.value ? 'Invoice updated' : 'Invoice created' });
    router.push({ name: 'invoice-view', params: { id: result.id } });
  }
}

// Watch for due date changes
watch(() => formData.value.due_date, updatePreviewNumber);

// Lifecycle
onMounted(async () => {
  await clientStore.loadClients();
  await profileStore.loadProfile();

  if (profileStore.profile?.default_tax_rate) {
    formData.value.tax_rate = profileStore.profile.default_tax_rate;
  }

  if (isEdit.value) {
    const invoice = await invoiceStore.loadInvoice(route.params.id as string);
    if (invoice) {
      formData.value = {
        client_id: invoice.client_id,
        invoice_date: invoice.invoice_date,
        due_date: invoice.due_date,
        tax_rate: invoice.tax_rate,
        title: invoice.title || '',
        notes: invoice.notes || '',
        items: invoice.items.map(item => ({
          description: item.description,
          quantity: item.quantity,
          unit: item.unit || '',
          unit_price: item.unit_price,
        })),
      };
      previewNumber.value = invoice.invoice_number;
    }
  }
});
</script>
