<template>
  <q-page class="flex flex-center bg-grey-2">
    <q-card class="login-card">
      <q-card-section class="text-center">
        <div class="text-h4 text-primary q-mb-sm">PennyPilot</div>
        <div class="text-subtitle2 text-grey">Personal Finance Made Simple</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit">
          <q-input
            v-model="email"
            label="Email"
            type="email"
            outlined
            :rules="[(v) => !!v || 'Email is required', (v) => /.+@.+/.test(v) || 'Invalid email']"
          >
            <template v-slot:prepend>
              <q-icon name="email" />
            </template>
          </q-input>

          <q-input
            v-model="password"
            label="Password"
            :type="showPassword ? 'text' : 'password'"
            outlined
            class="q-mt-md"
            :rules="[(v) => !!v || 'Password is required']"
          >
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
            <template v-slot:append>
              <q-icon
                :name="showPassword ? 'visibility_off' : 'visibility'"
                class="cursor-pointer"
                @click="showPassword = !showPassword"
              />
            </template>
          </q-input>

          <q-banner v-if="error" class="bg-negative text-white q-mt-md" rounded>
            {{ error }}
          </q-banner>

          <q-btn
            type="submit"
            color="primary"
            class="full-width q-mt-lg"
            label="Login"
            :loading="isLoading"
          />
        </q-form>
      </q-card-section>

      <q-separator />

      <q-card-section class="text-center">
        <div class="text-body2">
          Don't have an account?
          <router-link to="/register" class="text-primary">Register</router-link>
        </div>
      </q-card-section>

      <q-card-section class="text-center q-pt-none">
        <q-btn flat color="grey" label="Continue Offline" @click="continueOffline" />
      </q-card-section>
    </q-card>
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
  // Allow offline usage without auth for demo/testing
  router.push('/');
}
</script>

<style scoped>
.login-card {
  width: 100%;
  max-width: 400px;
}
</style>
