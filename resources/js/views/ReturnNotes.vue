<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('nav.returnNotes') }}</div>
      <q-btn color="info" icon="add" :label="$t('common.add')" @click="openDialog" />
    </div>

    <!-- Filters -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        :placeholder="$t('common.search') + '...'"
        style="max-width: 300px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append v-if="searchText">
          <q-icon name="clear" class="cursor-pointer" @click="searchText = ''" />
        </template>
      </q-input>

      <q-select
        v-model="filterDistributor"
        :options="distributors"
        option-label="name"
        option-value="id"
        outlined
        dense
        :label="$t('sales.distributor')"
        style="max-width: 300px"
        clearable
      />

      <q-space />
    </div>

    <!-- Orders Table -->
    <q-table
      :rows="filteredOrders"
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
      <template v-slot:body-cell-distributor="props">
        <q-td :props="props">
          {{ props.row.distributor?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-order_date="props">
        <q-td :props="props">
          {{ props.value ? new Date(props.value).toLocaleDateString('en-GB') : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="visibility" color="primary" @click="viewDetails(props.row)">
            <q-tooltip>{{ $t('common.view') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="edit" color="positive" @click="editOrder(props.row)">
            <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="deleteOrder(props.row)">
            <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 900px; max-width: 95vw;">
        <q-card-section class="bg-primary text-white">
          <div class="row items-center justify-between">
            <div class="text-h6">
              {{ editMode ? $t('common.edit') : $t('common.add') }} {{ $t('nav.returnNotes') }}
            </div>
            <q-btn flat round dense icon="close" @click="closeDialog" />
          </div>
        </q-card-section>

        <q-card-section class="q-pa-md">
          <q-form @submit="saveOrder">
            <div class="row q-col-gutter-md">
              <div class="col-4">
                <q-select
                  v-model="form.distributor_id"
                  :options="distributors"
                  option-label="name"
                  option-value="id"
                  emit-value
                  map-options
                  :label="$t('sales.distributor') + ' *'"
                  outlined
                  dense
                  :rules="[val => !!val || $t('messages.required')]"
                  @update:model-value="loadCommittedProducts"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.order_number"
                  :label="$t('deliveryNotes.orderNumber') + ' *'"
                  outlined
                  dense
                  :rules="[val => !!val || $t('messages.required')]"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.order_date"
                  :label="$t('common.date') + ' *'"
                  type="date"
                  outlined
                  dense
                  :rules="[val => !!val || $t('messages.required')]"
                />
              </div>
              <div class="col-12">
                <q-input
                  v-model="form.notes"
                  :label="$t('common.notes')"
                  outlined
                  dense
                  type="textarea"
                  rows="2"
                />
              </div>
            </div>

            <!-- Items Section -->
            <div class="q-mt-md">
              <div class="row items-center justify-between q-mb-sm">
                <div class="text-h6">{{ $t('common.items') }}</div>
                <div class="text-caption text-grey-6" v-if="form.distributor_id">
                  {{ $t('deliveryNotes.productsWillLoad') }}
                </div>
                <div class="text-caption text-grey-6" v-else>
                  {{ $t('deliveryNotes.selectDistributorToLoad') }}
                </div>
              </div>

              <q-table
                :rows="form.items"
                :columns="itemColumns"
                row-key="id"
                flat
                bordered
                :no-data-label="$t('common.noData')"
                :rows-per-page-label="$t('common.rowsPerPage')"
              >
                <template v-slot:body-cell-product="props">
                  <q-td :props="props">
                    <div v-if="props.row.product">
                      <div class="text-weight-bold">{{ props.row.product.name }}</div>
                      <div class="text-caption text-grey-6" v-if="props.row.product.category">
                        {{ props.row.product.category.name }}
                      </div>
                    </div>
                    <div v-else class="text-grey-6">N/A</div>
                  </q-td>
                </template>
                <template v-slot:body-cell-actions="props">
                  <q-td :props="props">
                    <q-btn flat dense icon="delete" color="negative" @click="removeItem(props.rowIndex)" />
                  </q-td>
                </template>
                <template v-slot:body-cell-quantity="props">
                  <q-td :props="props">
                    <div class="row items-center no-wrap">
                      <q-input
                        v-model.number="props.row.quantity"
                        type="number"
                        outlined
                        dense
                        :min="0"
                        :max="props.row.committed_quantity || 0"
                        step="0.01"
                        style="width: 120px"
                      />
                      <span class="q-ml-sm text-grey-7 text-weight-medium">/{{ formatQuantity(props.row.committed_quantity || 0) }}</span>
                    </div>
                  </q-td>
                </template>
              </q-table>
            </div>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- View Details Dialog - Return Note PDF View -->
    <q-dialog v-model="showDetailsDialog">
      <q-card class="return-note-card" style="overflow-y: auto;">
        <q-card-section class="invoice-header q-pa-none">
          <div class="row items-center justify-between q-pa-md">
            <q-btn flat dense icon="close" @click="showDetailsDialog = false" />
            <div class="text-h6">{{ $t('returnNotes.viewReturnNote') }}</div>
            <q-btn 
              flat 
              dense 
              icon="picture_as_pdf" 
              :label="$t('returnNotes.saveAsPdf')" 
              color="primary"
              @click="saveAsPDF"
              :loading="pdfLoading"
            />
          </div>
        </q-card-section>

        <q-card-section v-if="selectedOrder" class="invoice-content" id="return-note-content">
          <!-- Invoice Header -->
          <div class="invoice-header-section">
            <div class="row items-start justify-between">
              <!-- Left: Date and Order Number -->
              <div class="col-3">
                <div class="invoice-label q-mb-xs">{{ $t('returnNotes.date') }}</div>
                <div class="invoice-value">{{ formatDate(selectedOrder.order_date) }}</div>
                <div class="invoice-label q-mt-md q-mb-xs">{{ $t('returnNotes.orderNo') }}</div>
                <div class="invoice-value">{{ selectedOrder.order_number }}</div>
              </div>

              <!-- Center: Company Name -->
              <div class="col-5 text-center">
                <div class="text-h4 text-weight-bold q-mb-md">{{ $t('returnNotes.returnNoteTitle') }}</div>
                <div class="company-name text-weight-bold">{{ companySettings.company_name || $t('returnNotes.yourCompany') }}</div>
              </div>

              <!-- Right: Logo -->
              <div class="col-3 text-right">
                <div v-if="companySettings.company_logo" class="company-logo q-mb-md">
                  <img :src="getLogoUrl(companySettings.company_logo)" alt="Company Logo" style="max-width: 100px; max-height: 100px;" />
                </div>
                <div v-else class="logo-placeholder q-mb-md">
                  <q-icon name="business" size="80px" color="grey-4" />
                  <div class="text-caption text-grey-6">{{ $t('sales.logoName') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Company and Distributor Info -->
          <div class="invoice-info-section">
            <div class="row q-col-gutter-md">
              <!-- Company Info -->
              <div class="col-6">
                <div class="invoice-label q-mb-xs">{{ $t('returnNotes.yourCompany').toUpperCase() }}</div>
                <div class="invoice-info">
                  <div v-if="companySettings.address">{{ companySettings.address }}</div>
                  <div v-if="companySettings.phone">{{ $t('returnNotes.phone') }}: {{ companySettings.phone }}</div>
                  <div v-if="companySettings.email">{{ $t('settings.email') }}: {{ companySettings.email }}</div>
                </div>
              </div>

              <!-- Distributor Info -->
              <div class="col-6 text-right">
                <div class="invoice-label q-mb-xs">{{ $t('returnNotes.distributor').toUpperCase() }}</div>
                <div class="invoice-info">
                  <div class="text-weight-bold">{{ selectedOrder.distributor?.name || 'N/A' }}</div>
                  <div v-if="selectedOrder.distributor?.phone">{{ $t('returnNotes.phone') }}: {{ selectedOrder.distributor.phone }}</div>
                  <div v-if="selectedOrder.distributor?.vehicle_plate">{{ $t('returnNotes.vehicle') }}: {{ selectedOrder.distributor.vehicle_plate }}</div>
                  <div v-if="selectedOrder.distributor?.vehicle_type">{{ $t('returnNotes.type') }}: {{ selectedOrder.distributor.vehicle_type }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Details Bar -->
          <div class="transaction-bar return-note-bar">
            <div class="row">
              <div class="col-3">
                <div class="transaction-label">{{ $t('returnNotes.orderType') }}</div>
                <div class="transaction-value">{{ $t('returnNotes.return') }} (إرجاع)</div>
              </div>
              <div class="col-3">
                <div class="transaction-label">{{ $t('returnNotes.orderDate') }}</div>
                <div class="transaction-value">{{ formatDate(selectedOrder.order_date) }}</div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="invoice-items-section" v-if="selectedOrder.details && selectedOrder.details.length > 0">
            <table class="invoice-table return-note-table">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 55%">{{ $t('returnNotes.description') }}</th>
                  <th style="width: 20%" class="text-right">{{ $t('returnNotes.quantity') }}</th>
                  <th style="width: 20%" class="text-right">{{ $t('returnNotes.unit') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in selectedOrder.details" :key="index">
                  <td class="text-center">{{ index + 1 }}</td>
                  <td>
                    <div class="text-weight-medium">{{ item.product?.name || 'Product' }}</div>
                    <div class="text-caption text-grey-6" v-if="item.product?.product_description">
                      {{ item.product.product_description }}
                    </div>
                  </td>
                  <td class="text-right">{{ (parseFloat(item.quantity) || 0).toFixed(2) }}</td>
                  <td class="text-right">{{ item.product?.unit?.name || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center q-pa-md text-grey-6">
            {{ $t('returnNotes.noItems') }}
          </div>

          <!-- Totals Section -->
          <div class="invoice-totals-section return-note-totals">
            <div class="row justify-end">
              <div class="totals-box">
                <div class="total-row">
                  <span class="total-label">{{ $t('returnNotes.totalQuantity') }}</span>
                  <span class="total-value final">{{ getTotalQuantity().toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="invoice-notes-section" v-if="selectedOrder.notes">
            <div class="invoice-label q-mb-xs">{{ $t('returnNotes.notes') }}</div>
            <div class="invoice-notes">{{ selectedOrder.notes }}</div>
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
import '../styles/invoice.css';
import { formatDate, generateInvoiceFileName, getLogoUrl } from '../utils/invoiceUtils';

const $q = useQuasar();
const { t } = useI18n();
const orders = ref([]);
const distributors = ref([]);
const products = ref([]);
const searchText = ref('');
const filterDistributor = ref(null);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const showDetailsDialog = ref(false);
const editMode = ref(false);
const selectedOrder = ref(null);
const pdfLoading = ref(false);
const companySettings = ref({
  company_name: '',
  company_logo: '',
  phone: '',
  email: '',
  address: '',
});

const form = ref({
  distributor_id: null,
  order_number: '',
  order_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: [],
});

const columns = computed(() => [
  { name: 'order_number', label: t('transfers.transferNumber'), field: 'order_number', align: 'left', sortable: true },
  { name: 'distributor', label: t('sales.distributor'), field: 'distributor', align: 'left' },
  { name: 'order_date', label: t('common.date'), field: 'order_date', align: 'left', sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center', sortable: false },
]);

const itemColumns = computed(() => [
  { name: 'product', label: t('products.name'), field: 'product', align: 'left' },
  { name: 'quantity', label: t('sales.quantity'), field: 'quantity', align: 'left' },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
]);

const detailColumns = computed(() => [
  { name: 'product', label: t('products.name'), field: 'product', align: 'left' },
  { name: 'quantity', label: t('sales.quantity'), field: 'quantity', align: 'left' },
]);

// Base products list (all products for return notes)
const baseProductsList = computed(() => {
  return products.value;
});

// Product options for select (filtered dynamically)
const productOptions = ref([]);

const filterProductOptions = (val, update) => {
  if (val === '') {
    update(() => {
      productOptions.value = baseProductsList.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    productOptions.value = baseProductsList.value.filter(
      product => product.name?.toLowerCase().includes(needle)
    );
  });
};

const filteredOrders = computed(() => {
  let result = orders.value.filter(order => order.order_type === 'entree');

  if (searchText.value) {
    const search = searchText.value.toLowerCase();
    result = result.filter(order =>
      order.order_number?.toLowerCase().includes(search) ||
      order.distributor?.name?.toLowerCase().includes(search)
    );
  }

  if (filterDistributor.value) {
    result = result.filter(order => order.distributor_id === filterDistributor.value.id);
  }

  return result;
});

const loadOrders = async () => {
  loading.value = true;
  try {
    const response = await api.get('/distribution-orders', { params: { order_type: 'entree' } });
    orders.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/distributors');
    distributors.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  }
};

const loadProducts = async () => {
  try {
    const response = await api.get('/products');
    products.value = response.data;
    productOptions.value = baseProductsList.value;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  }
};

const loadCommittedProducts = async () => {
  if (!form.value.distributor_id) {
    form.value.items = [];
    productOptions.value = [];
    return;
  }

  try {
    // Ensure distributor_id is a number, not an object
    let distributorId = form.value.distributor_id;
    if (typeof distributorId === 'object' && distributorId !== null && distributorId.id) {
      distributorId = distributorId.id;
      // Update form.value.distributor_id to be the ID
      form.value.distributor_id = distributorId;
    }
    
    const response = await api.get('/distribution-orders/committed-products', {
      params: { distributor_id: distributorId }
    });
    
    const committedProducts = response.data;
    
    // Update productOptions to only show committed products
    productOptions.value = committedProducts;
    
    // Auto-populate items with committed products
    // Default quantity is the committed quantity (full return)
    form.value.items = committedProducts.map(product => ({
      product_id: product.id,
      product: product, // Store full product object
      committed_quantity: product.committed_quantity,
      quantity: product.committed_quantity, // Default to full committed quantity
    }));
    
  } catch (error) {
    console.error('Failed to load committed products:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
    form.value.items = [];
    productOptions.value = [];
  }
};

const formatQuantity = (quantity) => {
  if (!quantity && quantity !== 0) return '0';
  return parseFloat(quantity).toFixed(2);
};

const openDialog = async () => {
  editMode.value = false;
  
  // Get next order number from API
  let nextOrderNumber = '';
  try {
    const response = await api.get('/distribution-orders/next-number');
    nextOrderNumber = response.data.order_number;
  } catch (error) {
    console.error('Failed to get next order number:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
    // Don't open dialog if we can't get order number
    return;
  }
  
  form.value = {
    distributor_id: null,
    order_number: nextOrderNumber,
    order_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [],
  };
  productOptions.value = [];
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editMode.value = false;
  selectedOrder.value = null;
};

const addItem = () => {
  form.value.items.push({
    id: Date.now(),
    product_id: null,
    quantity: 0,
  });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

// Track retry attempts to prevent infinite loops
let saveOrderRetryCount = 0;
const MAX_RETRY_ATTEMPTS = 3;

const saveOrder = async () => {
  saving.value = true;
  try {
    // Reset retry count on successful save
    saveOrderRetryCount = 0;
    // Ensure distributor_id is a number, not an object
    let distributorId = form.value.distributor_id;
    if (typeof distributorId === 'object' && distributorId !== null && distributorId.id) {
      distributorId = distributorId.id;
    }
    
    // Filter items to only include those with product_id and quantity >= 0.01
    const validItems = form.value.items
      .filter(item => {
        const productId = typeof item.product_id === 'object' && item.product_id !== null 
          ? item.product_id.id 
          : item.product_id;
        const quantity = parseFloat(item.quantity) || 0;
        return productId && quantity >= 0.01;
      })
      .map(item => {
        const productId = typeof item.product_id === 'object' && item.product_id !== null 
          ? item.product_id.id 
          : item.product_id;
        const quantity = parseFloat(item.quantity) || 0;
        return {
          product_id: productId,
          quantity: quantity >= 0.01 ? quantity : 0.01,
        };
      });

    // Validate required fields
    if (!distributorId) {
      $q.notify({ type: 'negative', message: t('messages.selectDistributor') });
      saving.value = false;
      return;
    }

    if (!form.value.order_number) {
      $q.notify({ type: 'negative', message: t('messages.enterOrderNumber') });
      saving.value = false;
      return;
    }

    if (!form.value.order_date) {
      $q.notify({ type: 'negative', message: t('messages.selectOrderDate') });
      saving.value = false;
      return;
    }

    if (validItems.length === 0) {
      $q.notify({ 
        type: 'negative', 
        message: t('messages.addAtLeastOneItem'),
        timeout: 5000
      });
      saving.value = false;
      return;
    }

    const payload = {
      distributor_id: distributorId,
      order_type: 'entree',
      order_number: form.value.order_number,
      order_date: form.value.order_date,
      notes: form.value.notes || '',
      items: validItems,
    };

    // Ensure all IDs are numbers
    payload.distributor_id = parseInt(payload.distributor_id, 10);
    payload.items = payload.items.map(item => ({
      product_id: parseInt(item.product_id, 10),
      quantity: parseFloat(item.quantity)
    }));

    console.log('Sending payload:', JSON.stringify(payload, null, 2));
    console.log('Form value:', JSON.stringify(form.value, null, 2));
    console.log('Valid items:', JSON.stringify(validItems, null, 2));
    console.log('Distributor ID type:', typeof payload.distributor_id, payload.distributor_id);
    console.log('Items count:', payload.items.length);

    if (editMode.value) {
      await api.put(`/distribution-orders/${selectedOrder.value.id}`, payload);
    } else {
      await api.post('/distribution-orders', payload);
    }

    $q.notify({ type: 'positive', message: t('messages.savedSuccessfully') });
    closeDialog();
    loadOrders();
  } catch (error) {
    console.error('Error saving return note:', error);
    console.error('Error response:', error.response?.data);
    console.error('Error response errors:', error.response?.data?.errors);
    
    // Build detailed error message
    let errorMessage = 'Failed to save return note';
    
    if (error.response?.data?.errors) {
      // Laravel validation errors
      const errors = error.response.data.errors;
      const errorMessages = Object.keys(errors).map(key => {
        return `${key}: ${errors[key].join(', ')}`;
      });
      errorMessage = errorMessages.join('\n');
      
      // If order_number error, try to get a new order number and retry automatically
      if (errors.order_number && errors.order_number.some(msg => msg.includes('already been taken'))) {
        if (saveOrderRetryCount < MAX_RETRY_ATTEMPTS) {
          saveOrderRetryCount++;
          try {
            const response = await api.get('/distribution-orders/next-number');
            const newOrderNumber = response.data.order_number;
            form.value.order_number = newOrderNumber;
            
            // Retry saving automatically with the new order number
            $q.notify({
              type: 'info',
              message: `Order number updated to ${newOrderNumber}. Retrying... (Attempt ${saveOrderRetryCount}/${MAX_RETRY_ATTEMPTS})`,
              timeout: 2000
            });
            
            // Wait a bit then retry
            setTimeout(async () => {
              await saveOrder();
            }, 500);
            
            return; // Exit early, saveOrder will be called again
          } catch (err) {
            console.error('Failed to get new order number:', err);
            errorMessage += '\n\nFailed to get a new order number. Please try again.';
          }
        } else {
          errorMessage += `\n\nMaximum retry attempts (${MAX_RETRY_ATTEMPTS}) reached. Please try again manually.`;
        }
      }
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
      
      // If order_number error, try to get a new order number and retry automatically
      if (error.response.data.message.includes('order number') && error.response.data.message.includes('already been taken')) {
        if (saveOrderRetryCount < MAX_RETRY_ATTEMPTS) {
          saveOrderRetryCount++;
          try {
            const response = await api.get('/distribution-orders/next-number');
            const newOrderNumber = response.data.order_number;
            form.value.order_number = newOrderNumber;
            
            // Retry saving automatically with the new order number
            $q.notify({
              type: 'info',
              message: `Order number updated to ${newOrderNumber}. Retrying... (Attempt ${saveOrderRetryCount}/${MAX_RETRY_ATTEMPTS})`,
              timeout: 2000
            });
            
            // Wait a bit then retry
            setTimeout(async () => {
              await saveOrder();
            }, 500);
            
            return; // Exit early, saveOrder will be called again
          } catch (err) {
            console.error('Failed to get new order number:', err);
            errorMessage += '\n\nFailed to get a new order number. Please try again.';
          }
        } else {
          errorMessage += `\n\nMaximum retry attempts (${MAX_RETRY_ATTEMPTS}) reached. Please try again manually.`;
        }
      }
    } else if (error.response?.data?.error) {
      errorMessage = error.response.data.error;
    }
    
    $q.notify({ 
      type: 'negative', 
      message: errorMessage || t('messages.failedToSave'),
      timeout: 8000,
      multiLine: true
    });
  } finally {
    saving.value = false;
  }
};

const editOrder = async (order) => {
  loading.value = true;
  try {
    const response = await api.get(`/distribution-orders/${order.id}`);
    const orderData = response.data;
    
    selectedOrder.value = orderData;
    editMode.value = true;
    form.value = {
      distributor_id: orderData.distributor_id,
      order_number: orderData.order_number,
      order_date: orderData.order_date ? new Date(orderData.order_date).toISOString().split('T')[0] : '',
      notes: orderData.notes || '',
      items: [],
    };
    
    // Load committed products for this distributor
    if (orderData.distributor_id) {
      const committedResponse = await api.get('/distribution-orders/committed-products', {
        params: { distributor_id: orderData.distributor_id }
      });
      
      const committedProducts = committedResponse.data;
      productOptions.value = committedProducts;
      
      // Map existing order details with committed products
      const existingDetails = orderData.details || [];
      form.value.items = committedProducts.map(product => {
        const existingDetail = existingDetails.find(d => d.product_id === product.id);
        return {
          product_id: product.id,
          product: product,
          committed_quantity: product.committed_quantity,
          quantity: existingDetail ? parseFloat(existingDetail.quantity) : 0,
        };
      });
    } else {
      productOptions.value = [];
    }
    
    showDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const viewDetails = async (order) => {
  loading.value = true;
  try {
    // Fetch full order details with relationships
    const response = await api.get(`/distribution-orders/${order.id}`);
    selectedOrder.value = response.data;
    
    // Ensure company settings are loaded
    if (!companySettings.value.company_name) {
      await loadCompanySettings();
    }
    
    showDetailsDialog.value = true;
  } catch (error) {
    console.error('Failed to load order details:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const loadCompanySettings = async () => {
  try {
    const response = await api.get('/settings');
    const settings = response.data;
    companySettings.value = {
      company_name: settings.company_name || '',
      company_logo: settings.company_logo || '',
      phone: settings.phone || '',
      email: settings.email || '',
      address: settings.address || '',
    };
  } catch (error) {
    console.error('Failed to load company settings:', error);
  }
};

const getTotalQuantity = () => {
  if (!selectedOrder.value || !selectedOrder.value.details) return 0;
  return selectedOrder.value.details.reduce((sum, item) => {
    return sum + (parseFloat(item.quantity) || 0);
  }, 0);
};

const saveAsPDF = async () => {
  if (!selectedOrder.value) return;
  
  pdfLoading.value = true;
  try {
    const returnNoteContent = document.getElementById('return-note-content');
    if (!returnNoteContent) {
      $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
      pdfLoading.value = false;
      return;
    }

    $q.notify({
      type: 'info',
      message: t('messages.generatingPdf'),
      timeout: 2000
    });

    // Import and use the composable for PDF generation
    const { useInvoicePDF } = await import('../composables/useInvoicePDF');
    const pdfComposable = useInvoicePDF({
      onProgress: (message) => {
        console.log(message);
      }
    });

    const fileName = generateInvoiceFileName(
      selectedOrder.value.order_number,
      'return_note',
      new Date(selectedOrder.value.order_date)
    );

    await pdfComposable.generatePDF(returnNoteContent, {}, fileName);

    $q.notify({
      type: 'positive',
      message: t('messages.pdfGenerated'),
      timeout: 2000
    });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({
      type: 'negative',
      message: t('messages.failedToGeneratePdf') + ': ' + (error.message || 'Unknown error'),
      timeout: 5000
    });
  } finally {
    pdfLoading.value = false;
  }
};

const deleteOrder = async (order) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete return note "${order.order_number}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/distribution-orders/${order.id}`);
      $q.notify({ type: 'positive', message: t('messages.deletedSuccessfully') });
      loadOrders();
    } catch (error) {
      $q.notify({ type: 'negative', message: t('messages.failedToDelete') });
    }
  });
};

onMounted(() => {
  loadOrders();
  loadDistributors();
  loadProducts();
  loadCompanySettings();
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

/* Return Note Specific Styles - Blue Theme */
.return-note-card {
  max-width: 210mm;
  width: 210mm;
}

/* Blue Transaction Bar */
.return-note-bar {
  background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%) !important;
}

/* Blue Table Header */
.return-note-table thead {
  background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%) !important;
}

.return-note-table th {
  border-bottom: 2px solid #64b5f6 !important;
}

/* Blue Totals Box */
.return-note-totals {
  background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
}

/* Blue Zebra Striping */
.return-note-table tbody tr:nth-child(even) {
  background-color: #e3f2fd !important;
}

.return-note-table tbody tr:hover {
  background-color: #bbdefb !important;
}

/* Company Name Color */
.return-note-card .company-name {
  color: #1976d2 !important;
}

/* Total Value Final Color */
.return-note-card .total-value.final {
  color: #1976d2 !important;
}

/* Dialog sizing for A4 */
:deep(.q-dialog__inner) {
  padding: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

:deep(.q-dialog__inner > div) {
  max-width: 210mm !important;
  width: 210mm !important;
  max-height: 90vh;
}

.return-note-card {
  max-width: 210mm;
  width: 210mm;
  margin: 0 auto;
}

@media (max-width: 900px) {
  .return-note-card {
    max-width: 100%;
    width: 100%;
  }
  
  :deep(.q-dialog__inner > div) {
    max-width: 100% !important;
    width: 100% !important;
  }
}
</style>
