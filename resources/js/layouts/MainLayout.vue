<template>
  <q-layout view="hHh lpR fFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" @click="drawer = !drawer" />
        <q-toolbar-title class="absolute-center">{{ $t('system.name') }}</q-toolbar-title>
        <q-space />
        
        <div class="row items-center q-gutter-sm">
          <div class="text-subtitle1">{{ userName }}</div>
          <q-btn flat round dense icon="account_circle">
            <q-menu>
              <q-list style="min-width: 150px">
                <q-item clickable v-close-popup @click="showPasswordDialog = true">
                  <q-item-section avatar>
                    <q-icon name="lock" />
                  </q-item-section>
                  <q-item-section>{{ $t('nav.changePassword') }}</q-item-section>
                </q-item>
                
                <q-item clickable v-close-popup :to="{ name: 'Settings' }">
                  <q-item-section avatar>
                    <q-icon name="settings" />
                  </q-item-section>
                  <q-item-section>{{ $t('settings.title') }}</q-item-section>
                </q-item>

                <q-item v-if="isAdmin" clickable v-close-popup :to="{ name: 'Users' }">
                  <q-item-section avatar>
                    <q-icon name="people" />
                  </q-item-section>
                  <q-item-section>{{ $t('nav.manageUsers') }}</q-item-section>
                </q-item>

                <q-separator />

                <q-item clickable v-close-popup @click="logout">
                  <q-item-section avatar>
                    <q-icon name="logout" />
                  </q-item-section>
                  <q-item-section>{{ $t('nav.logout') }}</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawer" show-if-above bordered>
      <q-list>
        
        <q-item clickable :to="{ name: 'Dashboard' }" exact>
          <q-item-section avatar>
            <q-icon name="dashboard" />
          </q-item-section>
          <q-item-section>{{ $t('nav.dashboard') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Products' }">
          <q-item-section avatar>
            <q-icon name="shopping_bag" />
          </q-item-section>
          <q-item-section>{{ $t('products.title') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Categories' }">
          <q-item-section avatar>
            <q-icon name="category" />
          </q-item-section>
          <q-item-section>{{ $t('nav.categories') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'ProductFamilies' }">
          <q-item-section avatar>
            <q-icon name="family_restroom" />
          </q-item-section>
          <q-item-section>{{ $t('nav.productFamilies') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Suppliers' }">
          <q-item-section avatar>
            <q-icon name="local_shipping" />
          </q-item-section>
          <q-item-section>{{ $t('nav.suppliers') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Clients' }">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>{{ $t('clients.title') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Distributors' }">
          <q-item-section avatar>
            <q-icon name="airport_shuttle" />
          </q-item-section>
          <q-item-section>{{ $t('distributors.title') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Purchases' }">
          <q-item-section avatar>
            <q-icon name="shopping_cart" />
          </q-item-section>
          <q-item-section>{{ $t('purchases.title') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Inventory' }">
          <q-item-section avatar>
            <q-icon name="inventory_2" />
          </q-item-section>
          <q-item-section>{{ $t('nav.inventory') }}</q-item-section>
        </q-item>



        <q-item clickable :to="{ name: 'DistributorStock' }">
          <q-item-section avatar>
            <q-icon name="inventory" />
          </q-item-section>
          <q-item-section>{{ $t('nav.distributorStock') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Sales' }">
          <q-item-section avatar>
            <q-icon name="point_of_sale" />
          </q-item-section>
          <q-item-section>{{ $t('sales.title') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'DeliveryNotes' }">
          <q-item-section avatar>
            <q-icon name="unarchive" />
          </q-item-section>
          <q-item-section>{{ $t('nav.deliveryNotes') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'ReturnNotes' }">
          <q-item-section avatar>
            <q-icon name="archive" />
          </q-item-section>
          <q-item-section>{{ $t('nav.returnNotes') }}</q-item-section>
        </q-item>

        <q-item clickable :to="{ name: 'Reports' }">
          <q-item-section avatar>
            <q-icon name="assessment" />
          </q-item-section>
          <q-item-section>{{ $t('nav.reports') }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>

    <!-- Change Password Dialog -->
    <q-dialog v-model="showPasswordDialog">
      <q-card style="min-width: 350px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ $t('system.changePassword') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="changePassword" class="q-gutter-md">
            <q-input
              v-model="passwordForm.current_password"
              :label="$t('users.currentPassword')"
              type="password"
              outlined
              dense
              :rules="[val => !!val || $t('messages.required')]"
            />
            <q-input
              v-model="passwordForm.new_password"
              :label="$t('users.newPassword')"
              type="password"
              outlined
              dense
              :rules="[val => !!val || $t('messages.required'), val => val.length >= 8 || $t('users.minChars')]"
            />
            <q-input
              v-model="passwordForm.new_password_confirmation"
              :label="$t('users.confirmPassword')"
              type="password"
              outlined
              dense
              :rules="[
                val => !!val || $t('messages.required'),
                val => val === passwordForm.new_password || $t('messages.error')
              ]"
            />
            
            <div class="row justify-end q-mt-md">
              <q-btn :label="$t('common.cancel')" flat v-close-popup />
              <q-btn type="submit" :label="$t('products.update')" color="primary" :loading="changingPassword" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const router = useRouter();
const drawer = ref(false);
const showPasswordDialog = ref(false);
const changingPassword = ref(false);

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));

const userName = computed(() => user.value.name || 'User');
const isAdmin = computed(() => user.value.role === 'admin');

const changePassword = async () => {
  changingPassword.value = true;
  try {
    await api.post('/change-password', passwordForm.value);
    $q.notify({ type: 'positive', message: t('system.passwordChanged') });
    showPasswordDialog.value = false;
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    };
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || t('messages.failedToSave') });
  } finally {
    changingPassword.value = false;
  }
};

const logout = () => {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  router.push('/login');
};

// Refresh user data on mount
onMounted(async () => {
  try {
    const response = await api.get('/user');
    user.value = response.data;
    localStorage.setItem('user', JSON.stringify(response.data));
  } catch (error) {
    // If token is invalid, logout
    if (error.response?.status === 401) {
      logout();
    }
  }
});
</script>
