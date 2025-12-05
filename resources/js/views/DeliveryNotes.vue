<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Delivery Notes (إذن خروج)</div>
      <q-btn color="positive" icon="add" label="New Delivery Note" @click="openDialog" />
    </div>

    <!-- Filters -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search delivery notes..."
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
        label="Distributor"
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
      <template v-slot:body-cell-items_count="props">
        <q-td :props="props" class="text-center">
          {{ props.row.details_count || (props.row.details ? props.row.details.length : 0) }}
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="visibility" color="primary" @click="viewDetails(props.row)">
            <q-tooltip>View Details</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="edit" color="positive" @click="editOrder(props.row)">
            <q-tooltip>Edit</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="deleteOrder(props.row)">
            <q-tooltip>Delete</q-tooltip>
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
              {{ editMode ? 'Edit' : 'New' }} Delivery Note
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
                  label="Distributor (الموزع) *"
                  outlined
                  dense
                  :rules="[val => !!val || 'Required']"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.order_number"
                  label="Order Number (رقم الأمر) *"
                  outlined
                  dense
                  :rules="[val => !!val || 'Required']"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.order_date"
                  label="Order Date (تاريخ الأمر) *"
                  type="date"
                  outlined
                  dense
                  :rules="[val => !!val || 'Required']"
                />
              </div>
              <div class="col-12">
                <q-input
                  v-model="form.notes"
                  label="Notes (ملاحظات)"
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
                <div class="text-h6">Items (المنتجات)</div>
                <q-btn color="primary" icon="add" label="Add Item" @click="addItem" />
              </div>

              <q-table
                :rows="form.items"
                :columns="itemColumns"
                row-key="id"
                flat
                bordered
              >
                <template v-slot:body-cell-product="props">
                  <q-td :props="props" :class="{ 'text-red': getProductStock(props.row.product_id, props.row) === 0 }">
                    <q-select
                      v-model="props.row.product_id"
                      :options="productOptions"
                      option-label="name"
                      option-value="id"
                      emit-value
                      map-options
                      outlined
                      dense
                      use-input
                      input-debounce="300"
                      @filter="(val, update) => filterProductOptions(val, update)"
                      @update:model-value="(val) => handleProductSelection(props.row.id, val)"
                    >
                      <template v-slot:option="scope">
                        <q-item
                          v-bind="scope.itemProps"
                          :class="{ 'text-red': (parseFloat(scope.opt.current_stock_quantity) || 0) === 0 }"
                        >
                          <q-item-section>
                            <q-item-label :class="{ 'text-red': (parseFloat(scope.opt.current_stock_quantity) || 0) === 0 }">
                              {{ scope.opt.name }}
                            </q-item-label>
                            <q-item-label caption>
                              Stock: {{ scope.opt.current_stock_quantity || 0 }}
                            </q-item-label>
                          </q-item-section>
                        </q-item>
                      </template>
                      <template v-slot:no-option>
                        <q-item>
                          <q-item-section class="text-grey">
                            No products available
                          </q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </q-td>
                </template>
                <template v-slot:body-cell-actions="props">
                  <q-td :props="props">
                    <q-btn flat dense icon="delete" color="negative" @click="removeItem(props.rowIndex)" />
                  </q-td>
                </template>
                <template v-slot:body-cell-quantity="props">
                  <q-td :props="props">
                    <div class="row items-center q-gutter-sm">
                      <q-input
                        v-model.number="props.row.quantity"
                        type="number"
                        outlined
                        dense
                        min="1"
                        step="1"
                        :max="getProductStock(props.row.product_id, props.row)"
                        @update:model-value="(val) => validateQuantity(props.row, val)"
                        style="flex: 1"
                      />
                      <span v-if="props.row.product_id" class="text-grey-7 q-ml-xs">
                        / {{ getStockForDisplay(props.row) }}
                      </span>
                    </div>
                  </q-td>
                </template>
              </q-table>
            </div>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- View Details Dialog -->
    <!-- Delivery Note Invoice View Dialog -->
    <q-dialog v-model="showDetailsDialog">
      <q-card class="delivery-note-card" style="overflow-y: auto;">
        <q-card-section class="invoice-header q-pa-none">
          <div class="row items-center justify-between q-pa-md">
            <q-btn flat dense icon="close" @click="showDetailsDialog = false" />
            <div class="text-h6">Delivery Note (وصل إخراج السلع)</div>
            <q-btn 
              flat 
              dense 
              icon="picture_as_pdf" 
              label="Save as PDF" 
              color="primary"
              @click="saveAsPDF"
              :loading="pdfLoading"
            />
          </div>
        </q-card-section>

        <q-card-section v-if="selectedOrder" class="invoice-content" id="delivery-note-content">
          <!-- Invoice Header -->
          <div class="invoice-header-section">
            <div class="row items-start justify-between">
              <!-- Left: Date and Order Number -->
              <div class="col-3">
                <div class="invoice-label q-mb-xs">DATE</div>
                <div class="invoice-value">{{ formatDate(selectedOrder.order_date) }}</div>
                <div class="invoice-label q-mt-md q-mb-xs">ORDER NO</div>
                <div class="invoice-value">{{ selectedOrder.order_number }}</div>
              </div>

              <!-- Center: Company Name -->
              <div class="col-5 text-center">
                <div class="text-h4 text-weight-bold q-mb-md">DELIVERY NOTE</div>
                <div class="company-name text-weight-bold">{{ companySettings.company_name || 'YOUR COMPANY' }}</div>
              </div>

              <!-- Right: Logo -->
              <div class="col-3 text-right">
                <div v-if="companySettings.company_logo" class="company-logo q-mb-md">
                  <img :src="getLogoUrl(companySettings.company_logo)" alt="Company Logo" style="max-width: 100px; max-height: 100px;" />
                </div>
                <div v-else class="logo-placeholder q-mb-md">
                  <q-icon name="business" size="80px" color="grey-4" />
                  <div class="text-caption text-grey-6">Logo Name</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Company and Distributor Info -->
          <div class="invoice-info-section">
            <div class="row q-col-gutter-md">
              <!-- Company Info -->
              <div class="col-6">
                <div class="invoice-label q-mb-xs">YOUR COMPANY</div>
                <div class="invoice-info">
                  <div v-if="companySettings.address">{{ companySettings.address }}</div>
                  <div v-if="companySettings.phone">Phone: {{ companySettings.phone }}</div>
                  <div v-if="companySettings.email">Email: {{ companySettings.email }}</div>
                </div>
              </div>

              <!-- Distributor Info -->
              <div class="col-6 text-right">
                <div class="invoice-label q-mb-xs">DISTRIBUTOR</div>
                <div class="invoice-info">
                  <div class="text-weight-bold">{{ selectedOrder.distributor?.name || 'N/A' }}</div>
                  <div v-if="selectedOrder.distributor?.phone">Phone: {{ selectedOrder.distributor.phone }}</div>
                  <div v-if="selectedOrder.distributor?.vehicle_plate">Vehicle: {{ selectedOrder.distributor.vehicle_plate }}</div>
                  <div v-if="selectedOrder.distributor?.vehicle_type">Type: {{ selectedOrder.distributor.vehicle_type }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Details Bar -->
          <div class="transaction-bar delivery-note-bar">
            <div class="row">
              <div class="col-3">
                <div class="transaction-label">ORDER TYPE</div>
                <div class="transaction-value">Delivery (إخراج)</div>
              </div>
              <div class="col-3">
                <div class="transaction-label">ORDER DATE</div>
                <div class="transaction-value">{{ formatDate(selectedOrder.order_date) }}</div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="invoice-items-section" v-if="selectedOrder.details && selectedOrder.details.length > 0">
            <table class="invoice-table delivery-note-table">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 55%">DESCRIPTION</th>
                  <th style="width: 20%" class="text-right">QUANTITY</th>
                  <th style="width: 20%" class="text-right">UNIT</th>
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
            No items in this delivery note
          </div>

          <!-- Totals Section -->
          <div class="invoice-totals-section">
            <div class="row justify-end">
              <div class="totals-box delivery-note-totals">
                <div class="total-row">
                  <span class="total-label">Total Items</span>
                  <span class="total-value">{{ selectedOrder.details?.length || 0 }}</span>
                </div>
                <div class="total-row">
                  <span class="total-label">Total Quantity</span>
                  <span class="total-value final">{{ getTotalQuantity().toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="invoice-notes-section" v-if="selectedOrder.notes">
            <div class="invoice-label q-mb-xs">NOTES</div>
            <div class="invoice-notes">{{ selectedOrder.notes }}</div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="invoice-actions">
          <q-btn label="Close" flat @click="showDetailsDialog = false" />
          <q-btn 
            label="Save as PDF" 
            icon="picture_as_pdf" 
            color="primary"
            @click="saveAsPDF"
            :loading="pdfLoading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import api from '../api';
import '../styles/invoice.css';
import { formatDate, generateInvoiceFileName, getLogoUrl } from '../utils/invoiceUtils';

const $q = useQuasar();
const orders = ref([]);
const distributors = ref([]);
const products = ref([]);
const searchText = ref('');
const filterDistributor = ref(null);
const loading = ref(false);
const saving = ref(false);
const pdfLoading = ref(false);
const showDialog = ref(false);
const showDetailsDialog = ref(false);
const editMode = ref(false);
const selectedOrder = ref(null);
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

const columns = [
  { name: 'order_number', label: 'Order Number', field: 'order_number', align: 'left', sortable: true },
  { name: 'distributor', label: 'Distributor', field: 'distributor', align: 'left' },
  { name: 'order_date', label: 'Date', field: 'order_date', align: 'left', sortable: true },
  { name: 'items_count', label: 'Products Count (عدد المنتجات)', field: 'details_count', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center', sortable: false },
];

const itemColumns = [
  { name: 'product', label: 'Product', field: 'product', align: 'left' },
  { name: 'quantity', label: 'Quantity', field: 'quantity', align: 'left' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

const detailColumns = [
  { name: 'product', label: 'Product', field: 'product', align: 'left' },
  { name: 'quantity', label: 'Quantity', field: 'quantity', align: 'left' },
];

// Base products list (only products with stock > 0)
const baseProductsList = computed(() => {
  return products.value.filter(p => (parseFloat(p.current_stock_quantity) || 0) > 0);
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
  let result = orders.value.filter(order => order.order_type === 'sortie');

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
    const response = await api.get('/distribution-orders', { params: { order_type: 'sortie' } });
    orders.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load delivery notes' });
  } finally {
    loading.value = false;
  }
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/distributors');
    distributors.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load distributors' });
  }
};

const loadProducts = async () => {
  try {
    const response = await api.get('/products');
    products.value = response.data;
    productOptions.value = baseProductsList.value;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load products' });
  }
};

const openDialog = async () => {
  editMode.value = false;
  
  // Get next order number from API
  let nextOrderNumber = '';
  try {
    const response = await api.get('/distribution-orders/next-number');
    nextOrderNumber = response.data.order_number;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to get next order number' });
  }
  
  form.value = {
    distributor_id: null,
    order_number: nextOrderNumber,
    order_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [],
  };
  productOptions.value = baseProductsList.value;
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

// Handle product selection from dropdown
const handleProductSelection = (itemId, val) => {
  try {
    // Check if form and items exist
    if (!form || !form.value || !form.value.items) {
      console.error('Form or items not initialized');
      return;
    }
    
    // Handle case where val might be the full product object instead of just ID
    const productId = typeof val === 'object' && val !== null && val.id !== undefined ? val.id : val;
    const selectedProductObj = typeof val === 'object' && val !== null && val.id !== undefined ? val : null;
    
    // Find the item index in form.items
    const itemIndex = form.value.items.findIndex(item => item && item.id === itemId);
    if (itemIndex === -1) {
      console.error('Item not found in form.items', { itemId, items: form.value.items });
      return;
    }
    
    // Update product_id
    const productIdValue = productId || (selectedProductObj ? selectedProductObj.id : null);
    if (!productIdValue) {
      console.error('No product ID found', { val, selectedProductObj });
      return;
    }
    
    // Find the selected product from all possible sources
    let selectedProduct = selectedProductObj;
    
    // If we got the full object, use it; otherwise search
    if (!selectedProduct || !selectedProduct.current_stock_quantity) {
      // Priority: baseProductsList (has all products with stock) > products > productOptions
      // Search in baseProductsList first (has all products with stock > 0)
      if (baseProductsList.value && baseProductsList.value.length > 0) {
        selectedProduct = baseProductsList.value.find(p => p.id === productIdValue);
      }
      
      // If not found, search in full products list
      if (!selectedProduct && products.value && products.value.length > 0) {
        selectedProduct = products.value.find(p => p.id === productIdValue);
      }
      
      // If still not found, search in productOptions (filtered list)
      if (!selectedProduct && productOptions.value && productOptions.value.length > 0) {
        selectedProduct = productOptions.value.find(p => p.id === productIdValue);
      }
    }
    
    // Update the item with the selected product (reactive update)
    if (selectedProduct) {
      // Force reactive update by creating new object
      form.value.items[itemIndex] = {
        ...form.value.items[itemIndex],
        product_id: productIdValue,
        product: { ...selectedProduct }
      };
    } else {
      form.value.items[itemIndex] = {
        ...form.value.items[itemIndex],
        product_id: productIdValue,
        product: null
      };
    }
    
    updateItemProduct(form.value.items[itemIndex], selectedProduct);
  } catch (error) {
    console.error('Error in handleProductSelection:', error);
  }
};

// Helper function to get stock for display - direct access to item
const getStockForDisplay = (row) => {
  if (!row || !row.product_id) return 0;
  
  // Find the item in form.items by id (row.id should match item.id)
  const itemId = row.id || (typeof row === 'number' ? row : null);
  if (!itemId) return 0;
  
  const item = form.value.items.find(i => i.id === itemId);
  if (!item || !item.product_id) return 0;
  
  // First priority: use item.product if available (saved from selection)
  if (item.product && item.product.id === item.product_id) {
    const stock = parseFloat(item.product.current_stock_quantity);
    if (!isNaN(stock) && stock >= 0) {
      return stock;
    }
  }
  
  // Fallback: search in all product lists
  let product = null;
  
  // Search in baseProductsList first (contains all products with stock > 0)
  if (baseProductsList.value && baseProductsList.value.length > 0) {
    product = baseProductsList.value.find(p => p.id === item.product_id);
  }
  
  // If not found, search in full products list
  if (!product && products.value && products.value.length > 0) {
    product = products.value.find(p => p.id === item.product_id);
  }
  
  // If still not found, search in productOptions (filtered list)
  if (!product && productOptions.value && productOptions.value.length > 0) {
    product = productOptions.value.find(p => p.id === item.product_id);
  }
  
  if (product) {
    const stock = parseFloat(product.current_stock_quantity);
    return !isNaN(stock) && stock >= 0 ? stock : 0;
  }
  
  return 0;
};

// Helper function to get stock for display by item id (keep for backward compatibility)
const getProductStockFromItem = (itemId) => {
  if (!itemId) return 0;
  
  // Find the item in form.items
  const item = form.value.items.find(i => i.id === itemId);
  if (!item || !item.product_id) return 0;
  
  // First priority: use item.product if available (most reliable)
  if (item.product && item.product.id === item.product_id) {
    const stock = parseFloat(item.product.current_stock_quantity);
    if (!isNaN(stock)) {
      return stock;
    }
  }
  
  // Fallback: use getStockForDisplay logic
  return getStockForDisplay(item);
};

const getProductStock = (productId, item = null) => {
  if (!productId) return 0;
  
  // First, try to get from item.product if available (most reliable)
  if (item && item.product && item.product.id === productId) {
    const stock = parseFloat(item.product.current_stock_quantity);
    if (!isNaN(stock)) {
      return stock;
    }
  }
  
  // Search in all possible locations to get the most up-to-date stock
  // 1. First, try productOptions (filtered list, may have latest data)
  let product = productOptions.value.find(p => p.id === productId);
  
  // 2. If not found, search in baseProductsList (all products with stock > 0)
  if (!product && baseProductsList.value && baseProductsList.value.length > 0) {
    product = baseProductsList.value.find(p => p.id === productId);
  }
  
  // 3. If still not found, search in full products list
  if (!product && products.value && products.value.length > 0) {
    product = products.value.find(p => p.id === productId);
  }
  
  if (product) {
    const stock = parseFloat(product.current_stock_quantity);
    return !isNaN(stock) ? stock : 0;
  }
  
  return 0;
};

const validateQuantity = (item, quantity) => {
  if (!item.product_id) return;
  
  const stock = getProductStock(item.product_id, item);
  const qty = parseFloat(quantity) || 0;
  
  // لا يمكن إضافة كمية أكبر من المخزون المتاح
  if (qty > stock) {
    item.quantity = stock;
    $q.notify({
      type: 'warning',
      message: `لا يمكن إضافة كمية أكبر من المخزون المتاح (${stock})`,
      timeout: 3000,
    });
    return;
  }
  
  // تأكد من أن الكمية ليست أقل من 0.01
  if (qty < 0.01 && qty > 0) {
    item.quantity = 0.01;
  }
};

const updateItemProduct = (item, selectedProduct = null) => {
  // Validate quantity against stock
  if (item.product_id) {
    let product = selectedProduct;
    
    // If selectedProduct is not provided, search in all locations
    if (!product) {
      // 1. Search in full products list first (most reliable, always has all products)
      product = products.value.find(p => p.id === item.product_id);
      
      // 2. If not found, search in baseProductsList
      if (!product && baseProductsList.value && baseProductsList.value.length > 0) {
        product = baseProductsList.value.find(p => p.id === item.product_id);
      }
      
      // 3. If still not found, search in productOptions
      if (!product && productOptions.value && productOptions.value.length > 0) {
        product = productOptions.value.find(p => p.id === item.product_id);
      }
    }
    
    if (product) {
      // Store a copy of the full product object
      item.product = { ...product };
    } else {
      item.product = null;
    }
    
    // Validate quantity if already set
    if (item.quantity) {
      validateQuantity(item, item.quantity);
    }
  } else {
    item.product = null;
  }
};

const saveOrder = async () => {
  saving.value = true;
  try {
    // Validate stock
    for (const item of form.value.items) {
      if (item.product_id && item.quantity > 0) {
        const stock = getProductStock(item.product_id, item);
        if (item.quantity > stock) {
          $q.notify({
            type: 'negative',
            message: `Quantity for ${products.value.find(p => p.id === item.product_id)?.name || 'product'} cannot exceed available stock (${stock})`,
          });
          saving.value = false;
          return;
        }
      }
    }

    // Validate required fields - extract distributor_id first
    const distributorId = typeof form.value.distributor_id === 'object' && form.value.distributor_id !== null
      ? form.value.distributor_id.id
      : form.value.distributor_id;
    
    if (!distributorId) {
      $q.notify({ type: 'negative', message: 'Please select a distributor' });
      saving.value = false;
      return;
    }

    if (!form.value.order_number || form.value.order_number.trim() === '') {
      $q.notify({ type: 'negative', message: 'Please enter an order number' });
      saving.value = false;
      return;
    }

    if (!form.value.order_date) {
      $q.notify({ type: 'negative', message: 'Please select an order date' });
      saving.value = false;
      return;
    }

    // Filter and validate items
    const validItems = form.value.items
      .filter(item => item.product_id && item.quantity > 0)
      .map(item => ({
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
      }));

    if (validItems.length === 0) {
      $q.notify({ type: 'negative', message: 'Please add at least one item to the delivery note' });
      saving.value = false;
      return;
    }

    const payload = {
      distributor_id: distributorId,
      order_type: 'sortie',
      order_number: form.value.order_number.trim(),
      order_date: form.value.order_date,
      notes: form.value.notes || '',
      items: validItems,
    };

    console.log('Sending payload:', payload);

    if (editMode.value) {
      await api.put(`/distribution-orders/${selectedOrder.value.id}`, payload);
    } else {
      await api.post('/distribution-orders', payload);
    }

    $q.notify({ type: 'positive', message: 'Delivery note saved successfully' });
    closeDialog();
    loadOrders();
  } catch (error) {
    console.error('Error saving delivery note:', error);
    console.error('Error response:', error.response?.data);
    
    // Handle validation errors
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const validationErrors = error.response.data.errors;
      const errorMessages = Object.keys(validationErrors)
        .map(key => `${key}: ${validationErrors[key].join(', ')}`)
        .join('\n');
      $q.notify({ 
        type: 'negative', 
        message: `Validation errors:\n${errorMessages}`,
        multiLine: true,
        timeout: 5000
      });
    } else {
      const errorMessage = error.response?.data?.error 
        || error.response?.data?.message 
        || error.message 
        || 'Failed to save delivery note';
      $q.notify({ 
        type: 'negative', 
        message: errorMessage,
        timeout: 5000
      });
    }
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
      items: (orderData.details || []).map(d => ({
        id: d.id,
        product_id: d.product_id,
        quantity: d.quantity,
      })),
    };
    productOptions.value = baseProductsList.value;
    showDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load delivery note for editing' });
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
    showDetailsDialog.value = true;
  } catch (error) {
    console.error('Failed to load order details:', error);
    $q.notify({ type: 'negative', message: 'Failed to load order details' });
  } finally {
    loading.value = false;
  }
};

const deleteOrder = async (order) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete delivery note "${order.order_number}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/distribution-orders/${order.id}`);
      $q.notify({ type: 'positive', message: 'Delivery note deleted' });
      loadOrders();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete delivery note' });
    }
  });
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
    const deliveryNoteContent = document.getElementById('delivery-note-content');
    if (!deliveryNoteContent) {
      $q.notify({ type: 'negative', message: 'Delivery note content not found' });
      pdfLoading.value = false;
      return;
    }

    $q.notify({
      type: 'info',
      message: 'Generating PDF...',
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
      'delivery_note',
      new Date(selectedOrder.value.order_date)
    );

    await pdfComposable.generatePDF(deliveryNoteContent, {}, fileName);

    $q.notify({
      type: 'positive',
      message: 'PDF generated successfully',
      timeout: 2000
    });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to generate PDF: ' + (error.message || 'Unknown error'),
      timeout: 5000
    });
  } finally {
    pdfLoading.value = false;
  }
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

.text-red {
  color: #c10015 !important;
}

:deep(.q-item.text-red) {
  color: #c10015 !important;
}

:deep(.q-item-label.text-red) {
  color: #c10015 !important;
}

/* Delivery Note Specific Styles - Red Theme */
.delivery-note-card {
  max-width: 210mm;
  width: 210mm;
}

/* Red Transaction Bar */
.delivery-note-bar {
  background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%) !important;
}

/* Red Table Header */
.delivery-note-table thead {
  background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%) !important;
}

.delivery-note-table th {
  border-bottom: 2px solid #e57373 !important;
}

/* Red Totals Box */
.delivery-note-totals {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%) !important;
}

/* Red Zebra Striping */
.delivery-note-table tbody tr:nth-child(even) {
  background-color: #ffebee !important;
}

.delivery-note-table tbody tr:hover {
  background-color: #ffcdd2 !important;
}

/* Company Name Color */
.delivery-note-card .company-name {
  color: #d32f2f !important;
}

/* Total Value Final Color */
.delivery-note-card .total-value.final {
  color: #d32f2f !important;
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

.delivery-note-card {
  max-width: 210mm;
  width: 210mm;
  margin: 0 auto;
}

@media (max-width: 900px) {
  .delivery-note-card {
    max-width: 100%;
    width: 100%;
  }
  
  :deep(.q-dialog__inner > div) {
    max-width: 100% !important;
    width: 100% !important;
  }
}
</style>
