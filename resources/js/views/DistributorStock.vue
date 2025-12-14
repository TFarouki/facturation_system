<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('nav.distributorStock') }}</div>
      <div class="row q-gutter-sm">
        <q-btn 
          v-if="selectedDistributor && filteredProducts.length > 0"
          color="orange" 
          icon="picture_as_pdf" 
          :label="$t('distributorStock.stockCheckPdf')" 
          :loading="generatingPdf"
          @click="printStockCheckSheet" 
        />
        <q-btn color="secondary" icon="download" :label="$t('distributorStock.export')" @click="exportToExcel" />
      </div>
    </div>

    <!-- Filters -->
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-select
        v-model="selectedDistributor"
        :options="distributors"
        option-label="name"
        option-value="id"
        outlined
        dense
        :label="$t('distributorStock.selectDistributor')"
        style="min-width: 300px"
        clearable
        emit-value
        map-options
      >
        <template v-slot:prepend>
          <q-icon name="person" />
        </template>
      </q-select>

      <q-input
        v-model="searchText"
        outlined
        dense
        :placeholder="$t('distributorStock.searchProducts')"
        style="min-width: 250px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append v-if="searchText">
          <q-icon name="clear" class="cursor-pointer" @click="searchText = ''" />
        </template>
      </q-input>

      <q-space />

      <div class="text-subtitle1 text-grey-7">
        {{ $t('distributorStock.totalProducts') }}: <span class="text-weight-bold text-primary">{{ filteredProducts.length }}</span>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row q-col-gutter-md q-mb-md" v-if="selectedDistributor">
      <div class="col-12 col-md-3">
        <q-card class="bg-primary text-white">
          <q-card-section>
            <div class="text-subtitle2">{{ $t('distributorStock.totalProducts') }}</div>
            <div class="text-h4">{{ summaryStats.totalProducts }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card class="bg-positive text-white">
          <q-card-section>
            <div class="text-subtitle2">{{ $t('distributorStock.committedQty') }}</div>
            <div class="text-h4">{{ formatQuantity(summaryStats.totalQuantity) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card :class="summaryStats.totalDifference !== 0 ? 'bg-negative' : 'bg-info'" class="text-white">
          <q-card-section>
            <div class="text-subtitle2">{{ $t('distributorStock.actualQty') }}</div>
            <div class="text-h4">{{ formatQuantity(summaryStats.totalActual) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-3">
        <q-card :class="summaryStats.totalDifference !== 0 ? 'bg-negative' : 'bg-grey-6'" class="text-white">
          <q-card-section>
            <div class="text-subtitle2">{{ $t('distributorStock.difference') }}</div>
            <div class="text-h4">{{ formatQuantity(summaryStats.totalDifference) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Distributor Info Card -->
    <q-card v-if="selectedDistributorInfo" class="q-mb-md">
      <q-card-section>
        <div class="row items-center q-gutter-md">
          <q-avatar size="60px" color="primary" text-color="white" icon="person" />
          <div class="col">
            <div class="text-h6">{{ selectedDistributorInfo.name }}</div>
            <div class="text-grey-7" v-if="selectedDistributorInfo.phone">
              <q-icon name="phone" size="xs" /> {{ selectedDistributorInfo.phone }}
            </div>
            <div class="text-grey-7" v-if="selectedDistributorInfo.vehicle_plate">
              <q-icon name="directions_car" size="xs" /> {{ selectedDistributorInfo.vehicle_plate }} - {{ selectedDistributorInfo.vehicle_type }}
            </div>
          </div>
          <div v-if="hasChanges" class="text-negative text-weight-bold">
            <q-icon name="warning" /> {{ $t('distributorStock.unsavedChanges') }}
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Products Table -->
    <q-table
      v-if="selectedDistributor"
      :rows="filteredProducts"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :pagination="{ rowsPerPage: 50 }"
      :rows-per-page-options="[20, 50, 100]"
    >
      <template v-slot:body-cell-product="props">
        <q-td :props="props">
          <div class="text-weight-bold">{{ props.row.name }}</div>
          <div class="text-caption text-grey-6" v-if="props.row.category">
            {{ props.row.category.name }}
          </div>
        </q-td>
      </template>
      <template v-slot:body-cell-first_delivery_date="props">
        <q-td :props="props" class="text-center">
          <div v-if="props.row.first_delivery_date">
            {{ formatDate(props.row.first_delivery_date) }}
          </div>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
      <template v-slot:body-cell-committed_quantity="props">
        <q-td :props="props" class="text-center">
          <span class="text-weight-bold text-h6">{{ formatQuantity(props.row.committed_quantity) }}</span>
          <div class="text-caption text-grey-6" v-if="props.row.unit">
            {{ props.row.unit.unit_symbol_en }}
          </div>
        </q-td>
      </template>
      <template v-slot:body-cell-actual_quantity="props">
        <q-td :props="props" class="text-center">
          <q-input
            v-model.number="stockReview[props.row.id]"
            type="number"
            outlined
            dense
            :min="0"
            step="0.01"
            style="width: 100px"
            @update:model-value="onActualQuantityChange(props.row.id)"
          />
        </q-td>
      </template>
      <template v-slot:body-cell-difference="props">
        <q-td :props="props" class="text-center">
          <span 
            :class="getDifferenceClass(props.row.id, props.row.committed_quantity)"
            class="text-weight-bold text-h6"
          >
            {{ formatDifference(props.row.id, props.row.committed_quantity) }}
          </span>
        </q-td>
      </template>
      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-lg text-grey-6">
          <q-icon name="inventory_2" size="xl" class="q-mr-md" />
          <span>{{ $t('distributorStock.noProductsFoundForDistributor') }}</span>
        </div>
      </template>
      <template v-slot:bottom-row v-if="filteredProducts.length > 0">
        <q-tr class="bg-grey-2">
          <q-td colspan="2" class="text-right text-weight-bold">TOTAL:</q-td>
          <q-td class="text-center text-weight-bold text-h6">{{ formatQuantity(summaryStats.totalQuantity) }}</q-td>
          <q-td class="text-center text-weight-bold text-h6">{{ formatQuantity(summaryStats.totalActual) }}</q-td>
          <q-td class="text-center text-weight-bold text-h6" :class="summaryStats.totalDifference !== 0 ? 'text-negative' : ''">
            {{ formatQuantity(summaryStats.totalDifference) }}
          </q-td>
        </q-tr>
      </template>
    </q-table>

    <!-- Action Buttons -->
    <div v-if="selectedDistributor && filteredProducts.length > 0" class="row justify-end q-mt-md q-gutter-sm">
      <q-btn 
        v-if="!hasChanges"
        :label="$t('distributorStock.confirmMatch')" 
        color="positive" 
        icon="check_circle"
        :loading="saving"
        @click="confirmNoChanges" 
      />
      <q-btn 
        v-else
        :label="$t('distributorStock.saveReview')" 
        color="primary" 
        icon="save"
        :loading="saving"
        @click="saveStockReview" 
      />
    </div>

    <!-- Pending Reviews Alert -->
    <q-banner v-if="hasPendingReviews && selectedDistributor" class="bg-warning text-white q-mt-md" rounded>
      <template v-slot:avatar>
        <q-icon name="warning" />
      </template>
      <div class="text-weight-bold">{{ $t('distributorStock.pendingStockDiff') }}</div>
      <div>{{ $t('distributorStock.pendingDiffMessage') }}</div>
    </q-banner>

    <!-- All Distributors Overview -->
    <div v-if="!selectedDistributor" class="q-mt-lg">
      <div class="text-h5 q-mb-md">{{ $t('distributorStock.allDistributorsOverview') }}</div>
      <div class="row q-col-gutter-md">
        <div v-for="dist in distributorsWithStock" :key="dist.id" class="col-12 col-md-4">
          <q-card class="cursor-pointer" @click="selectedDistributor = dist.id">
            <q-card-section>
              <div class="row items-center justify-between">
                <div>
                  <div class="text-h6">{{ dist.name }}</div>
                  <div class="text-caption text-grey-6">{{ dist.phone }}</div>
                </div>
                <q-avatar color="primary" text-color="white" icon="person" />
              </div>
            </q-card-section>
            <q-separator />
            <q-card-section>
              <div class="row justify-between">
                <div>
                  <div class="text-caption text-grey-6">Products</div>
                  <div class="text-h6 text-primary">{{ dist.productCount }}</div>
                </div>
                <div>
                  <div class="text-caption text-grey-6">Total Qty</div>
                  <div class="text-h6 text-positive">{{ formatQuantity(dist.totalQuantity) }}</div>
                </div>
                <div v-if="dist.hasPendingDiff">
                  <div class="text-caption text-grey-6">Status</div>
                  <q-badge color="negative">Pending</q-badge>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { useQuasar } from 'quasar';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const loading = ref(false);
const saving = ref(false);
const generatingPdf = ref(false);
const distributors = ref([]);
const selectedDistributor = ref(null);
const committedProducts = ref([]);
const searchText = ref('');
const stockReview = ref({}); // { productId: actualQuantity }
const pendingReviews = ref({}); // Stored pending reviews from backend

const columns = computed(() => [
  { name: 'product', label: t('products.name'), field: 'name', align: 'left', sortable: true },
  { name: 'first_delivery_date', label: t('common.date'), field: 'first_delivery_date', align: 'center', sortable: true },
  { name: 'committed_quantity', label: t('distributorStock.committed'), field: 'committed_quantity', align: 'center', sortable: true },
  { name: 'actual_quantity', label: t('distributorStock.actual'), field: 'actual_quantity', align: 'center' },
  { name: 'difference', label: t('distributorStock.difference'), field: 'difference', align: 'center' },
]);

const selectedDistributorInfo = computed(() => {
  if (!selectedDistributor.value) return null;
  return distributors.value.find(d => d.id === selectedDistributor.value);
});

const filteredProducts = computed(() => {
  const products = Array.isArray(committedProducts.value) ? committedProducts.value : [];
  if (!searchText.value) return products;
  const search = searchText.value.toLowerCase();
  return products.filter(p => 
    p.name?.toLowerCase().includes(search) ||
    p.category?.name?.toLowerCase().includes(search)
  );
});

const summaryStats = computed(() => {
  const products = filteredProducts.value || [];
  const totalQuantity = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
  const totalActual = products.reduce((sum, p) => sum + (parseFloat(stockReview.value[p.id]) || 0), 0);
  return {
    totalProducts: products.length,
    totalQuantity,
    totalActual,
    totalDifference: totalActual - totalQuantity,
  };
});

const hasChanges = computed(() => {
  const products = filteredProducts.value || [];
  return products.some(p => {
    const actual = parseFloat(stockReview.value[p.id]) || 0;
    const committed = parseFloat(p.committed_quantity) || 0;
    return actual !== committed;
  });
});

const hasPendingReviews = computed(() => {
  return Object.keys(pendingReviews.value).length > 0;
});

const distributorsWithStock = computed(() => {
  return distributors.value.map(dist => ({
    ...dist,
    productCount: dist.stockCount || 0,
    totalQuantity: dist.totalCommitted || 0,
    hasPendingDiff: dist.hasPendingDiff || false,
  })).filter(d => d.productCount > 0 || d.totalQuantity > 0);
});

const formatQuantity = (qty) => {
  if (!qty && qty !== 0) return '0.00';
  return parseFloat(qty).toFixed(2);
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getDifferenceClass = (productId, committedQty) => {
  const actual = parseFloat(stockReview.value[productId]) || 0;
  const committed = parseFloat(committedQty) || 0;
  const diff = actual - committed;
  if (diff < 0) return 'text-negative';
  if (diff > 0) return 'text-positive';
  return 'text-grey-6';
};

const formatDifference = (productId, committedQty) => {
  const actual = parseFloat(stockReview.value[productId]) || 0;
  const committed = parseFloat(committedQty) || 0;
  const diff = actual - committed;
  if (diff === 0) return '0.00';
  return (diff > 0 ? '+' : '') + diff.toFixed(2);
};

const onActualQuantityChange = (productId) => {
  // Trigger reactivity
  stockReview.value = { ...stockReview.value };
};

const initializeStockReview = () => {
  const products = committedProducts.value || [];
  const review = {};
  products.forEach(p => {
    // Use pending review value if exists, otherwise use committed quantity
    review[p.id] = pendingReviews.value[p.id] !== undefined 
      ? pendingReviews.value[p.id] 
      : parseFloat(p.committed_quantity) || 0;
  });
  stockReview.value = review;
};

const resetStockReview = () => {
  $q.dialog({
    title: t('distributorStock.resetValues'),
    message: t('distributorStock.resetMessage'),
    cancel: true,
    persistent: true,
  }).onOk(() => {
    const products = committedProducts.value || [];
    const review = {};
    products.forEach(p => {
      review[p.id] = parseFloat(p.committed_quantity) || 0;
    });
    stockReview.value = review;
    $q.notify({ type: 'info', message: t('distributorStock.valuesReset') });
  });
};

const saveStockReview = async () => {
  if (!selectedDistributor.value) return;
  
  saving.value = true;
  try {
    const differences = [];
    const products = committedProducts.value || [];
    
    products.forEach(p => {
      const actual = parseFloat(stockReview.value[p.id]) || 0;
      const committed = parseFloat(p.committed_quantity) || 0;
      const diff = actual - committed;
      
      if (diff !== 0) {
        differences.push({
          product_id: p.id,
          product_name: p.name,
          committed_quantity: committed,
          actual_quantity: actual,
          difference: diff,
        });
      }
    });
    
    await api.post('/stock-reviews', {
      distributor_id: selectedDistributor.value,
      review_date: new Date().toISOString().split('T')[0],
      differences,
    });
    
    $q.notify({ 
      type: 'positive', 
      message: differences.length > 0 
        ? t('distributorStock.reviewSavedWithDiff', { count: differences.length }) 
        : t('distributorStock.reviewSaved')
    });
    
    // Reload to get updated pending reviews
    await loadCommittedProducts();
    
  } catch (error) {
    console.error('Failed to save stock review:', error);
    $q.notify({ type: 'negative', message: 'Failed to save stock review' });
  } finally {
    saving.value = false;
  }
};

const confirmNoChanges = async () => {
  $q.dialog({
    title: t('distributorStock.confirmMatch'),
    message: t('distributorStock.confirmMatchMessage'),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    if (!selectedDistributor.value) return;
    
    saving.value = true;
    try {
      // Save with empty differences to record the verification
      await api.post('/stock-reviews', {
        distributor_id: selectedDistributor.value,
        review_date: new Date().toISOString().split('T')[0],
        differences: [],
        notes: 'Stock verified - all quantities match',
      });
      
      $q.notify({ 
        type: 'positive', 
        message: t('distributorStock.stockVerifiedMatch')
      });
      
      // Reload to get updated pending reviews
      await loadCommittedProducts();
      
    } catch (error) {
      console.error('Failed to confirm stock match:', error);
      $q.notify({ type: 'negative', message: 'Failed to confirm stock match' });
    } finally {
      saving.value = false;
    }
  });
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/distributors');
    distributors.value = Array.isArray(response.data) ? response.data : [];
    
    // Load summary for each distributor
    for (const dist of distributors.value) {
      try {
        const stockResponse = await api.get(`/distributors/${dist.id}/committed-products`);
        const products = Array.isArray(stockResponse.data) ? stockResponse.data : [];
        dist.stockCount = products.length;
        dist.totalCommitted = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
      } catch (e) {
        dist.stockCount = 0;
        dist.totalCommitted = 0;
      }
    }
  } catch (error) {
    console.error('Failed to load distributors:', error);
    $q.notify({ type: 'negative', message: 'Failed to load distributors' });
  }
};

const loadCommittedProducts = async () => {
  if (!selectedDistributor.value) {
    committedProducts.value = [];
    stockReview.value = {};
    return;
  }
  
  loading.value = true;
  try {
    const response = await api.get(`/distributors/${selectedDistributor.value}/committed-products`);
    committedProducts.value = Array.isArray(response.data) ? response.data : [];
    
    // Load pending reviews for this distributor
    try {
      const reviewResponse = await api.get(`/stock-reviews/pending/${selectedDistributor.value}`);
      pendingReviews.value = reviewResponse.data || {};
    } catch (e) {
      pendingReviews.value = {};
    }
    
    // Initialize stock review with committed quantities or pending values
    initializeStockReview();
    
  } catch (error) {
    console.error('Failed to load committed products:', error);
    $q.notify({ type: 'negative', message: 'Failed to load committed products' });
    committedProducts.value = [];
  } finally {
    loading.value = false;
  }
};

const exportToExcel = () => {
  if (!filteredProducts.value.length) {
    $q.notify({ type: 'warning', message: t('messages.noDataToExport') });
    return;
  }
  
  const distributorName = selectedDistributorInfo.value?.name || 'All';
  
  // Create CSV content
  const headers = ['Product', 'Category', 'Start Date', 'Committed Qty', 'Actual Qty', 'Difference'];
  const rows = filteredProducts.value.map(p => {
    const actual = parseFloat(stockReview.value[p.id]) || 0;
    const committed = parseFloat(p.committed_quantity) || 0;
    return [
      p.name,
      p.category?.name || '',
      formatDate(p.first_delivery_date),
      formatQuantity(committed),
      formatQuantity(actual),
      formatQuantity(actual - committed),
    ];
  });
  
  // Add BOM for Excel to recognize Arabic characters
  const BOM = '\uFEFF';
  const csvContent = BOM + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
  
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `stock_review_${distributorName}_${new Date().toISOString().split('T')[0]}.csv`;
  link.click();
  
  $q.notify({ type: 'positive', message: t('messages.exportCompleted') });
};

const printStockCheckSheet = async () => {
  if (!filteredProducts.value.length || !selectedDistributorInfo.value) {
    $q.notify({ type: 'warning', message: t('messages.noDataToGeneratePdf') });
    return;
  }
  
  generatingPdf.value = true;
  
  const distributor = selectedDistributorInfo.value;
  const products = filteredProducts.value;
  const today = new Date().toISOString().split('T')[0];
  
  // Calculate total
  const totalCommitted = products.reduce((sum, p) => sum + (parseFloat(p.committed_quantity) || 0), 0);
  
  // Generate table rows HTML
  const tableRows = products.map((p, index) => {
    const committed = parseFloat(p.committed_quantity) || 0;
    return `
      <tr>
        <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${index + 1}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${p.name}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${p.category?.name || '-'}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${p.unit?.unit_symbol_en || '-'}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; font-weight: bold; background: #f5f5f5;">${formatQuantity(committed)}</td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; background: #fffde7; min-width: 80px;"></td>
        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; min-width: 60px;"></td>
      </tr>
    `;
  }).join('');
  
  // Create HTML content
  const htmlContent = `
    <div id="pdf-content" style="width: 794px; padding: 20px; font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; background: white; direction: rtl;">
      <!-- Header -->
      <div style="background: #1976d2; color: white; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px;">
        <div style="font-size: 22px; font-weight: bold; margin-bottom: 5px;">${t('distributorStock.stockCheckSheet')}</div>
      </div>
      
      <!-- Info Section -->
      <div style="display: flex; justify-content: space-between; background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap;">
        <div style="text-align: right; min-width: 150px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">اسم الموزع</div>
          <div style="font-size: 14px; font-weight: bold;">${distributor.name}</div>
        </div>
        <div style="text-align: right; min-width: 120px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">رقم الهاتف</div>
          <div style="font-size: 14px; font-weight: bold;">${distributor.phone || '-'}</div>
        </div>
        <div style="text-align: right; min-width: 120px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">رقم اللوحة</div>
          <div style="font-size: 14px; font-weight: bold;">${distributor.vehicle_plate || '-'}</div>
        </div>
        <div style="text-align: right; min-width: 100px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">التاريخ</div>
          <div style="font-size: 14px; font-weight: bold;">${today}</div>
        </div>
        <div style="text-align: right; min-width: 80px; margin: 5px;">
          <div style="font-size: 11px; color: #666;">${t('distributorStock.totalProducts')}</div>
          <div style="font-size: 14px; font-weight: bold;">${products.length}</div>
        </div>
      </div>
      
      <!-- Table -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px;">
        <thead>
          <tr style="background: #1976d2; color: white;">
            <th style="padding: 10px; border: 1px solid #1565c0; width: 5%;">#</th>
            <th style="padding: 10px; border: 1px solid #1565c0; text-align: right; width: 35%;">${t('products.product')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 15%;">${t('products.category')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 8%;">${t('products.unit')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 12%;">${t('distributorStock.committedQty')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 15%;">${t('distributorStock.actualQty')}</th>
            <th style="padding: 10px; border: 1px solid #1565c0; width: 10%;">${t('distributorStock.difference')}</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
          <tr style="background: #e3f2fd; font-weight: bold;">
            <td colspan="4" style="text-align: left; padding: 10px; border: 1px solid #ddd;">${t('common.total')}</td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;">${formatQuantity(totalCommitted)}</td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;"></td>
            <td style="text-align: center; padding: 10px; border: 1px solid #ddd;"></td>
          </tr>
        </tbody>
      </table>
      
      <!-- Notes Section -->
      <div style="border: 2px dashed #999; padding: 15px; margin-bottom: 30px; min-height: 60px; border-radius: 8px;">
        <div style="font-size: 12px; color: #666; margin-bottom: 10px;">ملاحظات:</div>
      </div>
      
      <!-- Signature Section -->
      <div style="display: flex; justify-content: space-around; margin-top: 40px;">
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">توقيع الموزع</div>
        </div>
        <div style="text-align: center; width: 200px;">
          <div style="border-top: 1px solid #333; margin-top: 50px; padding-top: 10px;">توقيع المراجع</div>
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
    const fileName = `stock_check_${distributor.name.replace(/\s+/g, '_')}_${today}.pdf`;
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

watch(selectedDistributor, () => {
  loadCommittedProducts();
});

onMounted(() => {
  loadDistributors();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>
