<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <q-btn flat icon="arrow_back" to="/" class="q-mr-sm" />
      <span class="text-h5">Business Profile</span>
    </div>

    <q-form @submit="handleSubmit" class="q-gutter-md">
      <!-- Business Information -->
      <q-card>
        <q-card-section>
          <div class="text-h6 q-mb-md">Business Information</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <q-input
                v-model="formData.business_name"
                label="Business Name"
                filled
                hint="Your company or personal name"
              />
            </div>
            <div class="col-12 col-md-6">
              <q-input
                v-model="formData.trading_name"
                label="Trading As"
                filled
                hint="Optional trading name"
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.registration_number"
                label="Company Registration"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.tax_number"
                label="Tax Reference Number"
                filled
                hint="SARS tax number"
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.vat_number"
                label="VAT Number"
                filled
                hint="If VAT registered"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Contact Information -->
      <q-card>
        <q-card-section>
          <div class="text-h6 q-mb-md">Contact Information</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.email"
                label="Email"
                type="email"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.phone"
                label="Phone"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.website"
                label="Website"
                filled
              />
            </div>
            <div class="col-12">
              <q-input
                v-model="formData.address"
                label="Business Address"
                type="textarea"
                filled
                rows="3"
                hint="This will appear on your invoices"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Banking Details -->
      <q-card>
        <q-card-section>
          <div class="row items-center q-mb-md">
            <div class="text-h6">Banking Details</div>
            <q-space />
            <q-badge v-if="hasBankingDetails" color="positive" label="Complete" />
            <q-badge v-else color="warning" label="Incomplete" />
          </div>
          <div class="text-caption text-grey q-mb-md">
            These details will appear on your invoices for client payments
          </div>

          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.bank_name"
                label="Bank Name"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.bank_branch"
                label="Branch"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.bank_branch_code"
                label="Branch Code"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.account_holder"
                label="Account Holder Name"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.account_number"
                label="Account Number"
                filled
              />
            </div>
            <div class="col-12 col-md-4">
              <q-select
                v-model="formData.account_type"
                :options="accountTypeOptions"
                label="Account Type"
                emit-value
                map-options
                filled
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Invoice Settings -->
      <q-card>
        <q-card-section>
          <div class="text-h6 q-mb-md">Invoice Settings</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.invoice_prefix"
                label="Invoice Prefix"
                filled
                hint="e.g., INV-"
              />
            </div>
            <div class="col-12 col-md-4">
              <q-input
                v-model.number="formData.default_tax_rate"
                label="Default VAT Rate (%)"
                type="number"
                filled
                min="0"
                max="100"
                step="0.5"
              />
            </div>
            <div class="col-12">
              <q-input
                v-model="formData.payment_terms"
                label="Payment Terms"
                filled
                hint="e.g., 'Payment due within 7 days of invoice date'"
              />
            </div>
            <div class="col-12">
              <q-input
                v-model="formData.invoice_notes"
                label="Default Invoice Notes"
                type="textarea"
                filled
                rows="2"
                hint="Default notes that appear on all invoices"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Actions -->
      <div class="row justify-end q-gutter-sm">
        <q-btn
          type="submit"
          color="primary"
          label="Save Profile"
          :loading="isLoading"
        />
      </div>
    </q-form>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { useBusinessProfileStore } from '@/stores/business-profile.store';

const $q = useQuasar();
const profileStore = useBusinessProfileStore();

// State
const formData = ref({
  business_name: '',
  trading_name: '',
  registration_number: '',
  tax_number: '',
  vat_number: '',
  email: '',
  phone: '',
  website: '',
  address: '',
  bank_name: '',
  bank_branch: '',
  bank_branch_code: '',
  account_holder: '',
  account_number: '',
  account_type: '' as string | null,
  invoice_prefix: 'INV-',
  invoice_notes: '',
  payment_terms: '',
  default_tax_rate: 0,
});

const accountTypeOptions = [
  { label: 'Cheque Account', value: 'cheque' },
  { label: 'Savings Account', value: 'savings' },
  { label: 'Transmission Account', value: 'transmission' },
  { label: 'Current Account', value: 'current' },
];

// Computed
const isLoading = computed(() => profileStore.isLoading);
const hasBankingDetails = computed(() => profileStore.bankingComplete);

// Methods
async function handleSubmit() {
  const result = await profileStore.updateProfile(formData.value);
  if (result) {
    $q.notify({ type: 'positive', message: 'Business profile saved' });
  } else {
    $q.notify({
      type: 'negative',
      message: 'Failed to save profile',
      caption: profileStore.error || 'Please check your input'
    });
  }
}

function loadFormData() {
  const profile = profileStore.profile;
  if (profile) {
    formData.value = {
      business_name: profile.business_name || '',
      trading_name: profile.trading_name || '',
      registration_number: profile.registration_number || '',
      tax_number: profile.tax_number || '',
      vat_number: profile.vat_number || '',
      email: profile.email || '',
      phone: profile.phone || '',
      website: profile.website || '',
      address: profile.address || '',
      bank_name: profile.bank_name || '',
      bank_branch: profile.bank_branch || '',
      bank_branch_code: profile.bank_branch_code || '',
      account_holder: profile.account_holder || '',
      account_number: profile.account_number || '',
      account_type: profile.account_type,
      invoice_prefix: profile.invoice_prefix || 'INV-',
      invoice_notes: profile.invoice_notes || '',
      payment_terms: profile.payment_terms || '',
      default_tax_rate: profile.default_tax_rate || 0,
    };
  }
}

// Watch for profile changes
watch(() => profileStore.profile, () => {
  loadFormData();
});

// Lifecycle
onMounted(async () => {
  await profileStore.loadProfile();
  loadFormData();
});
</script>
