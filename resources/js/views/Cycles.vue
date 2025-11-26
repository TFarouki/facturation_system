<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Distribution Cycles</div>
      <q-btn color="primary" icon="add" label="New Cycle" @click="showDialog = true" />
    </div>

    <q-table
      :rows="cycles"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="props.row.status === 'open' ? 'positive' : 'grey'">
            {{ props.row.status }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="visibility" @click="viewCycle(props.row)" />
          <q-btn v-if="props.row.status === 'open'" flat dense icon="add" color="primary" @click="addMovement(props.row)" />
          <q-btn v-if="props.row.status === 'open'" flat dense icon="lock" color="warning" @click="closeCycle(props.row)" />
        </q-td>
      </template>
    </q-table>

    <!-- New Cycle Dialog -->
    <q-dialog v-model="showDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">New Cycle</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveCycle">
            <q-select v-model="form.distributor_id" :options="distributorOptions" option-value="value" option-label="label" label="Distributor" outlined dense emit-value map-options class="q-mb-md" />
            <q-input v-model="form.start_date" label="Start Date" type="date" outlined dense class="q-mb-md" />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showDialog = false" />
              <q-btn type="submit" label="Create" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Add Movement Dialog -->
    <q-dialog v-model="showMovementDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Add Movement</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveMovement">
            <q-select v-model="movementForm.product_id" :options="productOptions" option-value="value" option-label="label" label="Product" outlined dense emit-value map-options class="q-mb-md" />
            <q-input v-model.number="movementForm.quantity" label="Quantity" type="number" outlined dense class="q-mb-md" />
            <q-select v-model="movementForm.movement_type" :options="['load', 'reload', 'return']" label="Type" outlined dense class="q-mb-md" />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showMovementDialog = false" />
              <q-btn type="submit" label="Add" color="secondary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { onMounted, ref } from 'vue';
import api from '../api';

const $q = useQuasar();
const cycles = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const showMovementDialog = ref(false);
const selectedCycle = ref(null);
const distributorOptions = ref([]);
const productOptions = ref([]);

const form = ref({
  distributor_id: null,
  start_date: new Date().toISOString().split('T')[0],
});

const movementForm = ref({
  product_id: null,
  quantity: 0,
  movement_type: 'load',
});

const columns = [
  { name: 'id', label: 'Cycle #', field: 'id', align: 'left' },
  { name: 'distributor', label: 'Distributor', field: row => row.distributor?.name || '', align: 'left' },
  { name: 'start_date', label: 'Start Date', field: 'start_date', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'center' },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

const loadCycles = async () => {
  loading.value = true;
  try {
    const response = await api.get('/cycles');
    cycles.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load cycles' });
  } finally {
    loading.value = false;
  }
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/products'); // Placeholder - should be users endpoint
    distributorOptions.value = [{ label: 'Distributor 1', value: 1 }];
  } catch (error) {
    console.error('Failed to load distributors');
  }
};

const loadProducts = async () => {
  try {
    const response = await api.get('/products');
    productOptions.value = response.data.map(p => ({ label: p.name, value: p.id }));
  } catch (error) {
    console.error('Failed to load products');
  }
};

const saveCycle = async () => {
  saving.value = true;
  try {
    await api.post('/cycles', form.value);
    $q.notify({ type: 'positive', message: 'Cycle created' });
    showDialog.value = false;
    loadCycles();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to create cycle' });
  } finally {
    saving.value = false;
  }
};

const addMovement = (cycle) => {
  selectedCycle.value = cycle;
  showMovementDialog.value = true;
};

const saveMovement = async () => {
  saving.value = true;
  try {
    await api.post(`/cycles/${selectedCycle.value.id}/movements`, movementForm.value);
    $q.notify({ type: 'positive', message: 'Movement added' });
    showMovementDialog.value = false;
    loadCycles();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to add movement' });
  } finally {
    saving.value = false;
  }
};

const closeCycle = async (cycle) => {
  $q.dialog({
    title: 'Close Cycle',
    message: 'Are you sure you want to close this cycle?',
    cancel: true,
  }).onOk(async () => {
    try {
      const response = await api.post(`/cycles/${cycle.id}/close`);
      $q.notify({ 
        type: 'positive', 
        message: `Cycle closed. Balance: ${response.data.reconciliation.expected_balance}` 
      });
      loadCycles();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to close cycle' });
    }
  });
};

const viewCycle = (cycle) => {
  $q.notify({ type: 'info', message: 'Cycle details view coming soon' });
};

onMounted(() => {
  loadCycles();
  loadDistributors();
  loadProducts();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>
