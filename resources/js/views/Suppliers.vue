<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Suppliers (الموردين)</div>
      <q-btn color="primary" icon="add" label="Add Supplier" @click="() => openDialog()" />
    </div>

    <!-- Search Input -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search suppliers..."
        style="max-width: 400px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append v-if="searchText">
          <q-icon name="clear" class="cursor-pointer" @click="searchText = ''" />
        </template>
      </q-input>

      <q-space />

      <!-- Export to Excel -->
      <q-btn flat round dense icon="download" color="positive" @click="exportToExcel">
        <q-tooltip>Export to Excel</q-tooltip>
      </q-btn>
    </div>

    <q-table
      :rows="filteredSuppliers"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :rows-per-page-options="[10, 25, 50]"
    >
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="positive" @click="() => openDialog(props.row)">
            <q-tooltip>Edit</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="() => deleteSupplier(props.row)">
            <q-tooltip>Delete</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? 'Edit Supplier' : 'Add Supplier' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveSupplier">
            <div class="row q-col-gutter-md">
              <div class="col-12">
                <q-input v-model="form.name" label="Supplier Name *" outlined dense :rules="[val => !!val || 'Required']" />
              </div>
              <div class="col-6">
                <q-input v-model="form.contact_person" label="Contact Person" outlined dense />
              </div>
              <div class="col-6">
                <q-input v-model="form.phone" label="Phone" outlined dense />
              </div>
              <div class="col-6">
                <q-input v-model="form.email" label="Email" type="email" outlined dense />
              </div>
              <div class="col-6">
                <q-input v-model="form.tax_id" label="Tax ID" outlined dense />
              </div>
              <div class="col-12">
                <q-input v-model="form.address" label="Address" outlined dense type="textarea" rows="2" />
              </div>
              <div class="col-12">
                <q-input v-model="form.notes" label="Notes" outlined dense type="textarea" rows="2" />
              </div>
            </div>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
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
const suppliers = ref([]);
const searchText = ref('');
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const editMode = ref(false);
const selectedSupplier = ref(null);

const form = ref({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  address: '',
  tax_id: '',
  notes: '',
});

const columns = [
  { name: 'name', label: 'Supplier Name', field: 'name', align: 'left', sortable: true },
  { name: 'contact_person', label: 'Contact Person', field: 'contact_person', align: 'left', sortable: true },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'email', label: 'Email', field: 'email', align: 'left' },
  { name: 'tax_id', label: 'Tax ID', field: 'tax_id', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center', sortable: false },
];

// Computed property for filtered suppliers
const filteredSuppliers = computed(() => {
  if (!searchText.value) {
    return suppliers.value;
  }
  
  const search = searchText.value.toLowerCase();
  return suppliers.value.filter(supplier => 
    supplier.name?.toLowerCase().includes(search) ||
    supplier.contact_person?.toLowerCase().includes(search) ||
    supplier.phone?.toLowerCase().includes(search) ||
    supplier.email?.toLowerCase().includes(search)
  );
});

const loadSuppliers = async () => {
  loading.value = true;
  try {
    const response = await api.get('/suppliers');
    suppliers.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load suppliers' });
  } finally {
    loading.value = false;
  }
};

const openDialog = (supplier = null) => {
  if (supplier) {
    editMode.value = true;
    selectedSupplier.value = supplier;
    form.value = { 
      id: supplier.id,
      name: supplier.name,
      contact_person: supplier.contact_person || '',
      phone: supplier.phone || '',
      email: supplier.email || '',
      address: supplier.address || '',
      tax_id: supplier.tax_id || '',
      notes: supplier.notes || '',
    };
  } else {
    editMode.value = false;
    selectedSupplier.value = null;
    form.value = {
      name: '',
      contact_person: '',
      phone: '',
      email: '',
      address: '',
      tax_id: '',
      notes: '',
    };
  }
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editMode.value = false;
  selectedSupplier.value = null;
};

const saveSupplier = async () => {
  saving.value = true;
  try {
    if (editMode.value) {
      const supplierId = form.value.id || selectedSupplier.value?.id;
      
      if (!supplierId) {
        throw new Error('Supplier ID is missing');
      }
      
      await api.put(`/suppliers/${supplierId}`, form.value);
    } else {
      await api.post('/suppliers', form.value);
    }
    $q.notify({ type: 'positive', message: 'Supplier saved successfully' });
    closeDialog();
    loadSuppliers();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save supplier' });
  } finally {
    saving.value = false;
  }
};

const deleteSupplier = async (supplier) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete supplier "${supplier.name}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/suppliers/${supplier.id}`);
      $q.notify({ type: 'positive', message: 'Supplier deleted' });
      loadSuppliers();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete supplier' });
    }
  });
};

const exportToExcel = () => {
  try {
    // Prepare data for export
    const exportData = filteredSuppliers.value.map(supplier => ({
      'Supplier Name': supplier.name,
      'Contact Person': supplier.contact_person || '-',
      'Phone': supplier.phone || '-',
      'Email': supplier.email || '-',
      'Tax ID': supplier.tax_id || '-',
      'Address': supplier.address || '-',
      'Notes': supplier.notes || '-',
    }));

    // Convert to CSV
    const headers = Object.keys(exportData[0]);
    const csvContent = [
      headers.join(','),
      ...exportData.map(row => headers.map(header => {
        const value = row[header];
        // Escape commas and quotes
        return typeof value === 'string' && (value.includes(',') || value.includes('"'))
          ? `"${value.replace(/"/g, '""')}"` 
          : value;
      }).join(','))
    ].join('\n');

    // Create blob and download
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `suppliers_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    $q.notify({ type: 'positive', message: 'Suppliers exported successfully' });
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to export suppliers' });
  }
};

onMounted(() => {
  loadSuppliers();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

.rounded-table :deep(thead tr th) {
  background-color: #1976d2;
  color: white;
  font-weight: bold;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.rounded-table :deep(thead tr:first-child th) {
  position: sticky;
  top: 0;
  z-index: 1;
}
</style>
