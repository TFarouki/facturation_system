<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('nav.productFamilies') }}</div>
      <q-btn color="primary" icon="add" :label="$t('common.add')" @click="openDialog()" />
    </div>

    <!-- Search -->
    <div class="row items-center q-mb-md">
      <q-input
        v-model="searchText"
        outlined
        dense
        :placeholder="$t('productFamilies.searchPlaceholder')"
        style="min-width: 300px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append v-if="searchText">
          <q-icon name="clear" class="cursor-pointer" @click="searchText = ''" />
        </template>
      </q-input>
    </div>

    <!-- Table -->
    <q-table
      :rows="filteredFamilies"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      v-model:pagination="pagination"
      :rows-per-page-options="[10, 25, 50]"
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
    >
      <template v-slot:body-cell-products_count="props">
        <q-td :props="props">
          <q-badge color="primary" :label="props.row.products_count || 0" />
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="secondary" @click="openDialog(props.row)">
            <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="visibility" color="info" @click="viewProducts(props.row)">
            <q-tooltip>{{ $t('productFamilies.viewProducts') }}</q-tooltip>
          </q-btn>
          <q-btn 
            flat 
            dense 
            icon="delete" 
            color="negative" 
            @click="deleteFamily(props.row)"
            :disable="props.row.products_count > 0"
          >
            <q-tooltip>{{ props.row.products_count > 0 ? $t('productFamilies.deleteTip') : $t('common.delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 450px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? $t('productFamilies.editFamily') : $t('productFamilies.newFamily') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveFamily" class="q-gutter-md">
            <q-input
              v-model="form.name"
              :label="$t('distributors.name') + ' *'"
              outlined
              dense
              :rules="[val => !!val || $t('messages.required')]"
            />

            <q-input
              v-model="form.name_ar"
              :label="$t('productFamilies.nameAr')"
              outlined
              dense
            />

            <q-input
              v-model="form.description"
              :label="$t('products.description')"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Products List Dialog -->
    <q-dialog v-model="showProductsDialog">
      <q-card style="min-width: 700px; max-width: 90vw;">
        <q-card-section class="bg-info text-white">
          <div class="text-h6">{{ $t('productFamilies.productsInFamily', { name: selectedFamilyName }) }}</div>
        </q-card-section>
        
        <q-card-section class="q-pa-none">
          <q-table
            :rows="selectedFamilyProducts"
            :columns="productColumns"
            row-key="id"
            flat
            bordered
            :loading="loadingProducts"
            :pagination="{ rowsPerPage: 10 }"
            :rows-per-page-label="$t('common.rowsPerPage')"
            :no-data-label="$t('common.noData')"
            :loading-label="$t('common.loading')"
          >
             <template v-slot:body-cell-stock="props">
                <q-td :props="props" class="text-right">
                  <q-badge :color="props.value > 0 ? 'positive' : 'negative'">
                    {{ props.value }}
                  </q-badge>
                </q-td>
             </template>
          </q-table>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat :label="$t('common.close')" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const loading = ref(false);
const saving = ref(false);
const families = ref([]);
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const searchText = ref('');
const pagination = ref({ rowsPerPage: 25 });

// Products View State
const showProductsDialog = ref(false);
const selectedFamilyName = ref('');
const selectedFamilyProducts = ref([]);
const loadingProducts = ref(false);

const productColumns = computed(() => [
  { name: 'product_code', label: t('products.code'), field: 'product_code', align: 'left', sortable: true },
  { name: 'name', label: t('products.name'), field: 'name', align: 'left', sortable: true },
  { name: 'stock', label: t('products.currentStock'), field: 'current_stock_quantity', align: 'right', sortable: true },
  { name: 'barcode', label: t('products.barcode'), field: 'barcode', align: 'left' },
]);

const form = ref({
  name: '',
  name_ar: '',
  description: '',
});

const columns = computed(() => [
  { name: 'name', label: t('distributors.name'), field: 'name', align: 'left', sortable: true },
  { name: 'name_ar', label: t('productFamilies.nameAr'), field: 'name_ar', align: 'right', sortable: true },
  { name: 'description', label: t('products.description'), field: 'description', align: 'left', sortable: false },
  { name: 'products_count', label: t('products.title'), field: 'products_count', align: 'center', sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center', sortable: false },
]);

const filteredFamilies = computed(() => {
  if (!searchText.value) return families.value;
  
  const search = searchText.value.toLowerCase();
  return families.value.filter(f => 
    f.name?.toLowerCase().includes(search) ||
    f.name_ar?.includes(search) ||
    f.description?.toLowerCase().includes(search)
  );
});

const loadFamilies = async () => {
  loading.value = true;
  try {
    const response = await api.get('/product-families');
    families.value = response.data;
  } catch (error) {
    console.error('Failed to load families:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const openDialog = (family = null) => {
  if (family) {
    isEditing.value = true;
    editingId.value = family.id;
    form.value = {
      name: family.name,
      name_ar: family.name_ar || '',
      description: family.description || '',
    };
  } else {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
      name: '',
      name_ar: '',
      description: '',
    };
  }
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    name_ar: '',
    description: '',
  };
};

const saveFamily = async () => {
  saving.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/product-families/${editingId.value}`, form.value);
      $q.notify({ type: 'positive', message: t('productFamilies.updatedSuccessfully') });
    } else {
      await api.post('/product-families', form.value);
      $q.notify({ type: 'positive', message: t('productFamilies.createdSuccessfully') });
    }
    closeDialog();
    loadFamilies();
  } catch (error) {
    console.error('Failed to save family:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToSave') });
  } finally {
    saving.value = false;
  }
};

const deleteFamily = async (family) => {
  if (family.products_count > 0) {
    $q.notify({ type: 'warning', message: t('productFamilies.deleteTip') });
    return;
  }

  $q.dialog({
    title: t('productFamilies.confirmDeleteTitle'),
    message: t('productFamilies.confirmDeleteMessage', { name: family.name }),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/product-families/${family.id}`);
      $q.notify({ type: 'positive', message: t('productFamilies.deletedSuccessfully') });
      loadFamilies();
    } catch (error) {
      console.error('Failed to delete family:', error);
      $q.notify({ type: 'negative', message: error.response?.data?.message || t('messages.failedToDelete') });
    }
  });
};

onMounted(() => {
  loadFamilies();
});

const viewProducts = async (family) => {
  selectedFamilyName.value = family.name;
  showProductsDialog.value = true;
  loadingProducts.value = true;
  selectedFamilyProducts.value = [];
  
  try {
    const response = await api.get(`/product-families/${family.id}`);
    if (response.data && response.data.products) {
      selectedFamilyProducts.value = response.data.products;
    }
  } catch (error) {
    console.error('Failed to load family products:', error);
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loadingProducts.value = false;
  }
};
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>

