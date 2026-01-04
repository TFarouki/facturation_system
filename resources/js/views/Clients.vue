<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('clients.title') }}</div>
      <div class="row q-gutter-sm">
        <q-input
          v-model="searchText"
          outlined
          dense
          :placeholder="$t('common.search') + '...'"
          style="min-width: 250px"
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-btn color="primary" icon="add" :label="$t('clients.newClient')" @click="openDialog()" />
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
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
    >
      <template v-slot:body-cell-balance="props">
        <q-td :props="props" class="text-right">
          <q-badge :color="props.value > 0 ? 'negative' : 'positive'" class="text-weight-bold">
            {{ parseFloat(props.value || 0).toFixed(2) }} DH
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center q-gutter-xs">
            <q-btn flat dense icon="bar_chart" color="info" @click="openStatsDialog(props.row)">
              <q-tooltip>{{ $t('clients.statistics') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="receipt_long" color="warning" @click="openUnpaidSalesDialog(props.row)">
              <q-tooltip>{{ $t('clients.unpaidSales') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="edit" color="primary" @click="openDialog(props.row)">
              <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
            </q-btn>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Client Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px; max-width: 95vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? $t('clients.editClient') : $t('clients.newClient') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveClient" class="q-gutter-md">
            <q-input
              v-model="form.name"
              :label="$t('clients.name') + ' *'"
              outlined
              dense
              :rules="[val => !!val || $t('messages.required')]"
            />

            <q-input
              v-model="form.phone"
              :label="$t('clients.phone')"
              outlined
              dense
            />

            <q-input
              v-model="form.address"
              :label="$t('clients.address')"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <q-input
              v-model="form.notes"
              :label="$t('common.notes')"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
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
          <span class="text-subtitle1">{{ $t('messages.confirmDelete') }}</span>
        </q-card-section>
        <q-card-section class="text-grey-7">
          {{ clientToDelete?.name }}
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="$t('common.cancel')" v-close-popup />
          <q-btn flat :label="$t('common.delete')" color="negative" @click="deleteClient" :loading="deleting" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Unpaid Sales Dialog -->
    <q-dialog v-model="showUnpaidDialog">
      <q-card style="width: 800px; max-width: 90vw;">
        <q-card-section class="bg-warning text-white">
          <div class="row items-center justify-between">
            <div class="text-h6">{{ $t('clients.unpaidSalesTitle', { name: selectedClient?.name }) }}</div>
            <q-btn flat round dense icon="close" v-close-popup />
          </div>
        </q-card-section>

        <q-card-section>
          <q-table
            :rows="unpaidSales"
            :columns="unpaidColumns"
            row-key="id"
            :loading="loadingUnpaid"
            flat
            bordered
            :rows-per-page-options="[0]"
            hide-pagination
            :no-data-label="$t('common.noData')"
          >
            <template v-slot:body-cell-distributor="props">
              <q-td :props="props">
                {{ props.row.distributor?.name || '-' }}
              </q-td>
            </template>
            <template v-slot:body-cell-total_amount="props">
              <q-td :props="props" class="text-right">
                {{ parseFloat(props.value).toFixed(2) }} DH
              </q-td>
            </template>
            <template v-slot:body-cell-remaining_amount="props">
              <q-td :props="props" class="text-right text-negative text-weight-bold">
                {{ parseFloat(props.value).toFixed(2) }} DH
              </q-td>
            </template>
          </q-table>

          <div class="row justify-end q-mt-md">
            <div class="text-h6 text-negative">
              {{ $t('common.total') }}: {{ totalUnpaidAmount.toFixed(2) }} DH
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Statistics Dialog -->
    <q-dialog v-model="showStatsDialog">
      <q-card style="width: 900px; max-width: 95vw;">
        <q-card-section class="bg-info text-white row items-center">
          <div class="text-h6">{{ $t('clients.statisticsTitle', { name: statsData?.client_name }) }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pa-md">
          <div v-if="loadingStats" class="flex flex-center q-pa-xl">
            <q-spinner color="primary" size="3em" />
          </div>
          <div v-else>
            <!-- KPI Cards -->
            <div class="row q-col-gutter-md q-mb-lg">
              <div class="col-12 col-sm-6 col-md-3">
                <q-card flat bordered class="bg-blue-1">
                  <q-card-section class="q-pa-sm text-center">
                    <div class="text-caption text-grey-7">{{ $t('clients.totalPurchases') }}</div>
                    <div class="text-h6 text-primary">{{ parseFloat(statsData.total_revenue).toFixed(2) }} DH</div>
                    <div class="text-caption text-grey-6">{{ statsData.invoice_count }} {{ $t('clients.totalInvoices') }}</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <q-card flat bordered class="bg-green-1">
                  <q-card-section class="q-pa-sm text-center">
                    <div class="text-caption text-grey-7">{{ $t('payments.totalPaid') }}</div>
                    <div class="text-h6 text-positive">{{ parseFloat(statsData.total_paid).toFixed(2) }} DH</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <q-card flat bordered class="bg-red-1">
                  <q-card-section class="q-pa-sm text-center">
                    <div class="text-caption text-grey-7">{{ $t('payments.remaining') }}</div>
                    <div class="text-h6 text-negative">{{ parseFloat(statsData.total_remaining).toFixed(2) }} DH</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <q-card flat bordered class="bg-purple-1">
                  <q-card-section class="q-pa-sm text-center">
                    <div class="text-caption text-grey-7">{{ $t('reports.profit') }}</div>
                    <div class="text-h6 text-purple-8">{{ parseFloat(statsData.total_profit).toFixed(2) }} DH</div>
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <div class="row q-col-gutter-md">
              <!-- Top products -->
              <div class="col-12 col-md-6">
                <div class="text-subtitle1 q-mb-sm text-weight-bold">{{ $t('clients.topProducts') }}</div>
                <q-markup-table flat bordered dense>
                  <thead>
                    <tr>
                      <th class="text-left">{{ $t('products.product') }}</th>
                      <th class="text-right">{{ $t('sales.quantity') }}</th>
                      <th class="text-right">{{ $t('common.total') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in statsData.top_products" :key="item.name">
                      <td>{{ item.name }}</td>
                      <td class="text-right">{{ item.quantity.toFixed(2) }}</td>
                      <td class="text-right">{{ item.total_spent.toFixed(2) }} DH</td>
                    </tr>
                    <tr v-if="statsData.top_products.length === 0">
                      <td colspan="3" class="text-center text-grey-5 q-pa-md">{{ $t('common.noData') }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </div>

              <!-- Monthly trend -->
              <div class="col-12 col-md-6">
                <div class="text-subtitle1 q-mb-sm text-weight-bold">{{ $t('clients.monthlyTrend') }}</div>
                <q-list bordered separator dense>
                  <q-item v-for="point in statsData.monthly_trend" :key="point.month">
                    <q-item-section>
                      <q-item-label>{{ point.month }}</q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <div class="text-weight-bold text-primary">{{ point.total.toFixed(2) }} DH</div>
                    </q-item-section>
                    <q-item-section side v-if="point.total > 0" class="q-pl-sm">
                        <div style="height: 8px; background: #1976D2; border-radius: 4px;" :style="{ width: (point.total / Math.max(...statsData.monthly_trend.map(p => p.total)) * 100) + 'px' }"></div>
                    </q-item-section>
                  </q-item>
                   <q-item v-if="statsData.monthly_trend.length === 0">
                      <q-item-section class="text-center text-grey-5 q-pa-md">{{ $t('common.noData') }}</q-item-section>
                    </q-item>
                </q-list>
              </div>
            </div>
          </div>
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
const clients = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const showDialog = ref(false);
const showDeleteDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const clientToDelete = ref(null);
const selectedClient = ref(null);
const searchText = ref('');
const showUnpaidDialog = ref(false);
const unpaidSales = ref([]);
const loadingUnpaid = ref(false);
const showStatsDialog = ref(false);
const loadingStats = ref(false);
const statsData = ref({
    client_name: '',
    total_revenue: 0,
    total_paid: 0,
    total_remaining: 0,
    total_profit: 0,
    invoice_count: 0,
    top_products: [],
    monthly_trend: []
});

const form = ref({
  name: '',
  phone: '',
  address: '',
  notes: '',
});

const columns = computed(() => [
  { name: 'name', label: t('clients.name'), field: 'name', align: 'left', sortable: true },
  { name: 'phone', label: t('clients.phone'), field: 'phone', align: 'left', sortable: true },
  { name: 'balance', label: t('clients.balance'), field: 'balance', align: 'right', sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
]);

const unpaidColumns = computed(() => [
  { name: 'receipt_number', label: t('purchases.invoiceNumber'), field: 'receipt_number', align: 'left', sortable: true },
  { name: 'distributor', label: t('common.distributor') || 'Distributor', field: 'distributor', align: 'left' },
  { name: 'receipt_date', label: t('common.date'), field: 'receipt_date', align: 'left', format: val => formatDate(val), sortable: true },
  { name: 'total_amount', label: t('common.total'), field: 'total_amount', align: 'right' },
  { name: 'remaining_amount', label: t('payments.remaining'), field: 'remaining_amount', align: 'right' },
]);

const totalUnpaidAmount = computed(() => {
  return unpaidSales.value.reduce((sum, item) => sum + parseFloat(item.remaining_amount || 0), 0);
});

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
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
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
      $q.notify({ type: 'positive', message: t('messages.updatedSuccessfully') });
    } else {
      await api.post('/clients', form.value);
      $q.notify({ type: 'positive', message: t('messages.createdSuccessfully') });
    }
    closeDialog();
    loadClients();
  } catch (error) {
    const errorMessage = error.response?.data?.message || t('messages.failedToSave');
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
    $q.notify({ type: 'positive', message: t('messages.deletedSuccessfully') });
    showDeleteDialog.value = false;
    clientToDelete.value = null;
    loadClients();
  } catch (error) {
    if (error.response?.status === 409) {
      $q.notify({ type: 'warning', message: t('messages.cannotDeleteClientWithSales') });
    } else {
      const errorMessage = error.response?.data?.message || t('messages.failedToDelete');
      $q.notify({ type: 'negative', message: errorMessage });
    }
  } finally {
    deleting.value = false;
  }
};

const openUnpaidSalesDialog = async (client) => {
  selectedClient.value = client;
  showUnpaidDialog.value = true;
  loadingUnpaid.value = true;
  unpaidSales.value = [];
  try {
    const response = await api.get(`/clients/${client.id}/unpaid-sales`);
    unpaidSales.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loadingUnpaid.value = false;
  }
};

const openStatsDialog = async (client) => {
    selectedClient.value = client;
    showStatsDialog.value = true;
    loadingStats.value = true;
    try {
        const response = await api.get(`/clients/${client.id}/statistics`);
        statsData.value = response.data;
    } catch (error) {
        $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
    } finally {
        loadingStats.value = false;
    }
};

const formatDate = (date) => {
  if (!date) return '';
  const d = new Date(date);
  if (isNaN(d.getTime())) return date;
  return d.toISOString().split('T')[0];
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

