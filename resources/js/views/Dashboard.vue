<template>
  <q-page class="q-pa-md">
    <div class="text-h4 q-mb-md">Dashboard</div>
    
    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Total Products</div>
            <div class="text-h3 text-primary">{{ stats.totalProducts }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Active Cycles</div>
            <div class="text-h3 text-secondary">{{ stats.activeCycles }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">This Month Sales</div>
            <div class="text-h3 text-positive">{{ stats.monthlySales }}</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card class="stat-card">
          <q-card-section>
            <div class="text-h6">Low Stock Items</div>
            <div class="text-h3 text-warning">{{ stats.lowStock }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md q-mt-md">
      <div class="col-12">
        <q-card>
          <q-card-section>
            <div class="text-h6">Recent Activity</div>
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
import { onMounted, ref } from 'vue';
import api from '../api';

const stats = ref({
  totalProducts: 0,
  activeCycles: 0,
  monthlySales: 0,
  lowStock: 0,
});

const recentActivity = ref([
  { id: 1, icon: 'shopping_cart', color: 'primary', title: 'New purchase invoice created', time: '2 hours ago' },
  { id: 2, icon: 'sync', color: 'secondary', title: 'Cycle #123 closed', time: '5 hours ago' },
  { id: 3, icon: 'point_of_sale', color: 'positive', title: 'New sale recorded', time: '1 day ago' },
]);

onMounted(async () => {
  try {
    const [products, cycles] = await Promise.all([
      api.get('/products'),
      api.get('/cycles'),
    ]);
    
    stats.value.totalProducts = products.data.length;
    stats.value.activeCycles = cycles.data.filter(c => c.status === 'open').length;
  } catch (error) {
    console.error('Failed to load dashboard stats:', error);
  }
});
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
