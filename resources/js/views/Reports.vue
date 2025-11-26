<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">Profit Reports</div>

    <q-card class="q-mb-md">
      <q-card-section>
        <div class="text-h6 q-mb-md">Generate Profit Report</div>
        <div class="row q-col-gutter-md">
          <div class="col-4">
            <q-input v-model="startDate" label="Start Date" type="date" outlined dense />
          </div>
          <div class="col-4">
            <q-input v-model="endDate" label="End Date" type="date" outlined dense />
          </div>
          <div class="col-4">
            <q-btn label="Generate" color="primary" @click="generateReport" :loading="loading" class="full-width" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <div v-if="report" class="row q-col-gutter-md">
      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Total Revenue</div>
            <div class="text-h3 text-positive">${{ report.summary.total_revenue }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Cost of Goods</div>
            <div class="text-h3 text-negative">${{ report.summary.cost_of_goods_sold }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Gross Profit</div>
            <div class="text-h3 text-primary">${{ report.summary.gross_profit }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Profit Margin</div>
            <div class="text-h3 text-secondary">{{ report.summary.profit_margin }}%</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Product Breakdown</div>
            <q-table
              :rows="report.product_breakdown"
              :columns="breakdownColumns"
              row-key="name"
              flat
              bordered
              class="rounded-table"
            />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { ref } from 'vue';
import api from '../api';

const $q = useQuasar();
const loading = ref(false);
const report = ref(null);
const startDate = ref(new Date(new Date().setDate(1)).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

const breakdownColumns = [
  { name: 'name', label: 'Product', field: 'name', align: 'left', sortable: true },
  { name: 'quantity', label: 'Quantity Sold', field: 'total_quantity', align: 'right' },
  { name: 'revenue', label: 'Revenue', field: 'revenue', align: 'right', format: val => `$${val}` },
  { name: 'cost', label: 'Cost', field: 'cost', align: 'right', format: val => `$${val}` },
  { name: 'profit', label: 'Profit', field: 'profit', align: 'right', format: val => `$${val}` },
];

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
    $q.notify({ type: 'negative', message: 'Failed to generate report' });
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
