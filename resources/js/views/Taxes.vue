<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('taxes.title') }}</div>
      <q-btn color="primary" icon="add" :label="$t('taxes.addTax')" @click="openDialog()" />
    </div>

    <q-table
      :rows="taxes"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
    >
      <template v-slot:body-cell-is_active="props">
        <q-td :props="props">
          <q-chip :color="props.row.is_active ? 'positive' : 'negative'" text-color="white" dense>
            {{ props.row.is_active ? $t('taxes.active') : $t('taxes.inactive') }}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="primary" @click="openDialog(props.row)">
            <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
          </q-btn>
          <q-btn flat dense icon="delete" color="negative" @click="deleteTax(props.row)">
            <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ editMode ? $t('taxes.editTax') : $t('taxes.addTax') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveTax">
            <q-input v-model="form.name" :label="$t('taxes.name') + ' *'" outlined dense class="q-mb-md" :rules="[val => !!val || $t('messages.required')]" />
            <q-input 
              v-model.number="form.rate" 
              :label="$t('taxes.rate') + ' *'" 
              type="number" 
              outlined 
              dense 
              class="q-mb-md" 
              suffix="%"
              :rules="[val => val >= 0 || $t('taxes.mustBePositive')]" 
            />
            <q-input v-model="form.description" :label="$t('taxes.description')" outlined dense type="textarea" rows="2" class="q-mb-md" />
            <q-toggle v-model="form.is_active" :label="$t('taxes.active')" />

            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
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
import { useI18n } from 'vue-i18n';
import api from '../api';

const $q = useQuasar();
const { t } = useI18n();
const taxes = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const editMode = ref(false);
const selectedTax = ref(null);

const form = ref({
  name: '',
  rate: 0,
  description: '',
  is_active: true,
});

const columns = computed(() => [
  { name: 'name', label: t('taxes.name'), field: 'name', align: 'left', sortable: true },
  { name: 'rate', label: t('taxes.rate'), field: 'rate', align: 'right', sortable: true, format: val => `${val}%` },
  { name: 'description', label: t('taxes.description'), field: 'description', align: 'left' },
  { name: 'is_active', label: t('taxes.status'), field: 'is_active', align: 'center', sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
]);

const loadTaxes = async () => {
  loading.value = true;
  try {
    const response = await api.get('/taxes');
    taxes.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadTaxes') });
  } finally {
    loading.value = false;
  }
};

const openDialog = (tax = null) => {
  if (tax) {
    editMode.value = true;
    selectedTax.value = tax;
    form.value = {
      name: tax.name,
      rate: parseFloat(tax.rate),
      description: tax.description,
      is_active: Boolean(tax.is_active),
    };
  } else {
    editMode.value = false;
    selectedTax.value = null;
    form.value = {
      name: '',
      rate: 0,
      description: '',
      is_active: true,
    };
  }
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
  editMode.value = false;
  selectedTax.value = null;
};

const saveTax = async () => {
  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/taxes/${selectedTax.value.id}`, form.value);
    } else {
      await api.post('/taxes', form.value);
    }
    $q.notify({ type: 'positive', message: t('messages.taxSavedSuccessfully') });
    closeDialog();
    loadTaxes();
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToSaveTax') });
  } finally {
    saving.value = false;
  }
};

const deleteTax = async (tax) => {
  $q.dialog({
    title: t('taxes.confirmDelete'),
    message: t('taxes.deleteMessage', { name: tax.name }),
    cancel: true,
  }).onOk(async () => {
    try {
      await api.delete(`/taxes/${tax.id}`);
      $q.notify({ type: 'positive', message: t('messages.taxDeleted') });
      loadTaxes();
    } catch (error) {
      $q.notify({ type: 'negative', message: t('messages.failedToDeleteTax') });
    }
  });
};

onMounted(() => {
  loadTaxes();
});
</script>
