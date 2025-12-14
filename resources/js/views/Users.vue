<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('users.title') }}</div>
      <q-btn color="primary" icon="add" :label="$t('users.addUser')" @click="openDialog()" />
    </div>

    <q-table
      :rows="users"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
    >
      <template v-slot:body-cell-role="props">
        <q-td :props="props">
          <q-badge :color="props.row.role === 'admin' ? 'negative' : 'primary'">
            {{ props.row.role === 'admin' ? $t('users.admin') : $t('users.user') }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense round icon="edit" color="primary" @click="openDialog(props.row)">
            <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense round icon="delete" color="negative" @click="confirmDelete(props.row)">
            <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? $t('users.editUser') : $t('users.addUser') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveUser" class="q-gutter-md">
            <q-input v-model="form.name" :label="$t('users.name') + ' *'" outlined dense :rules="[val => !!val || $t('messages.required')]" />
            <q-input v-model="form.email" :label="$t('users.email') + ' *'" type="email" outlined dense :rules="[val => !!val || $t('messages.required')]" />
            <q-select v-model="form.role" :options="roleOptions" emit-value map-options :label="$t('users.role') + ' *'" outlined dense :rules="[val => !!val || $t('messages.required')]" />
            
            <q-input
              v-model="form.password"
              :label="$t('users.password')"
              type="password"
              outlined
              dense
              :rules="isEditing ? [] : [val => !!val || $t('messages.required'), val => val.length >= 8 || $t('users.minChars')]"
              :hint="isEditing ? $t('users.leaveBlankToKeep') : ''"
            />
            
            <div class="row justify-end q-mt-md">
              <q-btn :label="$t('common.cancel')" flat v-close-popup />
              <q-btn type="submit" :label="isEditing ? $t('products.update') : $t('users.create')" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const users = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const editingId = ref(null);

const form = ref({
  name: '',
  email: '',
  role: 'user',
  password: '',
});

const isEditing = computed(() => !!editingId.value);

const roleOptions = computed(() => [
  { label: t('users.admin'), value: 'admin' },
  { label: t('users.user'), value: 'user' },
]);

const columns = computed(() => [
  { name: 'name', label: t('users.name'), field: 'name', align: 'left', sortable: true },
  { name: 'email', label: t('users.email'), field: 'email', align: 'left', sortable: true },
  { name: 'role', label: t('users.role'), field: 'role', align: 'center', sortable: true },
  { name: 'created_at', label: t('users.createdAt'), field: row => new Date(row.created_at).toLocaleDateString(), align: 'left', sortable: true },
  { name: 'actions', label: t('common.actions'), align: 'center' },
]);

const loadUsers = async () => {
  loading.value = true;
  try {
    const response = await api.get('/users');
    users.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.error') });
  } finally {
    loading.value = false;
  }
};

const openDialog = (user = null) => {
  if (user) {
    editingId.value = user.id;
    form.value = {
      name: user.name,
      email: user.email,
      role: user.role,
      password: '',
    };
  } else {
    editingId.value = null;
    form.value = {
      name: '',
      email: '',
      role: 'user',
      password: '',
    };
  }
  showDialog.value = true;
};

const saveUser = async () => {
  saving.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/users/${editingId.value}`, form.value);
      $q.notify({ type: 'positive', message: t('messages.updatedSuccessfully') });
    } else {
      await api.post('/users', form.value);
      $q.notify({ type: 'positive', message: t('messages.createdSuccessfully') });
    }
    showDialog.value = false;
    loadUsers();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || t('messages.error') });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (user) => {
  $q.dialog({
    title: t('common.confirm'),
    message: t('messages.confirmDelete') + ` "${user.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/users/${user.id}`);
      $q.notify({ type: 'positive', message: t('messages.deletedSuccessfully') });
      loadUsers();
    } catch (error) {
      $q.notify({ type: 'negative', message: error.response?.data?.message || t('messages.error') });
    }
  });
};

onMounted(() => {
  loadUsers();
});
</script>
