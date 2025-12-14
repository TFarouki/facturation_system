import { createI18n } from 'vue-i18n';
import ar from './locales/ar';
import en from './locales/en';
import fr from './locales/fr';

// Get saved language from localStorage or default to 'ar'
const savedLanguage = localStorage.getItem('app_language') || 'ar';

const i18n = createI18n({
  legacy: false, // Use Composition API mode
  locale: savedLanguage,
  fallbackLocale: 'en',
  messages: {
    ar,
    en,
    fr,
  },
});

// Function to change language
export const setLanguage = (lang) => {
  i18n.global.locale.value = lang;
  localStorage.setItem('app_language', lang);
  
  // Set document direction for RTL languages
  if (lang === 'ar') {
    document.documentElement.setAttribute('dir', 'rtl');
    document.documentElement.setAttribute('lang', 'ar');
  } else {
    document.documentElement.setAttribute('dir', 'ltr');
    document.documentElement.setAttribute('lang', lang);
  }
};

// Initialize direction based on saved language
setLanguage(savedLanguage);

export default i18n;
