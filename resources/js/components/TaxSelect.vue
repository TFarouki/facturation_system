<template>
  <q-select
    v-model="model"
    :options="options"
    option-value="rate"
    option-label="label"
    :label="label"
    outlined
    dense
    emit-value
    map-options
    :rules="rules"
    :loading="loading"
  >
    <template v-slot:no-option>
      <q-item>
        <q-item-section class="text-grey">
          No taxes found
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../api';

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  label: {
    type: String,
    default: 'Tax Rate'
  },
  rules: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue !== null ? parseFloat(props.modelValue) : null,
  set: (val) => emit('update:modelValue', val)
});

const taxes = ref([]);
const loading = ref(false);

const options = computed(() => {
  return taxes.value.map(tax => ({
    label: `${parseFloat(tax.rate).toFixed(2)}%`,
    rate: parseFloat(tax.rate),
    value: tax.id
  }));
});

const loadTaxes = async () => {
  loading.value = true;
  try {
    const response = await api.get('/taxes');
    taxes.value = response.data;
  } catch (error) {
    console.error('Failed to load taxes', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadTaxes();
});
</script>
