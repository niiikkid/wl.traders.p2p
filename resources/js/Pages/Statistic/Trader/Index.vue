<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Статистика"/>

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Статистика</h2>
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
