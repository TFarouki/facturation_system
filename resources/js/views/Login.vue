<template>
  <div class="fullscreen bg-grey-2 flex flex-center">
    <q-card class="login-card" style="width: 400px; max-width: 90vw;">
      <q-card-section class="bg-primary text-white text-center">
        <div class="text-h5">{{ $t('system.title') }}</div>
        <div class="text-subtitle2">{{ $t('system.subtitle') }}</div>
      </q-card-section>
      <q-card-section>
        <q-form @submit="handleLogin">
          <q-input
            v-model="email"
            :label="$t('settings.email')"
            type="email"
            outlined
            dense
            class="q-mb-md"
            :rules="[val => !!val || $t('messages.required')]"
          >
            <template v-slot:prepend>
              <q-icon name="email" />
            </template>
          </q-input>
          <q-input
            v-model="password"
            :label="$t('settings.password')"
            :type="showPassword ? 'text' : 'password'"
            outlined
            dense
            class="q-mb-md"
            :rules="[val => !!val || $t('messages.required')]"
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
          <q-btn
            type="submit"
            :label="$t('system.login')"
            color="primary"
            class="full-width"
            :loading="loading"
          />
        </q-form>
      </q-card-section>
      <q-card-section v-if="error" class="text-negative text-center">
        {{ error }}
      </q-card-section>
    </q-card>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import api from '../api';
const router = useRouter();
const { t } = useI18n();
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');
const handleLogin = async () => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value,
    });
    
    localStorage.setItem('auth_token', response.data.token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    
    router.push('/');
  } catch (err) {
    error.value = err.response?.data?.message || t('system.loginFailed');
  } finally {
    loading.value = false;
  }
};
</script>
<style scoped>
.login-card {
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}
</style>