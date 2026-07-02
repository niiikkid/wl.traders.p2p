<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import WidgetHeader from '@/Components/MainPage/WidgetHeader.vue';
import MerchantApiLogAmountDistributionModal from '@/Modals/MerchantApiLogs/MerchantApiLogAmountDistributionModal.vue';

const loading = ref(false);
const loaded = ref(false);
const errored = ref(false);
const showAmountDistributionModal = ref(false);
const amountDistributionRoute = route('admin.merchant-api-logs.amount-distribution');

const failedTotal = ref(0);
const failedToday = ref(0);
const successTotal = ref(0);
const successToday = ref(0);
const sumBySuccessCurrencyToday = ref({});
const sumByFailedCurrencyToday = ref({});
const sumBySuccessCurrencyTotal = ref({});
const sumByFailedCurrencyTotal = ref({});
const requestsChart = ref({ labels: [], total: [], successful: [] });
const chartCurrencyOptions = ref([]);

const chartDate = ref(new Date().toISOString().slice(0, 10));
const chartMode = ref('day');
const selectedWeekdays = ref([1, 2, 3, 4, 5, 6, 7]);
const selectedChartCurrency = ref('');
const chartAmountFrom = ref('');
const chartAmountTo = ref('');

const weekdayOptions = [
    { value: 1, label: 'Пн' },
    { value: 2, label: 'Вт' },
    { value: 3, label: 'Ср' },
    { value: 4, label: 'Чт' },
    { value: 5, label: 'Пт' },
    { value: 6, label: 'Сб' },
    { value: 7, label: 'Вс' },
];

const averageDaysCount = computed(() => requestsChart.value.daysCount || 0);
const selectedChartDateLabel = computed(() => {
    const [year, month, day] = String(chartDate.value).split('-').map((item) => Number(item));
    const date = new Date(year, month - 1, day);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' })
        .format(date)
        .replace('.', '');
});
const chartTitle = computed(() => (chartMode.value === 'average' ? 'Средний день по часам' : 'Запросы по часам'));
const chartSubtitle = computed(() => {
    if (chartMode.value === 'day') {
        return selectedChartDateLabel.value;
    }
    return averageDaysCount.value > 0 ? `Среднее за ${averageDaysCount.value} дн.` : 'Среднее за выбранные дни';
});
const formatChartValue = (value) => (chartMode.value === 'average'
    ? Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
    : Math.round(value).toString());

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0';
    const rounded = Math.round(num * 100) / 100;
    return rounded.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const chartEl = ref(null);
const apexChart = ref(null);

const renderChart = async () => {
    await nextTick();
    if (!chartEl.value) {
        return;
    }

    if (!apexChart.value) {
        const { default: ApexCharts } = await import('apexcharts');
        apexChart.value = new ApexCharts(chartEl.value, {
            chart: { type: 'line', height: 240, toolbar: { show: false }, zoom: { enabled: false } },
            stroke: { curve: 'smooth', width: 3 },
            grid: { borderColor: 'rgba(148, 163, 184, 0.2)' },
            dataLabels: { enabled: false },
            markers: { size: 0, hover: { size: 4 } },
            legend: { position: 'top', horizontalAlign: 'left', labels: { colors: '#999' } },
            tooltip: { theme: 'dark', y: { formatter: (value) => `${formatChartValue(value)} запросов` } },
            series: [],
        });
        apexChart.value.render();
    }

    apexChart.value.updateOptions({
        series: [
            { name: chartMode.value === 'average' ? 'В среднем всего' : 'Всего запросов', data: requestsChart.value.total },
            { name: chartMode.value === 'average' ? 'В среднем успешных' : 'Успешные запросы', data: requestsChart.value.successful },
        ],
        xaxis: {
            categories: requestsChart.value.labels,
            labels: { style: { colors: '#999' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: '#999' }, formatter: formatChartValue } },
        colors: ['#ef4444', '#22c55e'],
    }, false, false);
};

const load = async (overrides = {}) => {
    if (loading.value) {
        return;
    }
    loading.value = true;
    errored.value = false;

    const params = {
        chart_date: chartDate.value,
        chart_mode: chartMode.value,
        chart_weekdays: selectedWeekdays.value,
        chart_currency: selectedChartCurrency.value || undefined,
        chart_amount_from: chartAmountFrom.value || undefined,
        chart_amount_to: chartAmountTo.value || undefined,
        ...overrides,
    };

    try {
        const { data } = await axios.get(route('admin.main.api-log-stats'), { params });
        failedTotal.value = data.failedTotal;
        failedToday.value = data.failedToday;
        successTotal.value = data.successTotal;
        successToday.value = data.successToday;
        sumBySuccessCurrencyToday.value = data.sumBySuccessCurrencyToday || {};
        sumByFailedCurrencyToday.value = data.sumByFailedCurrencyToday || {};
        sumBySuccessCurrencyTotal.value = data.sumBySuccessCurrencyTotal || {};
        sumByFailedCurrencyTotal.value = data.sumByFailedCurrencyTotal || {};
        requestsChart.value = data.requestsChart || { labels: [], total: [], successful: [] };
        chartCurrencyOptions.value = data.chartCurrencyOptions || [];
        chartDate.value = data.requestsChartDate || chartDate.value;
        chartMode.value = data.requestsChartMode || chartMode.value;
        selectedWeekdays.value = (data.requestsChartWeekdays || selectedWeekdays.value).map((weekday) => Number(weekday));
        loaded.value = true;
        await renderChart();
    } catch (error) {
        errored.value = true;
    } finally {
        loading.value = false;
    }
};

const parseIsoDate = (value) => {
    const [year, month, day] = String(value).split('-').map((item) => Number(item));
    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? new Date() : date;
};

const formatDateToIso = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const navigateChartDate = (step) => {
    const current = parseIsoDate(chartDate.value);
    const next = new Date(current.getFullYear(), current.getMonth(), current.getDate() + step);
    chartDate.value = formatDateToIso(next);
    load({ chart_date: chartDate.value });
};

const switchChartMode = (mode) => {
    if (chartMode.value === mode) {
        return;
    }
    chartMode.value = mode;
    load({ chart_mode: mode });
};

const toggleWeekday = (weekday) => {
    const selected = new Set(selectedWeekdays.value);
    if (selected.has(weekday)) {
        selected.delete(weekday);
    } else {
        selected.add(weekday);
    }
    if (selected.size === 0) {
        selected.add(weekday);
    }
    selectedWeekdays.value = [...selected].sort((left, right) => left - right);
    load({ chart_mode: 'average', chart_weekdays: selectedWeekdays.value });
};

const applyChartFilters = () => load();
const resetChartFilters = () => {
    selectedChartCurrency.value = '';
    chartAmountFrom.value = '';
    chartAmountTo.value = '';
    load({ chart_currency: undefined, chart_amount_from: undefined, chart_amount_to: undefined });
};

onMounted(() => {
    load();
});

onBeforeUnmount(() => {
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
});
</script>

<template>
    <div class="space-y-3">
        <WidgetHeader title="Статистика запросов API" :loading="loading" @refresh="load()" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="space-y-4 p-3 lg:p-4">
                <div v-if="loading && !loaded" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div v-for="n in 4" :key="n" class="skeleton h-24 w-full"></div>
                    </div>
                    <div class="skeleton h-60 w-full"></div>
                </div>

                <div v-else-if="errored" class="flex h-40 items-center justify-center text-sm text-error">
                    Не удалось загрузить статистику
                </div>

                <template v-else>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline btn-primary"
                            @click="showAmountDistributionModal = true"
                        >
                            Распределение по сумме
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="opacity-70">Успешно сегодня</p>
                                        <p class="text-2xl font-bold">{{ successToday }}</p>
                                    </div>
                                    <div class="rounded-full bg-success/10 p-3">
                                        <svg class="h-6 w-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="opacity-70">Ошибок сегодня</p>
                                        <p class="text-2xl font-bold">{{ failedToday }}</p>
                                    </div>
                                    <div class="rounded-full bg-error/10 p-3">
                                        <svg class="h-6 w-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="opacity-70">Успешно всего</p>
                                        <p class="text-2xl font-bold">{{ successTotal }}</p>
                                    </div>
                                    <div class="rounded-full bg-success/10 p-3">
                                        <svg class="h-6 w-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="opacity-70">Ошибок всего</p>
                                        <p class="text-2xl font-bold">{{ failedTotal }}</p>
                                    </div>
                                    <div class="rounded-full bg-error/10 p-3">
                                        <svg class="h-6 w-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/30 px-3 pb-7 pt-4 sm:px-6">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg text-base-content/70">{{ chartTitle }}</h3>
                                <p class="text-sm text-base-content/60">{{ chartSubtitle }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:items-end">
                                <div class="join join-horizontal">
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="chartMode === 'day' ? 'btn-active btn-primary' : 'bg-base-200/60 border-transparent'"
                                        @click="switchChartMode('day')"
                                    >
                                        День
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="chartMode === 'average' ? 'btn-active btn-primary' : 'bg-base-200/60 border-transparent'"
                                        @click="switchChartMode('average')"
                                    >
                                        Средний день
                                    </button>
                                </div>
                                <div v-if="chartMode === 'day'" class="join join-horizontal items-center">
                                    <button type="button" class="btn btn-sm btn-ghost join-item" @click="navigateChartDate(-1)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                                        </svg>
                                    </button>
                                    <span class="join-item min-w-36 px-3 text-center text-sm font-medium text-base-content">
                                        {{ selectedChartDateLabel }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-ghost join-item" @click="navigateChartDate(1)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                                <div v-else class="flex flex-wrap justify-start gap-1 sm:justify-end">
                                    <button
                                        v-for="weekday in weekdayOptions"
                                        :key="weekday.value"
                                        type="button"
                                        class="btn btn-xs"
                                        :class="selectedWeekdays.includes(weekday.value) ? 'btn-primary' : 'btn-outline'"
                                        @click="toggleWeekday(weekday.value)"
                                    >
                                        {{ weekday.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div ref="chartEl" class="h-60"></div>
                        <div class="mt-4 flex justify-end">
                            <div class="grid w-full grid-cols-1 gap-2 md:w-auto md:grid-cols-[minmax(9rem,12rem)_minmax(8rem,10rem)_minmax(8rem,10rem)_auto] md:items-end">
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Валюта</span>
                                    </div>
                                    <select v-model="selectedChartCurrency" class="select select-bordered select-sm w-full">
                                        <option value="">Все валюты</option>
                                        <option v-for="currency in chartCurrencyOptions" :key="currency" :value="currency">
                                            {{ currency.toUpperCase() }}
                                        </option>
                                    </select>
                                </label>
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Сумма от</span>
                                    </div>
                                    <input v-model="chartAmountFrom" type="number" min="0" step="0.01" class="input input-bordered input-sm w-full" placeholder="0">
                                </label>
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Сумма до</span>
                                    </div>
                                    <input v-model="chartAmountTo" type="number" min="0" step="0.01" class="input input-bordered input-sm w-full" placeholder="∞">
                                </label>
                                <div class="flex gap-2 md:justify-end">
                                    <button type="button" class="btn btn-primary btn-sm" @click="applyChartFilters">Применить</button>
                                    <button type="button" class="btn btn-ghost btn-sm" @click="resetChartFilters">Сбросить</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body">
                                <h3 class="mb-3 text-lg font-semibold">Суммы успешных запросов</h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium opacity-70">Сегодня</h4>
                                        <div class="space-y-2">
                                            <div v-for="(amount, currency) in sumBySuccessCurrencyToday" :key="'success-today-' + currency" class="flex justify-between">
                                                <span>{{ currency.toUpperCase() }}</span>
                                                <span class="font-medium">{{ formatNumber(amount) }}</span>
                                            </div>
                                            <div v-if="Object.keys(sumBySuccessCurrencyToday).length === 0" class="opacity-70">Нет данных</div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium opacity-70">Всего</h4>
                                        <div class="space-y-2">
                                            <div v-for="(amount, currency) in sumBySuccessCurrencyTotal" :key="'success-total-' + currency" class="flex justify-between">
                                                <span>{{ currency.toUpperCase() }}</span>
                                                <span class="font-medium">{{ formatNumber(amount) }}</span>
                                            </div>
                                            <div v-if="Object.keys(sumBySuccessCurrencyTotal).length === 0" class="opacity-70">Нет данных</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-border bg-base-200/40 shadow-none">
                            <div class="card-body">
                                <h3 class="mb-3 text-lg font-semibold">Суммы неудачных запросов</h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium opacity-70">Сегодня</h4>
                                        <div class="space-y-2">
                                            <div v-for="(amount, currency) in sumByFailedCurrencyToday" :key="'failed-today-' + currency" class="flex justify-between">
                                                <span>{{ currency.toUpperCase() }}</span>
                                                <span class="font-medium">{{ formatNumber(amount) }}</span>
                                            </div>
                                            <div v-if="Object.keys(sumByFailedCurrencyToday).length === 0" class="opacity-70">Нет данных</div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium opacity-70">Всего</h4>
                                        <div class="space-y-2">
                                            <div v-for="(amount, currency) in sumByFailedCurrencyTotal" :key="'failed-total-' + currency" class="flex justify-between">
                                                <span>{{ currency.toUpperCase() }}</span>
                                                <span class="font-medium">{{ formatNumber(amount) }}</span>
                                            </div>
                                            <div v-if="Object.keys(sumByFailedCurrencyTotal).length === 0" class="opacity-70">Нет данных</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <MerchantApiLogAmountDistributionModal
            :show="showAmountDistributionModal"
            :amount-distribution-route="amountDistributionRoute"
            @close="showAmountDistributionModal = false"
        />
    </div>
</template>
