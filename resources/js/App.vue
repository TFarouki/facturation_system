<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue';
import api from './api';
import { setLanguage } from './i18n';

// Load language from settings on app start
onMounted(async () => {
  // Only try to load settings if user is authenticated
  const token = localStorage.getItem('auth_token');
  if (!token) {
    // Use localStorage fallback for unauthenticated users
    const savedLang = localStorage.getItem('app_language');
    if (savedLang) {
      setLanguage(savedLang);
    }
    return;
  }

  try {
    const response = await api.get('/settings');
    if (response.data?.language) {
      setLanguage(response.data.language);
    }
  } catch (e) {
    // Use localStorage fallback if API fails
    const savedLang = localStorage.getItem('app_language');
    if (savedLang) {
      setLanguage(savedLang);
    }
  }
});
</script>
