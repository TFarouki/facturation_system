<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">Categories (الأصناف)</div>
      <q-btn color="primary" icon="add" label="Add Category" @click="openDialog()" />
    </div>

    <!-- Search Input -->
    <div class="q-mb-md">
      <q-input
        v-model="searchText"
        outlined
        dense
        placeholder="Search categories..."
        style="max-width: 400px"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append v-if="searchText">
          <q-icon name="clear" class="cursor-pointer" @click="searchText = ''" />
        </template>
      </q-input>
    </div>

    <q-table
      :rows="filteredCategories"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
    >
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="positive" @click="openDialog(props.row)">
            <q-tooltip>Edit</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="deleteCategory(props.row)">
            <q-tooltip>Delete</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="showDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? 'Edit Category' : 'Add Category' }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveCategory">
            <q-input v-model="form.name" label="Category Name" outlined dense class="q-mb-md" :rules="[val => !!val || 'Required']" />

            <div class="row justify-end q-gutter-sm">
              <q-btn label="Cancel" flat @click="showDialog = false" />
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
import { computed, onMounted, ref } from 'vue';
import api from '../api';

const $q = useQuasar();
const categories = ref([]);
const searchText = ref('');
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const editMode = ref(false);
const selectedCategory = ref(null);

const form = ref({
  name: '',
});

const columns = [
  { name: 'name', label: 'Category Name', field: 'name', align: 'left', sortable: true },
  { name: 'actions', label: 'Actions', field: 'actions', align: 'center' },
];

// Computed property for filtered categories
const filteredCategories = computed(() => {
  if (!searchText.value) {
    return categories.value;
  }
  
  const search = searchText.value.toLowerCase();
  return categories.value.filter(category => 
    category.name?.toLowerCase().includes(search)
  );
});

const loadCategories = async () => {
  loading.value = true;
  try {
    const response = await api.get('/categories');
    categories.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to load categories' });
  } finally {
    loading.value = false;
  }
};

const openDialog = (category = null) => {
  if (category) {
    editMode.value = true;
    selectedCategory.value = category;
    form.value = { name: category.name };
  } else {
    editMode.value = false;
    selectedCategory.value = null;
    form.value = { name: '' };
  }
  showDialog.value = true;
};

const saveCategory = async () => {
  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/categories/${selectedCategory.value.id}`, form.value);
    } else {
      await api.post('/categories', form.value);
    }
    $q.notify({ type: 'positive', message: 'Category saved successfully' });
    showDialog.value = false;
    loadCategories();
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Failed to save category' });
  } finally {
    saving.value = false;
  }
};

const deleteCategory = async (category) => {
  $q.dialog({
    title: 'Confirm',
    message: `Delete category "${category.name}"?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/categories/${category.id}`);
      $q.notify({ type: 'positive', message: 'Category deleted' });
      loadCategories();
    } catch (error) {
      $q.notify({ type: 'negative', message: 'Failed to delete category' });
    }
  });
};

onMounted(() => {
  loadCategories();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

.rounded-table :deep(thead tr th) {
  background-color: #1976d2;
  color: white;
  font-weight: bold;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.rounded-table :deep(thead tr:first-child th) {
  position: sticky;
  top: 0;
  z-index: 1;
}
</style>
