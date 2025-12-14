<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('nav.inventory') }}</div>
      <div class="row q-gutter-sm">
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
      <div class="col-12 col-md-3">
        <q-card class="bg-primary text-white">
          <q-card-section>
            <div class="text-h6">{{ $t('inventory.totalProducts') }}</div>
            <div class="text-h4">{{ statistics.totalProducts }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-positive text-white">
          <q-card-section>
            <div class="text-h6">{{ $t('inventory.inStock') }}</div>
            <div class="text-h4">{{ statistics.inStock }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-warning text-white">
          <q-card-section>
            <div class="text-h6">{{ $t('inventory.lowStock') }}</div>
            <div class="text-h4">{{ statistics.lowStock }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-negative text-white">
          <q-card-section>
            <div class="text-h6">{{ $t('inventory.outOfStock') }}</div>
            <div class="text-h4">{{ statistics.outOfStock }}</div>
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

      <q-btn-toggle
        v-model="viewMode"
        toggle-color="primary"
        :options="viewModeOptions"
      />

      <q-btn flat round dense icon="download" color="positive" @click="exportToExcel">
        <q-tooltip>{{ $t('inventory.exportToExcel') }}</q-tooltip>
      </q-btn>
    </div>

    <!-- Inventory Cards View -->
    <div v-if="viewMode === 'cards'">
      <div v-if="filteredInventory.length === 0" class="text-center q-pa-xl">
        <q-icon name="inventory_2" size="64px" color="grey-4" />
        <div class="text-h6 text-grey-6 q-mt-md">{{ $t('inventory.noProductsFound') }}</div>
      </div>
      <div v-else class="row q-col-gutter-md">
        <div
          v-for="item in filteredInventory"
          :key="item.id"
          class="col-12 col-sm-6 col-md-4 col-lg-3"
        >
        <q-card class="inventory-card">
          <q-card-section>
            <div class="row items-start justify-between q-mb-sm">
              <div class="col">
                <div class="text-h6 text-weight-bold">{{ item.name }}</div>
                <div class="text-caption text-grey-6">{{ item.category?.name || 'N/A' }}</div>
              </div>
              <q-btn
                flat
                round
                dense
                icon="more_vert"
                color="grey-7"
                size="sm"
              >
                <q-menu>
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup @click="viewProductDetails(item)">
                      <q-item-section avatar>
                        <q-icon name="info" />
                      </q-item-section>
                      <q-item-section>{{ $t('inventory.viewDetails') }}</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <!-- Stock Quantity and Trend -->
            <div class="row items-center q-mb-sm">
              <div class="col">
                <div class="text-caption text-grey-7">{{ $t('inventory.stockQuantity') }}</div>
                <div class="text-h6" :class="getStockQuantityClass(item.current_stock_quantity)">
                  {{ formatQuantity(item.current_stock_quantity) }}
                </div>
              </div>
              <div class="col-auto">
                <q-badge
                  :color="getStockStatusColor(item.stock_status)"
                  :label="item.stock_status"
                />
              </div>
            </div>

            <!-- Committed Quantity -->
            <div class="row items-center q-mb-sm">
              <div class="col">
                <div class="text-caption text-grey-7">{{ $t('inventory.inDistribution') }}</div>
                <div class="text-weight-bold">
                  {{ formatQuantity(item.committed_quantity || 0) }}
                </div>
              </div>
            </div>

            <!-- Stock Trend Chart -->
            <div class="q-mb-sm">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('inventory.stockTrend') }}</div>
              <div style="width: 100%; height: 60px; display: block;">
                <svg viewBox="0 0 120 40" style="width: 100%; height: 100%;" preserveAspectRatio="none">
                  <polyline
                    :points="generateChartPoints(item.stockHistory || [])"
                    fill="none"
                    stroke="#1976d2"
                    stroke-width="2"
                    vector-effect="non-scaling-stroke"
                  />
                </svg>
              </div>
            </div>

            <!-- Purchase Price and Trend -->
            <div class="row items-center q-mb-sm">
              <div class="col">
                <div class="text-caption text-grey-7">{{ $t('inventory.purchasePrice') }}</div>
                <div class="text-weight-bold">
                  {{ item.purchasePriceHistory?.current_price ? formatCurrency(item.purchasePriceHistory.current_price) : $t('inventory.na') }}
                </div>
              </div>
            </div>

            <!-- Price Trend Chart -->
            <div class="q-mb-sm">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('inventory.priceTrend') }}</div>
              <div style="width: 100%; height: 60px; display: block;">
                <svg viewBox="0 0 120 40" style="width: 100%; height: 100%;" preserveAspectRatio="none">
                  <polyline
                    :points="generatePriceChartPoints(item.purchasePriceHistory?.monthly_data || [])"
                    fill="none"
                    stroke="#26a69a"
                    stroke-width="2"
                    vector-effect="non-scaling-stroke"
                  />
                </svg>
              </div>
            </div>

            <!-- Sale Price -->
            <div class="row items-center">
              <div class="col">
                <div class="text-caption text-grey-7">{{ $t('inventory.salePriceWholesale') }}</div>
                <div class="text-weight-bold">
                  {{ item.currentPrice ? formatCurrency(item.currentPrice.wholesale_price) : $t('inventory.na') }}
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
        </div>
      </div>
    </div>

    <!-- Inventory Table -->
    <div v-else>
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
        <q-td :props="props" :class="getStockQuantityClass(props.row.current_stock_quantity)">
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

      <template v-slot:body-cell-stock_chart="props">
        <q-td :props="props">
          <div style="width: 120px; height: 40px; display: inline-block; position: relative;">
            <svg viewBox="0 0 120 40" style="width: 100%; height: 100%;" preserveAspectRatio="none">
              <polyline
                :points="generateChartPoints(props.row.stockHistory || [])"
                fill="none"
                stroke="#1976d2"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
              />
            </svg>
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-purchase_price="props">
        <q-td :props="props" class="text-right">
          <div v-if="props.row.purchasePriceHistory?.current_price" class="text-weight-bold">
            {{ formatCurrency(props.row.purchasePriceHistory.current_price) }}
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-price_chart="props">
        <q-td :props="props">
          <div style="width: 120px; height: 40px; display: inline-block; position: relative;">
            <svg viewBox="0 0 120 40" style="width: 100%; height: 100%;" preserveAspectRatio="none">
              <polyline
                :points="generatePriceChartPoints(props.row.purchasePriceHistory?.monthly_data || [])"
                fill="none"
                stroke="#26a69a"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
              />
            </svg>
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-price="props">
        <q-td :props="props">
          <div v-if="props.row.currentPrice" class="text-right">
            <div class="text-weight-bold">{{ formatCurrency(props.row.currentPrice.wholesale_price) }}</div>
            <div class="text-caption text-grey-6">{{ $t('inventory.wholesale') }}</div>
          </div>
          <div v-else class="text-grey-6">{{ $t('inventory.na') }}</div>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            flat
            round
            dense
            icon="more_vert"
            color="grey-7"
            size="sm"
          >
            <q-menu>
              <q-list style="min-width: 150px">
                <q-item clickable v-close-popup @click="viewProductDetails(props.row)">
                  <q-item-section avatar>
                    <q-icon name="info" />
                  </q-item-section>
                  <q-item-section>{{ $t('inventory.viewDetails') }}</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
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
              <div class="text-h6" :class="getStockQuantityClass(selectedProduct.current_stock_quantity)">
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
const inventory = ref([]);
const categories = ref([]);
const searchText = ref('');
const stockFilter = ref(null);
const categoryFilter = ref(null);
const viewMode = ref('table'); // 'table' or 'cards'
const pagination = ref({
  rowsPerPage: 25
});
const showDetailsDialog = ref(false);
const selectedProduct = ref(null);

const stockFilterOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('inventory.inStock'), value: 'In Stock' },
  { label: t('inventory.lowStock'), value: 'Low Stock' },
  { label: t('inventory.outOfStock'), value: 'Out of Stock' },
]);

const viewModeOptions = computed(() => [
  { label: t('inventory.table'), value: 'table', icon: 'table_chart' },
  { label: t('inventory.cards'), value: 'cards', icon: 'view_module' }
]);

const columns = computed(() => [
  { name: 'name', label: t('products.name'), field: 'name', align: 'left', sortable: true },
  { name: 'category', label: t('products.category'), field: row => row.category?.name || 'N/A', align: 'left', sortable: true },
  { name: 'current_stock_quantity', label: t('products.stock'), field: 'current_stock_quantity', align: 'center', sortable: true },
  { name: 'committed_quantity', label: t('inventory.inDistribution'), field: row => row.committed_quantity || 0, align: 'center', sortable: true },
  { name: 'stock_chart', label: t('inventory.stockTrend'), field: 'stock_chart', align: 'center', sortable: false },
  { name: 'purchase_price', label: t('purchases.purchasePrice'), field: 'purchase_price', align: 'right', sortable: true },
  { name: 'price_chart', label: t('inventory.priceTrend'), field: 'price_chart', align: 'center', sortable: false },
  { name: 'price', label: t('products.wholesalePrice'), field: row => row.currentPrice?.wholesale_price || 0, align: 'right', sortable: true },
  { name: 'stock_status', label: t('common.status'), field: 'stock_status', align: 'center', sortable: true },
  { name: 'actions', label: '', field: 'actions', align: 'center', sortable: false, style: 'width: 50px' },
]);

const statistics = computed(() => {
  const total = inventory.value.length;
  const inStock = inventory.value.filter(item => item.stock_status === 'In Stock').length;
  const lowStock = inventory.value.filter(item => item.stock_status === 'Low Stock').length;
  const outOfStock = inventory.value.filter(item => item.stock_status === 'Out of Stock').length;

  return {
    totalProducts: total,
    inStock,
    lowStock,
    outOfStock,
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
      let stockStatus = 'Out of Stock';
      if (quantity > 10) {
        stockStatus = 'In Stock';
      } else if (quantity > 0) {
        stockStatus = 'Low Stock';
      }
      
      return {
        ...product,
        stock_status: stockStatus,
        stockHistory: [], // Will be loaded on demand or lazily
      };
    });
    
    // Load stock history for all products in parallel (after initial load)
    loadStockHistoryForProducts(products);
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const loadStockHistoryForProducts = async (products) => {
  try {
    // Load stock history and purchase price history for all products in parallel
    const stockHistoryPromises = products.map(product => 
      api.get(`/products/${product.id}/stock-history`).catch(() => ({ data: { monthly_data: [] } }))
    );
    
    const priceHistoryPromises = products.map(product => 
      api.get(`/products/${product.id}/purchase-price-history`).catch(() => ({ data: { monthly_data: [] } }))
    );
    
    const [stockHistoryResults, priceHistoryResults] = await Promise.all([
      Promise.all(stockHistoryPromises),
      Promise.all(priceHistoryPromises),
    ]);
    
    // Update inventory with stock history and price history
    inventory.value = inventory.value.map((item, index) => {
      const stockHistory = stockHistoryResults[index]?.data?.monthly_data || [];
      const priceHistory = priceHistoryResults[index]?.data || null;
      return {
        ...item,
        stockHistory: stockHistory,
        purchasePriceHistory: priceHistory,
      };
    });
  } catch (error) {
    console.error('Failed to load stock/price history:', error);
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
  switch (status) {
    case 'In Stock':
      return 'positive';
    case 'Low Stock':
      return 'warning';
    case 'Out of Stock':
      return 'negative';
    default:
      return 'grey';
  }
};

const getStockQuantityClass = (quantity) => {
  const qty = parseFloat(quantity) || 0;
  if (qty === 0) return 'text-negative';
  if (qty <= 10) return 'text-warning';
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

const generateChartPoints = (stockHistory) => {
  if (!stockHistory || stockHistory.length === 0) {
    // Return flat line in the middle if no data
    return '0,20 120,20';
  }

  const width = 120;
  const height = 40;
  const padding = 5;
  const chartWidth = width - (padding * 2);
  const chartHeight = height - (padding * 2);

  // Get stock values from history
  const stockValues = stockHistory.map(item => parseFloat(item.stock_level) || 0);
  
  // Find min and max for scaling
  const allValues = stockValues.filter(v => !isNaN(v));
  if (allValues.length === 0) {
    return '0,20 120,20';
  }
  
  const maxStock = Math.max(...allValues);
  const minStock = Math.min(...allValues);
  const range = maxStock - minStock || 1; // Avoid division by zero
  
  // Add some padding to the range if all values are the same to make changes more visible
  const paddedMin = minStock > 0 ? minStock * 0.9 : 0;
  const paddedMax = maxStock * 1.1;
  const paddedRange = paddedMax - paddedMin || 1;

  // Generate points
  const points = stockValues.map((value, index) => {
    const x = padding + (index / Math.max(stockValues.length - 1, 1)) * chartWidth;
    // Normalize value to 0-1 range using padded range for better visibility
    const normalizedValue = paddedRange > 0 
      ? ((value - paddedMin) / paddedRange)
      : 0.5;
    // Clamp to 0-1 range
    const clampedValue = Math.max(0, Math.min(1, normalizedValue));
    // Invert Y (SVG coordinates: 0 is at top, so we subtract from height)
    const y = padding + chartHeight - (clampedValue * chartHeight);
    return `${x.toFixed(2)},${y.toFixed(2)}`;
  }).join(' ');

  return points;
};

const generatePriceChartPoints = (priceHistory) => {
  if (!priceHistory || priceHistory.length === 0) {
    // Return flat line in the middle if no data
    return '0,20 120,20';
  }

  const width = 120;
  const height = 40;
  const padding = 5;
  const chartWidth = width - (padding * 2);
  const chartHeight = height - (padding * 2);

  // Get price values from history (use avg_price, fallback to available price)
  const priceValues = priceHistory.map(item => {
    const price = item.avg_price !== null ? parseFloat(item.avg_price) : 
                  (item.min_price !== null ? parseFloat(item.min_price) : null);
    return price !== null && !isNaN(price) ? price : null;
  });
  
  // Filter out null values for min/max calculation
  const validPrices = priceValues.filter(v => v !== null);
  if (validPrices.length === 0) {
    return '0,20 120,20';
  }
  
  const maxPrice = Math.max(...validPrices);
  const minPrice = Math.min(...validPrices);
  const range = maxPrice - minPrice || 1; // Avoid division by zero

  // Generate points, interpolating null values
  let lastValidPrice = null;
  let lastValidIndex = -1;
  
  const points = priceValues.map((value, index) => {
    const x = padding + (index / Math.max(priceValues.length - 1, 1)) * chartWidth;
    
    // If value is null, use last valid value or min price
    let price = value;
    if (price === null) {
      price = lastValidPrice !== null ? lastValidPrice : minPrice;
    } else {
      lastValidPrice = price;
      lastValidIndex = index;
    }
    
    // Normalize value to 0-1 range
    const normalizedValue = range > 0 
      ? ((price - minPrice) / range)
      : 0.5;
    // Invert Y (SVG coordinates: 0 is at top, so we subtract from height)
    const y = padding + chartHeight - (normalizedValue * chartHeight);
    return `${x.toFixed(2)},${y.toFixed(2)}`;
  }).join(' ');

  return points;
};

const printInventoryCheckSheet = async () => {
  if (!filteredInventory.value.length) {
    $q.notify({ type: 'warning', message: t('messages.noDataToGeneratePdf') });
    return;
  }
  
  generatingPdf.value = true;
  
  const products = filteredInventory.value;
  const today = new Date().toISOString().split('T')[0];
  
  // Calculate totals
  const totalStock = products.reduce((sum, p) => sum + (parseFloat(p.current_stock_quantity) || 0), 0);
  const totalCommitted = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
  
  // Generate table rows HTML
  const tableRows = products.map((p, index) => {
    const stock = parseFloat(p.current_stock_quantity) || 0;
    const committed = parseFloat(p.committed_quantity) || 0;
    const statusColor = p.stock_status === 'In Stock' ? '#4caf50' : 
                        p.stock_status === 'Low Stock' ? '#ff9800' : '#f44336';
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
        <div style="text-align: center; min-width: 80px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('inventory.outOfStock')}</div>
          <div style="font-size: 14px; font-weight: bold; color: #f44336;">${statistics.value.outOfStock}</div>
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

