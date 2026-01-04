<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">{{ $t('nav.dashboard') }}</div>
    
    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('dashboard.totalProducts') }}</div>
            <div class="text-h3 text-primary">{{ stats.totalProducts }}</div>
          </q-card-section>
        </q-card>
      </div>



      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('dashboard.monthSales') }}</div>
            <div class="text-h3 text-positive">{{ stats.monthlySales }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('dashboard.lowStock') }}</div>
            <div class="text-h3 text-warning">{{ stats.lowStock }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card" :class="{ 'bg-orange-1': stats.pendingReviews > 0 }">
          <q-card-section>
            <div class="text-h6" :class="{ 'text-orange-9': stats.pendingReviews > 0 }">
              {{ $t('dashboard.pendingReviews') }}
            </div>
            <div class="text-h3" :class="stats.pendingReviews > 0 ? 'text-orange-9' : 'text-grey-5'">
              {{ stats.pendingReviews }}
            </div>
          </q-card-section>
          <q-card-actions v-if="stats.pendingReviews > 0" align="right">
            <q-btn flat color="orange-9" :label="$t('common.view')" to="/distributor-stock" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md q-mt-md">
      <div class="col-12 col-md-8">
        <q-card>
          <q-card-section>
            <div class="text-h6 text-warning">{{ $t('dashboard.lowStockProducts') }}</div>
          </q-card-section>
          <q-separator />
          <q-table
            :rows="lowStockProducts"
            :columns="lowStockColumns"
            row-key="id"
            flat
            :pagination="{ rowsPerPage: 5 }"
          >
            <template v-slot:body-cell-current_stock_quantity="props">
              <q-td :props="props" class="text-negative text-weight-bold">
                {{ formatQuantity(props.row.current_stock_quantity) }}
              </q-td>
            </template>
          </q-table>
        </q-card>
      </div>
      <div class="col-12 col-md-4">
        <q-card>
          <q-card-section>
            <div class="text-h6">{{ $t('dashboard.recentActivity') }}</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <q-list>
              <q-item v-for="activity in recentActivity" :key="activity.id">
                <q-item-section avatar>
                  <q-icon :name="activity.icon" :color="activity.color" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ activity.title }}</q-item-label>
                  <q-item-label caption>{{ activity.time }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const { t } = useI18n();

const stats = ref({
  totalProducts: 0,
  monthlySales: 0,
  lowStock: 0,
  pendingReviews: 0,
});

const lowStockProducts = ref([]);

const recentActivity = computed(() => [
  { id: 1, icon: 'shopping_cart', color: 'primary', title: t('dashboard.newPurchaseInvoice'), time: t('dashboard.hoursAgo', [2]) },
  { id: 2, icon: 'sync', color: 'secondary', title: t('dashboard.cycleClosed', [123]), time: t('dashboard.hoursAgo', [5]) },
  { id: 3, icon: 'point_of_sale', color: 'positive', title: t('dashboard.newSaleRecorded'), time: t('dashboard.dayAgo') },
]);

onMounted(async () => {
  try {
    const products = await api.get('/products');
    
    stats.value.totalProducts = products.data.length;
    
    // Calculate low stock items using the same logic as Inventory.vue
    const lowStockItems = products.data.filter(product => {
      const quantity = parseFloat(product.current_stock_quantity) || 0;
      const minStock = product.min_stock_level || 10;
      return quantity > 0 && quantity <= minStock;
    });

    stats.value.lowStock = lowStockItems.length;
    lowStockProducts.value = lowStockItems;

    // Fetch pending stock reviews count
    const reviewsSummary = await api.get('/stock-reviews/summary');
    stats.value.pendingReviews = reviewsSummary.data.pending_count;
  } catch (error) {
    console.error('Failed to load dashboard stats:', error);
  }
});
const lowStockColumns = computed(() => [
  { name: 'name', label: t('products.name'), field: 'name', align: 'left' },
  { name: 'current_stock_quantity', label: t('products.stock'), field: 'current_stock_quantity', align: 'center' },
  { name: 'min_stock_level', label: t('products.minStockLevel'), field: row => row.min_stock_level || 10, align: 'center' },
]);

const formatQuantity = (quantity) => {
  const qty = parseFloat(quantity) || 0;
  return qty.toFixed(2);
};
</script>

<style scoped>
.stat-card {
  border-radius: 12px;
  transition: transform 0.2s;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}
</style>
