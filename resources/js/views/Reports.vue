<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-lg">
      <div class="text-h4 text-weight-bold text-primary">
        <q-icon name="analytics" class="q-mr-sm" />
        {{ $t('nav.reports') }}
      </div>
      
      <div class="row q-gutter-sm items-center">
        <q-input v-model="startDate" :label="$t('reports.startDate')" type="date" outlined dense bg-white />
        <q-input v-model="endDate" :label="$t('reports.endDate')" type="date" outlined dense bg-white />
        <q-btn 
          color="primary" 
          icon="refresh" 
          :label="$t('reports.generate')" 
          @click="fetchAllReports" 
          :loading="loading" 
          class="q-px-md"
          unelevated
        />
      </div>
    </div>

    <q-card flat bordered class="rounded-card bg-white">
      <q-tabs
        v-model="tab"
        dense
        class="text-grey"
        active-color="primary"
        indicator-color="primary"
        align="left"
        narrow-indicator
      >
        <q-tab name="profit" icon="payments" :label="$t('reports.profitAnalysis')" />
        <q-tab name="transactions" icon="receipt_long" :label="$t('reports.transactions')" />
        <q-tab name="top_products" icon="trending_up" :label="$t('reports.topProducts')" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated>
        <!-- Profit Analysis Tab -->
        <q-tab-panel name="profit" class="q-pa-md">
          <div v-if="loading" class="flex flex-center q-pa-xl">
            <q-spinner color="primary" size="3em" />
          </div>
          <div v-else-if="profitReport">
            <div class="row q-col-gutter-md q-mb-lg">
              <div v-for="(stat, index) in summaryStats" :key="index" class="col-12 col-sm-6 col-md-3">
                <q-card flat bordered class="stat-card" :class="'bg-' + stat.color + '-1'">
                  <q-card-section>
                    <div class="text-subtitle2" :class="'text-' + stat.color + '-8'">{{ stat.label }}</div>
                    <div class="text-h5 text-weight-bold" :class="'text-' + stat.color + '-9'">{{ stat.value }}{{ stat.suffix }}</div>
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <q-card flat bordered>
              <q-card-section>
                <div class="text-h6 q-mb-md">{{ $t('reports.productBreakdown') }}</div>
                <q-table
                  :rows="profitReport.product_breakdown"
                  :columns="breakdownColumns"
                  row-key="name"
                  flat
                  dense
                  class="no-border"
                  :rows-per-page-options="[10, 20, 50]"
                  :no-data-label="$t('common.noData')"
                />
              </q-card-section>
            </q-card>
          </div>
          <div v-else class="text-center q-pa-xl text-grey-6">
             <q-icon name="info" size="4em" />
             <div class="text-h6">{{ $t('common.noData') }}</div>
          </div>
        </q-tab-panel>

        <!-- Transactions Tab -->
        <q-tab-panel name="transactions" class="q-pa-md">
          <div v-if="loading" class="flex flex-center q-pa-xl">
            <q-spinner color="primary" size="3em" />
          </div>
          <div v-else>
            <q-table
              :rows="transactions"
              :columns="transactionColumns"
              row-key="id"
              flat
              bordered
              :rows-per-page-options="[20, 50, 100]"
              :no-data-label="$t('common.noData')"
            >
              <template v-slot:body-cell-profit="props">
                <q-td :props="props" :class="props.value >= 0 ? 'text-positive' : 'text-negative'">
                  {{ props.value.toFixed(2) }} DH
                </q-td>
              </template>
            </q-table>
          </div>
        </q-tab-panel>

        <!-- Top Products Tab -->
        <q-tab-panel name="top_products" class="q-pa-md">
          <div v-if="loading" class="flex flex-center q-pa-xl">
            <q-spinner color="primary" size="3em" />
          </div>
          <div v-else>
            <div class="row q-col-gutter-md q-mb-md">
                <div class="col-12 col-md-4">
                     <q-select
                        v-model="topProductsSort"
                        :options="sortOptions"
                        :label="$t('reports.sortBy')"
                        outlined
                        dense
                        emit-value
                        map-options
                        @update:model-value="fetchTopProducts"
                     />
                </div>
            </div>

            <q-table
              :rows="topProducts"
              :columns="topProductColumns"
              row-key="name"
              flat
              bordered
              :rows-per-page-options="[20]"
              :no-data-label="$t('common.noData')"
            >
               <template v-slot:body-cell-rank="props">
                <q-td :props="props">
                  <q-badge :color="getRankColor(props.pageIndex)" class="text-weight-bold">
                    #{{ props.pageIndex + 1 }}
                  </q-badge>
                </q-td>
              </template>
            </q-table>
          </div>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();

const tab = ref('profit');
const loading = ref(false);
const startDate = ref(new Date(new Date().setDate(1)).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

// Reports Data
const profitReport = ref(null);
const transactions = ref([]);
const topProducts = ref([]);
const topProductsSort = ref('quantity');

const sortOptions = [
    { label: t('reports.quantitySold'), value: 'quantity' },
    { label: t('reports.revenue'), value: 'revenue' },
    { label: t('reports.profit'), value: 'profit' },
];

const summaryStats = computed(() => {
  if (!profitReport.value) return [];
  return [
    { label: t('reports.totalRevenue'), value: profitReport.value.summary.total_revenue, suffix: ' DH', color: 'blue' },
    { label: t('reports.costOfGoods'), value: profitReport.value.summary.cost_of_goods_sold, suffix: ' DH', color: 'orange' },
    { label: t('reports.grossProfit'), value: profitReport.value.summary.gross_profit, suffix: ' DH', color: 'green' },
    { label: t('reports.profitMargin'), value: profitReport.value.summary.profit_margin, suffix: '%', color: 'purple' },
  ];
});

const breakdownColumns = computed(() => [
  { name: 'name', label: t('reports.product'), field: 'name', align: 'left', sortable: true },
  { name: 'quantity', label: t('reports.quantitySold'), field: 'total_quantity', align: 'right' },
  { name: 'revenue', label: t('reports.revenue'), field: 'revenue', align: 'right', format: val => parseFloat(val).toFixed(2) + ' DH' },
  { name: 'cost', label: t('reports.cost'), field: 'cost', align: 'right', format: val => parseFloat(val).toFixed(2) + ' DH' },
  { name: 'profit', label: t('reports.profit'), field: 'profit', align: 'right', format: val => parseFloat(val).toFixed(2) + ' DH' },
]);

const transactionColumns = computed(() => [
  { name: 'receipt_number', label: t('sales.receiptNumber'), field: 'receipt_number', align: 'left', sortable: true },
  { name: 'date', label: t('common.date'), field: 'date', align: 'left', sortable: true },
  { name: 'client', label: t('sales.client'), field: 'client', align: 'left' },
  { name: 'distributor', label: t('sales.distributor'), field: 'distributor', align: 'left' },
  { name: 'total_amount', label: t('common.total'), field: 'total_amount', align: 'right', format: val => val.toFixed(2) + ' DH' },
  { name: 'profit', label: t('reports.profit'), field: 'profit', align: 'right' },
]);

const topProductColumns = computed(() => [
  { name: 'rank', label: t('reports.rank'), field: 'rank', align: 'center', style: 'width: 80px' },
  { name: 'name', label: t('reports.product'), field: 'name', align: 'left', sortable: true },
  { name: 'quantity', label: t('reports.quantitySold'), field: 'total_quantity', align: 'right', sortable: true },
  { name: 'revenue', label: t('reports.revenue'), field: 'revenue', align: 'right', sortable: true, format: val => parseFloat(val).toFixed(2) + ' DH' },
  { name: 'profit', label: t('reports.profit'), field: 'profit', align: 'right', sortable: true, format: val => parseFloat(val).toFixed(2) + ' DH' },
]);

const fetchAllReports = async () => {
    loading.value = true;
    try {
        await Promise.all([
            fetchProfitReport(),
            fetchTransactions(),
            fetchTopProducts()
        ]);
    } catch (e) {
        $q.notify({ type: 'negative', message: t('messages.error') });
    } finally {
        loading.value = false;
    }
};

const fetchProfitReport = async () => {
    const response = await api.get('/reports/profit', {
      params: { start_date: startDate.value, end_date: endDate.value },
    });
    profitReport.value = response.data;
};

const fetchTransactions = async () => {
    const response = await api.get('/reports/transactions', {
      params: { start_date: startDate.value, end_date: endDate.value },
    });
    transactions.value = response.data;
};

const fetchTopProducts = async () => {
    const response = await api.get('/reports/top-products', {
      params: { 
          start_date: startDate.value, 
          end_date: endDate.value,
          sort_by: topProductsSort.value
      },
    });
    topProducts.value = response.data;
};

const getRankColor = (index) => {
    if (index === 0) return 'amber';
    if (index === 1) return 'grey-6';
    if (index === 2) return 'orange-8';
    return 'blue-grey-2';
};

onMounted(() => {
    fetchAllReports();
});
</script>

<style scoped>
.rounded-card {
  border-radius: 16px;
  overflow: hidden;
}

.stat-card {
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.no-border :deep(.q-table__container) {
    box-shadow: none !important;
    border: none !important;
}

.q-tab-panel {
    min-height: 400px;
}
</style>
