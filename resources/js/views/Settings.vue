<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">System Settings</div>

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
        <q-tab name="company" label="Company Information" icon="business" />
        <q-tab name="pricing" label="Pricing Configuration" icon="price_change" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="company">
          <q-form @submit="saveSettings" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-input v-model="form.company_name" label="Company Name" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.email" label="Email" type="email" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.phone" label="Phone" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.address" label="Address" type="textarea" rows="2" outlined dense />
              </div>
              
              <div class="col-12">
                <q-separator class="q-my-sm" />
                <div class="text-h6 q-mb-sm">Contact Person</div>
              </div>
              
              <div class="col-12 col-md-6">
                <q-input v-model="form.contact_person" label="Contact Name" outlined dense />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.contact_phone" label="Contact Phone" outlined dense />
              </div>

              <div class="col-12">
                <q-separator class="q-my-sm" />
                <div class="text-h6 q-mb-sm">Company Logo</div>
              </div>

              <div class="col-12">
                <div class="row q-col-gutter-md items-center">
                  <div class="col-12 col-md-6">
                    <q-file
                      v-model="logoFile"
                      label="Upload Logo"
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
                        <q-tooltip>View Logo</q-tooltip>
                      </q-btn>
                    </div>
                    <div v-else class="text-caption text-grey-6 q-pa-sm">
                      No logo uploaded
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row justify-end q-mt-md">
              <q-btn type="submit" color="primary" label="Save Changes" :loading="saving" />
            </div>
          </q-form>
        </q-tab-panel>

        <q-tab-panel name="pricing">
          <div class="text-subtitle1 q-mb-md">
            Configure automatic price calculations based on Wholesale Price.
          </div>

          <q-form @submit="saveSettings" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-input
                  v-model.number="form.semi_wholesale_percentage"
                  label="Semi-Wholesale Margin (%)"
                  type="number"
                  outlined
                  dense
                  suffix="%"
                  :rules="[val => val >= 0 || 'Must be positive']"
                />
                <div class="text-caption text-grey">
                  Example: 100 DH (Wholesale) + {{ form.semi_wholesale_percentage }}% = {{ calculateExample(100, form.semi_wholesale_percentage) }} DH
                </div>
              </div>

              <div class="col-12 col-md-6">
                <q-input
                  v-model.number="form.retail_percentage"
                  label="Retail Margin (%)"
                  type="number"
                  outlined
                  dense
                  suffix="%"
                  :rules="[val => val >= 0 || 'Must be positive']"
                />
                <div class="text-caption text-grey">
                  Example: 100 DH (Wholesale) + {{ form.retail_percentage }}% = {{ calculateExample(100, form.retail_percentage) }} DH
                </div>
              </div>
            </div>

            <div class="row justify-end q-mt-md">
              <q-btn type="submit" color="primary" label="Save Changes" :loading="saving" />
            </div>
          </q-form>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>

    <!-- Logo View Dialog -->
    <q-dialog v-model="showLogoDialog">
      <q-card style="max-width: 600px; width: 100%">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Company Logo</div>
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
import api from '../api';

const $q = useQuasar();
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
});

const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    form.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load settings' });
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
    $q.notify({ type: 'positive', message: 'Settings saved successfully' });
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to save settings';
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
    $q.notify({ type: 'positive', message: 'Logo uploaded successfully' });
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to upload logo' });
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
