<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Sales Receipts (سندات البيع)</div>
      <q-btn color="primary" icon="add" label="New Sale" @click="openDialog" />
    </div>

    <q-table
      :rows="receipts"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :pagination="{ rowsPerPage: 20 }"
    >
      <template v-slot:body-cell-receipt_number="props">
        <q-td :props="props">
          <div class="text-weight-bold">{{ props.value }}</div>
        </q-td>
      </template>
      <template v-slot:body-cell-distributor="props">
        <q-td :props="props">
          {{ props.row.distributor?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-client="props">
        <q-td :props="props">
          {{ props.row.client?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-items_count="props">
        <q-td :props="props" class="text-center">
          <q-badge color="primary">{{ props.row.details?.length || 0 }}</q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-date="props">
        <q-td :props="props">
          {{ formatDate(props.row.receipt_date) }}
        </q-td>
      </template>
      <template v-slot:body-cell-total="props">
        <q-td :props="props" class="text-right text-weight-bold">
          {{ calculateReceiptTotal(props.row).toFixed(2) }} DH
        </q-td>
      </template>
      <template v-slot:body-cell-image="props">
        <q-td :props="props">
          <q-btn 
            v-if="props.row.receipt_image_path" 
            flat 
            dense 
            :icon="getFileIcon(props.row.receipt_image_path)" 
            :color="getFileIconColor(props.row.receipt_image_path)" 
            @click="viewImage(props.row.receipt_image_path)"
          >
            <q-tooltip>{{ getFileTooltip(props.row.receipt_image_path) }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center justify-center q-gutter-xs">
            <q-btn flat dense icon="visibility" color="positive" @click="viewReceipt(props.row)">
              <q-tooltip>View Details</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="edit" color="primary" @click="editReceipt(props.row)">
              <q-tooltip>Edit</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>Delete</q-tooltip>
            </q-btn>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- New/Edit Sale Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 900px; max-width: 95vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? 'Edit Sales Receipt (تعديل سند البيع)' : 'New Sales Receipt (سند بيع جديد)' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveSale">
            <!-- Row 1: Distributor, Receipt Number, Receipt Date -->
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-4">
                <q-select
                  v-model="form.distributor_id"
                  :options="distributorOptions"
                  option-value="id"
                  option-label="name"
                  label="Distributor (الموزع)"
                  outlined
                  dense
                  emit-value
                  map-options
                  :rules="[val => !!val || 'Distributor is required']"
                  @update:model-value="loadCommittedProducts"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.receipt_number"
                  label="Receipt Number (رقم السند)"
                  outlined
                  dense
                  readonly
                  :rules="[val => !!val || 'Receipt number is required']"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.receipt_date"
                  label="Receipt Date (تاريخ السند)"
                  type="date"
                  outlined
                  dense
                  :rules="[val => !!val || 'Receipt date is required']"
                />
              </div>
            </div>

            <!-- Row 2: Customer Name and Receipt Image -->
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-6">
                <q-select
                  v-model="form.client_id"
                  :options="clientOptions"
                  option-value="id"
                  option-label="name"
                  label="Customer (العميل)"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  input-debounce="300"
                  :loading="searchingClients"
                  @filter="filterClients"
                  @update:model-value="handleClientSelection"
                  :rules="[val => !!val || 'Customer is required']"
                >
                  <template v-slot:option="{ itemProps, opt }">
                    <q-item v-bind="itemProps">
                      <q-item-section>
                        <q-item-label>{{ opt.name }}</q-item-label>
                        <q-item-label caption v-if="opt.phone">{{ opt.phone }}</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                  <template v-slot:no-option="{ inputValue }">
                    <q-item>
                      <q-item-section class="text-grey">
                        No results for "{{ inputValue }}"
                      </q-item-section>
                    </q-item>
                    <q-item clickable @click="openAddClientDialog(inputValue)">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section>
                        <q-item-label class="text-primary">Add new client "{{ inputValue }}"</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                  <template v-slot:after-options>
                    <q-item clickable @click="openAddClientDialog('')">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section>
                        <q-item-label class="text-primary">Add new client</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-6">
                <q-file
                  v-model="form.receipt_image"
                  label="Receipt Image/PDF (صورة/PDF السند)"
                  outlined
                  dense
                  accept="image/*,.pdf"
                  clearable
                >
                  <template v-slot:prepend>
                    <q-icon name="attach_file" />
                  </template>
                </q-file>
              </div>
            </div>

            <!-- Items Section -->
            <div class="text-subtitle2 q-mt-md q-mb-sm">Items (المنتجات)</div>
            
            <q-table
              :rows="form.items"
              :columns="itemColumns"
              row-key="id"
              flat
              bordered
              class="q-mb-md"
              hide-no-data
            >
              <template v-slot:body-cell-product="props">
                <q-td :props="props">
                  <q-select
                    v-model="props.row.product_id"
                    :options="committedProductOptions"
                    option-value="id"
                    option-label="name"
                    outlined
                    dense
                    emit-value
                    map-options
                    use-input
                    input-debounce="300"
                    @filter="filterProducts"
                    @update:model-value="(val) => handleProductSelection(props.row, val)"
                  >
                    <template v-slot:option="{ itemProps, opt }">
                      <q-item v-bind="itemProps">
                        <q-item-section>
                          <q-item-label>{{ opt.name }}</q-item-label>
                          <q-item-label caption>Committed: {{ formatQuantity(opt.committed_quantity) }}</q-item-label>
                        </q-item-section>
                      </q-item>
                    </template>
                  </q-select>
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
                      :max="getCommittedQuantityForProduct(props.row.product_id)"
                      @update:model-value="validateQuantity(props.row)"
                      style="width: 80px;"
                    />
                    <span class="text-grey-7 q-ml-xs" v-if="props.row.product_id">
                      /{{ formatQuantity(getCommittedQuantityForProduct(props.row.product_id)) }}
                    </span>
                  </div>
                </q-td>
              </template>
              
              <template v-slot:body-cell-price_type="props">
                <q-td :props="props">
                  <q-select
                    v-model="props.row.price_type_used"
                    :options="priceTypeOptions"
                    option-value="value"
                    option-label="label"
                    outlined
                    dense
                    emit-value
                    map-options
                    @update:model-value="() => updatePriceForItem(props.row)"
                  />
                </q-td>
              </template>
              
              <template v-slot:body-cell-unit_price="props">
                <q-td :props="props">
                  <q-input
                    v-model.number="props.row.selling_price"
                    type="number"
                    outlined
                    dense
                    step="0.01"
                    :min="0"
                  />
                </q-td>
              </template>
              
              <template v-slot:body-cell-total_price="props">
                <q-td :props="props" class="text-right text-weight-medium">
                  {{ (props.row.quantity * props.row.selling_price).toFixed(2) }} DH
                </q-td>
              </template>
              
              <template v-slot:body-cell-actions="props">
                <q-td :props="props" class="text-center">
                  <q-btn flat dense icon="delete" color="negative" @click="removeItem(props.rowIndex)" />
                </q-td>
              </template>

              <!-- Add Item button row at the bottom -->
              <template v-slot:bottom-row>
                <q-tr>
                  <q-td colspan="6">
                    <q-btn 
                      flat 
                      dense 
                      icon="add" 
                      label="Add Item" 
                      color="primary" 
                      class="full-width" 
                      @click="addItem" 
                    />
                  </q-td>
                </q-tr>
              </template>
            </q-table>

            <!-- Grand Total -->
            <div class="row justify-end q-mb-md">
              <div class="col-auto">
                <q-card flat bordered class="bg-grey-2">
                  <q-card-section class="q-pa-sm">
                    <div class="row items-center q-gutter-md">
                      <div class="text-subtitle1 text-weight-medium">Grand Total (المجموع الكلي):</div>
                      <div class="text-h6 text-primary text-weight-bold">{{ grandTotal.toFixed(2) }} DH</div>
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
    <!-- Add Client Dialog -->
    <q-dialog v-model="showAddClientDialog" persistent>
      <q-card style="min-width: 400px;">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Add New Client (إضافة عميل جديد)</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveNewClient" class="q-gutter-md">
            <q-input
              v-model="newClientForm.name"
              label="Name (الاسم) *"
              outlined
              dense
              :rules="[val => !!val || 'Name is required']"
            />

            <q-input
              v-model="newClientForm.phone"
              label="Phone (الهاتف)"
              outlined
              dense
            />

            <q-input
              v-model="newClientForm.address"
              label="Address (العنوان)"
              outlined
              dense
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showAddClientDialog = false" />
              <q-btn type="submit" label="Save" color="secondary" :loading="savingClient" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Sales Receipt Preview Dialog -->
    <q-dialog v-model="showReceiptDialog">
      <q-card style="width: 800px; max-width: 95vw;">
        <q-card-section class="row items-center q-pa-sm bg-primary text-white">
          <q-btn flat dense icon="close" @click="showReceiptDialog = false" />
          <div class="text-h6 q-ml-md">Sales Receipt</div>
          <q-space />
          <q-btn 
            flat 
            dense 
            icon="picture_as_pdf" 
            label="Save PDF" 
            @click="saveReceiptAsPDF"
            :loading="pdfLoading"
          />
        </q-card-section>

        <q-card-section v-if="selectedReceipt" class="receipt-content" id="receipt-content">
          <!-- Receipt Header -->
          <div class="receipt-header-section">
            <div class="row items-start justify-between">
              <!-- Left: Date and Receipt Number -->
              <div class="col-3">
                <div class="receipt-label q-mb-xs">DATE</div>
                <div class="receipt-value">{{ formatDate(selectedReceipt.receipt_date) }}</div>
                <div class="receipt-label q-mt-md q-mb-xs">RECEIPT NO</div>
                <div class="receipt-value">{{ selectedReceipt.receipt_number }}</div>
              </div>

              <!-- Center: Title -->
              <div class="col-5 text-center">
                <div class="text-h4 text-weight-bold q-mb-md">SALES RECEIPT</div>
                <div class="company-name text-weight-bold">{{ companySettings.company_name || 'YOUR COMPANY' }}</div>
              </div>

              <!-- Right: Logo -->
              <div class="col-3 text-right">
                <div v-if="companySettings.company_logo" class="company-logo q-mb-md">
                  <img :src="getLogoUrl(companySettings.company_logo)" alt="Company Logo" style="max-width: 100px; max-height: 100px;" />
                </div>
                <div v-else class="logo-placeholder q-mb-md">
                  <q-icon name="business" size="80px" color="grey-4" />
                </div>
              </div>
            </div>
          </div>

          <!-- Distributor and Client Info -->
          <div class="receipt-info-section">
            <div class="row q-col-gutter-md">
              <!-- Distributor Info -->
              <div class="col-6">
                <div class="receipt-label q-mb-xs">DISTRIBUTOR</div>
                <div class="receipt-info">
                  <div class="text-weight-bold">{{ selectedReceipt.distributor?.name || 'N/A' }}</div>
                  <div v-if="selectedReceipt.distributor?.phone">Phone: {{ selectedReceipt.distributor.phone }}</div>
                </div>
              </div>

              <!-- Client Info -->
              <div class="col-6 text-right">
                <div class="receipt-label q-mb-xs">CLIENT</div>
                <div class="receipt-info">
                  <div class="text-weight-bold">{{ selectedReceipt.client?.name || 'N/A' }}</div>
                  <div v-if="selectedReceipt.client?.phone">Phone: {{ selectedReceipt.client.phone }}</div>
                  <div v-if="selectedReceipt.client?.address">{{ selectedReceipt.client.address }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="receipt-items-section" v-if="selectedReceipt.details && selectedReceipt.details.length > 0">
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 35%">DESCRIPTION</th>
                  <th style="width: 15%">PRICE TYPE</th>
                  <th style="width: 10%">QUANTITY</th>
                  <th style="width: 15%" class="text-right">UNIT PRICE</th>
                  <th style="width: 20%" class="text-right">LINE TOTAL</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in selectedReceipt.details" :key="index">
                  <td class="text-center">{{ index + 1 }}</td>
                  <td>
                    <div class="text-weight-medium">{{ item.product?.name || 'Product' }}</div>
                  </td>
                  <td>{{ getPriceTypeLabel(item.price_type_used) }}</td>
                  <td>{{ parseFloat(item.quantity).toFixed(2) }}</td>
                  <td class="text-right">{{ parseFloat(item.selling_price).toFixed(2) }} DH</td>
                  <td class="text-right">{{ (parseFloat(item.quantity) * parseFloat(item.selling_price)).toFixed(2) }} DH</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center q-pa-md text-grey-6">
            No items in this receipt
          </div>

          <!-- Totals Section -->
          <div class="receipt-totals-section">
            <div class="row justify-end">
              <div class="totals-box">
                <div class="total-row">
                  <span class="total-label">Subtotal</span>
                  <span class="total-value">{{ calculateReceiptTotal(selectedReceipt).toFixed(2) }} DH</span>
                </div>
                <div class="total-row">
                  <span class="total-label">Total</span>
                  <span class="total-value final">{{ calculateReceiptTotal(selectedReceipt).toFixed(2) }} DH</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="receipt-notes-section" v-if="selectedReceipt.notes">
            <div class="receipt-label q-mb-xs">NOTES</div>
            <div class="receipt-notes">{{ selectedReceipt.notes }}</div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="receipt-actions">
          <q-btn label="Close" flat @click="showReceiptDialog = false" />
          <q-btn 
            label="Save as PDF" 
            icon="picture_as_pdf" 
            color="primary"
            @click="saveReceiptAsPDF"
            :loading="pdfLoading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { onMounted, ref, computed } from 'vue';
import api from '../api';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

const $q = useQuasar();
const receipts = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const distributorOptions = ref([]);
const committedProducts = ref([]);
const committedProductOptions = ref([]);
const allProducts = ref([]);

// Receipt preview
const showReceiptDialog = ref(false);
const selectedReceipt = ref(null);
const pdfLoading = ref(false);
const companySettings = ref({});

// Client-related refs
const clientOptions = ref([]);
const allClients = ref([]);
const searchingClients = ref(false);
const showAddClientDialog = ref(false);
const savingClient = ref(false);
const newClientForm = ref({
  name: '',
  phone: '',
  address: '',
});

const form = ref({
  receipt_number: '',
  distributor_id: null,
  client_id: null,
  receipt_date: new Date().toISOString().split('T')[0],
  receipt_image: null,
  items: [],
});

const columns = [
  { name: 'receipt_number', label: 'Receipt # (رقم السند)', field: 'receipt_number', align: 'left', sortable: true },
  { name: 'distributor', label: 'Distributor (الموزع)', field: 'distributor_id', align: 'left', sortable: true },
  { name: 'client', label: 'Client (العميل)', field: 'client_id', align: 'left', sortable: true },
  { name: 'date', label: 'Date (التاريخ)', field: 'receipt_date', align: 'left', sortable: true },
  { name: 'items_count', label: '# Items', field: row => row.details?.length || 0, align: 'center', sortable: true },
  { name: 'total', label: 'Total (المجموع)', field: row => calculateReceiptTotal(row), align: 'right', sortable: true },
  { name: 'image', label: 'Image (الصورة)', field: 'receipt_image_path', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

const itemColumns = [
  { name: 'product', label: 'Product (المنتج)', field: 'product_id', align: 'left' },
  { name: 'quantity', label: 'Quantity Sold (الكمية المباعة)', field: 'quantity', align: 'left' },
  { name: 'price_type', label: 'Price Type (نوع السعر)', field: 'price_type_used', align: 'left' },
  { name: 'unit_price', label: 'Unit Price (سعر الوحدة)', field: 'selling_price', align: 'right' },
  { name: 'total_price', label: 'Total (المجموع)', field: row => (row.quantity * row.selling_price).toFixed(2), align: 'right' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

// Computed property for grand total
const grandTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.selling_price);
  }, 0);
});

const priceTypeOptions = [
  { label: 'Wholesale (جملة)', value: 'wholesale' },
  { label: 'Semi-Wholesale (نصف جملة)', value: 'semi_wholesale' },
  { label: 'Retail (تقسيط)', value: 'retail' },
];

const loadReceipts = async () => {
  loading.value = true;
  try {
    const response = await api.get('/sales');
    receipts.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load receipts' });
  } finally {
    loading.value = false;
  }
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/distributors');
    distributorOptions.value = response.data;
  } catch (error) {
    console.error('Failed to load distributors:', error);
    $q.notify({ type: 'negative', message: 'Failed to load distributors' });
  }
};

const loadClients = async () => {
  try {
    const response = await api.get('/clients');
    allClients.value = response.data;
    clientOptions.value = response.data;
  } catch (error) {
    console.error('Failed to load clients:', error);
  }
};

const filterClients = async (val, update, abort) => {
  if (val.length < 1) {
    update(() => {
      clientOptions.value = allClients.value;
    });
    return;
  }

  searchingClients.value = true;
  try {
    const response = await api.get('/clients/search', { params: { q: val } });
    update(() => {
      clientOptions.value = response.data;
    });
  } catch (error) {
    console.error('Failed to search clients:', error);
    update(() => {
      clientOptions.value = allClients.value.filter(
        (c) => c.name.toLowerCase().includes(val.toLowerCase())
      );
    });
  } finally {
    searchingClients.value = false;
  }
};

const handleClientSelection = (clientId) => {
  form.value.client_id = clientId;
};

const openAddClientDialog = (initialName = '') => {
  newClientForm.value = {
    name: initialName,
    phone: '',
    address: '',
  };
  showAddClientDialog.value = true;
};

const saveNewClient = async () => {
  if (!newClientForm.value.name) {
    $q.notify({ type: 'negative', message: 'Client name is required' });
    return;
  }

  savingClient.value = true;
  try {
    const response = await api.post('/clients', newClientForm.value);
    const newClient = response.data;
    
    // Add to clients list and select it
    allClients.value.unshift(newClient);
    clientOptions.value = allClients.value;
    form.value.client_id = newClient.id;
    
    showAddClientDialog.value = false;
    $q.notify({ type: 'positive', message: 'Client added successfully' });
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to add client';
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    savingClient.value = false;
  }
};

const loadCommittedProducts = async () => {
  if (!form.value.distributor_id) {
    committedProducts.value = [];
    committedProductOptions.value = [];
    return;
  }

  try {
    const response = await api.get('/distribution-orders/committed-products', {
      params: { distributor_id: form.value.distributor_id },
    });
    committedProducts.value = response.data;
    committedProductOptions.value = response.data;
    
    // Also load all products for price lookup
    const productsResponse = await api.get('/products');
    allProducts.value = productsResponse.data;
  } catch (error) {
    console.error('Failed to load committed products:', error);
    $q.notify({ type: 'negative', message: 'Failed to load committed products' });
  }
};

const filterProducts = (val, update) => {
  if (val === '') {
    update(() => {
      committedProductOptions.value = committedProducts.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    committedProductOptions.value = committedProducts.value.filter(
      (p) => p.name.toLowerCase().indexOf(needle) > -1
    );
  });
};

const getCommittedQuantityForProduct = (productId) => {
  if (!productId) return 0;
  const product = committedProducts.value.find((p) => p.id === productId);
  return product?.committed_quantity || 0;
};

const getProductPrice = (productId, priceType) => {
  if (!productId) return 0;
  
  // First try to find in committedProducts (which already has currentPrice)
  let product = committedProducts.value.find((p) => p.id === productId);
  
  // If not found, try allProducts
  if (!product) {
    product = allProducts.value.find((p) => p.id === productId);
  }
  
  if (!product?.currentPrice) return 0;

  switch (priceType) {
    case 'wholesale':
      return parseFloat(product.currentPrice.wholesale_price || 0);
    case 'semi_wholesale':
      return parseFloat(product.currentPrice.semi_wholesale_price || 0);
    case 'retail':
      return parseFloat(product.currentPrice.retail_price || 0);
    default:
      return 0;
  }
};

const handleProductSelection = (item, productId) => {
  item.product_id = productId;
  // Update price when product is selected
  updatePriceForItem(item);
};

const updatePriceForItem = (item) => {
  if (!item.product_id || !item.price_type_used) return;
  
  const newPrice = getProductPrice(item.product_id, item.price_type_used);
  item.selling_price = newPrice;
};


const validateQuantity = (item) => {
  const maxQuantity = getCommittedQuantityForProduct(item.product_id);
  if (item.quantity > maxQuantity) {
    item.quantity = maxQuantity;
    $q.notify({
      type: 'warning',
      message: `Quantity cannot exceed committed quantity (${formatQuantity(maxQuantity)})`,
      timeout: 2000,
    });
  }
};

const formatQuantity = (quantity) => {
  if (!quantity && quantity !== 0) return '0';
  return parseFloat(quantity).toFixed(2);
};

const openDialog = async () => {
  // Reset editing state
  isEditing.value = false;
  editingId.value = null;
  
  // Get next receipt number from API
  let nextReceiptNumber = '';
  try {
    const response = await api.get('/sales/next-receipt-number');
    nextReceiptNumber = response.data.receipt_number;
  } catch (error) {
    console.error('Failed to get next receipt number:', error);
    $q.notify({ type: 'negative', message: 'Failed to get next receipt number' });
    return; // Don't open dialog if we can't get receipt number
  }

  form.value = {
    receipt_number: nextReceiptNumber,
    distributor_id: null,
    client_id: null,
    receipt_date: new Date().toISOString().split('T')[0],
    receipt_image: null,
    items: [],
  };
  committedProducts.value = [];
  committedProductOptions.value = [];
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    receipt_number: '',
    distributor_id: null,
    client_id: null,
    receipt_date: new Date().toISOString().split('T')[0],
    receipt_image: null,
    items: [],
  };
  committedProducts.value = [];
  committedProductOptions.value = [];
};

const addItem = () => {
  form.value.items.push({
    id: Date.now(),
    product_id: null,
    quantity: 0,
    selling_price: 0,
    price_type_used: 'wholesale',
  });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

const saveSale = async () => {
  saving.value = true;
  try {
    // Validate form
    if (!form.value.distributor_id) {
      $q.notify({ type: 'negative', message: 'Please select a distributor' });
      return;
    }

    if (!form.value.client_id) {
      $q.notify({ type: 'negative', message: 'Please select a customer' });
      return;
    }

    if (!form.value.receipt_number) {
      $q.notify({ type: 'negative', message: 'Receipt number is required' });
      return;
    }

    // Filter valid items
    const validItems = form.value.items.filter(
      (item) => item.product_id && item.quantity > 0 && item.selling_price > 0
    );

    if (validItems.length === 0) {
      $q.notify({ type: 'negative', message: 'Please add at least one item with valid quantity and price' });
      return;
    }

    const formData = new FormData();
    formData.append('receipt_number', form.value.receipt_number);
    formData.append('distributor_id', form.value.distributor_id);
    formData.append('client_id', form.value.client_id);
    formData.append('receipt_date', form.value.receipt_date);
    
    if (form.value.receipt_image) {
      formData.append('receipt_image', form.value.receipt_image);
    }
    
    formData.append('items', JSON.stringify(validItems));

    if (isEditing.value) {
      // Update existing receipt
      formData.append('_method', 'PUT');
      await api.post(`/sales/${editingId.value}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: 'Sales receipt updated successfully' });
    } else {
      // Create new receipt
      await api.post('/sales', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: 'Sales receipt created successfully' });
    }
    
    closeDialog();
    loadReceipts();
  } catch (error) {
    console.error('Error saving sales receipt:', error);
    const errorMessage = error.response?.data?.message || error.response?.data?.error || 'Failed to save sales receipt';
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    saving.value = false;
  }
};

const viewImage = (path) => {
  window.open(`http://localhost:8000/storage/${path}`, '_blank');
};

const calculateReceiptTotal = (receipt) => {
  if (!receipt.details || receipt.details.length === 0) return 0;
  return receipt.details.reduce((sum, detail) => {
    return sum + (parseFloat(detail.quantity) * parseFloat(detail.selling_price));
  }, 0);
};

const formatDate = (date) => {
  if (!date) return '';
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getFileIcon = (path) => {
  if (!path) return 'attach_file';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'picture_as_pdf';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
  return 'attach_file';
};

const getFileIconColor = (path) => {
  if (!path) return 'grey';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'red';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'blue';
  return 'grey';
};

const getFileTooltip = (path) => {
  if (!path) return 'View File';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'View PDF';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'View Image';
  return 'View File';
};

const viewReceipt = async (receipt) => {
  loading.value = true;
  try {
    // Fetch full receipt details with relationships
    const response = await api.get(`/sales/${receipt.id}`);
    selectedReceipt.value = response.data;
    
    // Ensure company settings are loaded
    if (!companySettings.value.company_name) {
      await loadSettings();
    }
    
    showReceiptDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load receipt details' });
  } finally {
    loading.value = false;
  }
};

const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    companySettings.value = response.data;
  } catch (error) {
    console.error('Failed to load settings:', error);
  }
};

const getLogoUrl = (logoPath) => {
  if (!logoPath) return '';
  // Use same origin to avoid CORS issues
  return `/storage/${logoPath}`;
};

const getPriceTypeLabel = (priceType) => {
  switch (priceType) {
    case 'wholesale': return 'Wholesale';
    case 'semi_wholesale': return 'Semi-Wholesale';
    case 'retail': return 'Retail';
    default: return priceType;
  }
};

const getPriceTypeColor = (priceType) => {
  switch (priceType) {
    case 'wholesale': return 'green';
    case 'semi_wholesale': return 'orange';
    case 'retail': return 'blue';
    default: return 'grey';
  }
};

const saveReceiptAsPDF = async () => {
  if (!selectedReceipt.value) return;
  
  pdfLoading.value = true;
  try {
    const receiptContent = document.getElementById('receipt-content');
    if (!receiptContent) {
      $q.notify({ type: 'negative', message: 'Receipt content not found' });
      return;
    }

    // Show loading notification
    $q.notify({
      type: 'info',
      message: 'Generating PDF...',
      timeout: 2000
    });

    // A4 dimensions in mm
    const pageWidth = 210;
    const pageHeight = 297;
    const marginTop = 20;
    const marginBottom = 20;
    const marginLeft = 15;
    const marginRight = 15;
    const footerHeight = 10;
    
    const contentWidth = pageWidth - marginLeft - marginRight;
    
    // Create PDF
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    // Get sections
    const headerSection = receiptContent.querySelector('.receipt-header-section');
    const infoSection = receiptContent.querySelector('.receipt-info-section');
    const itemsTable = receiptContent.querySelector('.receipt-table');
    const totalsSection = receiptContent.querySelector('.receipt-totals-section');
    const notesSection = receiptContent.querySelector('.receipt-notes-section');
    
    let currentY = marginTop;
    let currentPage = 1;
    
    // Function to add page number
    const addPageNumber = (page, total) => {
      pdf.setFillColor(255, 255, 255);
      pdf.rect(pageWidth - marginRight - 35, pageHeight - 16, 35, 12, 'F');
      pdf.setFontSize(11);
      pdf.setTextColor(40, 40, 40);
      pdf.setFont('helvetica', 'bold');
      const pageText = page + ' / ' + total;
      const textWidth = pdf.getTextWidth(pageText);
      pdf.text(pageText, pageWidth - marginRight - textWidth, pageHeight - 10);
    };
    
    // Function to check if we need a new page
    const checkNewPage = (requiredHeight) => {
      if (currentY + requiredHeight > pageHeight - marginBottom - footerHeight) {
        pdf.addPage();
        currentPage++;
        currentY = marginTop;
        return true;
      }
      return false;
    };
    
    // Capture and add header section
    if (headerSection) {
      const headerCanvas = await html2canvas(headerSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const headerImg = headerCanvas.toDataURL('image/png');
      const headerHeight = (headerCanvas.height * contentWidth) / headerCanvas.width;
      
      checkNewPage(headerHeight);
      pdf.addImage(headerImg, 'PNG', marginLeft, currentY, contentWidth, headerHeight);
      currentY += headerHeight + 5;
    }
    
    // Capture and add info section
    if (infoSection) {
      const infoCanvas = await html2canvas(infoSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const infoImg = infoCanvas.toDataURL('image/png');
      const infoHeight = (infoCanvas.height * contentWidth) / infoCanvas.width;
      
      checkNewPage(infoHeight);
      pdf.addImage(infoImg, 'PNG', marginLeft, currentY, contentWidth, infoHeight);
      currentY += infoHeight + 5;
    }
    
    // Capture and add items table
    if (itemsTable) {
      const tableCanvas = await html2canvas(itemsTable.closest('.receipt-items-section') || itemsTable, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const tableImg = tableCanvas.toDataURL('image/png');
      const tableHeight = (tableCanvas.height * contentWidth) / tableCanvas.width;
      
      checkNewPage(tableHeight);
      pdf.addImage(tableImg, 'PNG', marginLeft, currentY, contentWidth, tableHeight);
      currentY += tableHeight + 5;
    }
    
    // Capture and add totals section
    if (totalsSection) {
      const totalsCanvas = await html2canvas(totalsSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const totalsImg = totalsCanvas.toDataURL('image/png');
      const totalsHeight = (totalsCanvas.height * contentWidth) / totalsCanvas.width;
      
      checkNewPage(totalsHeight);
      pdf.addImage(totalsImg, 'PNG', marginLeft, currentY, contentWidth, totalsHeight);
      currentY += totalsHeight + 5;
    }
    
    // Capture and add notes section if exists
    if (notesSection) {
      const notesCanvas = await html2canvas(notesSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const notesImg = notesCanvas.toDataURL('image/png');
      const notesHeight = (notesCanvas.height * contentWidth) / notesCanvas.width;
      
      checkNewPage(notesHeight);
      pdf.addImage(notesImg, 'PNG', marginLeft, currentY, contentWidth, notesHeight);
    }
    
    // Add page numbers to all pages
    const totalPages = currentPage;
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      addPageNumber(i, totalPages);
    }
    
    // Save the PDF
    pdf.save('sales_receipt_' + selectedReceipt.value.receipt_number + '.pdf');
    
    $q.notify({
      type: 'positive',
      message: 'PDF saved successfully'
    });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({ type: 'negative', message: 'Failed to generate PDF' });
  } finally {
    pdfLoading.value = false;
  }
};

const editReceipt = async (receipt) => {
  isEditing.value = true;
  editingId.value = receipt.id;
  
  // Set form values from receipt
  form.value = {
    receipt_number: receipt.receipt_number,
    distributor_id: receipt.distributor_id,
    client_id: receipt.client_id,
    receipt_date: formatDate(receipt.receipt_date),
    receipt_image: null, // Can't pre-fill file input
    items: [],
  };
  
  // Load committed products for this distributor
  await loadCommittedProducts();
  
  // Also load all products for items that might not be in committed list anymore
  try {
    const productsResponse = await api.get('/products');
    allProducts.value = productsResponse.data;
  } catch (error) {
    console.error('Failed to load products:', error);
  }
  
  // Map receipt details to form items
  if (receipt.details && receipt.details.length > 0) {
    form.value.items = receipt.details.map(detail => ({
      id: detail.id,
      product_id: detail.product_id,
      quantity: parseFloat(detail.quantity),
      selling_price: parseFloat(detail.selling_price),
      price_type_used: detail.price_type_used,
    }));
  }
  
  showDialog.value = true;
};

const confirmDelete = (receipt) => {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete receipt ${receipt.receipt_number}?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/sales/${receipt.id}`);
      $q.notify({ type: 'positive', message: 'Receipt deleted successfully' });
      loadReceipts();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete receipt' });
    }
  });
};

onMounted(() => {
  loadReceipts();
  loadDistributors();
  loadClients();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

/* Receipt Preview Styles - Similar to Purchase Invoice */
.receipt-content {
  background: #ffffff;
  padding: 40px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.receipt-header-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #1976d2;
}

.receipt-label {
  font-size: 10px;
  color: #666;
  letter-spacing: 1px;
  text-transform: uppercase;
  font-weight: 600;
}

.receipt-value {
  font-size: 14px;
  color: #333;
  font-weight: 600;
}

.company-name {
  font-size: 16px;
  color: #1976d2;
}

.receipt-info-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e0e0e0;
}

.receipt-info {
  font-size: 13px;
  line-height: 1.6;
  color: #333;
}

.receipt-items-section {
  margin-bottom: 20px;
}

.receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.receipt-table thead tr {
  background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  color: white;
}

.receipt-table th {
  padding: 12px 15px;
  text-align: left;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.receipt-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e0e0e0;
}

.receipt-table tbody tr:nth-child(even) {
  background: #f8f9fa;
}

.receipt-totals-section {
  margin-top: 20px;
  padding-top: 20px;
}

.totals-box {
  background: #f8f9fa;
  padding: 15px 25px;
  border-radius: 8px;
  min-width: 280px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
  font-size: 14px;
}

.total-row:last-child {
  border-bottom: none;
}

.total-label {
  color: #666;
  font-weight: 500;
}

.total-value {
  font-weight: 600;
  color: #333;
}

.total-value.final {
  font-size: 18px;
  font-weight: bold;
  color: #1976d2;
}

.receipt-notes-section {
  margin-top: 30px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
}

.receipt-notes {
  font-size: 13px;
  color: #333;
  line-height: 1.6;
}

.receipt-actions {
  border-top: 1px solid #e0e0e0;
  padding: 15px 20px;
}

@media print {
  .receipt-content {
    padding: 20px;
  }
}
</style>
