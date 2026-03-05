<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  modelValue: String,
});

const emit = defineEmits(['update:modelValue']);

const detailTypes = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await axios.get('/admin/filters/detail-types');
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
  <div class="w-full md:w-auto">
    <label for="detail-type-filter" class="label p-0">
      <span class="label-text">Тип реквизита</span>
    </label>
    <select
      id="detail-type-filter"
      class="select select-sm select-bordered w-full"
      :value="modelValue"
      @change="updateValue"
      :disabled="loading"
    >
      <option value="">Все типы</option>
      <option v-for="type in detailTypes" :key="type.value" :value="type.value">
        {{ type.label }}
      </option>
    </select>
  </div>
</template>
