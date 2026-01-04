<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('nav.inventory') }}</div>
      <div class="row q-gutter-sm">
        <q-btn 
          v-if="filteredInventory.length > 0"
          color="secondary" 
          icon="request_quote" 
          :label="$t('inventory.priceList')" 
          :loading="generatingPriceListPdf"
          @click="printPriceList" 
        />
        <q-btn 
          v-if="filteredInventory.length > 0"
          color="orange" 
          icon="picture_as_pdf" 
          :label="$t('inventory.inventoryCheckPdf')" 
          :loading="generatingPdf"
          @click="printInventoryCheckSheet" 
        />
        <q-btn color="positive" icon="download" :label="$t('inventory.exportToExcel')" @click="exportToExcel" />
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-2">
        <q-card class="bg-primary text-white">
          <q-card-section>
            <div class="text-h6" style="font-size: 1rem">{{ $t('inventory.totalProducts') }}</div>
            <div class="text-h4">{{ statistics.totalProducts }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-positive text-white">
          <q-card-section>
            <div class="text-h6" style="font-size: 1rem">{{ $t('inventory.inStock') }}</div>
            <div class="text-h4">{{ statistics.inStock }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-warning text-white">
          <q-card-section>
            <div class="text-h6" style="font-size: 1rem">{{ $t('inventory.lowStock') }}</div>
            <div class="text-h4">{{ statistics.lowStock }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4">
        <q-card class="bg-info text-white">
          <q-card-section>
            <div class="text-h6" style="font-size: 1rem">{{ $t('inventory.totalInventoryValue') }}</div>
            <div class="text-h4">{{ formatCurrency(statistics.totalInventoryValue) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-input
        v-model="searchText"
        outlined
        dense
        :placeholder="$t('inventory.searchProducts')"
        style="min-width: 300px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>

      <q-select
        v-model="stockFilter"
        :options="stockFilterOptions"
        option-value="value"
        option-label="label"
        :label="$t('inventory.stockStatus')"
        outlined
        dense
        emit-value
        map-options
        clearable
        style="min-width: 200px"
      />

      <q-select
        v-model="distributionFilter"
        :options="distributionFilterOptions"
        option-value="value"
        option-label="label"
        :label="$t('inventory.distributionStatus')"
        outlined
        dense
        emit-value
        map-options
        clearable
        style="min-width: 200px"
      />

      <q-select
        v-model="categoryFilter"
        :options="categoryOptions"
        option-value="value"
        option-label="label"
        :label="$t('inventory.category')"
        outlined
        dense
        emit-value
        map-options
        clearable
        style="min-width: 200px"
      />

      <q-space />

      <q-btn flat round dense icon="download" color="positive" @click="exportToExcel">
        <q-tooltip>{{ $t('inventory.exportToExcel') }}</q-tooltip>
      </q-btn>
    </div>

    <!-- Inventory Table -->
    <div>
    <q-table
      :rows="filteredInventory"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
      v-model:pagination="pagination"
      :rows-per-page-options="[10, 25, 50, 100]"
    >
      <template v-slot:body-cell-stock_status="props">
        <q-td :props="props">
          <q-badge
            :color="getStockStatusColor(props.row.stock_status)"
            :label="props.row.stock_status"
          />
        </q-td>
      </template>

      <template v-slot:body-cell-current_stock_quantity="props">
        <q-td :props="props" :class="getStockQuantityClass(props.row)">
          <div class="text-weight-bold">{{ formatQuantity(props.row.current_stock_quantity) }}</div>
          <div class="text-caption text-grey-6">
            {{ props.row.unit?.name || props.row.unit?.name_en || 'unit' }}
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-committed_quantity="props">
        <q-td :props="props" class="text-center">
          <div class="text-weight-bold">{{ formatQuantity(props.row.committed_quantity || 0) }}</div>
          <div class="text-caption text-grey-6">
            {{ props.row.unit?.name || props.row.unit?.name_en || 'unit' }}
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-cmup="props">
        <q-td :props="props" class="text-center">
          <div class="text-weight-bold">{{ props.row.cmup || '0.70' }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-purchase_price="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.current_price?.wholesale_price" class="text-weight-bold">
            {{ formatCurrency(props.row.current_price.wholesale_price * (props.row.cmup || 0.7)) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-total_value="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.current_price?.wholesale_price && props.row.current_stock_quantity" class="text-weight-bold text-primary">
            {{ formatCurrency((props.row.current_price.wholesale_price * (props.row.cmup || 0.7)) * props.row.current_stock_quantity) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-wholesale_price="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.current_price && props.row.current_price.wholesale_price !== null && props.row.current_price.wholesale_price !== undefined && parseFloat(props.row.current_price.wholesale_price) > 0" class="text-weight-bold">
            {{ formatCurrency(props.row.current_price.wholesale_price) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-semi_wholesale_price="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.current_price && props.row.current_price.semi_wholesale_price !== null && props.row.current_price.semi_wholesale_price !== undefined && parseFloat(props.row.current_price.semi_wholesale_price) > 0" class="text-weight-bold">
            {{ formatCurrency(props.row.current_price.semi_wholesale_price) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-retail_price="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.current_price && props.row.current_price.retail_price !== null && props.row.current_price.retail_price !== undefined && parseFloat(props.row.current_price.retail_price) > 0" class="text-weight-bold">
            {{ formatCurrency(props.row.current_price.retail_price) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>


    </q-table>
    </div>

    <!-- Product Details Dialog -->
    <q-dialog v-model="showDetailsDialog">
      <q-card style="min-width: 500px; max-width: 700px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ $t('products.productDetails') }}</div>
        </q-card-section>

        <q-card-section v-if="selectedProduct">
          <div class="row q-col-gutter-md">
            <div class="col-12">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.name') }}</div>
              <div class="text-h6">{{ selectedProduct.name }}</div>
            </div>

            <div class="col-6">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.category') }}</div>
              <div>{{ selectedProduct.category?.name || 'N/A' }}</div>
            </div>

            <div class="col-6">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.unit') }}</div>
              <div>{{ selectedProduct.unit?.name || selectedProduct.unit?.name_en || 'N/A' }}</div>
            </div>

            <div class="col-6">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.currentStock') }}</div>
              <div class="text-h6" :class="getStockQuantityClass(selectedProduct)">
                {{ formatQuantity(selectedProduct.current_stock_quantity) }}
              </div>
            </div>

            <div class="col-6">
              <div class="text-subtitle2 text-grey-7">{{ $t('inventory.stockStatus') }}</div>
              <q-badge
                :color="getStockStatusColor(selectedProduct.stock_status)"
                :label="selectedProduct.stock_status"
                class="q-mt-xs"
              />
            </div>

            <div class="col-12" v-if="selectedProduct.currentPrice">
              <q-separator class="q-my-md" />
              <div class="text-subtitle2 text-grey-7 q-mb-sm">{{ $t('products.currentPrices') }}</div>
              <div class="row q-col-gutter-sm">
                <div class="col-4">
                  <div class="text-caption text-grey-6">{{ $t('sales.wholesale') }}</div>
                  <div class="text-weight-bold">{{ formatCurrency(selectedProduct.currentPrice.wholesale_price) }}</div>
                </div>
                <div class="col-4">
                  <div class="text-caption text-grey-6">{{ $t('sales.semiWholesale') }}</div>
                  <div class="text-weight-bold">{{ formatCurrency(selectedProduct.currentPrice.semi_wholesale_price) }}</div>
                </div>
                <div class="col-4">
                  <div class="text-caption text-grey-6">{{ $t('sales.retail') }}</div>
                  <div class="text-weight-bold">{{ formatCurrency(selectedProduct.currentPrice.retail_price) }}</div>
                </div>
              </div>
            </div>

            <div class="col-12" v-if="selectedProduct.barcode">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.barcode') }}</div>
              <div>{{ selectedProduct.barcode }}</div>
            </div>

            <div class="col-12" v-if="selectedProduct.product_description">
              <div class="text-subtitle2 text-grey-7">{{ $t('products.description') }}</div>
              <div>{{ selectedProduct.product_description }}</div>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn :label="$t('common.close')" flat v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const loading = ref(false);
const generatingPdf = ref(false);
const generatingPriceListPdf = ref(false);
const inventory = ref([]);
const categories = ref([]);
const searchText = ref('');
const stockFilter = ref(null);
const categoryFilter = ref(null);
const distributionFilter = ref(null);
const pagination = ref({
  rowsPerPage: 25
});
const showDetailsDialog = ref(false);
const selectedProduct = ref(null);

const stockFilterOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('inventory.inStock'), value: t('inventory.inStock') }, // Value must match what is set in loadInventory
  { label: t('inventory.lowStock'), value: t('inventory.lowStock') },
]);

const distributionFilterOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('inventory.inDistribution'), value: 'in_distribution' },
  { label: t('inventory.notInDistribution'), value: 'not_in_distribution' },
]);

const columns = computed(() => [
  { name: 'name', label: t('products.name'), field: 'name', align: 'left', sortable: true },
  { name: 'current_stock_quantity', label: t('products.stock'), field: 'current_stock_quantity', align: 'center', sortable: true },
  { name: 'committed_quantity', label: t('inventory.inDistribution'), field: row => row.committed_quantity || 0, align: 'center', sortable: true },
  { name: 'wholesale_price', label: t('sales.wholesale'), field: row => row.current_price?.wholesale_price || 0, align: 'right', sortable: true },
  { name: 'cmup', label: t('inventory.cmup'), field: 'cmup', align: 'center', sortable: true },
  { name: 'purchase_price', label: t('purchases.purchasePrice'), field: 'purchase_price', align: 'right', sortable: true },
  { name: 'semi_wholesale_price', label: t('sales.semiWholesale'), field: row => row.current_price?.semi_wholesale_price || 0, align: 'right', sortable: true },
  { name: 'retail_price', label: t('sales.retail'), field: row => row.current_price?.retail_price || 0, align: 'right', sortable: true },
  { name: 'total_value', label: t('common.total'), field: 'total_value', align: 'right', sortable: true },
  { name: 'stock_status', label: t('common.status'), field: 'stock_status', align: 'center', sortable: true },
]);

const statistics = computed(() => {
  const total = inventory.value.length;
  // Use localized strings for comparison as stock_status is now localized
  const inStock = inventory.value.filter(item => item.stock_status === t('inventory.inStock')).length;
  const lowStock = inventory.value.filter(item => item.stock_status === t('inventory.lowStock')).length;
  const outOfStock = inventory.value.filter(item => item.stock_status === t('inventory.outOfStock')).length;

  const totalInventoryValue = inventory.value.reduce((sum, item) => {
    const qty = parseFloat(item.current_stock_quantity) || 0;
    const wholesale = parseFloat(item.current_price?.wholesale_price) || 0;
    const cmup = parseFloat(item.cmup) || 0.7;
    const price = wholesale * cmup;
    return sum + (qty * price);
  }, 0);

  return {
    totalProducts: total,
    inStock,
    lowStock,
    outOfStock,
    totalInventoryValue,
  };
});

const categoryOptions = computed(() => {
  const options = [{ label: t('inventory.allCategories'), value: null }];
  categories.value.forEach(cat => {
    options.push({ label: cat.name, value: cat.id });
  });
  return options;
});

const filteredInventory = computed(() => {
  let filtered = [...inventory.value];

  // Search filter
  if (searchText.value) {
    const search = searchText.value.toLowerCase();
    filtered = filtered.filter(item => {
      return (
        item.name?.toLowerCase().includes(search) ||
        item.category?.name?.toLowerCase().includes(search)
      );
    });
  }

  // Stock status filter
  if (stockFilter.value) {
    filtered = filtered.filter(item => item.stock_status === stockFilter.value);
  }

  // Distribution filter
  if (distributionFilter.value) {
    if (distributionFilter.value === 'in_distribution') {
      filtered = filtered.filter(item => (item.committed_quantity || 0) > 0);
    } else if (distributionFilter.value === 'not_in_distribution') {
      filtered = filtered.filter(item => (item.committed_quantity || 0) === 0);
    }
  }

  // Category filter
  if (categoryFilter.value) {
    filtered = filtered.filter(item => item.category_id === categoryFilter.value);
  }

  return filtered;
});

const loadInventory = async () => {
  loading.value = true;
  try {
    const response = await api.get('/products');
    const products = response.data;
    
    inventory.value = products.map((product) => {
      // Determine stock status
      const quantity = parseFloat(product.current_stock_quantity) || 0;
      const minStock = product.min_stock_level || 10;
      let stockStatus = t('inventory.outOfStock');
      if (quantity > minStock) {
        stockStatus = t('inventory.inStock');
      } else if (quantity > 0) {
        stockStatus = t('inventory.lowStock');
      }
      
      return {
        ...product,
        stock_status: stockStatus,
        stockHistory: [], // Will be loaded on demand or lazily
      };
    });
    
    // Load stock history removed as charts are no longer displayed
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
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

const getStockStatusColor = (status) => {
  if (status === t('inventory.inStock')) return 'positive';
  if (status === t('inventory.lowStock')) return 'warning';
  if (status === t('inventory.outOfStock')) return 'negative';
  return 'grey';
};

const getStockQuantityClass = (product) => {
  if (!product) return '';
  const qty = parseFloat(product.current_stock_quantity) || 0;
  const minStock = product.min_stock_level || 10;
  
  if (qty === 0) return 'text-negative';
  if (qty <= minStock) return 'text-warning';
  return 'text-positive';
};

const formatQuantity = (quantity) => {
  const qty = parseFloat(quantity) || 0;
  return qty.toFixed(2);
};

const formatCurrency = (amount) => {
  const num = parseFloat(amount) || 0;
  return `${num.toFixed(2)} DH`;
};

const viewProductDetails = (product) => {
  selectedProduct.value = product;
  showDetailsDialog.value = true;
};


const printInventoryCheckSheet = async () => {
  if (!filteredInventory.value.length) {
    $q.notify({ type: 'warning', message: t('messages.noDataToGeneratePdf') });
    return;
  }
  
  generatingPdf.value = true;
  
  // Filter out products that are out of stock (using localized string matching or quantity check)
  // Since we rely on localized strings, checking quantity > 0 is safer and more robust
  const products = filteredInventory.value.filter(p => (parseFloat(p.current_stock_quantity) || 0) > 0);
  
  if (!products.length) {
    generatingPdf.value = false;
    $q.notify({ type: 'warning', message: t('messages.noDataToGeneratePdf') });
    return;
  }

  const today = new Date().toISOString().split('T')[0];
  
  // Calculate totals
  const totalStock = products.reduce((sum, p) => sum + (parseFloat(p.current_stock_quantity) || 0), 0);
  const totalCommitted = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
  
  // Generate table rows HTML
  const tableRows = products.map((p, index) => {
    const stock = parseFloat(p.current_stock_quantity) || 0;
    const committed = parseFloat(p.committed_quantity) || 0;
    const statusColor = p.stock_status === t('inventory.inStock') ? '#4caf50' : 
                        p.stock_status === t('inventory.lowStock') ? '#ff9800' : '#f44336';
    return `
      <tr>
        <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${index + 1}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${p.name}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${p.category?.name || '-'}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${p.unit?.unit_symbol_en || p.unit?.name || '-'}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; font-weight: bold; background: #f5f5f5;">${formatQuantity(stock)}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #1976d2;">${formatQuantity(committed)}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">
          <span style="background: ${statusColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">${p.stock_status}</span>
        </td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; background: #fffde7; min-width: 80px;"></td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; min-width: 60px;"></td>
      </tr>
    `;
  }).join('');
  
  // Create HTML content
  const htmlContent = `
    <div id="pdf-content" style="width: 794px; padding: 20px; font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; background: white; direction: ${$q.lang.rtl ? 'rtl' : 'ltr'};">
      <!-- Header -->
      <div style="background: #1976d2; color: white; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px;">
        <div style="font-size: 22px; font-weight: bold; margin-bottom: 5px;">${t('inventory.inventoryCheckSheet')}</div>
      </div>
      
      <!-- Info Section -->
      <div style="display: flex; justify-content: space-between; background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap;">
        <div style="text-align: center; min-width: 120px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('common.date')}</div>
          <div style="font-size: 14px; font-weight: bold;">${today}</div>
        </div>
        <div style="text-align: center; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.totalProducts')}</div>
          <div style="font-size: 14px; font-weight: bold;">${products.length}</div>
        </div>
        <div style="text-align: center; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.stockQuantity')}</div>
          <div style="font-size: 14px; font-weight: bold;">${formatQuantity(totalStock)}</div>
        </div>
        <div style="text-align: center; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.inDistribution')}</div>
          <div style="font-size: 14px; font-weight: bold; color: #1976d2;">${formatQuantity(totalCommitted)}</div>
        </div>
        <div style="text-align: center; min-width: 80px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.inStock')}</div>
          <div style="font-size: 14px; font-weight: bold; color: #4caf50;">${statistics.value.inStock}</div>
        </div>
        <div style="text-align: center; min-width: 80px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.lowStock')}</div>
          <div style="font-size: 14px; font-weight: bold; color: #ff9800;">${statistics.value.lowStock}</div>
        </div>
      </div>
      
      <!-- Table -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px;">
        <thead>
          <tr style="background: #1976d2; color: white;">
            <th style="padding: 10px; border: 1px solid #1565c0; width: 4%;">#</th>
            <th style="padding: 10px; border: 1px solid #1565c0; text-align: ${$q.lang.rtl ? 'right' : 'left'}; width: 25%;">${t('products.product')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 12%;">${t('products.category')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 6%;">${t('products.unit')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 10%;">${t('products.stock')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 10%;">${t('inventory.inDistribution')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 10%;">${t('common.status')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 13%;">${t('inventory.actualCount')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 10%;">${t('inventory.difference')}</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
          <tr style="background: #e3f2fd; font-weight: bold;">
            <td colspan="4" style="text-align: left; padding: 10px; border: 1px solid #ddd;">${t('common.total')}</td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;">${formatQuantity(totalStock)}</td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;">${formatQuantity(totalCommitted)}</td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;"></td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;"></td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;"></td>
          </tr>
        </tbody>
      </table>
      
      <!-- Notes Section -->
      <div style="border: 2px dashed #999; padding: 15px; margin-bottom: 30px; min-height: 60px; border-radius: 8px;">
        <div style="font-size: 12px; color: #666; margin-bottom: 10px;">${t('common.notes')}:</div>
      </div>
      
      <!-- Signature Section -->
      <div style="display: flex; justify-content: space-around; margin-top: 40px;">
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">${t('inventory.storekeeperSignature')}</div>
        </div>
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">${t('inventory.auditorSignature')}</div>
        </div>
      </div>
    </div>
  `;
  
  // Create temporary container
  const container = document.createElement('div');
  container.innerHTML = htmlContent;
  container.style.position = 'absolute';
  container.style.left = '-9999px';
  container.style.top = '0';
  document.body.appendChild(container);
  
  // Add Google Fonts for Arabic
  const link = document.createElement('link');
  link.href = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap';
  link.rel = 'stylesheet';
  document.head.appendChild(link);
  
  // Wait for fonts to load
  await new Promise(resolve => setTimeout(resolve, 500));
  
  try {
    const element = container.querySelector('#pdf-content');
    
    // Convert to canvas
    const canvas = await html2canvas(element, {
      scale: 2,
      useCORS: true,
      allowTaint: true,
      backgroundColor: '#ffffff'
    });
    
    // Create PDF
    const imgData = canvas.toDataURL('image/png');
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    });
    
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();
    const imgWidth = canvas.width;
    const imgHeight = canvas.height;
    const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
    const imgX = (pdfWidth - imgWidth * ratio) / 2;
    const imgY = 0;
    
    pdf.addImage(imgData, 'PNG', imgX, imgY, imgWidth * ratio, imgHeight * ratio);
    
    // Save PDF
    const fileName = `inventory_check_${today}.pdf`;
    pdf.save(fileName);
    $q.notify({ type: 'positive', message: t('messages.pdfDownloaded') });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToGeneratePdf') });
  } finally {
    // Cleanup
    document.body.removeChild(container);
    generatingPdf.value = false;
  }
};

const printPriceList = async () => {
  // Filter for products in distribution (committed_quantity > 0)
  const products = inventory.value.filter(p => (parseFloat(p.committed_quantity) || 0) > 0);

  if (!products.length) {
    $q.notify({ type: 'warning', message: t('messages.noProductsInDistribution') });
    return;
  }
  
  generatingPriceListPdf.value = true;
  
  const today = new Date().toISOString().split('T')[0];
  
  // Calculate totals
  const totalCommitted = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
  
  // Helper to check price availability
  const formatPriceOrDash = (price) => {
    return (price !== null && price !== undefined && parseFloat(price) > 0) ? formatCurrency(price) : '-';
  };
  
  // Generate table rows HTML
  const tableRows = products.map((p, index) => {
    const committed = parseFloat(p.committed_quantity) || 0;
    const wholesale = formatPriceOrDash(p.current_price?.wholesale_price);
    const semiWholesale = formatPriceOrDash(p.current_price?.semi_wholesale_price);
    const retail = formatPriceOrDash(p.current_price?.retail_price);
    
    return `
      <tr>
        <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${index + 1}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${p.name}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${p.category?.name || '-'}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #1976d2; font-weight: bold;">${formatQuantity(committed)} ${p.unit?.unit_symbol_en || ''}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${wholesale}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${semiWholesale}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${retail}</td>
      </tr>
    `;
  }).join('');
  
  // Create HTML content
  const htmlContent = `
    <div id="pdf-content-prices" style="width: 794px; padding: 20px; font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; background: white; direction: ${$q.lang.rtl ? 'rtl' : 'ltr'};">
      <!-- Header -->
      <div style="background: #26a69a; color: white; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px;">
        <div style="font-size: 22px; font-weight: bold; margin-bottom: 5px;">${t('inventory.priceList')}</div>
        <div style="font-size: 14px;">${t('inventory.productsInDistribution')}</div>
      </div>
      
      <!-- Info Section -->
      <div style="display: flex; justify-content: space-between; background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap;">
        <div style="text-align: center; min-width: 120px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('common.date')}</div>
          <div style="font-size: 14px; font-weight: bold;">${today}</div>
        </div>
        <div style="text-align: center; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.totalProducts')}</div>
          <div style="font-size: 14px; font-weight: bold;">${products.length}</div>
        </div>
        <div style="text-align: center; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.totalInDistribution')}</div>
          <div style="font-size: 14px; font-weight: bold; color: #1976d2;">${formatQuantity(totalCommitted)}</div>
        </div>
      </div>
      
      <!-- Table -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px;">
        <thead>
          <tr style="background: #26a69a; color: white;">
            <th style="padding: 10px; border: 1px solid #00897b; width: 5%;">#</th>
            <th style="padding: 10px; border: 1px solid #00897b; text-align: ${$q.lang.rtl ? 'right' : 'left'}; width: 30%;">${t('products.product')}</th>
            <th style="padding: 10px; border: 1px solid #00897b; width: 15%;">${t('products.category')}</th>
            <th style="padding: 10px; border: 1px solid #00897b; width: 15%;">${t('inventory.quantity')}</th>
            <th style="padding: 10px; border: 1px solid #00897b; width: 11%;">${t('sales.wholesale')}</th>
            <th style="padding: 10px; border: 1px solid #00897b; width: 12%;">${t('sales.semiWholesale')}</th>
            <th style="padding: 10px; border: 1px solid #00897b; width: 12%;">${t('sales.retail')}</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <!-- Signature Section -->
      <div style="display: flex; justify-content: space-around; margin-top: 40px;">
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">${t('inventory.storekeeperSignature')}</div>
        </div>
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">${t('inventory.distributorSignature')}</div>
        </div>
      </div>
    </div>
  `;
  
  // Create temporary container
  const container = document.createElement('div');
  container.innerHTML = htmlContent;
  container.style.position = 'absolute';
  container.style.left = '-9999px';
  container.style.top = '0';
  document.body.appendChild(container);
  
  // Add Google Fonts for Arabic if not already present (likely added by other function, but good to ensure)
  if (!document.querySelector('link[href*="fonts.googleapis.com"]')) {
      const link = document.createElement('link');
      link.href = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap';
      link.rel = 'stylesheet';
      document.head.appendChild(link);
  }
  
  // Wait for fonts to load
  await new Promise(resolve => setTimeout(resolve, 500));
  
  try {
    const element = container.querySelector('#pdf-content-prices');
    
    // Convert to canvas
    const canvas = await html2canvas(element, {
      scale: 2,
      useCORS: true,
      allowTaint: true,
      backgroundColor: '#ffffff'
    });
    
    // Create PDF
    const imgData = canvas.toDataURL('image/png');
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    });
    
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();
    const imgWidth = canvas.width;
    const imgHeight = canvas.height;
    const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
    const imgX = (pdfWidth - imgWidth * ratio) / 2;
    const imgY = 0;
    
    pdf.addImage(imgData, 'PNG', imgX, imgY, imgWidth * ratio, imgHeight * ratio);
    
    // Save PDF
    const fileName = `price_list_distribution_${today}.pdf`;
    pdf.save(fileName);
    $q.notify({ type: 'positive', message: t('messages.pdfDownloaded') });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToGeneratePdf') });
  } finally {
    // Cleanup
    document.body.removeChild(container);
    generatingPriceListPdf.value = false;
  }
};

const exportToExcel = () => {
  // TODO: Implement Excel export
  $q.notify({ type: 'info', message: t('messages.excelExportComingSoon') });
};

onMounted(() => {
  loadInventory();
  loadCategories();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

.inventory-card {
  border-radius: 12px;
  transition: transform 0.2s, box-shadow 0.2s;
  height: 100%;
}

.inventory-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>

