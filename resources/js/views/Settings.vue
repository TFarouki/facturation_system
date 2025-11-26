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
              <div class="col-12 col-md-6 flex flex-center" v-if="form.company_logo">
                <img :src="getLogoUrl(form.company_logo)" style="max-height: 100px; max-width: 100%" />
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
    await api.put('/settings', form.value);
    $q.notify({ type: 'positive', message: 'Settings saved successfully' });
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save settings' });
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

const calculateExample = (base, percentage) => {
  return (base * (1 + percentage / 100)).toFixed(2);
};

onMounted(() => {
  loadSettings();
});
</script>
