<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">{{ $t('nav.reports') }}</div>

    <q-card class="q-mb-md">
      <q-card-section>
        <div class="text-h6 q-mb-md">{{ $t('reports.generateProfitReport') }}</div>
        <div class="row q-col-gutter-md">
          <div class="col-4">
            <q-input v-model="startDate" :label="$t('reports.startDate')" type="date" outlined dense />
          </div>
          <div class="col-4">
            <q-input v-model="endDate" :label="$t('reports.endDate')" type="date" outlined dense />
          </div>
          <div class="col-4">
            <q-btn :label="$t('reports.generate')" color="primary" @click="generateReport" :loading="loading" class="full-width" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <div v-if="report" class="row q-col-gutter-md">
      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('reports.totalRevenue') }}</div>
            <div class="text-h3 text-positive">{{ report.summary.total_revenue }} DH</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('reports.costOfGoods') }}</div>
            <div class="text-h3 text-negative">{{ report.summary.cost_of_goods_sold }} DH</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('reports.grossProfit') }}</div>
            <div class="text-h3 text-primary">{{ report.summary.gross_profit }} DH</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">{{ $t('reports.profitMargin') }}</div>
            <div class="text-h3 text-secondary">{{ report.summary.profit_margin }}%</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">{{ $t('reports.productBreakdown') }}</div>
            <q-table
              :rows="report.product_breakdown"
              :columns="breakdownColumns"
              row-key="name"
              flat
              bordered
              class="rounded-table"
              :rows-per-page-label="$t('common.rowsPerPage')"
              :no-data-label="$t('common.noData')"
              :loading-label="$t('common.loading')"
            />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const loading = ref(false);
const report = ref(null);
const startDate = ref(new Date(new Date().setDate(1)).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

const breakdownColumns = computed(() => [
  { name: 'name', label: t('reports.product'), field: 'name', align: 'left', sortable: true },
  { name: 'quantity', label: t('reports.quantitySold'), field: 'total_quantity', align: 'right' },
  { name: 'revenue', label: t('reports.revenue'), field: 'revenue', align: 'right', format: val => `${val} DH` },
  { name: 'cost', label: t('reports.cost'), field: 'cost', align: 'right', format: val => `${val} DH` },
  { name: 'profit', label: t('reports.profit'), field: 'profit', align: 'right', format: val => `${val} DH` },
]);

const generateReport = async () => {
  loading.value = true;
  try {
    const response = await api.get('/reports/profit', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value,
      },
    });
    report.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.error') });
  } finally {
    loading.value = false;
  }
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

.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>
