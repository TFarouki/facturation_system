<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Distributors (الموزعين)</div>
      <q-btn color="primary" icon="add" label="Add Distributor" @click="() => openDialog()" />
    </div>

    <!-- Search Input -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search distributors..."
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
      :rows="filteredDistributors"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :rows-per-page-options="[10, 25, 50]"
    >
      <template v-slot:body-cell-vehicle_plate="props">
        <q-td :props="props" class="vehicle-plate-cell">
          <span class="vehicle-plate-text" dir="auto">{{ props.value }}</span>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="positive" @click="() => openDialog(props.row)">
            <q-tooltip>Edit</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="() => deleteDistributor(props.row)">
            <q-tooltip>Delete</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? 'Edit Distributor' : 'Add Distributor' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveDistributor">
            <div class="row q-col-gutter-md">
              <div class="col-12">
                <q-input v-model="form.name" label="Distributor Name (اسم الموزع) *" outlined dense :rules="[val => !!val || 'Required']" />
              </div>
              <div class="col-6">
                <q-input v-model="form.phone" label="Phone (رقم الهاتف)" outlined dense />
              </div>
              <div class="col-6">
                <q-input 
                  v-model="form.vehicle_plate" 
                  label="Vehicle Plate (رقم اللوحة)" 
                  outlined 
                  dense 
                  dir="auto"
                  class="vehicle-plate-input"
                />
              </div>
              <div class="col-6">
                <q-input v-model="form.vehicle_type" label="Vehicle Type (نوع السيارة)" outlined dense />
              </div>
              <div class="col-12">
                <q-input v-model="form.notes" label="Notes (ملاحظات)" outlined dense type="textarea" rows="2" />
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
const distributors = ref([]);
const searchText = ref('');
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const editMode = ref(false);
const selectedDistributor = ref(null);

const form = ref({
  name: '',
  phone: '',
  vehicle_plate: '',
  vehicle_type: '',
  notes: '',
});

const columns = [
  { name: 'name', label: 'Distributor Name', field: 'name', align: 'left', sortable: true },
  { name: 'phone', label: 'Phone', field: 'phone', align: 'left' },
  { name: 'vehicle_plate', label: 'Vehicle Plate', field: 'vehicle_plate', align: 'left', style: 'direction: ltr; text-align: left;' },
  { name: 'vehicle_type', label: 'Vehicle Type', field: 'vehicle_type', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center', sortable: false },
];

// Computed property for filtered distributors
const filteredDistributors = computed(() => {
  if (!searchText.value) {
    return distributors.value;
  }
  
  const search = searchText.value.toLowerCase();
  return distributors.value.filter(distributor => 
    distributor.name?.toLowerCase().includes(search) ||
    distributor.phone?.toLowerCase().includes(search) ||
    distributor.vehicle_plate?.toLowerCase().includes(search) ||
    distributor.vehicle_type?.toLowerCase().includes(search)
  );
});

const loadDistributors = async () => {
  loading.value = true;
  try {
    const response = await api.get('/distributors');
    distributors.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load distributors' });
  } finally {
    loading.value = false;
  }
};

const openDialog = (distributor = null) => {
  if (distributor) {
    editMode.value = true;
    selectedDistributor.value = distributor;
    form.value = { 
      id: distributor.id,
      name: distributor.name,
      phone: distributor.phone || '',
      vehicle_plate: distributor.vehicle_plate || '',
      vehicle_type: distributor.vehicle_type || '',
      notes: distributor.notes || '',
    };
  } else {
    editMode.value = false;
    selectedDistributor.value = null;
    form.value = {
      name: '',
      phone: '',
      vehicle_plate: '',
      vehicle_type: '',
      notes: '',
    };
  }
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editMode.value = false;
  selectedDistributor.value = null;
};

const saveDistributor = async () => {
  saving.value = true;
  try {
    if (editMode.value) {
      const distributorId = form.value.id || selectedDistributor.value?.id;
      
      if (!distributorId) {
        throw new Error('Distributor ID is missing');
      }
      
      await api.put(`/distributors/${distributorId}`, form.value);
    } else {
      await api.post('/distributors', form.value);
    }
    $q.notify({ type: 'positive', message: 'Distributor saved successfully' });
    closeDialog();
    loadDistributors();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save distributor' });
  } finally {
    saving.value = false;
  }
};

const deleteDistributor = async (distributor) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete distributor "${distributor.name}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/distributors/${distributor.id}`);
      $q.notify({ type: 'positive', message: 'Distributor deleted' });
      loadDistributors();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete distributor' });
    }
  });
};

const exportToExcel = () => {
  try {
    // Prepare data for export
    const exportData = filteredDistributors.value.map(distributor => ({
      'Distributor Name': distributor.name,
      'Phone': distributor.phone || '-',
      'Vehicle Plate': distributor.vehicle_plate || '-',
      'Vehicle Type': distributor.vehicle_type || '-',
      'Notes': distributor.notes || '-',
    }));

    // Convert to CSV
    const headers = Object.keys(exportData[0] || {});
    if (exportData.length === 0) {
      $q.notify({ type: 'warning', message: 'No data to export' });
      return;
    }

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
    link.setAttribute('download', `distributors_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    $q.notify({ type: 'positive', message: 'Distributors exported successfully' });
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to export distributors' });
  }
};

onMounted(() => {
  loadDistributors();
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

.vehicle-plate-input :deep(.q-field__input) {
  direction: ltr;
  text-align: left;
  font-family: 'Courier New', 'Courier', monospace;
  font-weight: 500;
  letter-spacing: 1px;
  unicode-bidi: embed;
}

.vehicle-plate-cell {
  font-family: 'Courier New', 'Courier', monospace;
  font-weight: 500;
  letter-spacing: 1px;
  white-space: nowrap;
}

.vehicle-plate-text {
  direction: ltr;
  text-align: left;
  font-family: 'Courier New', 'Courier', monospace;
  font-weight: 500;
  letter-spacing: 1px;
  unicode-bidi: embed;
}
</style>
