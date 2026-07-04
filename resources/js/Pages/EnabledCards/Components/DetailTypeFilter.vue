<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import FilterField from '@/Components/Filters/Partials/FilterField.vue';

const props = defineProps({
  modelValue: String,
  filtersBasePath: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue']);

const detailTypes = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await axios.get(`${props.filtersBasePath}/detail-types`);
    detailTypes.value = response.data;
    loading.value = false;
  } catch (error) {
    console.error('Ошибка при загрузке типов реквизитов:', error);
    loading.value = false;
  }
});

const updateValue = (event) => {
  emit('update:modelValue', event.target.value);
};
</script>

<template>
  <div class="min-w-0 w-full sm:w-40">
    <FilterField label="Тип реквизита">
      <select
        id="detail-type-filter"
        class="select select-bordered select-sm w-full"
        :value="modelValue"
        :disabled="loading"
        @change="updateValue"
      >
        <option value="">Все типы</option>
        <option v-for="type in detailTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>
    </FilterField>
  </div>
</template>
