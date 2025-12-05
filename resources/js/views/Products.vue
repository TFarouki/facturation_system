<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Products</div>
      <q-btn color="primary" icon="add" label="Add Product" @click="showDialog = true" />
    </div>

    <!-- Table Toolbar -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <!-- Search -->
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search products..."
        style="min-width: 300px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>

      <q-space />

      <!-- Export to Excel -->
      <q-btn flat round dense icon="download" color="positive" @click="exportToExcel">
        <q-tooltip>Export to Excel</q-tooltip>
      </q-btn>

      <!-- Column Visibility Settings -->
      <q-btn flat round dense icon="more_vert" color="primary">
        <q-tooltip>Settings</q-tooltip>
        <q-menu>
          <q-list style="min-width: 200px">
            <q-item-label header>Show/Hide Columns</q-item-label>
            <q-item tag="label" v-for="col in toggleableColumns" :key="col.name">
              <q-item-section>
                <q-checkbox v-model="visibleColumns" :val="col.name" :label="col.label" />
              </q-item-section>
            </q-item>
            <q-separator />
            <q-item tag="label">
              <q-item-section>
                <q-checkbox v-model="showActionsColumn" label="Actions Column" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </div>

    <q-table
      :rows="filteredProducts"
      :columns="displayedColumns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      v-model:pagination="pagination"
      :rows-per-page-options="[10, 25, 50, 100]"
    >
    >
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="secondary" @click="editProduct(props.row)">
            <q-tooltip>Edit Product</q-tooltip>
          </q-btn>
          <q-btn round dense icon="attach_money" color="amber" size="sm" @click="updatePrices(props.row)">
            <q-tooltip>إضافة التسعير</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="deleteProduct(props.row)">
            <q-tooltip>Delete Product</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? 'Edit Product' : 'Add Product' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveProduct">
            <div class="row q-col-gutter-md">
              <!-- Product Code and Name -->
              <div class="col-4">
                <q-input v-model="form.product_code" label="Product Code (رمز المنتج)" outlined dense />
              </div>
              <div class="col-8">
                <q-input v-model="form.name" label="Product Name *" outlined dense :rules="[val => !!val || 'Required']" />
              </div>
              
              <!-- Product Family and Category -->
              <div class="col-6">
                <q-select
                  v-model="form.product_family_id"
                  :options="filteredFamilies"
                  option-value="id"
                  :option-label="opt => opt.name_ar ? `${opt.name} (${opt.name_ar})` : opt.name"
                  label="Product Family (عائلة المنتج)"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  clearable
                  @filter="filterFamilies"
                  @new-value="createNewFamily"
                  new-value-mode="add-unique"
                >
                  <template v-slot:no-option>
                    <q-item clickable @click="openAddFamilyDialog">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section class="text-primary">
                        Add new family
                      </q-item-section>
                    </q-item>
                  </template>
                  <template v-slot:after-options>
                    <q-item clickable @click="openAddFamilyDialog">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section class="text-primary">
                        Add new family
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-6">
                <CategorySelect v-model="form.category_id" label="Category" />
              </div>
              
              <!-- Barcode and Unit -->
              <div class="col-6">
                <q-input v-model="form.barcode" label="Barcode" outlined dense />
              </div>
              <div class="col-6">
                <q-select 
                  v-model="form.unit_id" 
                  :options="units" 
                  option-value="id" 
                  :option-label="opt => `${opt.unit_name_ar} (${opt.unit_name_en || opt.unit_symbol_en})`"
                  label="Unit *" 
                  outlined 
                  dense 
                  emit-value 
                  map-options 
                  :rules="[val => !!val || 'Required']" 
                />
              </div>
              
              <!-- Stock Quantity -->
              <div class="col-12">
                <q-input v-model.number="form.current_stock_quantity" label="Stock Quantity *" type="number" outlined dense :rules="[val => val >= 0 || 'Must be positive']" />
              </div>
              
              <!-- Description - Full Row -->
              <div class="col-12">
                <q-input v-model="form.product_description" label="Description" outlined dense type="textarea" rows="2" />
              </div>
            </div>

            <!-- Pricing Section - Collapsible (Optional for new products) -->
            <q-expansion-item
              v-if="!editMode"
              icon="attach_money"
              label="Pricing (Optional)"
              caption="Add pricing information"
              class="q-mt-md"
              dense
            >
              <q-card>
                <q-card-section>
                  <div class="row q-col-gutter-md">
                    <div class="col-3">
                      <q-input 
                        v-model.number="form.wholesale_price" 
                        label="Wholesale" 
                        type="number" 
                        outlined 
                        dense 
                        suffix="DH" 
                        @update:model-value="calculatePrices"
                      />
                    </div>
                    <div class="col-3">
                      <q-input v-model.number="form.semi_wholesale_price" label="Semi-Wholesale" type="number" outlined dense suffix="DH" />
                    </div>
                    <div class="col-3">
                      <q-input v-model.number="form.retail_price" label="Retail" type="number" outlined dense suffix="DH" />
                    </div>
                    <div class="col-3">
                      <TaxSelect v-model="form.tax_rate" label="Tax" />
                    </div>
                  </div>
                </q-card-section>
              </q-card>
            </q-expansion-item>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="closeProductDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Price Update Dialog -->
    <q-dialog v-model="showPriceDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">Update Prices</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="savePrices">
            <div class="row q-col-gutter-md">
              <div class="col-4">
                <q-input 
                  v-model.number="priceForm.wholesale_price" 
                  label="Wholesale Price" 
                  type="number" 
                  outlined 
                  dense 
                  suffix="DH"
                  step="1"
                  @update:model-value="calculateUpdatePrices"
                />
              </div>
              <div class="col-4">
                <q-input 
                  v-model.number="priceForm.semi_wholesale_price" 
                  label="Semi-Wholesale" 
                  type="number" 
                  outlined 
                  dense 
                  suffix="DH"
                  step="1"
                />
              </div>
              <div class="col-4">
                <q-input 
                  v-model.number="priceForm.retail_price" 
                  label="Retail Price" 
                  type="number" 
                  outlined 
                  dense 
                  suffix="DH"
                  step="1"
                />
              </div>
              <div class="col-12">
                <TaxSelect v-model="priceForm.tax_rate" label="Tax Rate" />
              </div>
            </div>

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="showPriceDialog = false" />
              <q-btn type="submit" label="Update" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Add Family Dialog -->
    <q-dialog v-model="showAddFamilyDialog" persistent @show="focusFamilyNameInput">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Add New Product Family</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveNewFamily" class="q-gutter-md">
            <q-input
              ref="familyNameInputRef"
              v-model="newFamilyForm.name"
              label="Name (English) *"
              outlined
              dense
              :rules="[val => !!val || 'Required']"
            />

            <q-input
              v-model="newFamilyForm.name_ar"
              label="Name (Arabic) الاسم بالعربية"
              outlined
              dense
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showAddFamilyDialog = false" />
              <q-btn type="submit" label="Save" color="secondary" :loading="savingFamily" />
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
import CategorySelect from '../components/CategorySelect.vue';
import TaxSelect from '../components/TaxSelect.vue';

const $q = useQuasar();
const products = ref([]);
const units = ref([]);
const families = ref([]);
const filteredFamilies = ref([]);
const loading = ref(false);
const saving = ref(false);
const savingFamily = ref(false);
const showDialog = ref(false);
const showPriceDialog = ref(false);
const showAddFamilyDialog = ref(false);
const editMode = ref(false);
const selectedProduct = ref(null);
const settings = ref({
  semi_wholesale_percentage: 0,
  retail_percentage: 0,
});

const newFamilyForm = ref({
  name: '',
  name_ar: '',
});
const familyNameInputRef = ref(null);

// Table features
const searchText = ref('');
const showActionsColumn = ref(true);
const visibleColumns = ref(['product_code', 'product_family', 'category', 'unit', 'stock', 'cmup', 'wholesale', 'semi_wholesale', 'retail', 'tax']);
const pagination = ref({
  rowsPerPage: 10
});

const form = ref({
  name: '',
  product_code: '',
  product_family_id: null,
  product_description: '',
  unit_id: null,
  current_stock_quantity: 0,
  barcode: '',
  category_id: null,
  wholesale_price: 0,
  semi_wholesale_price: 0,
  retail_price: 0,
});

const priceForm = ref({
  wholesale_price: 0,
  semi_wholesale_price: 0,
  retail_price: 0,
  tax_rate: 0,
});

const columns = [
  { name: 'product_code', label: 'Code', field: 'product_code', align: 'left', sortable: true },
  { name: 'name', label: 'Product Name', field: 'name', align: 'left', sortable: true },
  { name: 'product_family', label: 'Family', field: row => row.product_family?.name || row.product_family || '-', align: 'left', sortable: true },
  { name: 'category', label: 'Category', field: row => row.category?.name || '-', align: 'left', sortable: true },
  { name: 'unit', label: 'Unit', field: row => row.unit?.unit_name_ar || '-', align: 'left', sortable: true },
  { name: 'stock', label: 'Stock', field: row => row.current_stock_quantity || 0, align: 'right', sortable: true, format: val => Math.floor(val) },
  { name: 'cmup', label: 'CMUP', field: row => row.cmup_cost || 0, align: 'right', sortable: true, format: val => `${val} DH` },
  { name: 'wholesale', label: 'Wholesale', field: row => row.current_price?.wholesale_price || 0, align: 'right', sortable: true, format: val => `${val} DH` },
  { name: 'semi_wholesale', label: 'Semi-Wholesale', field: row => row.current_price?.semi_wholesale_price || 0, align: 'right', sortable: true, format: val => `${val} DH` },
  { name: 'retail', label: 'Retail', field: row => row.current_price?.retail_price || 0, align: 'right', sortable: true, format: val => `${val} DH` },
  { name: 'tax', label: 'Tax', field: row => row.current_price?.tax_rate || 0, align: 'right', sortable: true, format: val => `${val}%` },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center', sortable: false },
];

// Computed properties
const toggleableColumns = computed(() => {
  return columns.filter(col => col.name !== 'name' && col.name !== 'actions');
});

const displayedColumns = computed(() => {
  let cols = columns.filter(col => {
    if (col.name === 'name') return true;
    if (col.name === 'actions') return showActionsColumn.value;
    return visibleColumns.value.includes(col.name);
  });
  return cols;
});

const filteredProducts = computed(() => {
  let filtered = products.value;
  
  // Apply search filter to all fields
  if (searchText.value) {
    const search = searchText.value.toLowerCase();
    filtered = filtered.filter(product => {
      return (
        product.name?.toLowerCase().includes(search) ||
        product.product_code?.toLowerCase().includes(search) ||
        product.product_family?.toLowerCase().includes(search) ||
        product.category?.name?.toLowerCase().includes(search) ||
        product.unit?.unit_name_ar?.toLowerCase().includes(search) ||
        product.unit?.unit_name_en?.toLowerCase().includes(search) ||
        product.barcode?.toLowerCase().includes(search) ||
        product.current_stock_quantity?.toString().includes(search) ||
        product.cmup_cost?.toString().includes(search) ||
        product.current_price?.wholesale_price?.toString().includes(search) ||
        product.current_price?.semi_wholesale_price?.toString().includes(search) ||
        product.current_price?.retail_price?.toString().includes(search) ||
        product.current_price?.tax_rate?.toString().includes(search)
      );
    });
  }
  
  return filtered;
});

const loadProducts = async () => {
  loading.value = true;
  try {
    const response = await api.get('/products');
    products.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load products' });
  } finally {
    loading.value = false;
  }
};

const loadUnits = async () => {
  try {
    const response = await api.get('/units');
    units.value = response.data;
    
    // Set default unit to "قطعة" (Piece)
    const pieceUnit = units.value.find(u => u.unit_name_ar === 'قطعة');
    if (pieceUnit && !form.value.unit_id) {
      form.value.unit_id = pieceUnit.id;
    }
  } catch (error) {
    console.error('Failed to load units');
  }
};

const loadFamilies = async () => {
  try {
    const response = await api.get('/product-families');
    families.value = response.data;
    filteredFamilies.value = response.data;
  } catch (error) {
    console.error('Failed to load families');
  }
};

const filterFamilies = (val, update) => {
  update(() => {
    if (!val) {
      filteredFamilies.value = families.value;
    } else {
      const needle = val.toLowerCase();
      filteredFamilies.value = families.value.filter(f => 
        f.name?.toLowerCase().includes(needle) ||
        f.name_ar?.includes(needle)
      );
    }
  });
};

const createNewFamily = (val, done) => {
  // This is called when user types and presses enter
  // We'll open the dialog instead
  if (val.length > 0) {
    newFamilyForm.value.name = val;
    openAddFamilyDialog();
  }
  done(null); // Don't add the value directly
};

const openAddFamilyDialog = () => {
  showAddFamilyDialog.value = true;
};

const focusFamilyNameInput = () => {
  setTimeout(() => {
    familyNameInputRef.value?.focus();
  }, 100);
};

const saveNewFamily = async () => {
  if (!newFamilyForm.value.name) return;
  
  savingFamily.value = true;
  try {
    const response = await api.post('/product-families', newFamilyForm.value);
    const newFamily = response.data;
    
    // Add to families list
    families.value.push(newFamily);
    filteredFamilies.value = [...families.value];
    
    // Select the new family
    form.value.product_family_id = newFamily.id;
    
    // Close dialog and reset form
    showAddFamilyDialog.value = false;
    newFamilyForm.value = { name: '', name_ar: '' };
    
    $q.notify({ type: 'positive', message: 'Family created successfully' });
  } catch (error) {
    console.error('Failed to create family:', error);
    $q.notify({ type: 'negative', message: 'Failed to create family' });
  } finally {
    savingFamily.value = false;
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

const calculatePrices = (val) => {
  const price = parseFloat(val) || 0;
  const semiPercent = parseFloat(settings.value.semi_wholesale_percentage) || 0;
  const retailPercent = parseFloat(settings.value.retail_percentage) || 0;

  form.value.semi_wholesale_price = parseFloat((price * (1 + semiPercent / 100)).toFixed(2));
  form.value.retail_price = parseFloat((price * (1 + retailPercent / 100)).toFixed(2));
};

const calculateUpdatePrices = (val) => {
  const price = parseFloat(val) || 0;
  const semiPercent = parseFloat(settings.value.semi_wholesale_percentage) || 0;
  const retailPercent = parseFloat(settings.value.retail_percentage) || 0;

  priceForm.value.semi_wholesale_price = parseFloat((price * (1 + semiPercent / 100)).toFixed(2));
  priceForm.value.retail_price = parseFloat((price * (1 + retailPercent / 100)).toFixed(2));
};



const editProduct = (product) => {
  editMode.value = true;
  selectedProduct.value = product;
  form.value = {
    name: product.name,
    product_code: product.product_code || '',
    product_family_id: product.product_family_id || null,
    product_description: product.product_description || '',
    unit_id: product.unit_id,
    current_stock_quantity: product.current_stock_quantity,
    barcode: product.barcode,
    category_id: product.category_id,
    wholesale_price: product.current_price?.wholesale_price || 0,
    semi_wholesale_price: product.current_price?.semi_wholesale_price || 0,
    retail_price: product.current_price?.retail_price || 0,
  };
  showDialog.value = true;
};

const closeProductDialog = () => {
  showDialog.value = false;
  editMode.value = false;
  selectedProduct.value = null;
  
  // Get default unit (قطعة)
  const pieceUnit = units.value.find(u => u.unit_name_ar === 'قطعة');
  
  form.value = {
    name: '',
    product_code: '',
    product_family_id: null,
    product_description: '',
    unit_id: pieceUnit?.id || null,
    current_stock_quantity: 0,
    barcode: '',
    category_id: null,
    wholesale_price: 0,
    semi_wholesale_price: 0,
    retail_price: 0,
  };
};

const saveProduct = async () => {
  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/products/${selectedProduct.value.id}`, form.value);
      $q.notify({ type: 'positive', message: 'Product saved successfully' });
      closeProductDialog();
      loadProducts();
    } else {
      // Create product with optional pricing
      const productData = {
        name: form.value.name,
        product_code: form.value.product_code,
        product_family_id: form.value.product_family_id,
        product_description: form.value.product_description,
        unit_id: form.value.unit_id,
        current_stock_quantity: form.value.current_stock_quantity,
        barcode: form.value.barcode,
        category_id: form.value.category_id,
        wholesale_price: form.value.wholesale_price || 0,
        semi_wholesale_price: form.value.semi_wholesale_price || 0,
        retail_price: form.value.retail_price || 0,
        tax_rate: form.value.tax_rate || 0,
      };
      await api.post('/products', productData);
      $q.notify({ type: 'positive', message: 'Product saved successfully' });
      closeProductDialog();
      loadProducts();
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save product' });
  } finally {
    saving.value = false;
  }
};

const updatePrices = (product) => {
  selectedProduct.value = product;
  priceForm.value = {
    wholesale_price: product.current_price?.wholesale_price || 0,
    semi_wholesale_price: product.current_price?.semi_wholesale_price || 0,
    retail_price: product.current_price?.retail_price || 0,
    tax_rate: product.current_price?.tax_rate || 0,
  };
  showPriceDialog.value = true;
};

const savePrices = async () => {
  saving.value = true;
  try {
    const productId = selectedProduct.value?.id || tempProductForPricing.value?.id;
    await api.put(`/products/${productId}/prices`, priceForm.value);
    $q.notify({ type: 'positive', message: 'Prices updated successfully' });
    showPriceDialog.value = false;
    showAddPriceDialog.value = false;
    tempProductForPricing.value = null;
    loadProducts();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to update prices' });
  } finally {
    saving.value = false;
  }
};

const deleteProduct = async (product) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete product "${product.name}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/products/${product.id}`);
      $q.notify({ type: 'positive', message: 'Product deleted' });
      loadProducts();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete product' });
    }
  });
};

const exportToExcel = () => {
  try {
    // Prepare data for export
    const exportData = filteredProducts.value.map(product => ({
      'Product Name': product.name,
      'Category': product.category?.name || '-',
      'Unit': product.unit?.unit_name_ar || '-',
      'Stock': Math.floor(product.current_stock_quantity || 0),
      'CMUP (DH)': product.cmup_cost || 0,
      'Wholesale (DH)': product.current_price?.wholesale_price || 0,
      'Semi-Wholesale (DH)': product.current_price?.semi_wholesale_price || 0,
      'Retail (DH)': product.current_price?.retail_price || 0,
      'Tax (%)': product.current_price?.tax_rate || 0,
      'Barcode': product.barcode || '-',
    }));

    // Convert to CSV
    const headers = Object.keys(exportData[0]);
    const csvContent = [
      headers.join(','),
      ...exportData.map(row => headers.map(header => {
        const value = row[header];
        // Escape commas and quotes
        return typeof value === 'string' && value.includes(',') 
          ? `"${value.replace(/"/g, '""')}"` 
          : value;
      }).join(','))
    ].join('\n');

    // Create blob and download
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `products_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    $q.notify({ type: 'positive', message: 'Products exported successfully' });
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to export products' });
  }
};

onMounted(() => {
  loadProducts();
  loadUnits();
  loadFamilies();
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
</style>
