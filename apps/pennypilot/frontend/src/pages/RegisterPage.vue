<template>
  <q-page class="flex flex-center bg-grey-2">
    <q-card class="register-card">
      <q-card-section class="text-center">
        <div class="text-h4 text-primary q-mb-sm">PennyPilot</div>
        <div class="text-subtitle2 text-grey">Create Your Account</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit">
          <q-input
            v-model="name"
            label="Full Name"
            outlined
            :rules="[(v) => !!v || 'Name is required']"
          >
            <template v-slot:prepend>
              <q-icon name="person" />
            </template>
          </q-input>

          <q-input
            v-model="email"
            label="Email"
            type="email"
            outlined
            class="q-mt-md"
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
            :rules="[(v) => !!v || 'Password is required', (v) => v.length >= 8 || 'Min 8 characters']"
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

          <q-input
            v-model="passwordConfirmation"
            label="Confirm Password"
            :type="showPassword ? 'text' : 'password'"
            outlined
            class="q-mt-md"
            :rules="[(v) => v === password || 'Passwords must match']"
          >
            <template v-slot:prepend>
              <q-icon name="lock" />
            </template>
          </q-input>

          <q-checkbox v-model="consent" class="q-mt-md">
            <span class="text-body2">
              I agree to the
              <a href="#" class="text-primary">Terms of Service</a> and
              <a href="#" class="text-primary">Privacy Policy</a>
            </span>
          </q-checkbox>

          <q-banner v-if="error" class="bg-negative text-white q-mt-md" rounded>
            {{ error }}
          </q-banner>

          <q-btn
            type="submit"
            color="primary"
            class="full-width q-mt-lg"
            label="Create Account"
            :loading="isLoading"
            :disable="!consent"
          />
        </q-form>
      </q-card-section>

      <q-separator />

      <q-card-section class="text-center">
        <div class="text-body2">
          Already have an account?
          <router-link to="/login" class="text-primary">Login</router-link>
        </div>
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

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const showPassword = ref(false);
const consent = ref(false);

const isLoading = computed(() => userStore.isLoading);
const error = computed(() => userStore.error);

async function onSubmit() {
  const success = await userStore.register({
    name: name.value,
    email: email.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  });
  if (success) {
    router.push('/');
  }
}
</script>

<style scoped>
.register-card {
  width: 100%;
  max-width: 450px;
}
</style>
