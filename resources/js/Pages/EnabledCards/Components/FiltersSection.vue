<script setup>
import { ref, watch } from 'vue';
import DetailTypeFilter from './DetailTypeFilter.vue';
import PaymentGatewayFilter from './PaymentGatewayFilter.vue';
import UserFilter from './UserFilter.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  initialFilters: Object,
});

// Состояние фильтров
const filters = ref({
  detail_type: props.initialFilters?.detail_type || '',
  payment_gateway_id: props.initialFilters?.payment_gateway_id || '',
  user_id: props.initialFilters?.user_id || '',
});

// Флаг для отслеживания изменений (чтобы не делать запрос при первой инициализации)
const isInitialized = ref(false);

// Применение фильтров
const applyFilters = () => {
  if (!isInitialized.value) {
    isInitialized.value = true;
    return;
  }
  
  router.visit(window.location.pathname, {
    method: 'get',
    data: filters.value,
    preserveState: true,
    preserveScroll: true,
    only: ['statistics', 'filters']
  });
};

// Сброс фильтров
const resetFilters = () => {
  filters.value = {
    detail_type: '',
    payment_gateway_id: '',
    user_id: '',
  };
  
  router.visit(window.location.pathname, {
    method: 'get',
    data: {},
    preserveState: true,
    preserveScroll: true,
    only: ['statistics', 'filters']
  });
};

// Отслеживаем изменение любого из фильтров
watch(filters, () => {
  applyFilters();
}, { deep: true });

// Устанавливаем флаг инициализации после монтирования
setTimeout(() => {
  isInitialized.value = true;
}, 100);
</script>

<template>
  <div class="card bg-base-100 shadow mb-6">
    <div class="card-body p-4">
      <div class="flex flex-col md:flex-row justify-between gap-6 mb-2">
        <h3 class="card-title text-lg">Фильтры</h3>

        <button
          @click="resetFilters"
          class="btn btn-ghost btn-sm gap-1"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          Сбросить фильтры
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <DetailTypeFilter v-model="filters.detail_type" />
        <PaymentGatewayFilter v-model="filters.payment_gateway_id" />
        <UserFilter v-model="filters.user_id" />
      </div>
    </div>
  </div>
</template> 