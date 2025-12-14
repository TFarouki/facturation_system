<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('distributors.title') }}</div>
      <q-btn color="primary" icon="add" :label="$t('distributors.newDistributor')" @click="() => openDialog()" />
    </div>

    <!-- Search Input -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        :placeholder="$t('common.search') + '...'"
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
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
      :rows-per-page-options="[10, 25, 50]"
    >
      <template v-slot:body-cell-vehicle_plate="props">
        <q-td :props="props" class="vehicle-plate-cell">
          <span class="vehicle-plate-text" dir="auto">{{ props.value }}</span>
        </q-td>
      </template>
      <template v-slot:body-cell-balance="props">
        <q-td :props="props" class="text-right">
            <q-badge :color="props.value > 0 ? 'negative' : 'positive'" class="text-weight-bold">
                {{ parseFloat(props.value || 0).toFixed(2) }} DH
            </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
           <q-btn flat dense icon="payments" color="primary" @click="() => openSettlementDialog(props.row)">
            <q-tooltip>{{ $t('distributors.settlements') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="edit" color="positive" @click="() => openDialog(props.row)">
            <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="() => deleteDistributor(props.row)">
            <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? $t('distributors.editDistributor') : $t('distributors.newDistributor') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveDistributor">
            <div class="row q-col-gutter-md">
              <div class="col-12">
                <q-input v-model="form.name" :label="$t('distributors.name') + ' *'" outlined dense :rules="[val => !!val || $t('messages.required')]" />
              </div>
              <div class="col-6">
                <q-input v-model="form.phone" :label="$t('distributors.phone')" outlined dense />
              </div>
              <div class="col-6">
                <q-input 
                  v-model="form.vehicle_plate" 
                  :label="$t('distributors.vehiclePlate')" 
                  outlined 
                  dense 
                  dir="auto"
                  class="vehicle-plate-input"
                />
              </div>
              <div class="col-6">
                <q-input v-model="form.vehicle_type" :label="$t('distributors.vehicleType')" outlined dense />
              </div>
              <div class="col-12">
                <q-input v-model="form.notes" :label="$t('common.notes')" outlined dense type="textarea" rows="2" />
              </div>
            </div>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Settlement/Liabilities Dialog -->
    <q-dialog v-model="showSettlementDialog" full-width>
      <q-card>
        <q-card-section class="bg-primary text-white">
          <div class="row items-center justify-between">
            <div class="text-h6">{{ $t('distributors.settlementsTitle', { name: selectedDistributorForSettlement?.name }) }}</div>
            <q-btn flat round dense icon="close" v-close-popup />
          </div>
        </q-card-section>

        <q-card-section class="q-pa-md">
           <!-- Summary Cards -->
           <div class="row q-mb-lg justify-center q-gutter-md">
             <div class="col-12 col-md-3">
               <q-card flat bordered class="bg-blue-1 text-center">
                 <q-card-section>
                   <div class="text-subtitle1 text-grey-8">{{ $t('distributors.stockValue') }}</div>
                   <div class="text-h4 text-primary text-weight-bold">
                       {{ parseFloat(unpaidStats.stock_value || 0).toFixed(2) }} DH
                   </div>
                 </q-card-section>
               </q-card>
             </div>
             <div class="col-12 col-md-3">
               <q-card flat bordered class="bg-orange-1 text-center">
                 <q-card-section>
                   <div class="text-subtitle1 text-grey-8">{{ $t('distributors.unpaidSalesTotal') }}</div>
                   <div class="text-h4 text-warning text-weight-bold">
                       {{ parseFloat(unpaidStats.total_unpaid_sales || 0).toFixed(2) }} DH
                   </div>
                 </q-card-section>
               </q-card>
             </div>
             <div class="col-12 col-md-3">
               <q-card flat bordered class="bg-red-1 text-center">
                 <q-card-section>
                   <div class="text-subtitle1 text-grey-8">{{ $t('distributors.totalLiabilities') }}</div>
                   <div class="text-h4 text-negative text-weight-bold">
                       {{ parseFloat(unpaidStats.total_liabilities || 0).toFixed(2) }} DH
                   </div>
                 </q-card-section>
               </q-card>
             </div>
           </div>

           <!-- Unpaid Sales Table -->
           <q-table
             :title="$t('distributors.liabilitiesTableTitle')"
             :rows="unpaidStats.sales"
             :columns="unpaidColumns"
             row-key="id"
             :loading="loadingUnpaid"
             :no-data-label="$t('common.noData')"
             :loading-label="$t('common.loading')"
             flat
             bordered
             :pagination="{ rowsPerPage: 10 }"
           >
             <template v-slot:body-cell-client="props">
               <q-td :props="props">
                 {{ props.row.client?.name || 'Unknown' }}
               </q-td>
             </template>
             <template v-slot:body-cell-actions="props">
                <q-td :props="props">
                  <q-btn flat round dense color="primary" icon="visibility" @click="viewReceiptDetails(props.row)">
                    <q-tooltip>{{ $t('distributors.viewReceiptDetails') }}</q-tooltip>
                  </q-btn>
                </q-td>
             </template>
           </q-table>

        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Receipt Details Dialog -->
    <q-dialog v-model="showReceiptDetailsDialog">
        <q-card style="min-width: 600px">
            <q-card-section class="bg-secondary text-white">
                <div class="text-h6">{{ $t('distributors.receiptDetailsTitle', { number: selectedReceipt?.receipt_number }) }}</div>
            </q-card-section>
            <q-card-section>
                <div class="text-subtitle2 q-mb-sm">{{ $t('sales.client') }}: {{ selectedReceipt?.client?.name }}</div>
                <div class="text-subtitle2 q-mb-md">{{ $t('common.date') }}: {{ selectedReceipt?.receipt_date }}</div>
                
                <q-markup-table flat bordered dense>
                    <thead>
                        <tr>
                            <th class="text-left">{{ $t('products.product') }}</th>
                            <th class="text-right">{{ $t('sales.quantity') }}</th>
                            <th class="text-right">{{ $t('products.price') }}</th>
                            <th class="text-right">{{ $t('common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in selectedReceipt?.details" :key="item.id">
                            <td>{{ item.product?.name }}</td>
                            <td class="text-right">{{ item.quantity }}</td>
                            <td class="text-right">{{ parseFloat(item.unit_price).toFixed(2) }}</td>
                            <td class="text-right">{{ parseFloat(item.subtotal).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </q-markup-table>
                 <div class="row justify-end q-mt-md text-h6">
                    {{ $t('common.total') }}: {{ parseFloat(selectedReceipt?.total_amount).toFixed(2) }} DH
                </div>
            </q-card-section>
            <q-card-actions align="right">
                <q-btn flat :label="$t('common.close')" color="primary" v-close-popup />
            </q-card-actions>
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
const distributors = ref([]);
const searchText = ref('');
const loading = ref(false);
const loadingUnpaid = ref(false);
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

const columns = computed(() => [
  { name: 'name', label: t('distributors.name'), field: 'name', align: 'left', sortable: true },
  { name: 'phone', label: t('distributors.phone'), field: 'phone', align: 'left' },
  { name: 'vehicle_plate', label: t('distributors.vehiclePlate'), field: 'vehicle_plate', align: 'left', style: 'direction: ltr; text-align: left;' },
  { name: 'vehicle_type', label: t('distributors.vehicleType'), field: 'vehicle_type', align: 'left' },
  { name: 'balance', label: t('distributors.balance'), field: 'balance', align: 'right', format: val => `${parseFloat(val || 0).toFixed(2)} DH`, sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center', sortable: false },
]);

const showSettlementDialog = ref(false);
const showReceiptDetailsDialog = ref(false);
const selectedDistributorForSettlement = ref(null);
const selectedReceipt = ref(null);
const unpaidStats = ref({ 
  stock_value: 0, 
  total_unpaid_sales: 0, 
  total_liabilities: 0, 
  sales: [] 
});

const unpaidColumns = computed(() => [
    { name: 'receipt_number', label: t('distributors.receiptNumber'), field: 'receipt_number', align: 'left' },
    { name: 'client', label: t('sales.client'), field: 'client', align: 'left' },
    { name: 'sale_date', label: t('common.date'), field: 'receipt_date', align: 'left' },
    { name: 'total_amount', label: t('distributors.receiptTotal'), field: 'total_amount', align: 'right', format: val => parseFloat(val).toFixed(2) },
    { name: 'paid_amount', label: t('distributors.cashCollected'), field: 'paid_amount', align: 'right', format: val => parseFloat(val).toFixed(2) },
    { name: 'remaining_amount', label: t('distributors.owedToCompany'), field: 'remaining_amount', align: 'right', format: val => parseFloat(val).toFixed(2), classes: 'text-negative text-weight-bold' },
    { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
]);

const openSettlementDialog = async (distributor) => {
    selectedDistributorForSettlement.value = distributor;
    showSettlementDialog.value = true;
    await fetchLiabilities(distributor.id);
};

const fetchLiabilities = async (distributorId) => {
    loadingUnpaid.value = true;
    try {
        const response = await api.get(`/distributors/${distributorId}/unpaid-sales`);
        unpaidStats.value = response.data;
    } catch (error) {
        console.error('Error fetching liabilities:', error);
        $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
    } finally {
        loadingUnpaid.value = false;
    }
};

const viewReceiptDetails = (receipt) => {
    selectedReceipt.value = receipt;
    showReceiptDetailsDialog.value = true;
};

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
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
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
    $q.notify({ type: 'positive', message: t('messages.savedSuccessfully') });
    closeDialog();
    loadDistributors();
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.error') });
  } finally {
    saving.value = false;
  }
};

const deleteDistributor = async (distributor) => {
  $q.dialog({
    title: t('distributors.confirmDeleteTitle'),
    message: t('distributors.confirmDeleteMessage', { name: distributor.name }),
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/distributors/${distributor.id}`);
      $q.notify({ type: 'positive', message: t('distributors.deletedSuccessfully') });
      loadDistributors();
    } catch (error) {
      if (error.response?.status === 409) {
          $q.notify({ type: 'warning', message: error.response?.data?.message || t('distributors.cannotDelete') });
      } else {
          $q.notify({ type: 'negative', message: t('messages.error') });
      }
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
      $q.notify({ type: 'warning', message: t('common.noData') });
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

    $q.notify({ type: 'positive', message: t('messages.exportedSuccessfully') });
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToExport') });
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
