<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">User Management</div>
      <q-btn color="primary" icon="add" label="Add User" @click="openDialog()" />
    </div>

    <q-table
      :rows="users"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
    >
      <template v-slot:body-cell-role="props">
        <q-td :props="props">
          <q-badge :color="props.row.role === 'admin' ? 'negative' : 'primary'">
            {{ props.row.role.toUpperCase() }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense round icon="edit" color="primary" @click="openDialog(props.row)" />
          <q-btn flat dense round icon="delete" color="negative" @click="confirmDelete(props.row)" />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? 'Edit User' : 'Add New User' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveUser" class="q-gutter-md">
            <q-input v-model="form.name" label="Name *" outlined dense :rules="[val => !!val || 'Required']" />
            <q-input v-model="form.email" label="Email *" type="email" outlined dense :rules="[val => !!val || 'Required']" />
            <q-select v-model="form.role" :options="['admin', 'user']" label="Role *" outlined dense :rules="[val => !!val || 'Required']" />
            
            <q-input
              v-model="form.password"
              label="Password"
              type="password"
              outlined
              dense
              :rules="isEditing ? [] : [val => !!val || 'Required', val => val.length >= 8 || 'Min 8 chars']"
              :hint="isEditing ? 'Leave blank to keep current password' : ''"
            />
            
            <div class="row justify-end q-mt-md">
              <q-btn label="Cancel" flat v-close-popup />
              <q-btn type="submit" :label="isEditing ? 'Update' : 'Create'" color="primary" :loading="saving" />
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
import api from '../api';

const $q = useQuasar();
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

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'email', label: 'Email', field: 'email', align: 'left', sortable: true },
  { name: 'role', label: 'Role', field: 'role', align: 'center', sortable: true },
  { name: 'created_at', label: 'Created At', field: row => new Date(row.created_at).toLocaleDateString(), align: 'left', sortable: true },
  { name: 'actions', label: 'Actions', align: 'center' },
];

const loadUsers = async () => {
  loading.value = true;
  try {
    const response = await api.get('/users');
    users.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load users' });
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
      $q.notify({ type: 'positive', message: 'User updated successfully' });
    } else {
      await api.post('/users', form.value);
      $q.notify({ type: 'positive', message: 'User created successfully' });
    }
    showDialog.value = false;
    loadUsers();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || 'Failed to save user' });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (user) => {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete user "${user.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/users/${user.id}`);
      $q.notify({ type: 'positive', message: 'User deleted successfully' });
      loadUsers();
    } catch (error) {
      $q.notify({ type: 'negative', message: error.response?.data?.message || 'Failed to delete user' });
    }
  });
};

onMounted(() => {
  loadUsers();
});
</script>
