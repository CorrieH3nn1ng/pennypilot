<template>
  <q-page class="login-page flex flex-center">
    <q-card class="login-card" flat>
      <!-- Logo & Branding -->
      <q-card-section class="text-center q-pt-lg q-pb-sm">
        <img
          src="/logo.png"
          alt="PennyPilot"
          class="login-logo q-mb-md"
        />
        <div class="text-h5 text-teal-9 text-weight-bold q-mb-xs">
          PennyPilot
        </div>
        <div class="text-subtitle1 text-grey-6 text-weight-light tagline">
          Personal Finance Made Simple
        </div>
      </q-card-section>

      <!-- Login Form -->
      <q-card-section class="q-px-lg">
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Email Input -->
          <q-input
            v-model="email"
            label="Email"
            type="email"
            inputmode="email"
            autocomplete="email"
            outlined
            rounded
            class="login-input"
            :rules="[
              (v) => !!v || 'Email is required',
              (v) => /.+@.+\..+/.test(v) || 'Please enter a valid email'
            ]"
            lazy-rules
          >
            <template v-slot:prepend>
              <q-icon name="email" color="teal-7" />
            </template>
          </q-input>

          <!-- Password Input -->
          <q-input
            v-model="password"
            label="Password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            outlined
            rounded
            class="login-input"
            :rules="[(v) => !!v || 'Password is required']"
            lazy-rules
          >
            <template v-slot:prepend>
              <q-icon name="lock" color="teal-7" />
            </template>
            <template v-slot:append>
              <q-btn
                flat
                round
                dense
                :icon="showPassword ? 'visibility_off' : 'visibility'"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                class="visibility-toggle"
                @click="showPassword = !showPassword"
              />
            </template>
          </q-input>

          <!-- Error Banner -->
          <q-banner
            v-if="error"
            class="bg-negative text-white error-banner"
            rounded
            dense
          >
            <template v-slot:avatar>
              <q-icon name="error" />
            </template>
            {{ error }}
          </q-banner>

          <!-- Login Button -->
          <q-btn
            type="submit"
            class="full-width login-btn q-mt-md"
            label="Login"
            unelevated
            :loading="isLoading"
            size="lg"
          />
        </q-form>
      </q-card-section>

      <q-separator class="q-mx-lg" />

      <!-- Register Link -->
      <q-card-section class="text-center q-py-md">
        <div class="text-body2 text-grey-7">
          Don't have an account?
          <router-link to="/register" class="register-link">
            Create one
          </router-link>
        </div>
      </q-card-section>

      <!-- Offline Mode -->
      <q-card-section class="text-center q-pt-none q-pb-lg">
        <q-btn
          flat
          no-caps
          class="offline-btn"
          icon="cloud_off"
          label="Continue Offline"
          @click="continueOffline"
        />
        <div class="text-caption text-grey-5 q-mt-xs">
          No internet? No problem.
        </div>
      </q-card-section>
    </q-card>

    <!-- Footer Tagline -->
    <div class="footer-tagline text-center q-mt-lg">
      <div class="text-caption text-grey-6">
        Your Cradle to Grave Financial GPS
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/stores/user.store';

const router = useRouter();
const userStore = useUserStore();

const email = ref('');
const password = ref('');
const showPassword = ref(false);

const isLoading = computed(() => userStore.isLoading);
const error = computed(() => userStore.error);

async function onSubmit() {
  const success = await userStore.login(email.value, password.value);
  if (success) {
    router.push('/');
  }
}

function continueOffline() {
  // Offline-first: Allow usage without auth for rural users
  router.push('/');
}
</script>

<style scoped>
/* Page Background */
.login-page {
  background: linear-gradient(135deg, #E0F2F1 0%, #B2DFDB 100%);
  min-height: 100vh;
}

/* Login Card - 12px border-radius per CLAUDE.md */
.login-card {
  width: 100%;
  max-width: 400px;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0, 77, 64, 0.12);
}

/* Logo */
.login-logo {
  width: 80px;
  height: 80px;
  object-fit: contain;
}

/* Tagline - Warm and fuzzy, softer weight */
.tagline {
  letter-spacing: 0.5px;
  font-style: italic;
}

/* Input fields - 12px border-radius, outlined premium style */
.login-input :deep(.q-field__control) {
  border-radius: 12px !important;
}

.login-input :deep(.q-field--outlined .q-field__control:before) {
  border-color: #B2DFDB;
}

.login-input :deep(.q-field--outlined .q-field__control:hover:before) {
  border-color: #004D40;
}

.login-input :deep(.q-field--outlined.q-field--focused .q-field__control:after) {
  border-color: #004D40;
  border-width: 2px;
}

/* Password visibility toggle - 44px+ touch target */
.visibility-toggle {
  min-width: 44px;
  min-height: 44px;
}

/* Login Button - Deep Teal primary color */
.login-btn {
  background-color: #004D40 !important;
  color: white !important;
  border-radius: 12px;
  font-weight: 600;
  letter-spacing: 0.5px;
  min-height: 48px;
}

.login-btn:hover {
  background-color: #00695C !important;
}

/* Error Banner */
.error-banner {
  border-radius: 12px;
}

/* Register Link */
.register-link {
  color: #004D40;
  text-decoration: none;
  font-weight: 600;
}

.register-link:hover {
  text-decoration: underline;
}

/* Offline Button - Flat Teal for rural users */
.offline-btn {
  color: #00796B !important;
  font-weight: 500;
}

.offline-btn:hover {
  background-color: rgba(0, 121, 107, 0.08);
}

/* Footer */
.footer-tagline {
  position: absolute;
  bottom: 24px;
  left: 0;
  right: 0;
}

/* Mobile-first responsive adjustments */
@media (max-width: 450px) {
  .login-card {
    margin: 16px;
    border-radius: 16px;
  }

  .login-logo {
    width: 64px;
    height: 64px;
  }
}
</style>
