<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Product Families (عائلات المنتجات)</div>
      <q-btn color="primary" icon="add" label="Add Family" @click="openDialog()" />
    </div>

    <!-- Search -->
    <div class="row items-center q-mb-md">
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search families..."
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
    >
      <template v-slot:body-cell-products_count="props">
        <q-td :props="props">
          <q-badge color="primary" :label="props.row.products_count || 0" />
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="secondary" @click="openDialog(props.row)">
            <q-tooltip>Edit</q-tooltip>
          </q-btn>
          <q-btn 
            flat 
            dense 
            icon="delete" 
            color="negative" 
            @click="deleteFamily(props.row)"
            :disable="props.row.products_count > 0"
          >
            <q-tooltip>{{ props.row.products_count > 0 ? 'Cannot delete - has products' : 'Delete' }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 450px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? 'Edit Family' : 'Add Family' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveFamily" class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="Name (English) *"
              outlined
              dense
              :rules="[val => !!val || 'Required']"
            />

            <q-input
              v-model="form.name_ar"
              label="Name (Arabic) الاسم بالعربية"
              outlined
              dense
            />

            <q-input
              v-model="form.description"
              label="Description"
              outlined
              dense
              type="textarea"
              rows="2"
            />

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
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import api from '../api';

const $q = useQuasar();
const loading = ref(false);
const saving = ref(false);
const families = ref([]);
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const searchText = ref('');
const pagination = ref({ rowsPerPage: 25 });

const form = ref({
  name: '',
  name_ar: '',
  description: '',
});

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'name_ar', label: 'الاسم بالعربية', field: 'name_ar', align: 'right', sortable: true },
  { name: 'description', label: 'Description', field: 'description', align: 'left', sortable: false },
  { name: 'products_count', label: 'Products', field: 'products_count', align: 'center', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center', sortable: false },
];

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
    $q.notify({ type: 'negative', message: 'Failed to load product families' });
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
      $q.notify({ type: 'positive', message: 'Family updated successfully' });
    } else {
      await api.post('/product-families', form.value);
      $q.notify({ type: 'positive', message: 'Family created successfully' });
    }
    closeDialog();
    loadFamilies();
  } catch (error) {
    console.error('Failed to save family:', error);
    $q.notify({ type: 'negative', message: 'Failed to save family' });
  } finally {
    saving.value = false;
  }
};

const deleteFamily = async (family) => {
  if (family.products_count > 0) {
    $q.notify({ type: 'warning', message: 'Cannot delete family with associated products' });
    return;
  }

  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete "${family.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/product-families/${family.id}`);
      $q.notify({ type: 'positive', message: 'Family deleted successfully' });
      loadFamilies();
    } catch (error) {
      console.error('Failed to delete family:', error);
      $q.notify({ type: 'negative', message: error.response?.data?.message || 'Failed to delete family' });
    }
  });
};

onMounted(() => {
  loadFamilies();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}
</style>

