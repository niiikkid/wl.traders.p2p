<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import { ref, computed, watch } from 'vue';
import MonthlyChart from './Components/MonthlyChart.vue';
import TablesSection from './Components/TablesSection.vue';

// Получаем данные из контроллера
const page = usePage();

const paymentDetails = computed(() => page.props.paymentDetails || {});
const closedOrders = computed(() => page.props.closedOrders || {});
const chartData = computed(() => page.props.chartData || {});
const currentMonth = computed(() => page.props.currentMonth || '');
const prevMonth = computed(() => page.props.prevMonth || '');
const nextMonth = computed(() => page.props.nextMonth || '');

const chartType = ref(page.props.chartType || 'turnover');
watch(
    () => page.props.chartType,
    (value) => {
        chartType.value = value || 'turnover';
    }
);

const tableType = ref(page.props.tableType || 'payment-details');
watch(
    () => page.props.tableType,
    (value) => {
        tableType.value = value || 'payment-details';
    }
);

// Обработка изменения типа графика
const handleChartTypeChanged = (type) => {
    chartType.value = type;

    // URL параметры обновляются прямо в компоненте MonthlyChart
};

// Обработка изменения типа таблицы
const handleTableTypeChanged = (type) => {
    tableType.value = type;

    // URL параметры обновляются прямо в компоненте TablesSection
};

// Экспорт сделок
const exportOrders = () => {
    window.open(route('trader.export.orders'), '_blank');
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Статистика"/>

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Статистика</h2>
                <div>
                    <button
                        @click="exportOrders"
                        type="button"
                        class="hidden md:flex btn btn-primary btn-sm"
                    >
                        <svg class="w-6 h-6 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2"/>
                        </svg>
                        Выгрузить сделки
                    </button>
                    <button
                        @click="exportOrders"
                        type="button"
                        class="md:hidden btn btn-primary btn-square btn-sm"
                    >
                        <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2"/>
                        </svg>
                    </button>
                </div>
            </div>

            <MonthlyChart
                :chart-data="chartData"
                :current-month="currentMonth"
                :prev-month="prevMonth"
                :next-month="nextMonth"
                :initial-chart-type="chartType"
                @chart-type-changed="handleChartTypeChanged"
            />

            <TablesSection
                :payment-details="paymentDetails"
                :closed-orders="closedOrders"
                :initial-table-type="tableType"
                @table-type-changed="handleTableTypeChanged"
            />
        </div>
    </div>
</template>
