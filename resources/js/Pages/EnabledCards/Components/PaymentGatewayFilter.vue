<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import FilterField from '@/Components/Filters/Partials/FilterField.vue';

const props = defineProps({
  modelValue: [String, Number],
  filtersBasePath: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const paymentGateways = ref([]);
const loading = ref(false);
const showDropdown = ref(false);
const selectedGateway = ref(null);

// Поиск платежных методов
const searchGateways = async () => {
  if (!searchQuery.value.trim() && !props.modelValue) {
    paymentGateways.value = [];
    return;
  }

  loading.value = true;

  try {
    const response = await axios.get(`${props.filtersBasePath}/payment-gateways`, {
      params: { query: searchQuery.value }
    });

    paymentGateways.value = response.data;
    showDropdown.value = true;
  } catch (error) {
    console.error('Ошибка при поиске платежных методов:', error);
  } finally {
    loading.value = false;
  }
};

// Дебаунс для поиска
let searchTimeout;
watch(searchQuery, (newVal) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    searchGateways();
  }, 300);
});

// Выбор платежного метода из списка
const selectGateway = (gateway) => {
  selectedGateway.value = gateway;
  searchQuery.value = gateway.label;
  emit('update:modelValue', gateway.value);
  showDropdown.value = false;
};

// Очистка выбора
const clearSelection = () => {
  selectedGateway.value = null;
  searchQuery.value = '';
  emit('update:modelValue', '');
};

// Проверка, если значение приходит из родительского компонента
watch(() => props.modelValue, async (newVal) => {
  if (!newVal) {
    selectedGateway.value = null;
    searchQuery.value = '';
    return;
  }

  if (!selectedGateway.value || selectedGateway.value.value !== newVal) {
    try {
      const response = await axios.get(`${props.filtersBasePath}/payment-gateways`, {
        params: { query: newVal }
      });

      if (response.data.length > 0) {
        const gateway = response.data.find(g => g.value == newVal);
        if (gateway) {
          selectedGateway.value = gateway;
          searchQuery.value = gateway.label;
        }
      }
    } catch (error) {
      console.error('Ошибка при загрузке платежного метода:', error);
    }
  }
}, { immediate: true });

// Закрытие выпадающего списка при клике вне компонента
const onClickOutside = (event) => {
  if (!event.target.closest('.payment-gateway-filter')) {
    showDropdown.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', onClickOutside);
});

// Загрузка начального значения, если оно есть
onMounted(async () => {
  if (props.modelValue) {
    try {
      const response = await axios.get(`${props.filtersBasePath}/payment-gateways`, {
        params: { query: props.modelValue }
      });

      if (response.data.length > 0) {
        const gateway = response.data.find(g => g.value == props.modelValue);
        if (gateway) {
          selectedGateway.value = gateway;
          searchQuery.value = gateway.label;
        }
      }
    } catch (error) {
      console.error('Ошибка при загрузке платежного метода:', error);
    }
  }
});
</script>

<template>
  <div class="payment-gateway-filter min-w-0 w-full sm:w-48">
    <FilterField label="Платежный метод">
    <div class="relative">
      <input
        id="payment-gateway-filter"
        type="text"
        class="input input-bordered input-sm w-full pr-8"
        placeholder="Поиск метода..."
        v-model="searchQuery"
        @focus="showDropdown = true"
        @input="showDropdown = true"
      />

      <button
        v-if="selectedGateway"
        type="button"
        class="btn btn-ghost btn-xs btn-circle absolute right-0.5 top-1/2 -translate-y-1/2"
        @click="clearSelection"
      >
        ✕
      </button>

      <span v-if="loading" class="loading loading-spinner loading-xs absolute right-2 top-1/2 -translate-y-1/2"></span>

      <!-- Выпадающий список результатов -->
      <div
        v-if="showDropdown && paymentGateways.length > 0"
        class="menu menu-sm bg-base-100 rounded-box absolute z-10 w-full mt-1 shadow"
      >
        <ul>
          <li
            v-for="gateway in paymentGateways"
            :key="gateway.value"
            @click="selectGateway(gateway)"
          >
            <a>{{ gateway.label }}</a>
          </li>
        </ul>
      </div>

      <!-- Сообщение "Ничего не найдено" -->
      <div
        v-if="showDropdown && searchQuery && !loading && paymentGateways.length === 0"
        class="alert alert-info shadow mt-1 absolute z-10 w-full"
      >
        <span>Ничего не найдено</span>
      </div>
    </div>
    </FilterField>
  </div>
</template>
