<template>
  <div>
    <!-- Category Select with Search -->
    <q-select
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
      :options="filteredCategories"
      option-value="id"
      option-label="name"
      :label="label"
      outlined
      dense
      emit-value
      map-options
      use-input
      @filter="filterCategories"
      :clearable="clearable"
      :rules="rules"
      ref="selectRef"
    >
      <template v-slot:no-option>
        <q-item>
          <q-item-section class="text-center">
            <q-btn
              flat
              color="primary"
              icon="add"
              label="Add New Category"
              @click="openAddCategoryDialog"
            />
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <!-- Add Category Dialog -->
    <q-dialog v-model="showAddDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">Add New Category</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveCategory">
            <q-input
              v-model="newCategoryName"
              label="Category Name *"
              outlined
              dense
              autofocus
              :rules="[val => !!val || 'Required']"
            />

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn label="Cancel" flat @click="showAddDialog = false" />
              <q-btn
                type="submit"
                label="Save"
                color="secondary"
                :loading="saving"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { useQuasar } from 'quasar';
import { onMounted, ref } from 'vue';
import api from '../api';

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  label: {
    type: String,
    default: 'Category'
  },
  clearable: {
    type: Boolean,
    default: true
  },
  rules: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue', 'category-added']);

const $q = useQuasar();
const selectRef = ref(null);
const categories = ref([]);
const filteredCategories = ref([]);
const showAddDialog = ref(false);
const newCategoryName = ref('');
const searchText = ref('');
const saving = ref(false);

const loadCategories = async () => {
  try {
    const response = await api.get('/categories');
    categories.value = response.data;
    filteredCategories.value = response.data;
  } catch (error) {
    console.error('Failed to load categories');
  }
};

const filterCategories = (val, update) => {
  searchText.value = val; // Store the search text
  
  if (val === '') {
    update(() => {
      filteredCategories.value = categories.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    filteredCategories.value = categories.value.filter(
      v => v.name.toLowerCase().indexOf(needle) > -1
    );
  });
};

const openAddCategoryDialog = () => {
  newCategoryName.value = searchText.value; // Pre-fill with search text
  showAddDialog.value = true;
};

const saveCategory = async () => {
  saving.value = true;
  try {
    const response = await api.post('/categories', {
      name: newCategoryName.value
    });
    
    $q.notify({
      type: 'positive',
      message: 'Category added successfully'
    });
    
    // Reload categories
    await loadCategories();
    
    // Clear search text
    searchText.value = '';
    if (selectRef.value) {
      selectRef.value.updateInputValue('', true);
    }
    
    // Clear selection first, then auto-select the new category
    emit('update:modelValue', null);
    setTimeout(() => {
      emit('update:modelValue', response.data.id);
      emit('category-added', response.data);
    }, 100);
    
    showAddDialog.value = false;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Failed to add category'
    });
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadCategories();
});
</script>
