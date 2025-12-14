<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">{{ $t('settings.title') }}</div>

    <q-card>
      <q-tabs
        v-model="tab"
        dense
        class="text-grey"
        active-color="primary"
        indicator-color="primary"
        align="justify"
        narrow-indicator
      >
        <q-tab name="company" :label="$t('settings.companyInfo')" icon="business" />
        <q-tab name="pricing" :label="$t('settings.pricing')" icon="price_change" />
        <q-tab name="language" :label="$t('settings.language')" icon="language" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="company">
          <q-form @submit="saveSettings" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-input v-model="form.company_name" :label="$t('settings.companyName')" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.email" :label="$t('settings.email')" type="email" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.phone" :label="$t('settings.phone')" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.address" :label="$t('settings.address')" type="textarea" rows="2" outlined dense />
              </div>
              
              <div class="col-12">
                <q-separator class="q-my-sm" />
                <div class="text-h6 q-mb-sm">{{ $t('settings.contactPerson') }}</div>
              </div>
              
              <div class="col-12 col-md-6">
                <q-input v-model="form.contact_person" :label="$t('settings.contactPerson')" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.contact_phone" :label="$t('settings.phone')" outlined dense />
              </div>

              <div class="col-12">
                <q-separator class="q-my-sm" />
                <div class="text-h6 q-mb-sm">{{ $t('settings.companyLogo') }}</div>
              </div>

              <div class="col-12">
                <div class="row q-col-gutter-md items-center">
                  <div class="col-12 col-md-6">
                    <q-file
                      v-model="logoFile"
                      :label="$t('settings.uploadLogo')"
                      outlined
                      dense
                      accept="image/*"
                      @update:model-value="uploadLogo"
                    >
                      <template v-slot:prepend>
                        <q-icon name="attach_file" />
                      </template>
                    </q-file>
                  </div>
                  <div class="col-12 col-md-6">
                    <div v-if="form.company_logo" class="row items-center q-gutter-sm">
                      <div class="q-pa-sm" style="border: 1px solid #e0e0e0; border-radius: 4px; background: #fafafa;">
                        <img 
                          :src="getLogoUrl(form.company_logo)" 
                          style="max-height: 80px; max-width: 150px; display: block;" 
                          alt="Company Logo"
                          @error="handleImageError"
                        />
                      </div>
                      <q-btn
                        flat
                        dense
                        icon="visibility"
                        color="primary"
                        @click="viewLogo"
                      >
                        <q-tooltip>{{ $t('settings.viewLogo') }}</q-tooltip>
                      </q-btn>
                    </div>
                    <div v-else class="text-caption text-grey-6 q-pa-sm">
                      {{ $t('settings.noLogo') }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row justify-end q-mt-md">
              <q-btn type="submit" color="primary" :label="$t('settings.saveChanges')" :loading="saving" />
            </div>
          </q-form>
        </q-tab-panel>

        <q-tab-panel name="pricing">
          <div class="text-subtitle1 q-mb-md">
            {{ $t('settings.pricingDescription') }}
          </div>

          <q-form @submit="saveSettings" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-input
                  v-model.number="form.semi_wholesale_percentage"
                  :label="$t('settings.semiWholesaleMargin')"
                  type="number"
                  outlined
                  dense
                  suffix="%"
                  :rules="[val => val >= 0 || 'Must be positive']"
                />
                <div class="text-caption text-grey">
                  {{ $t('settings.example') }}: 100 DH + {{ form.semi_wholesale_percentage }}% = {{ calculateExample(100, form.semi_wholesale_percentage) }} DH
                </div>
              </div>

              <div class="col-12 col-md-6">
                <q-input
                  v-model.number="form.retail_percentage"
                  :label="$t('settings.retailMargin')"
                  type="number"
                  outlined
                  dense
                  suffix="%"
                  :rules="[val => val >= 0 || 'Must be positive']"
                />
                <div class="text-caption text-grey">
                  {{ $t('settings.example') }}: 100 DH + {{ form.retail_percentage }}% = {{ calculateExample(100, form.retail_percentage) }} DH
                </div>
              </div>
            </div>

            <div class="row justify-end q-mt-md">
              <q-btn type="submit" color="primary" :label="$t('settings.saveChanges')" :loading="saving" />
            </div>
          </q-form>
        </q-tab-panel>

        <q-tab-panel name="language">
          <div class="text-subtitle1 q-mb-md">
            {{ $t('settings.chooseLanguage') }}
          </div>

          <q-form @submit="saveSettings" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-select
                  v-model="form.language"
                  :options="languageOptions"
                  :label="$t('settings.systemLanguage')"
                  outlined
                  dense
                  emit-value
                  map-options
                  option-value="value"
                  option-label="label"
                >
                  <template v-slot:prepend>
                    <q-icon name="translate" />
                  </template>
                </q-select>
              </div>
            </div>

            <div class="row justify-end q-mt-md">
              <q-btn type="submit" color="primary" :label="$t('settings.saveChanges')" :loading="saving" />
            </div>
          </q-form>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>

    <!-- Logo View Dialog -->
    <q-dialog v-model="showLogoDialog">
      <q-card style="max-width: 600px; width: 100%">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ $t('settings.logoDialogTitle') }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section class="flex flex-center">
          <img 
            v-if="form.company_logo" 
            :src="getLogoUrl(form.company_logo)" 
            style="max-width: 100%; max-height: 500px; object-fit: contain;" 
            alt="Company Logo"
          />
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';
import { setLanguage } from '../i18n';

const $q = useQuasar();
const { t, locale } = useI18n();
const tab = ref('company');
const saving = ref(false);
const logoFile = ref(null);
const showLogoDialog = ref(false);

const form = ref({
  company_name: '',
  company_logo: null,
  phone: '',
  email: '',
  address: '',
  contact_person: '',
  contact_phone: '',
  semi_wholesale_percentage: 0,
  retail_percentage: 0,
  language: 'ar',
});

const languageOptions = [
  { value: 'ar', label: 'العربية (Arabic)' },
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'Français (French)' },
];

const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    form.value = response.data;
    
    // Apply saved language
    if (response.data.language) {
      setLanguage(response.data.language);
    }
  } catch (error) {
    // Don't show notification for 401 (user will be redirected to login)
    if (error.response?.status === 401) {
      return;
    }
    console.error('Failed to load settings:', error.response?.data || error.message);
    const errorMsg = error.response?.data?.message || error.response?.data?.error || t('messages.failedToLoadData');
    $q.notify({ type: 'negative', message: errorMsg });
  }
};

const saveSettings = async () => {
  saving.value = true;
  try {
    // Prepare data to send - exclude null/empty values that shouldn't be updated
    const dataToSend = { ...form.value };
    
    // Remove company_logo if it's null (it should only be updated via uploadLogo)
    if (dataToSend.company_logo === null || dataToSend.company_logo === '') {
      delete dataToSend.company_logo;
    }
    
    // Ensure percentages are numbers, not strings
    if (dataToSend.semi_wholesale_percentage !== undefined) {
      dataToSend.semi_wholesale_percentage = parseFloat(dataToSend.semi_wholesale_percentage) || 0;
    }
    if (dataToSend.retail_percentage !== undefined) {
      dataToSend.retail_percentage = parseFloat(dataToSend.retail_percentage) || 0;
    }
    
    await api.put('/settings', dataToSend);
    
    // Apply language change
    if (dataToSend.language) {
      setLanguage(dataToSend.language);
    }
    
    $q.notify({ type: 'positive', message: t('messages.savedSuccessfully') });
  } catch (error) {
    const errorMessage = error.response?.data?.message || t('messages.failedToSave');
    const errorDetails = error.response?.data?.errors;
    let fullMessage = errorMessage;
    if (errorDetails) {
      const details = Object.values(errorDetails).flat().join(', ');
      fullMessage = `${errorMessage}: ${details}`;
    }
    $q.notify({ type: 'negative', message: fullMessage });
  } finally {
    saving.value = false;
  }
};

const uploadLogo = async (file) => {
  if (!file) return;

  const formData = new FormData();
  formData.append('logo', file);

  try {
    const response = await api.post('/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    form.value.company_logo = response.data.company_logo;
    $q.notify({ type: 'positive', message: t('messages.savedSuccessfully') });
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToSave') });
  }
};

const getLogoUrl = (path) => {
  if (!path) return '';
  return `http://localhost:8000/storage/${path}`;
};

const viewLogo = () => {
  if (!form.value.company_logo) return;
  showLogoDialog.value = true;
};

const handleImageError = (event) => {
  console.error('Failed to load logo image:', event);
};

const calculateExample = (base, percentage) => {
  return (base * (1 + percentage / 100)).toFixed(2);
};

onMounted(() => {
  loadSettings();
});
</script>
