<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Clients (العملاء)</div>
      <div class="row q-gutter-sm">
        <q-input
          v-model="searchText"
          outlined
          dense
          placeholder="Search clients..."
          style="min-width: 250px"
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-btn color="primary" icon="add" label="New Client" @click="openDialog()" />
      </div>
    </div>

    <q-table
      :rows="filteredClients"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
    >
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center q-gutter-xs">
            <q-btn flat dense icon="edit" color="primary" @click="openDialog(props.row)">
              <q-tooltip>Edit Client</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>Delete Client</q-tooltip>
            </q-btn>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Client Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px; max-width: 95vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? 'Edit Client (تعديل العميل)' : 'New Client (عميل جديد)' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveClient" class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="Name (الاسم) *"
              outlined
              dense
              :rules="[val => !!val || 'Name is required']"
            />

            <q-input
              v-model="form.phone"
              label="Phone (الهاتف)"
              outlined
              dense
            />

            <q-input
              v-model="form.address"
              label="Address (العنوان)"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <q-input
              v-model="form.notes"
              label="Notes (ملاحظات)"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog">
      <q-card>
        <q-card-section class="row items-center">
          <q-icon name="warning" color="negative" size="md" class="q-mr-sm" />
          <span class="text-subtitle1">Are you sure you want to delete this client?</span>
        </q-card-section>
        <q-card-section class="text-grey-7">
          {{ clientToDelete?.name }}
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Delete" color="negative" @click="deleteClient" :loading="deleting" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import api from '../api';

const $q = useQuasar();
const clients = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const showDialog = ref(false);
const showDeleteDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const clientToDelete = ref(null);
const searchText = ref('');

const form = ref({
  name: '',
  phone: '',
  address: '',
  notes: '',
});

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'name', label: 'Name (الاسم)', field: 'name', align: 'left', sortable: true },
  { name: 'phone', label: 'Phone (الهاتف)', field: 'phone', align: 'left', sortable: true },
  { name: 'address', label: 'Address (العنوان)', field: 'address', align: 'left', sortable: true },
  { name: 'notes', label: 'Notes (ملاحظات)', field: 'notes', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

const filteredClients = computed(() => {
  if (!searchText.value) return clients.value;
  const search = searchText.value.toLowerCase();
  return clients.value.filter(
    (c) =>
      c.name?.toLowerCase().includes(search) ||
      c.phone?.toLowerCase().includes(search) ||
      c.address?.toLowerCase().includes(search)
  );
});

const loadClients = async () => {
  loading.value = true;
  try {
    const response = await api.get('/clients');
    clients.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load clients' });
  } finally {
    loading.value = false;
  }
};

const openDialog = (client = null) => {
  if (client) {
    isEditing.value = true;
    editingId.value = client.id;
    form.value = {
      name: client.name,
      phone: client.phone || '',
      address: client.address || '',
      notes: client.notes || '',
    };
  } else {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
      name: '',
      phone: '',
      address: '',
      notes: '',
    };
  }
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  form.value = {
    name: '',
    phone: '',
    address: '',
    notes: '',
  };
  isEditing.value = false;
  editingId.value = null;
};

const saveClient = async () => {
  saving.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/clients/${editingId.value}`, form.value);
      $q.notify({ type: 'positive', message: 'Client updated successfully' });
    } else {
      await api.post('/clients', form.value);
      $q.notify({ type: 'positive', message: 'Client created successfully' });
    }
    closeDialog();
    loadClients();
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to save client';
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (client) => {
  clientToDelete.value = client;
  showDeleteDialog.value = true;
};

const deleteClient = async () => {
  if (!clientToDelete.value) return;
  
  deleting.value = true;
  try {
    await api.delete(`/clients/${clientToDelete.value.id}`);
    $q.notify({ type: 'positive', message: 'Client deleted successfully' });
    showDeleteDialog.value = false;
    clientToDelete.value = null;
    loadClients();
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to delete client';
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    deleting.value = false;
  }
};

onMounted(() => {
  loadClients();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>

