<script setup>
import { ref, defineProps, onMounted, watch } from 'vue';
import PaymentDetailsStats from './PaymentDetailsStats.vue';
import ClosedOrdersTable from './ClosedOrdersTable.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    paymentDetails: {
        type: Object,
        required: true
    },
    closedOrders: {
        type: Object,
        required: true
    },
    initialTableType: {
        type: String,
        default: 'payment-details'
    }
});

const emit = defineEmits(['table-type-changed']);

// Активный таб (payment-details или closed-orders)
const activeTab = ref(props.initialTableType);

// Переключение таба
const setActiveTab = (tab) => {
    activeTab.value = tab;
    emit('table-type-changed', tab);

    // Обновляем URL параметры без перезагрузки страницы
    const urlParams = new URLSearchParams(window.location.search);
    const month = urlParams.get('month') || '';
    const chartType = urlParams.get('chartType') || 'turnover';

    router.visit(route(route().current()), {
        data: {
            month: month,
            chartType: chartType,
            tableType: tab,
            page: 1 // Сбрасываем пагинацию при переключении вкладок
        },
        preserveScroll: true,
        preserveState: false, // Отключаем сохранение состояния для обновления данных
        only: []
    });
};

// Следим за изменением initialTableType из props
watch(() => props.initialTableType, (newType) => {
    if (newType !== activeTab.value) {
        activeTab.value = newType;
    }
});

// При монтировании проверяем URL параметры
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tableTypeParam = urlParams.get('tableType');

    if (tableTypeParam && (tableTypeParam === 'payment-details' || tableTypeParam === 'closed-orders')) {
        activeTab.value = tableTypeParam;
    }
});
</script>

<template>
    <section class="space-y-6">
        <!-- Переключатель (как у метрик) без иконок -->
        <div class="join mb-6">
            <button class="btn btn-sm join-item" :class="{ 'btn-active btn-primary': activeTab === 'payment-details' }" @click="setActiveTab('payment-details')">
                Реквизиты
            </button>
            <button class="btn btn-sm join-item" :class="{ 'btn-active btn-primary': activeTab === 'closed-orders' }" @click="setActiveTab('closed-orders')">
                Сделки
            </button>
        </div>

        <!-- Содержимое табов -->
        <div>
            <!-- Таблица платежных реквизитов -->
            <div v-if="activeTab === 'payment-details'">
                <PaymentDetailsStats
                    :payment-details="paymentDetails"
                />
            </div>

            <!-- Таблица закрытых сделок -->
            <div v-if="activeTab === 'closed-orders'">
                <ClosedOrdersTable
                    :closed-orders="closedOrders"
                />
            </div>
        </div>
    </section>
</template>
