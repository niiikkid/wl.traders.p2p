<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import {computed, nextTick, onBeforeUnmount, onMounted, ref, unref, watch} from "vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import DisplayID from "@/Components/DisplayID.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MerchantApiLogAmountDistributionModal from "@/Modals/MerchantApiLogs/MerchantApiLogAmountDistributionModal.vue";
import {useModalStore} from "@/store/modal";
import {useHasActiveTableFilters} from "@/composables/useHasActiveTableFilters.js";
import ApexCharts from 'apexcharts';

const modalStore = useModalStore();
const page = usePage();

const isAdminMerchantApiLogsPage = computed(() => route().current() === 'admin.merchant-api-logs.index');
const showAmountDistributionModal = ref(false);
const amountDistributionRoute = computed(() => {
    if (route().current('analyst.merchant-api-logs.index')) {
        return route('analyst.merchant-api-logs.amount-distribution');
    }

    if (route().current('admin.merchant-api-logs.index')) {
        return route('admin.merchant-api-logs.amount-distribution');
    }

    return null;
});
const filtersPanelRef = ref(null);
const hasActiveMerchantApiLogFilters = useHasActiveTableFilters();
const filtersPanelOpen = computed(() => unref(filtersPanelRef.value?.displayFilters) ?? false);
const isRefreshingPage = ref(false);
const activeApiLogTab = computed(() => page.props.activeApiLogTab || 'orders');
const isPayoutLogsTab = computed(() => activeApiLogTab.value === 'payouts');
const entityColumnLabel = computed(() => isPayoutLogsTab.value ? 'Выплата' : 'Сделка');
const entityUuidPlaceholder = computed(() => isPayoutLogsTab.value ? 'UUID выплаты' : 'UUID сделки');
const detailColumnLabel = computed(() => isPayoutLogsTab.value ? 'Метод' : 'Реквизит');
const detailFieldLabel = computed(() => isPayoutLogsTab.value ? 'Метод выплаты:' : 'Тип реквизита:');

const toggleFiltersFromToolbar = () => {
    filtersPanelRef.value?.toggleFiltersDisplay?.();
};

const refreshMerchantApiLogsPage = () => {
    if (isRefreshingPage.value) {
        return;
    }

    isRefreshingPage.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshingPage.value = false;
        },
    });
};
const logs = computed(() => page.props.logs);
const canManageMerchantApiLogDeletion = computed(() => Boolean(page.props.can_manage_merchant_api_log_deletion));
const expandedRows = ref({}); // Для отслеживания развернутых строк (desktop)
const expandedCards = ref({}); // Для отслеживания развернутых карточек (mobile)
const chart = ref(null);
const apexChart = ref(null);
const chartDate = ref(page.props.requestsChartDate || new Date().toISOString().slice(0, 10));
const requestsChart = computed(() => page.props.requestsChart || {labels: [], total: [], successful: []});
const chartMode = ref(page.props.requestsChartMode || 'day');
const selectedWeekdays = ref((page.props.requestsChartWeekdays || [1, 2, 3, 4, 5, 6, 7]).map((weekday) => Number(weekday)));
const chartFilters = computed(() => page.props.requestsChartFilters || {});
const chartCurrencyOptions = computed(() => page.props.chartCurrencyOptions || []);
const selectedChartCurrency = ref(chartFilters.value.currency || '');
const chartAmountFrom = ref(chartFilters.value.amount_from ?? '');
const chartAmountTo = ref(chartFilters.value.amount_to ?? '');
const weekdayOptions = [
    {value: 1, label: 'Пн'},
    {value: 2, label: 'Вт'},
    {value: 3, label: 'Ср'},
    {value: 4, label: 'Чт'},
    {value: 5, label: 'Пт'},
    {value: 6, label: 'Сб'},
    {value: 7, label: 'Вс'},
];
const averageDaysCount = computed(() => requestsChart.value.daysCount || 0);
const selectedChartDateLabel = computed(() => {
    const [year, month, day] = String(chartDate.value).split('-').map((item) => Number(item));
    const date = new Date(year, month - 1, day);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date).replace('.', '');
});
const chartTitle = computed(() => chartMode.value === 'average' ? 'Средний день по часам' : 'Запросы по часам');
const chartSubtitle = computed(() => {
    if (chartMode.value === 'day') {
        return selectedChartDateLabel.value;
    }

    return averageDaysCount.value > 0
        ? `Среднее за ${averageDaysCount.value} дн.`
        : 'Среднее за выбранные дни';
});
const formatChartValue = (value) => chartMode.value === 'average'
    ? Number(value).toLocaleString('ru-RU', {maximumFractionDigits: 2})
    : Math.round(value).toString();

// Получение статистических данных из props (реактивно к Inertia reload / visit)
const failedTotal = computed(() => page.props.failedTotal);
const failedToday = computed(() => page.props.failedToday);
const successTotal = computed(() => page.props.successTotal);
const successToday = computed(() => page.props.successToday);
const sumBySuccessCurrencyToday = computed(() => page.props.sumBySuccessCurrencyToday);
const sumByFailedCurrencyToday = computed(() => page.props.sumByFailedCurrencyToday);
const sumBySuccessCurrencyTotal = computed(() => page.props.sumBySuccessCurrencyTotal);
const sumByFailedCurrencyTotal = computed(() => page.props.sumByFailedCurrencyTotal);

// Данные для удаления логов по периоду
const startDate = ref('');
const endDate = ref('');
const processing = ref(false);

// Функция для проверки, выбраны ли обе даты
const areBothDatesSelected = () => {
    return startDate.value && endDate.value;
};

// Функция для удаления логов
const deleteLogsByDateRange = () => {
    processing.value = true;
    router.post(route('admin.merchant-api-logs.delete'), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        onSuccess: () => {
            processing.value = false;
            startDate.value = '';
            endDate.value = '';
        },
        onError: () => {
            processing.value = false;
        }
    });
};

// Функция для подтверждения удаления
const confirmDelete = () => {
    if (!areBothDatesSelected()) return;

    modalStore.openConfirmModal({
        title: 'Подтверждение удаления',
        body: `Вы уверены, что хотите удалить все логи API запросов за период с ${startDate.value} по ${endDate.value}? Это действие нельзя отменить.`,
        confirm_button_name: 'Удалить',
        confirm: deleteLogsByDateRange
    });
};

const switchApiLogTab = (tab) => {
    if (activeApiLogTab.value === tab) {
        return;
    }

    expandedRows.value = {};
    expandedCards.value = {};

    router.visit(page.url?.split('?')[0] || window.location.pathname, {
        data: {
            ...route().params,
            tab,
            page: 1,
            per_page: logs.value?.meta?.per_page ?? 10,
            filters: page.props.filters,
        },
        preserveScroll: true,
    });
};

// Функция для форматирования чисел
const formatNumber = (num) => {
    if (num === undefined || num === null) return '0';
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// Функция для форматирования времени выполнения в секунды
const formatExecutionTime = (timeMs) => {
    if (timeMs === undefined || timeMs === null) return '-';
    const seconds = timeMs / 1000;
    return seconds.toLocaleString('ru-RU', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }) + ' сек';
}

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
    const currentDate = parseIsoDate(chartDate.value);
    const nextDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate() + step);
    const nextChartDate = formatDateToIso(nextDate);

    chartDate.value = nextChartDate;
    reloadChart({chart_date: nextChartDate});
};

const reloadChart = (overrides = {}) => {
    router.visit(route(route().current()), {
        data: {
            ...route().params,
            chart_date: chartDate.value,
            chart_mode: chartMode.value,
            chart_weekdays: selectedWeekdays.value,
            chart_currency: selectedChartCurrency.value || undefined,
            chart_amount_from: chartAmountFrom.value || undefined,
            chart_amount_to: chartAmountTo.value || undefined,
            ...overrides,
        },
        replace: true,
        preserveScroll: true,
        preserveState: false,
    });
};

const applyChartFilters = () => {
    reloadChart();
};

const resetChartFilters = () => {
    selectedChartCurrency.value = '';
    chartAmountFrom.value = '';
    chartAmountTo.value = '';
    reloadChart({
        chart_currency: undefined,
        chart_amount_from: undefined,
        chart_amount_to: undefined,
    });
};

const switchChartMode = (mode) => {
    if (chartMode.value === mode) {
        return;
    }

    chartMode.value = mode;
    reloadChart({chart_mode: mode});
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
    reloadChart({
        chart_mode: 'average',
        chart_weekdays: selectedWeekdays.value,
    });
};

const renderChart = () => {
    if (!chart.value) {
        return;
    }

    if (!apexChart.value) {
        apexChart.value = new ApexCharts(chart.value, {
            chart: {
                type: 'line',
                height: 240,
                toolbar: {show: false},
                zoom: {enabled: false},
            },
            stroke: {
                curve: 'smooth',
                width: 3,
            },
            grid: {
                borderColor: 'rgba(148, 163, 184, 0.2)',
            },
            dataLabels: {enabled: false},
            markers: {
                size: 0,
                hover: {size: 4},
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                labels: {
                    colors: '#999',
                },
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: (value) => `${formatChartValue(value)} запросов`,
                },
            },
            series: [],
        });
        apexChart.value.render();
    }

    apexChart.value.updateOptions({
        series: [
            {
                name: chartMode.value === 'average' ? 'В среднем всего' : 'Всего запросов',
                data: requestsChart.value.total,
            },
            {
                name: chartMode.value === 'average' ? 'В среднем успешных' : 'Успешные запросы',
                data: requestsChart.value.successful,
            },
        ],
        xaxis: {
            categories: requestsChart.value.labels,
            labels: {
                style: {
                    colors: '#999',
                },
            },
            axisBorder: {show: false},
            axisTicks: {show: false},
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                style: {
                    colors: '#999',
                },
                formatter: formatChartValue,
            },
        },
        colors: ['#ef4444', '#22c55e'],
    }, false, false);
};

// Функция для переключения состояния развернутой строки (desktop)
const toggleRow = (logId) => {
    expandedRows.value[logId] = !expandedRows.value[logId];
};

// Функция для переключения состояния развернутой карточки (mobile)
const toggleExpand = (logId) => {
    expandedCards.value[logId] = !expandedCards.value[logId];
};

defineOptions({ layout: AuthenticatedLayout })

onMounted(() => {
    nextTick(renderChart);
});

watch(requestsChart, () => {
    nextTick(renderChart);
}, {deep: true});

watch(() => page.props.requestsChartDate, (value) => {
    chartDate.value = value || chartDate.value;
});

watch(() => page.props.requestsChartMode, (value) => {
    chartMode.value = value || chartMode.value;
});

watch(() => page.props.requestsChartWeekdays, (value) => {
    selectedWeekdays.value = (value || selectedWeekdays.value).map((weekday) => Number(weekday));
});

watch(() => page.props.requestsChartFilters, (value) => {
    selectedChartCurrency.value = value?.currency || '';
    chartAmountFrom.value = value?.amount_from ?? '';
    chartAmountTo.value = value?.amount_to ?? '';
});

onBeforeUnmount(() => {
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
});
</script>

<template>
    <div>
        <Head title="Логи API-запросов" />

        <MainTableSection
            title="Логи API-запросов"
            :data="logs"
            :visit-extra-data="{ tab: activeApiLogTab }"
        >
            <template v-if="isAdminMerchantApiLogsPage" #button>
                <div class="flex max-w-full min-w-0 flex-wrap items-center justify-end gap-2">
                    <div
                        class="inline-flex max-w-full flex-wrap items-center justify-end gap-2 rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm"
                    >
                        <div class="relative inline-flex shrink-0">
                            <button
                                type="button"
                                class="btn btn-sm btn-square btn-primary btn-outline rounded-lg"
                                :class="{ 'btn-active': filtersPanelOpen }"
                                title="Фильтры"
                                aria-label="Показать или скрыть фильтры"
                                @click.prevent="toggleFiltersFromToolbar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                </svg>
                            </button>
                            <span
                                v-if="hasActiveMerchantApiLogFilters"
                                class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border border-base-100 bg-error"
                                aria-hidden="true"
                                title="Есть применённые фильтры"
                            />
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-square btn-secondary btn-outline shrink-0 rounded-lg"
                            :disabled="isRefreshingPage"
                            title="Обновить"
                            aria-label="Обновить страницу"
                            @click="refreshMerchantApiLogsPage"
                        >
                            <span
                                v-if="isRefreshingPage"
                                class="loading loading-spinner loading-sm text-secondary"
                                role="status"
                            />
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="h-5 w-5 shrink-0"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>

            <template #header>
                <div role="tablist" class="tabs tabs-border mb-4">
                    <button
                        type="button"
                        role="tab"
                        class="tab"
                        :class="{ 'tab-active': activeApiLogTab === 'orders' }"
                        @click="switchApiLogTab('orders')"
                    >
                        Сделки
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="tab"
                        :class="{ 'tab-active': activeApiLogTab === 'payouts' }"
                        @click="switchApiLogTab('payouts')"
                    >
                        Выплаты
                    </button>
                </div>

                <FiltersPanel
                    ref="filtersPanelRef"
                    name="merchant-api-logs"
                    :query="{ tab: activeApiLogTab }"
                    :omit-default-toggle-button="isAdminMerchantApiLogsPage"
                >
                    <InputFilter
                        name="merchant"
                        placeholder="Мерчант (имя или uuid)"
                    />
                    <InputFilter
                        name="externalID"
                        placeholder="Внешний ID"
                    />
                    <InputFilter
                        name="uuid"
                        :placeholder="entityUuidPlaceholder"
                    />
                    <InputFilter
                        name="minAmount"
                        placeholder="Мин. сумма"
                    />
                    <InputFilter
                        name="maxAmount"
                        placeholder="Макс. сумма"
                    />
                    <InputFilter
                        name="currency"
                        placeholder="Валюта"
                    />
                    <InputFilter
                        name="method"
                        placeholder="Метод (код)"
                    />
                    <DropdownFilter
                        name="apiLogStatuses"
                        title="Статусы"
                    />
                </FiltersPanel>
            </template>

            <template v-slot:body>
                <!-- Панель статистики -->
                <div v-if="!isPayoutLogsTab" class="mb-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-xl font-semibold">Статистика запросов</h2>
                        <button
                            v-if="amountDistributionRoute"
                            type="button"
                            class="btn btn-sm btn-outline btn-primary"
                            @click="showAmountDistributionModal = true"
                        >
                            Распределение по сумме
                        </button>
                    </div>

                    <!-- Карточки статистики -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Успешные запросы сегодня -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Успешно сегодня</p>
                                    <p class="text-2xl font-bold">{{ successToday }}</p>
                                </div>
                                <div class="bg-success/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Неудачные запросы сегодня -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Ошибок сегодня</p>
                                    <p class="text-2xl font-bold">{{ failedToday }}</p>
                                </div>
                                <div class="bg-error/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Успешные запросы всего -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Успешно всего</p>
                                    <p class="text-2xl font-bold">{{ successTotal }}</p>
                                </div>
                                <div class="bg-success/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Неудачные запросы всего -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Ошибок всего</p>
                                    <p class="text-2xl font-bold">{{ failedTotal }}</p>
                                </div>
                                <div class="bg-error/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow mt-4 pt-4 pb-7 px-6 pl-3">
                        <div class="flex flex-col gap-3 pl-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-base-content/70 text-lg">{{ chartTitle }}</h3>
                                <p class="text-sm text-base-content/60">{{ chartSubtitle }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:items-end">
                                <div class="join join-horizontal">
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="chartMode === 'day' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="switchChartMode('day')"
                                    >
                                        День
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="chartMode === 'average' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="switchChartMode('average')"
                                    >
                                        Средний день
                                    </button>
                                </div>
                                <div v-if="chartMode === 'day'" class="join join-horizontal items-center">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ghost join-item"
                                    @click="navigateChartDate(-1)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                                    </svg>
                                </button>
                                <span class="join-item px-3 text-sm font-medium text-base-content min-w-36 text-center">
                                    {{ selectedChartDateLabel }}
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ghost join-item"
                                    @click="navigateChartDate(1)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                    </svg>
                                </button>
                                </div>
                                <div v-else class="flex flex-wrap gap-1 justify-start sm:justify-end">
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
                        <div ref="chart" class="h-60"></div>
                        <div class="mt-4 flex justify-end pl-3">
                            <div class="grid w-full grid-cols-1 gap-2 md:w-auto md:grid-cols-[minmax(9rem,12rem)_minmax(8rem,10rem)_minmax(8rem,10rem)_auto] md:items-end">
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Валюта</span>
                                    </div>
                                    <select v-model="selectedChartCurrency" class="select select-bordered select-sm w-full">
                                        <option value="">Все валюты</option>
                                        <option
                                            v-for="currency in chartCurrencyOptions"
                                            :key="currency"
                                            :value="currency"
                                        >
                                            {{ currency.toUpperCase() }}
                                        </option>
                                    </select>
                                </label>
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Сумма от</span>
                                    </div>
                                    <input
                                        v-model="chartAmountFrom"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="input input-bordered input-sm w-full"
                                        placeholder="0"
                                    >
                                </label>
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-xs">Сумма до</span>
                                    </div>
                                    <input
                                        v-model="chartAmountTo"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="input input-bordered input-sm w-full"
                                        placeholder="∞"
                                    >
                                </label>
                                <div class="flex gap-2 md:justify-end">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        @click="applyChartFilters"
                                    >
                                        Применить
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        @click="resetChartFilters"
                                    >
                                        Сбросить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Суммы по валютам -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <!-- Суммы успешных запросов -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h3 class="text-lg font-semibold mb-3">Суммы успешных запросов</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Сегодня</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumBySuccessCurrencyToday" :key="'success-today-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumBySuccessCurrencyToday).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Всего</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumBySuccessCurrencyTotal" :key="'success-total-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumBySuccessCurrencyTotal).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Суммы неудачных запросов -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h3 class="text-lg font-semibold mb-3">Суммы неудачных запросов</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Сегодня</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumByFailedCurrencyToday" :key="'failed-today-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumByFailedCurrencyToday).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Всего</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumByFailedCurrencyTotal" :key="'failed-total-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumByFailedCurrencyTotal).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Панель управления логами -->
                    <div v-if="canManageMerchantApiLogDeletion" class="mt-6">
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h4 class="text-md font-medium mb-2">Управление логами</h4>
                            <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                <div class="w-full md:flex-grow grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="form-control w-full">
                                        <div class="label"><span class="label-text">Начальная дата</span></div>
                                        <input type="date" v-model="startDate" class="input input-bordered w-full" />
                                    </label>
                                    <label class="form-control w-full">
                                        <div class="label"><span class="label-text">Конечная дата</span></div>
                                        <input type="date" v-model="endDate" class="input input-bordered w-full" />
                                    </label>
                                </div>
                                <button
                                    @click="confirmDelete"
                                    class="btn btn-error rounded-xl"
                                    :disabled="!areBothDatesSelected() || processing"
                                >
                                    <span v-if="!processing">Удалить</span>
                                    <span v-else>Удаление...</span>
                                </button>
                            </div>
                            <p class="mt-2 text-sm opacity-70">
                                Выберите период, за который нужно удалить логи. Будут удалены все логи, созданные в указанный период.
                            </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col">
                                            Мерчант
                                        </th>
                                        <th scope="col">
                                            {{ entityColumnLabel }}
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Внешний ID
                                        </th>
                                        <th scope="col">
                                            Сумма
                                        </th>
<!--                                <th scope="col">
                                    Метод
                                </th>-->
                                        <th scope="col" class="text-nowrap">
                                            {{ detailColumnLabel }}
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Время
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col">
                                            Создан
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="log in logs.data" :key="log.id">
                                        <tr
                                            class="hover cursor-pointer"
                                            @click.stop="toggleRow(log.id)"
                                        >
                                            <th scope="row" class="font-medium whitespace-nowrap">
                                                {{ log.id }}
                                            </th>
                                            <td>
                                                {{ log.merchant.name }}
                                            </td>
                                            <td>
                                                <DisplayUUID
                                                    v-if="isPayoutLogsTab ? log.payout : log.order"
                                                    :uuid="isPayoutLogsTab ? log.payout?.uuid : log.order?.uuid"
                                                />
                                            </td>
                                            <td>
                                                <DisplayID v-if="log.external_id" :id="log.external_id"/>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                <div v-if="log.amount" class="text-nowrap">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else>-</div>
                                            </td>
<!--                                    <td>
                                        {{ log.payment_gateway || '-' }}
                                    </td>-->
                                            <td>
                                                {{ log.payment_detail_type || '-' }}
                                            </td>
                                            <td>
                                                <span
                                                    :class="log.execution_time
                                                        ? (log.execution_time < 1000 ? 'badge badge-success'
                                                        : log.execution_time < 3000 ? 'badge badge-warning'
                                                        : 'badge badge-error')
                                                        : 'badge'"
                                                    class="text-nowrap badge-xs"
                                                >
                                                    {{ formatExecutionTime(log.execution_time) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                    class="rounded-full flex items-center justify-center badge-xs"
                                                >
                                                    <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </span>
                                            </td>
                                            <td>
                                                <DateTime :data="log.created_at"></DateTime>
                                            </td>
                                        </tr>
                                        <!-- Развернутая информация -->
                                        <tr v-if="expandedRows[log.id]" class="bg-base-200">
                                            <td colspan="10" class="px-6 py-4">
                                                <h4 class="font-semibold mb-2">Детали</h4>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div v-if="log.request_data" class="mb-4">
                                                        <div class="opacity-70 mb-1">Данные запроса:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                                    </div>

                                                    <div v-if="log.response_data">
                                                        <div class="opacity-70 mb-1">Данные ответа:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                                    </div>
                                                </div>
                                                <div class="mt-4 grid grid-cols-2 gap-4">
                                                    <div v-if="log.user_agent">
                                                        <div class="opacity-70 mb-1">User Agent:</div>
                                                        <div class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ log.user_agent }}</div>
                                                    </div>
                                                    <div v-if="log.ip_address">
                                                        <div class="opacity-70 mb-1">IP адрес:</div>
                                                        <div class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ log.ip_address }}</div>
                                                    </div>
                                                </div>
                                                <div v-if="log.execution_time" class="mt-4">
                                                    <div class="opacity-70 mb-1">Время выполнения:</div>
                                                    <div>{{ formatExecutionTime(log.execution_time) }}</div>
                                                </div>
                                                <div v-if="log.error_message" class="mt-4">
                                                    <div class="opacity-70 mb-1">Сообщение об ошибке:</div>
                                                    <div class="text-error">{{ log.error_message }}</div>
                                                </div>
                                                <div v-if="log.exception_class" class="mt-4">
                                                    <div class="opacity-70 mb-1">Класс исключения:</div>
                                                    <div class="text-error">{{ log.exception_class }}</div>
                                                </div>
                                                <div v-if="log.exception_message" class="mt-4">
                                                    <div class="opacity-70 mb-1">Сообщение исключения:</div>
                                                    <div class="text-error">{{ log.exception_message }}</div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="log in logs.data"
                                :key="log.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Компактная шапка: ID и дата -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content">{{ log.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="log.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0 inline-flex items-center gap-5">
                                            <div class="w-30">
                                                <div class="text-xs text-base-content/70 mb-1">Мерчант</div>
                                                <div class="text-sm text-base-content truncate">{{ log.merchant.name }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-base-content/70 mb-1">Сумма</div>
                                                <div v-if="log.amount" class="text-sm text-nowrap text-base-content">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else class="text-sm text-base-content/60">-</div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span
                                                :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                class="rounded-full flex items-center justify-center badge-xs"
                                            >
                                                <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </span>
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(log.id)"
                                                :aria-expanded="!!expandedCards[log.id]"
                                                :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs text-base-content/70 mb-1">Мерчант</div>
                                                <div class="text-sm text-base-content truncate">{{ log.merchant.name }}</div>
                                            </div>
                                            <div>
                                                <span
                                                    :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                    class="rounded-full flex items-center justify-center badge-xs"
                                                >
                                                    <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-xs text-base-content/70 mb-1">Сумма</div>
                                                <div v-if="log.amount" class="text-sm text-nowrap text-base-content">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else class="text-sm text-base-content/60">-</div>
                                            </div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(log.id)"
                                                    :aria-expanded="!!expandedCards[log.id]"
                                                    :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[log.id]" class="mt-3 space-y-2 bg-base-300/50 rounded-box p-2">
                                        <div v-if="isPayoutLogsTab ? log.payout : log.order" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">{{ entityColumnLabel }}:</span>
                                            <DisplayUUID :uuid="isPayoutLogsTab ? log.payout?.uuid : log.order?.uuid"/>
                                        </div>
                                        <div v-if="log.external_id" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Внешний ID:</span>
                                            <DisplayID :id="log.external_id"/>
                                        </div>
                                        <div v-if="log.payment_detail_type" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">{{ detailFieldLabel }}</span>
                                            <span class="text-base-content/60">{{ log.payment_detail_type }}</span>
                                        </div>
                                        <div v-if="log.execution_time" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Время выполнения:</span>
                                            <span
                                                :class="log.execution_time
                                                    ? (log.execution_time < 1000 ? 'text-success'
                                                    : log.execution_time < 3000 ? 'text-warning'
                                                    : 'text-error')
                                                    : ''"
                                                class="text-sm font-medium"
                                            >
                                                {{ formatExecutionTime(log.execution_time) }}
                                            </span>
                                        </div>
                                        <div v-if="log.user_agent" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">User Agent:</div>
                                            <div class="bg-base-100 p-2 rounded overflow-auto max-h-32 text-xs break-words">{{ log.user_agent }}</div>
                                        </div>
                                        <div v-if="log.ip_address" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">IP адрес:</div>
                                            <div class="bg-base-100 p-2 rounded text-xs">{{ log.ip_address }}</div>
                                        </div>
                                        <div v-if="log.request_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные запроса:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                        </div>
                                        <div v-if="log.response_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные ответа:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                        </div>
                                        <div v-if="log.error_message" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Сообщение об ошибке:</div>
                                            <div class="text-error text-sm break-words">{{ log.error_message }}</div>
                                        </div>
                                        <div v-if="log.exception_class" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Класс исключения:</div>
                                            <div class="text-error text-sm break-words">{{ log.exception_class }}</div>
                                        </div>
                                        <div v-if="log.exception_message" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Сообщение исключения:</div>
                                            <div class="text-error text-sm break-words">{{ log.exception_message }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal />

        <MerchantApiLogAmountDistributionModal
            :show="showAmountDistributionModal"
            :amount-distribution-route="amountDistributionRoute"
            @close="showAmountDistributionModal = false"
        />
    </div>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
