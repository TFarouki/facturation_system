<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Sales Receipts</div>
      <q-btn color="primary" icon="add" label="New Sale" @click="showDialog = true" />
    </div>

    <q-table
      :rows="receipts"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
    >
      <template v-slot:body-cell-image="props">
        <q-td :props="props">
          <q-btn v-if="props.row.receipt_image_path" flat dense icon="image" @click="viewImage(props.row.receipt_image_path)" />
        </q-td>
      </template>
    </q-table>

    <!-- New Sale Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 600px; max-width: 90vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">New Sales Receipt</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveSale">
            <div class="row q-col-gutter-md">
              <div class="col-6">
                <q-select v-model="form.cycle_id" :options="cycleOptions" option-value="value" option-label="label" label="Cycle" outlined dense emit-value map-options />
              </div>
              <div class="col-6">
                <q-input v-model="form.customer_name" label="Customer Name" outlined dense />
              </div>
              <div class="col-6">
                <q-input v-model="form.receipt_date" label="Receipt Date" type="date" outlined dense />
              </div>
              <div class="col-6">
                <q-file v-model="form.receipt_image" label="Receipt Image" outlined dense accept="image/*">
                  <template v-slot:prepend>
                    <q-icon name="attach_file" />
                  </template>
                </q-file>
              </div>
            </div>

            <div class="text-subtitle2 q-mt-md q-mb-sm">Items</div>
            <div v-for="(item, index) in form.items" :key="index" class="row q-col-gutter-sm q-mb-sm">
              <div class="col-3">
                <q-select v-model="item.product_id" :options="productOptions" option-value="value" option-label="label" label="Product" outlined dense emit-value map-options />
              </div>
              <div class="col-2">
                <q-input v-model.number="item.quantity" label="Quantity" type="number" outlined dense />
              </div>
              <div class="col-2">
                <q-input v-model.number="item.selling_price" label="Price" type="number" outlined dense />
              </div>
              <div class="col-3">
                <q-select v-model="item.price_type_used" :options="['wholesale', 'semi_wholesale', 'installment']" label="Price Type" outlined dense />
              </div>
              <div class="col-2">
                <q-btn flat dense icon="delete" color="negative" @click="removeItem(index)" />
              </div>
            </div>
            <q-btn flat icon="add" label="Add Item" @click="addItem" class="q-mb-md" />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="closeDialog" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
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
const receipts = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const cycleOptions = ref([]);
const productOptions = ref([]);

const form = ref({
  cycle_id: null,
  distributor_id: 1, // Should come from auth user
  customer_name: '',
  receipt_date: new Date().toISOString().split('T')[0],
  receipt_image: null,
  items: [{ product_id: null, quantity: 0, selling_price: 0, price_type_used: 'wholesale' }],
});

const columns = [
  { name: 'id', label: 'Receipt #', field: 'id', align: 'left' },
  { name: 'customer', label: 'Customer', field: 'customer_name', align: 'left' },
  { name: 'date', label: 'Date', field: 'receipt_date', align: 'left' },
  { name: 'image', label: 'Image', field: 'receipt_image_path', align: 'center' },
];

const loadReceipts = async () => {
  loading.value = true;
  try {
    const response = await api.get('/sales');
    receipts.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load receipts' });
  } finally {
    loading.value = false;
  }
};

const loadCycles = async () => {
  try {
    const response = await api.get('/cycles');
    cycleOptions.value = response.data
      .filter(c => c.status === 'open')
      .map(c => ({ label: `Cycle #${c.id}`, value: c.id }));
  } catch (error) {
    console.error('Failed to load cycles');
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

const addItem = () => {
  form.value.items.push({ product_id: null, quantity: 0, selling_price: 0, price_type_used: 'wholesale' });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

const saveSale = async () => {
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('cycle_id', form.value.cycle_id);
    formData.append('distributor_id', form.value.distributor_id);
    formData.append('customer_name', form.value.customer_name);
    formData.append('receipt_date', form.value.receipt_date);
    if (form.value.receipt_image) {
      formData.append('receipt_image', form.value.receipt_image);
    }
    formData.append('items', JSON.stringify(form.value.items));

    await api.post('/sales', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    $q.notify({ type: 'positive', message: 'Sales receipt created' });
    closeDialog();
    loadReceipts();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save receipt' });
  } finally {
    saving.value = false;
  }
};

const closeDialog = () => {
  showDialog.value = false;
  form.value = {
    cycle_id: null,
    distributor_id: 1,
    customer_name: '',
    receipt_date: new Date().toISOString().split('T')[0],
    receipt_image: null,
    items: [{ product_id: null, quantity: 0, selling_price: 0, price_type_used: 'wholesale' }],
  };
};

const viewImage = (path) => {
  window.open(`http://localhost:8000/storage/${path}`, '_blank');
};

onMounted(() => {
  loadReceipts();
  loadCycles();
  loadProducts();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>
