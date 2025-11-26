<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Purchase Invoices</div>
      <div class="row q-gutter-sm">
        <q-input
          v-model="searchText"
          outlined
          dense
          placeholder="Search invoices..."
          style="min-width: 250px"
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-btn color="secondary" icon="download" flat dense @click="exportToExcel">
          <q-tooltip>Export to Excel</q-tooltip>
        </q-btn>
        <q-btn color="grey-7" icon="more_vert" flat dense>
          <q-tooltip>Options</q-tooltip>
          <q-menu>
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup @click="toggleFilterMismatch">
                <q-item-section avatar>
                  <q-icon :name="filterMismatchOnly ? 'check_box' : 'check_box_outline_blank'" color="primary" />
                </q-item-section>
                <q-item-section>Show only mismatches</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn color="primary" icon="add" label="New Purchase" @click="showDialog = true" />
      </div>
    </div>

    <q-table
      :rows="filteredInvoices"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      v-model:pagination="pagination"
    >
      <template v-slot:body-cell-supplier="props">
        <q-td :props="props">
          {{ props.row.supplier?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-date="props">
        <q-td :props="props">
          {{ props.row.invoice_date ? new Date(props.row.invoice_date).toLocaleDateString('en-GB') : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-total_calculated="props">
        <q-td :props="props">
          {{ props.row.total_amount ? parseFloat(props.row.total_amount).toFixed(2) + ' DH' : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-total="props">
        <q-td :props="props">
          {{ (props.row.total_in_invoice || props.row.total_amount) ? parseFloat(props.row.total_in_invoice || props.row.total_amount).toFixed(2) + ' DH' : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-items_count="props">
        <q-td :props="props">
          {{ props.row.details_count || 0 }}
        </q-td>
      </template>
      <template v-slot:body-cell-image="props">
        <q-td :props="props">
          <q-btn 
            v-if="props.row.invoice_image_path" 
            round 
            dense 
            :icon="getFileIcon(props.row.invoice_image_path)" 
            :color="getFileColor(props.row.invoice_image_path)"
            size="sm"
            @click="viewImage(props.row.invoice_image_path)"
          >
            <q-tooltip>{{ getFileTooltip(props.row.invoice_image_path) }}</q-tooltip>
          </q-btn>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center q-gutter-xs">   
            <q-btn flat dense icon="visibility" color="positive" @click="viewDetails(props.row)">
              <q-tooltip>View Details</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="edit" color="primary" @click="editInvoice(props.row)">
              <q-tooltip>Edit Invoice</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>Delete Invoice</q-tooltip>
            </q-btn>
            <q-btn 
              flat 
              dense 
              icon="attach_file" 
              color="primary" 
              @click="openAttachFileDialog(props.row)"
            >
              <q-tooltip>Attach Invoice Document</q-tooltip>
            </q-btn>
            <q-icon 
              v-if="props.row.it_has_def" 
              name="warning" 
              color="warning" 
              size="md"
            >
              <q-tooltip>Total mismatch detected!</q-tooltip>
            </q-icon>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- Add Purchase Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 700px; max-width: 95vw;">
        <!-- Header with Warning -->
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">New Purchase Invoice</div>
        </q-card-section>


        <q-card-section class="q-pa-md">
          <q-form @submit.prevent="handleSaveAttempt">
            <!-- Section 1: Invoice Information -->
            <div class="section-header compact">
              <q-icon name="receipt" size="sm" class="q-mr-xs" />
              <span class="text-subtitle2 text-weight-bold">Invoice Information</span>
            </div>
            <q-separator class="q-mb-xs" />
            
            <div class="row q-col-gutter-xs q-mb-md">
              <div class="col-4">
                <q-select
                  v-model="form.supplier_id"
                  :options="supplierOptions"
                  option-value="value"
                  option-label="label"
                  label="Supplier *"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  @filter="filterSuppliers"
                  :rules="[val => !!val || 'Required']"
                >
                  <template v-slot:prepend>
                    <q-icon name="business" size="xs" />
                  </template>
                  <template v-slot:no-option>
                    <q-item clickable @click="openSupplierDialog">
                      <q-item-section class="text-primary">
                        <q-icon name="add" /> Add New Supplier
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-3">
                <q-input 
                  v-model="form.invoice_number" 
                  label="Invoice Number *" 
                  outlined 
                  dense 
                  :rules="[val => !!val || 'Required']"
                >
                  <template v-slot:prepend>
                    <q-icon name="tag" size="xs" />
                  </template>
                </q-input>
              </div>
              <div class="col-2-5">
                <q-input 
                  v-model="form.invoice_date" 
                  label="Date *" 
                  type="date" 
                  outlined 
                  dense 
                  :rules="[val => !!val || 'Required']"
                >
                  <template v-slot:prepend>
                    <q-icon name="event" size="xs" />
                  </template>
                </q-input>
              </div>
              <div class="col-2-5">
                <q-input
                  v-model.number="form.manual_total"
                  label="Total *"
                  type="number"
                  outlined
                  dense
                  step="0.01"
                  suffix="DH"
                  :rules="[val => val > 0 || 'Required']"
                >
                  <template v-slot:prepend>
                    <q-icon name="payments" size="xs" />
                  </template>
                </q-input>
              </div>
            </div>

            <!-- Section 2: Products List -->
            <div class="section-header">
              <q-icon name="inventory_2" size="sm" class="q-mr-sm" />
              <span class="text-subtitle1 text-weight-bold">Products</span>
            </div>
            <q-separator class="q-mb-md" />
            
            <div class="products-section q-mb-lg">
              <div v-for="(item, index) in form.items" :key="index" class="row q-col-gutter-sm q-mb-sm">
                <div class="col-4">
                  <q-select
                    v-model="item.product_id"
                    :options="productOptions"
                    option-value="value"
                    option-label="label"
                    label="Product"
                    outlined
                    dense
                    emit-value
                    map-options
                    use-input
                    @filter="filterProducts"
                  >
                    <template v-slot:no-option>
                      <q-item clickable @click="openProductDialog">
                        <q-item-section class="text-primary">
                          <q-icon name="add" /> Add New Product
                        </q-item-section>
                      </q-item>
                    </template>
                  </q-select>
                </div>
                <div class="col-3">
                  <q-input 
                    v-model.number="item.quantity" 
                    label="Quantity" 
                    type="number" 
                    outlined 
                    dense 
                    min="0"
                    step="0.01"
                  />
                </div>
                <div class="col-3">
                  <q-input 
                    v-model.number="item.purchase_price" 
                    label="Price (DH)" 
                    type="number" 
                    outlined 
                    dense 
                    step="0.01"
                    min="0"
                  />
                </div>
                <div class="col-2 flex items-center justify-center">
                  <q-btn 
                    flat 
                    dense 
                    round
                    icon="delete" 
                    color="negative" 
                    @click="removeItem(index)"
                    :disable="form.items.length === 1"
                  >
                    <q-tooltip>Remove Item</q-tooltip>
                  </q-btn>
                </div>
              </div>

              <div class="row q-mt-sm">
                <q-btn 
                  flat 
                  color="primary" 
                  icon="add" 
                  label="Add Item" 
                  @click="addItem" 
                  size="sm"
                />
              </div>
            </div>

            <!-- Section 3: Total & Notes -->
            <div class="section-header compact">
              <q-icon name="calculate" size="sm" class="q-mr-xs" />
              <span class="text-subtitle2 text-weight-bold">Calculated Total & Notes</span>
            </div>
            <q-separator class="q-mb-xs" />
            
            <div class="row q-col-gutter-xs">
              <div class="col-3">
                <div class="q-pa-xs bg-grey-2 rounded-borders" style="height: 100%;">
                  <div class="text-caption text-grey-7">Calculated Total</div>
                  <div class="text-h6 text-weight-bold">
                    {{ calculatedTotal.toFixed(2) }} DH
                  </div>
                </div>
              </div>
              <div class="col-9">
                <q-input 
                  v-model="form.notes" 
                  label="Notes" 
                  outlined 
                  dense 
                  type="textarea" 
                  rows="2"
                >
                  <template v-slot:prepend>
                    <q-icon name="notes" size="xs" />
                  </template>
                </q-input>
              </div>
            </div>

            <!-- Mismatch handling toggle -->
            <div class="row q-mt-md q-mb-sm">
              <q-toggle
                v-model="allowMismatch"
                label="Save despite total difference"
                color="warning"
                dense
              />
            </div>

            <!-- Action Buttons -->
            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn 
                type="submit" 
                label="Save" 
                color="primary" 
                :loading="saving"
                icon="save"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Quick Add Product Dialog -->
    <q-dialog v-model="showProductDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Quick Add Product</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveQuickProduct">
            <q-input v-model="productForm.name" label="Product Name *" outlined dense class="q-mb-md" :rules="[val => !!val || 'Required']" />
            <q-input v-model="productForm.product_code" label="Product Code" outlined dense class="q-mb-md" />
            <q-input v-model="productForm.barcode" label="Barcode" outlined dense class="q-mb-md" />
            <category-select v-model="productForm.category_id" label="Category" />
            <q-input v-model="productForm.unit_of_measure" label="Unit" outlined dense class="q-mb-md" />
            <q-input v-model.number="productForm.current_stock_quantity" label="Initial Stock" type="number" outlined dense class="q-mb-md" />
            
            <div class="text-subtitle2 q-mb-sm">Pricing</div>
            <div class="row q-col-gutter-sm q-mb-md">
              <div class="col-3">
                <q-input v-model.number="productForm.wholesale_price" label="Wholesale" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <q-input v-model.number="productForm.semi_wholesale_price" label="Semi-Wholesale" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <q-input v-model.number="productForm.retail_price" label="Retail" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <TaxSelect v-model="productForm.tax_rate" label="Tax" />
              </div>
            </div>

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showProductDialog = false" />
              <q-btn type="submit" label="Save & Select" color="secondary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Quick Add Supplier Dialog -->
    <q-dialog v-model="showSupplierDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Quick Add Supplier</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveQuickSupplier">
            <q-input v-model="supplierForm.name" label="Supplier Name *" outlined dense class="q-mb-md" :rules="[val => !!val || 'Required']" />
            <q-input v-model="supplierForm.contact_person" label="Contact Person" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.phone" label="Phone" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.email" label="Email" type="email" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.address" label="Address" outlined dense type="textarea" rows="2" class="q-mb-md" />
            <q-input v-model="supplierForm.tax_id" label="Tax ID" outlined dense class="q-mb-md" />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showSupplierDialog = false" />
              <q-btn type="submit" label="Save & Select" color="secondary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Price Update Dialog -->
    <q-dialog v-model="showPriceDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-warning text-white">
          <div class="text-h6">Price Updates Required</div>
        </q-card-section>

        <q-card-section>
          <div v-for="update in priceUpdates" :key="update.product_id" class="q-mb-md">
            <div class="text-subtitle1">{{ update.product_name }}</div>
            <div class="text-caption">New purchase cost: {{ update.new_purchase_cost }} DH</div>
            <div class="row q-col-gutter-sm q-mt-sm">
              <div class="col-4">
                <q-input 
                  v-model.number="update.wholesale_price" 
                  label="Wholesale" 
                  type="number" 
                  outlined 
                  dense 
                  step="0.01" 
                  @update:model-value="val => calculateUpdatePrices(val, update)"
                />
              </div>
              <div class="col-4">
                <q-input v-model.number="update.semi_wholesale_price" label="Semi-Wholesale" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-4">
                <q-input v-model.number="update.retail_price" label="Retail" type="number" outlined dense step="0.01" />
              </div>
            </div>
          </div>

          <div class="row justify-end q-gutter-sm q-mt-md">
            <q-btn label="Skip" flat @click="showPriceDialog = false" />
            <q-btn label="Update Prices" color="warning" @click="updatePrices" :loading="saving" />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Invoice Details Dialog -->
    <q-dialog v-model="showDetailsDialog">
      <q-card style="min-width: 700px; max-width: 90vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">Invoice Details</div>
        </q-card-section>

        <q-card-section v-if="selectedInvoice">
          <div class="row q-col-gutter-md q-mb-md">
            <div class="col-6">
              <div class="text-caption text-grey-7">Invoice Number</div>
              <div class="text-body1">{{ selectedInvoice.invoice_number }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey-7">Date</div>
              <div class="text-body1">{{ selectedInvoice.invoice_date }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey-7">Supplier</div>
              <div class="text-body1">{{ selectedInvoice.supplier?.name || 'N/A' }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey-7">Total Amount</div>
              <div class="text-body1 text-weight-bold">{{ selectedInvoice.total_amount ? selectedInvoice.total_amount.toFixed(2) + ' DH' : 'N/A' }}</div>
            </div>
            <div class="col-12" v-if="selectedInvoice.notes">
              <div class="text-caption text-grey-7">Notes</div>
              <div class="text-body1">{{ selectedInvoice.notes }}</div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 q-mb-sm">Items ({{ selectedInvoice.details_count || 0 }})</div>
          <q-table
            v-if="selectedInvoice.details"
            :rows="selectedInvoice.details"
            :columns="detailsColumns"
            row-key="id"
            flat
            bordered
            dense
            hide-pagination
            :rows-per-page-options="[0]"
          >
            <template v-slot:body-cell-product="props">
              <q-td :props="props">
                {{ props.row.product?.name || 'N/A' }}
              </q-td>
            </template>
            <template v-slot:body-cell-unit="props">
              <q-td :props="props">
                {{ props.row.product?.unit?.unit_name_ar || 'N/A' }}
              </q-td>
            </template>
            <template v-slot:body-cell-subtotal="props">
              <q-td :props="props">
                {{ (props.row.quantity * props.row.purchase_price).toFixed(2) }} DH
              </q-td>
            </template>
          </q-table>

          <div v-if="selectedInvoice.invoice_image_path" class="q-mt-md">
            <div class="text-caption text-grey-7 q-mb-sm">Invoice Image</div>
            <q-btn color="primary" icon="image" label="View Image" @click="viewImage(selectedInvoice.invoice_image_path)" />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn label="Close" flat @click="showDetailsDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Attach File Dialog -->
    <q-dialog v-model="showAttachDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Attach Invoice Document</div>
          <div class="text-caption text-grey-7">
            Invoice #{{ selectedInvoiceForAttach?.invoice_number }}
          </div>
        </q-card-section>

        <q-card-section>
          <q-file
            v-model="attachedFile"
            outlined
            label="Select PDF or Image"
            accept="image/*,application/pdf"
            max-file-size="5242880"
            @rejected="onFileRejected"
          >
            <template v-slot:prepend>
              <q-icon name="attach_file" />
            </template>
          </q-file>
          <div class="text-caption text-grey-7 q-mt-sm">
            Maximum file size: 5MB
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn label="Cancel" flat @click="closeAttachDialog" />
          <q-btn 
            label="Upload" 
            color="primary" 
            :disable="!attachedFile"
            :loading="saving"
            @click="uploadInvoiceFile" 
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
import CategorySelect from '../components/CategorySelect.vue';
import TaxSelect from '../components/TaxSelect.vue';


const $q = useQuasar();
const invoices = ref([]);
const products = ref([]);
const suppliers = ref([]);
const units = ref([]);
const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const showProductDialog = ref(false);
const showSupplierDialog = ref(false);
const showPriceDialog = ref(false);
const showAttachDialog = ref(false);
const productOptions = ref([]);
const supplierOptions = ref([]);
const allProducts = ref([]);
const allSuppliers = ref([]);
const priceUpdates = ref([]);
const selectedInvoiceForAttach = ref(null);
const attachedFile = ref(null);
const settings = ref({
  semi_wholesale_percentage: 0,
  retail_percentage: 0,
});

const form = ref({
  supplier_id: null,
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0], // Default to today
  invoice_image: null,
  notes: '',
  manual_total: 0,
  items: [{ product_id: null, quantity: 1, purchase_price: 0 }],
});

const productForm = ref({
  name: '',
  product_description: '',
  unit_of_measure: '',
  current_stock_quantity: 0,
  product_code: '',
  barcode: '',
  cmup_cost: 0,
  tax_rate: 0,
  category_id: null,
  wholesale_price: 0,
  semi_wholesale_price: 0,
  retail_price: 0,
});

const supplierForm = ref({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  address: '',
  tax_id: '',
  notes: '',
});

const searchText = ref('');
const pagination = ref({
  rowsPerPage: 10
});
const showDetailsDialog = ref(false);
const selectedInvoice = ref(null);

const columns = [
  { name: 'invoice_number', label: 'Invoice #', field: 'invoice_number', align: 'left', sortable: true },
  { name: 'supplier', label: 'Supplier', align: 'left', sortable: true },
  { name: 'date', label: 'Date', field: 'invoice_date', align: 'left', sortable: true },
  { name: 'total_calculated', label: 'Total Calculated', align: 'right', sortable: true },
  { name: 'total', label: 'Total in Invoice', align: 'right', sortable: true },
  { name: 'items_count', label: 'Items', align: 'center', sortable: true },
  { name: 'image', label: 'Image', align: 'center' },
  { name: 'actions', label: 'Actions', align: 'center' },
];

const detailsColumns = [
  { name: 'product', label: 'Product', align: 'left' },
  { name: 'unit', label: 'Unit', align: 'left' },
  { name: 'quantity', label: 'Quantity', field: 'quantity', align: 'center' },
  { name: 'purchase_price', label: 'Price', field: 'purchase_price', align: 'right', format: val => `${val.toFixed(2)} DH` },
  { name: 'subtotal', label: 'Subtotal', align: 'right' },
];

const calculatedTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.purchase_price);
  }, 0);
});

const totalDifference = computed(() => {
  if (!form.value.manual_total) return 0;
  return form.value.manual_total - calculatedTotal.value;
});

const totalDifferenceClass = computed(() => {
  if (totalDifference.value === 0 && form.value.manual_total > 0) {
    return 'text-positive';
  } else if (totalDifference.value !== 0) {
    return 'text-warning';
  }
  return '';
});

const filterMismatchOnly = ref(false);

const filteredInvoices = computed(() => {
  let result = invoices.value;
  
  // Apply mismatch filter
  if (filterMismatchOnly.value) {
    result = result.filter(invoice => invoice.it_has_def === true);
  }
  
  // Apply search filter
  if (searchText.value) {
    const needle = searchText.value.toLowerCase();
    result = result.filter(invoice => {
      return (
        invoice.invoice_number?.toLowerCase().includes(needle) ||
        invoice.supplier?.name?.toLowerCase().includes(needle) ||
        invoice.invoice_date?.toLowerCase().includes(needle)
      );
    });
  }
  
  return result;
});

const loadInvoices = async () => {
  loading.value = true;
  try {
    const response = await api.get('/purchases');
    invoices.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load invoices' });
  } finally {
    loading.value = false;
  }
};

const toggleFilterMismatch = () => {
  filterMismatchOnly.value = !filterMismatchOnly.value;
};

const loadProducts = async () => {
  try {
    const response = await api.get('/products');
    products.value = response.data;
    allProducts.value = response.data;
    productOptions.value = response.data.map(p => ({ label: p.name, value: p.id }));
  } catch (error) {
    console.error('Failed to load products');
  }
};

const loadSuppliers = async () => {
  try {
    const response = await api.get('/suppliers');
    suppliers.value = response.data;
    allSuppliers.value = response.data;
    supplierOptions.value = response.data.map(s => ({ label: s.name, value: s.id }));
  } catch (error) {
    console.error('Failed to load suppliers');
  }
};

const loadCategories = async () => {
  try {
    const response = await api.get('/categories');
    categories.value = response.data;
  } catch (error) {
    console.error('Failed to load categories');
  }
};

const filterProducts = (val, update) => {
  if (val === '') {
    update(() => {
      productOptions.value = allProducts.value.map(p => ({ label: p.name, value: p.id }));
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    productOptions.value = allProducts.value
      .filter(v => v.name.toLowerCase().indexOf(needle) > -1)
      .map(p => ({ label: p.name, value: p.id }));
  });
};

const filterSuppliers = (val, update) => {
  if (val === '') {
    update(() => {
      supplierOptions.value = allSuppliers.value.map(s => ({ label: s.name, value: s.id }));
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    supplierOptions.value = allSuppliers.value
      .filter(v => v.name.toLowerCase().indexOf(needle) > -1)
      .map(s => ({ label: s.name, value: s.id }));
  });
};

const openProductDialog = () => {
  productForm.value = {
    name: '',
    product_description: '',
    unit_of_measure: '',
    current_stock_quantity: 0,
    product_code: '',
    barcode: '',
    cmup_cost: 0,
    tax_rate: 0,
    category_id: null,
    wholesale_price: 0,
    semi_wholesale_price: 0,
    retail_price: 0,
  };
  showProductDialog.value = true;
};

const openSupplierDialog = () => {
  supplierForm.value = {
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    tax_id: '',
    notes: '',
  };
  showSupplierDialog.value = true;
};

const saveQuickProduct = async () => {
  saving.value = true;
  try {
    const response = await api.post('/products', productForm.value);
    const newProduct = response.data;
    
    // Refresh products list
    await loadProducts();
    
    // Auto-select the new product in the last item row
    const lastItemIndex = form.value.items.length - 1;
    if (lastItemIndex >= 0) {
      form.value.items[lastItemIndex].product_id = newProduct.id;
    }
    
    $q.notify({ type: 'positive', message: 'Product created and selected' });
    showProductDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to create product' });
  } finally {
    saving.value = false;
  }
};

const saveQuickSupplier = async () => {
  saving.value = true;
  try {
    const response = await api.post('/suppliers', supplierForm.value);
    const newSupplier = response.data;
    
    // Refresh suppliers list
    await loadSuppliers();
    
    // Auto-select the new supplier
    form.value.supplier_id = newSupplier.id;
    
    $q.notify({ type: 'positive', message: 'Supplier created and selected' });
    showSupplierDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to create supplier' });
  } finally {
    saving.value = false;
  }
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.value.invoice_image = file;
  }
};

const addItem = () => {
  form.value.items.push({ product_id: null, quantity: 1, purchase_price: 0 });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

const savePurchase = async () => {
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('supplier_id', form.value.supplier_id);
    
    // Get supplier name from suppliers list
    const supplier = suppliers.value.find(s => s.id === form.value.supplier_id);
    formData.append('supplier_name', supplier ? supplier.name : '');
    
    formData.append('invoice_number', form.value.invoice_number);
    formData.append('invoice_date', form.value.invoice_date);
    formData.append('total_amount', form.value.manual_total || 0); // Send manual total as total_amount
    formData.append('notes', form.value.notes || '');
    if (form.value.invoice_image) {
      formData.append('invoice_image', form.value.invoice_image);
    }
    formData.append('items', JSON.stringify(form.value.items));

    let response;
    if (form.value.id) {
      // Update existing invoice
      formData.append('_method', 'PUT');
      response = await api.post(`/purchases/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: 'Purchase invoice updated' });
    } else {
      // Create new invoice
      response = await api.post('/purchases', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: 'Purchase invoice created' });
    }
    
    if (response.data.price_updates_required?.length > 0) {
      priceUpdates.value = response.data.price_updates_required;
      showPriceDialog.value = true;
    }
    
    closeDialog();
    loadInvoices();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || 'Failed to save purchase' });
  } finally {
    saving.value = false;
  }
};

const allowMismatch = ref(false);

const handleSaveAttempt = () => {
  // If totals match, manual total is zero, or user enabled mismatch toggle, save directly
  if (totalDifference.value === 0 || form.value.manual_total === 0 || allowMismatch.value) {
    savePurchase();
  } else {
    // Show warning notification prompting user to enable mismatch toggle
    $q.notify({
      type: 'warning',
      message: 'Total mismatch detected. Enable "Save despite difference" toggle to proceed.',
    });
  }
};

const confirmSaveWithDifference = () => {
  $q.dialog({
    title: 'Confirm Save',
    message: `There is a difference of ${Math.abs(totalDifference.value).toFixed(2)} DH between the manual total and calculated total. Are you sure you want to save this invoice?`,
    cancel: true,
    persistent: true,
    ok: {
      label: 'Yes, Save Anyway',
      color: 'warning',
      icon: 'warning'
    },
    cancel: {
      label: 'Cancel',
      flat: true
    }
  }).onOk(() => {
    savePurchase();
  });
};

const updatePrices = async () => {
  saving.value = true;
  try {
    for (const update of priceUpdates.value) {
      await api.put(`/products/${update.product_id}/prices`, {
        wholesale_price: update.wholesale_price,
        semi_wholesale_price: update.semi_wholesale_price,
        retail_price: update.retail_price,
      });
    }
    $q.notify({ type: 'positive', message: 'Prices updated successfully' });
    showPriceDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to update prices' });
  } finally {
    saving.value = false;
  }
};

const closeDialog = () => {
  showDialog.value = false;
  form.value = {
    supplier_id: null,
    invoice_number: '',
    invoice_date: new Date().toISOString().split('T')[0], // Reset to today
    invoice_image: null,
    notes: '',
    manual_total: 0,
    items: [{ product_id: null, quantity: 1, purchase_price: 0 }],
  };
};

const viewImage = (path) => {
  const extension = path.split('.').pop().toLowerCase();
  const url = `http://localhost:8000/storage/${path}`;
  
  if (extension === 'pdf') {
    // For PDF, open in new tab with PDF viewer
    window.open(url, '_blank');
  } else {
    // For images, show in a dialog or open in new tab
    window.open(url, '_blank');
  }
};

const viewDetails = async (invoice) => {
  loading.value = true;
  try {
    // Fetch full invoice details with relationships
    const response = await api.get(`/purchases/${invoice.id}`);
    selectedInvoice.value = response.data;
    showDetailsDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load invoice details' });
  } finally {
    loading.value = false;
  }
};

const editInvoice = async (invoice) => {
  loading.value = true;
  try {
    // Fetch full invoice details with relationships
    const response = await api.get(`/purchases/${invoice.id}`);
    const invoiceData = response.data;
    
    // Populate form with invoice data
    form.value = {
      id: invoiceData.id,
      supplier_id: invoiceData.supplier_id,
      invoice_number: invoiceData.invoice_number,
      invoice_date: invoiceData.invoice_date,
      invoice_image: null, // Don't pre-populate file input
      notes: invoiceData.notes || '',
      manual_total: invoiceData.total_in_invoice || invoiceData.total_amount,
      items: invoiceData.details.map(detail => ({
        product_id: detail.product_id,
        quantity: parseFloat(detail.quantity),
        purchase_price: parseFloat(detail.purchase_price)
      }))
    };
    
    // Open dialog
    showDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load invoice for editing' });
  } finally {
    loading.value = false;
  }
};

const openAttachFileDialog = (invoice) => {
  selectedInvoiceForAttach.value = invoice;
  attachedFile.value = null;
  showAttachDialog.value = true;
};

const closeAttachDialog = () => {
  showAttachDialog.value = false;
  selectedInvoiceForAttach.value = null;
  attachedFile.value = null;
};

const onFileRejected = () => {
  $q.notify({
    type: 'negative',
    message: 'File rejected. Please ensure it is an image or PDF and under 5MB.'
  });
};

const uploadInvoiceFile = async () => {
  if (!attachedFile.value || !selectedInvoiceForAttach.value) return;
  
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('invoice_image', attachedFile.value);
    
    await api.post(`/purchases/${selectedInvoiceForAttach.value.id}/attach-file`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    $q.notify({ type: 'positive', message: 'Invoice document uploaded successfully' });
    closeAttachDialog();
    loadInvoices();
  } catch (error) {
    $q.notify({ 
      type: 'negative', 
      message: error.response?.data?.message || 'Failed to upload document' 
    });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (invoice) => {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete invoice #${invoice.invoice_number}? This action cannot be undone.`,
    cancel: true,
    persistent: true,
    ok: {
      label: 'Delete',
      color: 'negative',
      icon: 'delete'
    },
    cancel: {
      label: 'Cancel',
      flat: true
    }
  }).onOk(() => {
    deleteInvoice(invoice.id);
  });
};

const deleteInvoice = async (id) => {
  loading.value = true;
  try {
    await api.delete(`/purchases/${id}`);
    $q.notify({ type: 'positive', message: 'Invoice deleted successfully' });
    loadInvoices();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || 'Failed to delete invoice' });
  } finally {
    loading.value = false;
  }
};



const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    settings.value = response.data;
  } catch (error) {
    console.error('Failed to load settings');
  }
};

const calculateUpdatePrices = (val, update) => {
  const price = parseFloat(val) || 0;
  const semiPercent = parseFloat(settings.value.semi_wholesale_percentage) || 0;
  const retailPercent = parseFloat(settings.value.retail_percentage) || 0;

  update.semi_wholesale_price = parseFloat((price * (1 + semiPercent / 100)).toFixed(2));
  update.retail_price = parseFloat((price * (1 + retailPercent / 100)).toFixed(2));
};

const getFileIcon = (filePath) => {
  if (!filePath) return 'image';
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? 'picture_as_pdf' : 'image';
};

const getFileColor = (filePath) => {
  if (!filePath) return 'primary';
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? 'red' : 'primary';
};

const getFileTooltip = (filePath) => {
  if (!filePath) return 'View Document';
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? 'View Invoice PDF' : 'View Invoice Image';
};

const exportToExcel = () => {
  const data = filteredInvoices.value;
  
  if (data.length === 0) {
    $q.notify({ type: 'warning', message: 'No data to export' });
    return;
  }

  // Prepare CSV content
  const headers = ['Invoice #', 'Supplier', 'Date', 'Total (DH)', 'Items Count', 'Notes'];
  const csvRows = [];
  
  // Add UTF-8 BOM for Excel to recognize Arabic characters
  csvRows.push('\uFEFF');
  csvRows.push(headers.join(','));
  
  data.forEach(invoice => {
    const row = [
      `"${invoice.invoice_number || ''}"`,
      `"${invoice.supplier?.name || 'N/A'}"`,
      `"${invoice.invoice_date || ''}"`,
      invoice.total_amount ? invoice.total_amount.toFixed(2) : '0.00',
      invoice.details_count || 0,
      `"${invoice.notes || ''}"`
    ];
    csvRows.push(row.join(','));
  });
  
  // Create and download file
  const csvContent = csvRows.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  
  link.setAttribute('href', url);
  link.setAttribute('download', `purchase_invoices_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

onMounted(() => {
  loadInvoices();
  loadProducts();
  loadSuppliers();
  loadSettings();
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

.section-header {
  display: flex;
  align-items: center;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: #1976d2;
}

.section-header.compact {
  margin-top: 0.5rem;
  margin-bottom: 0.25rem;
}

.products-section {
  max-height: 300px;
  overflow-y: auto;
  padding-right: 4px;
}

.products-section::-webkit-scrollbar {
  width: 6px;
}

.products-section::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.products-section::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.products-section::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.col-2-5 {
  width: 20.83%;
  flex: 0 0 20.83%;
  max-width: 20.83%;
}
</style>
