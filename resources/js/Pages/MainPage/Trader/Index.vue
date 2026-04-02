<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import ApexCharts from 'apexcharts';
import axios from 'axios';

const page = usePage();
const statistics = computed(() => page.props.statistics || {});
const incomeChartData = computed(() => page.props.chart || { labels: [], data: [] });
const conversionChartData = computed(() => page.props.conversionChart || { labels: [], data: [] });
const turnoverChartData = computed(() => page.props.turnoverChart || { labels: [], data: [] });
const ordersChartData = computed(() => page.props.ordersChart || { labels: [], data: [] });
const averageCheckChartData = computed(() => page.props.averageCheckChart || { labels: [], data: [] });
const selectedPeriodPresetProp = computed(() => page.props.selectedPeriodPreset || 'month');
const selectedDateFromProp = computed(() => page.props.selectedDateFrom || '');
const selectedDateToProp = computed(() => page.props.selectedDateTo || '');
const selectedFiltersProp = computed(() => page.props.selectedFilters || {});

const walletStats = computed(() => page.props.walletStats);
const rates = computed(() => page.props.data?.rates ?? []);

const formatNumber = (num) => {
    const roundedNum = Math.round(num * 100) / 100;
    return roundedNum.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

/** Как у админки: денежные поля уже в формате с бэка (toBeauty), иначе локальный formatNumber ломает строки и маскирует обновления. */
const statisticsFormated = computed(() => ({
    totalTurnover: statistics.value?.totalTurnover ?? '0.00',
    totalProfit: statistics.value?.totalProfit ?? '0.00',
    balance: statistics.value?.balance ?? '0.00',
    successOrderCount: statistics.value?.successOrderCount ?? 0,
}));

const parseAmount = (value) => {
    if (value === null || value === undefined) {
        return 0;
    }
    if (typeof value === 'number') {
        return value;
    }
    const normalized = String(value).replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
};

const selectedFiatCurrency = computed(() => {
    return (walletStats.value?.currency?.secondary ?? 'rub').toLowerCase();
});

const selectedFiatRate = computed(() => {
    const fiatRate = rates.value.find((rate) => rate.code === selectedFiatCurrency.value);
    const parsedRate = parseAmount(fiatRate?.sell_price);
    return parsedRate > 0 ? parsedRate : 1;
});

const toSelectedFiatEquivalent = (value) => {
    return formatNumber(parseAmount(value) * selectedFiatRate.value);
};

const financeOverview = computed(() => {
    const trustAmount = walletStats.value?.base?.trustAmount ?? '0';
    const trustReserveAmount = walletStats.value?.base?.trustReserveAmount ?? '0';
    const trustWithdrawalAmount = walletStats.value?.lockedForWithdrawalBalances?.trust?.primary ?? '0';
    const escrowOrdersAmount = walletStats.value?.escrowBalances?.orders?.balance?.primary ?? '0';
    const escrowOrdersCount = walletStats.value?.escrowBalances?.orders?.count ?? 0;
    const escrowDisputesAmount = walletStats.value?.escrowBalances?.disputes?.balance?.primary ?? '0';
    const escrowDisputesCount = walletStats.value?.escrowBalances?.disputes?.count ?? 0;
    const maxReserveBalance = walletStats.value?.maxReserveBalance ?? 0;

    return {
        primaryCurrency: (walletStats.value?.currency?.primary ?? 'usdt').toUpperCase(),
        trustAmount,
        trustReserveAmount,
        trustWithdrawalAmount,
        escrowOrdersAmount,
        escrowOrdersCount,
        escrowDisputesAmount,
        escrowDisputesCount,
        maxReserveBalance,
        reserveGoalReached: parseAmount(trustAmount) >= parseAmount(maxReserveBalance),
        secondaryCurrency: selectedFiatCurrency.value.toUpperCase(),
        trustAmountSecondary: toSelectedFiatEquivalent(trustAmount),
        escrowOrdersAmountSecondary: toSelectedFiatEquivalent(escrowOrdersAmount),
        escrowDisputesAmountSecondary: toSelectedFiatEquivalent(escrowDisputesAmount),
    };
});

const processing = ref(false);
const isMobile = ref(false);
const activeChartTab = ref('income');
const selectedPeriodPreset = ref(selectedPeriodPresetProp.value || 'month');
const selectedDateFrom = ref(selectedDateFromProp.value || '');
const selectedDateTo = ref(selectedDateToProp.value || '');
const filterDropdownOpen = ref(false);
const customPeriodDropdownOpen = ref(false);
const activeFilterType = ref('payment_method');
const chart = ref(null);
const apexChart = ref(null);

const selectedFilters = ref({
    payment_method: selectedFiltersProp.value.paymentMethodIds || [],
    payment_detail: selectedFiltersProp.value.paymentDetailIds || [],
});

const filterTypes = [
    { key: 'payment_method', label: 'Метод', requestKey: 'payment_method_ids', placeholder: 'Поиск платежного метода...' },
    { key: 'payment_detail', label: 'Реквизит', requestKey: 'payment_detail_ids', placeholder: 'Поиск реквизита...' },
];

const searchQueries = ref({
    payment_method: '',
    payment_detail: '',
});

const searchResults = ref({
    payment_method: [],
    payment_detail: [],
});

const selectedOptions = ref({
    payment_method: [],
    payment_detail: [],
});

const loadingOptions = ref({
    payment_method: false,
    payment_detail: false,
});

const searchDebounceTimers = {};

const chartTabs = [
    { value: 'income', label: 'Доход', colorToken: 'primary', seriesName: 'Доход ($)' },
    { value: 'turnover', label: 'Оборот', colorToken: 'secondary', seriesName: 'Оборот ($)' },
    { value: 'conversion', label: 'Конверсия', colorToken: 'success', seriesName: 'Конверсия (%)' },
    { value: 'orders', label: 'Количество сделок', colorToken: 'accent', seriesName: 'Сделок' },
    { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)' },
];

const periodPresetOptions = [
    { value: 'today', label: 'Сегодня' },
    { value: 'week', label: 'Неделя' },
    { value: 'month', label: 'Месяц' },
    { value: 'all', label: 'Все' },
];

const colorProbeSpans = {};
const getThemeColor = (token) => {
    let span = colorProbeSpans[token];
    if (!span) {
        span = document.createElement('span');
        span.style.position = 'absolute';
        span.style.left = '-9999px';
        span.className = `text-${token}`;
        span.textContent = 'color-probe';
        document.body.appendChild(span);
        colorProbeSpans[token] = span;
    }
    return getComputedStyle(span).color || '#6366f1';
};

const updateIsMobile = () => {
    if (typeof window === 'undefined') {
        return;
    }
    isMobile.value = window.innerWidth < 640;
};

const getLastPoints = (source, limit = 10) => {
    if (!source || !Array.isArray(source.data) || !Array.isArray(source.labels)) {
        return { data: [], labels: [] };
    }
    if (!isMobile.value) {
        return source;
    }
    const startIndex = Math.max(source.data.length - limit, 0);
    return {
        data: source.data.slice(startIndex),
        labels: source.labels.slice(startIndex),
    };
};

const normalizeChartLabels = (source, periodPreset) => {
    if (!source || !Array.isArray(source.labels) || !Array.isArray(source.data)) {
        return { labels: [], data: [] };
    }
    if (periodPreset === 'custom' || periodPreset === 'all') {
        return {
            data: source.data,
            labels: source.labels.map((label) => {
                if (typeof label !== 'string') {
                    return label;
                }
                const dateMonthMatch = label.match(/(\d{1,2})\.(\d{1,2})/);
                if (dateMonthMatch) {
                    const day = dateMonthMatch[1].padStart(2, '0');
                    const month = dateMonthMatch[2].padStart(2, '0');
                    return `${day}.${month}`;
                }
                return label;
            }),
        };
    }
    return {
        data: source.data,
        labels: source.labels.map((label) => {
            if (typeof label !== 'string') {
                return label;
            }
            const onlyNumber = label.match(/\d+/);
            return onlyNumber ? onlyNumber[0] : label;
        }),
    };
};

const chartDataByTab = computed(() => ({
    income: getLastPoints(normalizeChartLabels(incomeChartData.value, selectedPeriodPreset.value)),
    conversion: getLastPoints(normalizeChartLabels(conversionChartData.value, selectedPeriodPreset.value)),
    turnover: getLastPoints(normalizeChartLabels(turnoverChartData.value, selectedPeriodPreset.value)),
    orders: getLastPoints(normalizeChartLabels(ordersChartData.value, selectedPeriodPreset.value)),
    average_check: getLastPoints(normalizeChartLabels(averageCheckChartData.value, selectedPeriodPreset.value)),
}));

const activeTabConfig = computed(() => chartTabs.find((tab) => tab.value === activeChartTab.value) || chartTabs[0]);
const activeTabTitle = computed(() => activeTabConfig.value.label);
const activeData = computed(() => chartDataByTab.value[activeChartTab.value] || { labels: [], data: [] });

const getYFormatter = (tab) => {
    if (tab === 'conversion') {
        return (value) => `${value}%`;
    }
    if (tab === 'orders') {
        return (value) => Math.round(value);
    }
    return (value) => `$${value}`;
};

const getYRange = (tab) => {
    if (tab === 'conversion') {
        return { min: 0, max: 100 };
    }
    return {};
};

const renderChart = () => {
    if (!chart.value) {
        return;
    }
    const formatter = getYFormatter(activeChartTab.value);
    const { min, max } = getYRange(activeChartTab.value);
    const color = getThemeColor(activeTabConfig.value.colorToken);
    const currentData = activeData.value;

    if (!apexChart.value) {
        apexChart.value = new ApexCharts(chart.value, {
            chart: {
                type: 'line',
                height: '95%',
                background: 'transparent',
                toolbar: { show: false },
            },
            series: [],
            xaxis: { categories: [] },
            yaxis: {},
            stroke: { width: 2, curve: 'smooth' },
            grid: { borderColor: 'rgba(200, 200, 200, 0.1)' },
            markers: { size: 4, strokeColors: '#fff', strokeWidth: 2 },
            tooltip: { theme: 'dark' },
        });
        apexChart.value.render();
    }

    apexChart.value.updateOptions({
        series: [{
            name: activeTabConfig.value.seriesName,
            data: currentData.data,
        }],
        xaxis: {
            categories: currentData.labels,
            labels: { style: { colors: '#999' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min,
            max,
            labels: {
                style: { colors: '#999' },
                formatter,
            },
        },
        colors: [color],
        markers: { colors: [color] },
        tooltip: {
            theme: 'dark',
            y: { formatter },
        },
    }, false, false);
};

watch(activeChartTab, () => {
    renderChart();
});

watch(activeData, () => {
    renderChart();
}, { deep: true });

const applyFilter = () => {
    if (processing.value) {
        return;
    }
    processing.value = true;
    const requestData = {
        period: selectedPeriodPreset.value,
    };
    if (selectedPeriodPreset.value === 'custom' && selectedDateFrom.value && selectedDateTo.value) {
        requestData.date_from = selectedDateFrom.value;
        requestData.date_to = selectedDateTo.value;
    }
    filterTypes.forEach((filterType) => {
        const selectedIds = selectedFilters.value[filterType.key] || [];
        if (selectedIds.length > 0) {
            requestData[filterType.requestKey] = selectedIds;
        }
    });
    router.visit(route('trader.main.index'), {
        data: requestData,
        preserveScroll: true,
        preserveState: false,
        replace: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};

const loadFilterOptions = async (typeKey, query = '') => {
    loadingOptions.value[typeKey] = true;
    try {
        const response = await axios.get(
            route('trader.main.filter-options', { type: typeKey }),
            {
                params: {
                    query,
                    selected_ids: selectedFilters.value[typeKey] || [],
                },
            },
        );
        const options = Array.isArray(response.data) ? response.data : [];
        searchResults.value[typeKey] = options;
        const selectedIdsSet = new Set((selectedFilters.value[typeKey] || []).map((id) => Number(id)));
        const nextSelected = options.filter((option) => selectedIdsSet.has(Number(option.value)));
        const previousSelected = selectedOptions.value[typeKey] || [];
        selectedOptions.value[typeKey] = [
            ...nextSelected,
            ...previousSelected.filter((option) => selectedIdsSet.has(Number(option.value))),
        ].filter((option, index, array) => array.findIndex((item) => Number(item.value) === Number(option.value)) === index);
    } catch (error) {
        console.error('Ошибка загрузки фильтров статистики', error);
    } finally {
        loadingOptions.value[typeKey] = false;
    }
};

const getDisplayedOptions = (typeKey) => {
    const selected = selectedOptions.value[typeKey] || [];
    const selectedIdsSet = new Set((selectedFilters.value[typeKey] || []).map((id) => Number(id)));
    const rest = (searchResults.value[typeKey] || []).filter((option) => !selectedIdsSet.has(Number(option.value)));
    return [...selected, ...rest].filter((option, index, array) => array.findIndex((item) => Number(item.value) === Number(option.value)) === index);
};

const isOptionSelected = (typeKey, optionValue) => {
    const selected = selectedFilters.value[typeKey] || [];
    return selected.some((id) => Number(id) === Number(optionValue));
};

const toggleFilterOption = (typeKey, option, event) => {
    const checked = event.target.checked;
    const current = selectedFilters.value[typeKey] || [];
    if (checked) {
        if (!current.some((id) => Number(id) === Number(option.value))) {
            selectedFilters.value[typeKey] = [...current, Number(option.value)];
        }
        if (!selectedOptions.value[typeKey].some((item) => Number(item.value) === Number(option.value))) {
            selectedOptions.value[typeKey] = [option, ...(selectedOptions.value[typeKey] || [])];
        }
        return;
    }
    selectedFilters.value[typeKey] = current.filter((id) => Number(id) !== Number(option.value));
    selectedOptions.value[typeKey] = (selectedOptions.value[typeKey] || []).filter((item) => Number(item.value) !== Number(option.value));
};

const selectFilterType = (typeKey) => {
    activeFilterType.value = typeKey;
    loadFilterOptions(typeKey, searchQueries.value[typeKey] || '');
};

const applyAdvancedFilters = () => {
    filterDropdownOpen.value = false;
    applyFilter();
};

const resetAdvancedFilters = () => {
    selectedFilters.value = { payment_method: [], payment_detail: [] };
    selectedOptions.value = { payment_method: [], payment_detail: [] };
    searchQueries.value = { payment_method: '', payment_detail: '' };
    filterDropdownOpen.value = false;
    applyFilter();
};

const closeFilterDropdown = () => {
    filterDropdownOpen.value = false;
    if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
};

const toggleFilterDropdown = () => {
    filterDropdownOpen.value = !filterDropdownOpen.value;
    if (filterDropdownOpen.value) {
        loadFilterOptions(activeFilterType.value, searchQueries.value[activeFilterType.value] || '');
    }
};

const setPeriodPreset = (preset) => {
    if (selectedPeriodPreset.value === preset) {
        return;
    }
    selectedPeriodPreset.value = preset;
    if (preset !== 'custom') {
        customPeriodDropdownOpen.value = false;
    }
    if (preset !== 'custom') {
        applyFilter();
        return;
    }
    if (selectedDateFrom.value && selectedDateTo.value) {
        applyFilter();
    }
};

const openCustomPeriodDropdown = () => {
    selectedPeriodPreset.value = 'custom';
    customPeriodDropdownOpen.value = !customPeriodDropdownOpen.value;
};

const hasActiveAdvancedFilters = computed(() => Object.values(selectedFilters.value).some((items) => Array.isArray(items) && items.length > 0));

watch([selectedDateFrom, selectedDateTo], () => {
    if (
        selectedPeriodPreset.value === 'custom'
        && selectedDateFrom.value
        && selectedDateTo.value
    ) {
        applyFilter();
    }
});

watch(selectedPeriodPresetProp, (newValue) => {
    selectedPeriodPreset.value = newValue || 'month';
    if ((newValue || 'month') !== 'custom') {
        customPeriodDropdownOpen.value = false;
    }
});

watch(selectedDateFromProp, (newValue) => {
    selectedDateFrom.value = newValue || '';
});

watch(selectedDateToProp, (newValue) => {
    selectedDateTo.value = newValue || '';
});

watch(selectedFiltersProp, (newFilters) => {
    selectedFilters.value = {
        payment_method: newFilters?.paymentMethodIds || [],
        payment_detail: newFilters?.paymentDetailIds || [],
    };
}, { deep: true });

filterTypes.forEach((filterType) => {
    watch(() => searchQueries.value[filterType.key], (query) => {
        clearTimeout(searchDebounceTimers[filterType.key]);
        searchDebounceTimers[filterType.key] = setTimeout(() => {
            loadFilterOptions(filterType.key, query || '');
        }, 300);
    });
});

let themeObserver = null;
let scheduledThemeUpdate = false;

onMounted(() => {
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
    renderChart();
    filterTypes.forEach((filterType) => {
        if ((selectedFilters.value[filterType.key] || []).length > 0) {
            loadFilterOptions(filterType.key, '');
        }
    });
    themeObserver = new MutationObserver(() => {
        if (scheduledThemeUpdate) {
            return;
        }
        scheduledThemeUpdate = true;
        requestAnimationFrame(() => {
            renderChart();
            scheduledThemeUpdate = false;
        });
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme'],
    });
});

onBeforeUnmount(() => {
    if (themeObserver) {
        themeObserver.disconnect();
        themeObserver = null;
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile);
    }
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
    Object.values(colorProbeSpans).forEach((span) => {
        if (span && span.parentNode) {
            span.parentNode.removeChild(span);
        }
    });
    Object.values(searchDebounceTimers).forEach((timer) => {
        clearTimeout(timer);
    });
});

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Главная" />

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Главная</h2>
                <slot name="button"></slot>
            </div>

            <div>
                <section>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-base-content/70 text-xs">Оборот</p>
                                    <p class="text-lg font-semibold text-base-content truncate">${{ statisticsFormated.totalTurnover }}</p>
                                </div>
                                <svg class="w-5 h-5 text-success shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-base-content/70 text-xs">Доход</p>
                                    <p class="text-lg font-semibold text-base-content truncate">${{ statisticsFormated.totalProfit }}</p>
                                </div>
                                <svg class="w-5 h-5 text-info shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-base-content/70 text-xs">Общий баланс</p>
                                    <p class="text-lg font-semibold text-base-content truncate">${{ statisticsFormated.balance }}</p>
                                </div>
                                <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-base-content/70 text-xs">Сделки</p>
                                    <p class="text-lg font-semibold text-base-content">{{ statisticsFormated.successOrderCount }}</p>
                                </div>
                                <svg class="w-5 h-5 text-warning shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col xl:flex-row lg:items-start xl:justify-between gap-3">
                        <div class="hidden md:join md:join-horizontal md:flex md:flex-wrap">
                            <button
                                v-for="tab in chartTabs"
                                :key="tab.value"
                                type="button"
                                class="btn btn-sm join-item"
                                :class="activeChartTab === tab.value ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                @click="activeChartTab = tab.value"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="dropdown md:hidden">
                            <button
                                type="button"
                                tabindex="0"
                                class="btn btn-sm bg-base-100 border-transparent"
                            >
                                Тип графика: {{ activeTabTitle }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                                </svg>
                            </button>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-30 mt-2 menu p-2 shadow bg-base-100 rounded-box w-56 max-w-[calc(100vw-1rem)] border border-base-300"
                            >
                                <li v-for="tab in chartTabs" :key="`mobile-chart-tab-${tab.value}`">
                                    <button
                                        type="button"
                                        :class="activeChartTab === tab.value ? 'menu-active' : ''"
                                        @click="activeChartTab = tab.value"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="flex flex-col gap-2 lg:items-end">
                            <div class="flex items-start gap-2">
                                <div class="dropdown" :class="{ 'dropdown-open': filterDropdownOpen }">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-square relative"
                                        :class="hasActiveAdvancedFilters ? 'btn-primary border-transparent' : 'bg-base-100 border-transparent text-base-content hover:bg-primary hover:border-primary hover:text-primary-content'"
                                        title="Фильтры"
                                        @click="toggleFilterDropdown"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        <span
                                            v-if="hasActiveAdvancedFilters"
                                            class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-success border border-base-100"
                                        ></span>
                                    </button>

                                    <div class="dropdown-content z-30 mt-2 w-[20rem] md:w-[24rem] max-w-[calc(100vw-1rem)] bg-base-100 border border-base-300 rounded-box shadow p-3">
                                        <div class="grid grid-cols-1 md:grid-cols-[8.5rem_1fr] gap-3">
                                            <div class="border border-base-300 rounded-md p-2 space-y-1">
                                                <button
                                                    v-for="filterType in filterTypes"
                                                    :key="filterType.key"
                                                    type="button"
                                                    class="btn btn-xs w-full justify-between"
                                                    :class="{ 'btn-active btn-primary': activeFilterType === filterType.key }"
                                                    @click="selectFilterType(filterType.key)"
                                                >
                                                    {{ filterType.label }}
                                                    <span
                                                        v-if="(selectedFilters[filterType.key] || []).length"
                                                        class="badge badge-secondary badge-xs ml-1 shrink-0"
                                                    >
                                                        {{ (selectedFilters[filterType.key] || []).length }}
                                                    </span>
                                                </button>
                                            </div>

                                            <div class="space-y-3">
                                                <input
                                                    v-model="searchQueries[activeFilterType]"
                                                    type="text"
                                                    class="input input-bordered input-sm w-full"
                                                    :placeholder="filterTypes.find(f => f.key === activeFilterType)?.placeholder"
                                                >

                                                <div class="max-h-64 overflow-y-auto border border-base-300 rounded-md p-2 space-y-1">
                                                    <div v-if="loadingOptions[activeFilterType]" class="text-sm text-base-content/60 py-2">
                                                        Загрузка...
                                                    </div>
                                                    <div
                                                        v-for="option in getDisplayedOptions(activeFilterType)"
                                                        :key="`${activeFilterType}-${option.value}`"
                                                        class="w-full"
                                                    >
                                                        <label class="flex w-full cursor-pointer items-start gap-3 px-2 py-1">
                                                            <input
                                                                type="checkbox"
                                                                class="checkbox checkbox-sm mt-0.5"
                                                                :checked="isOptionSelected(activeFilterType, option.value)"
                                                                @change="toggleFilterOption(activeFilterType, option, $event)"
                                                            >
                                                            <span class="flex flex-col min-w-0">
                                                                <span class="text-sm leading-4 break-words">{{ option.label }}</span>
                                                                <span
                                                                    v-if="option.subtitle"
                                                                    class="text-xs leading-4 text-base-content/50 break-words mt-0.5"
                                                                >
                                                                    {{ option.subtitle }}
                                                                </span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                    <div
                                                        v-if="!loadingOptions[activeFilterType] && getDisplayedOptions(activeFilterType).length === 0"
                                                        class="text-sm text-base-content/60 py-2"
                                                    >
                                                        Ничего не найдено
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-base-300 flex justify-end gap-2">
                                            <button type="button" class="btn btn-outline btn-sm" :disabled="processing" @click="resetAdvancedFilters">
                                                Сбросить
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-sm" @click.prevent.stop="closeFilterDropdown">
                                                Закрыть
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" :disabled="processing" @click="applyAdvancedFilters">
                                                Применить
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="hidden md:join md:join-horizontal md:flex md:flex-wrap">
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="selectedPeriodPreset === 'today' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="setPeriodPreset('today')"
                                    >
                                        Сегодня
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="selectedPeriodPreset === 'week' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="setPeriodPreset('week')"
                                    >
                                        Неделя
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="selectedPeriodPreset === 'month' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="setPeriodPreset('month')"
                                    >
                                        Месяц
                                    </button>
                                    <div class="dropdown" :class="{ 'dropdown-open': customPeriodDropdownOpen }">
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item"
                                            :class="selectedPeriodPreset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                                            @click="openCustomPeriodDropdown"
                                        >
                                            Свой период
                                        </button>
                                        <div class="dropdown-content z-30 mt-2 w-72 bg-base-100 border border-base-300 rounded-box shadow p-3 left-0 right-auto translate-x-0">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    v-model="selectedDateFrom"
                                                    type="date"
                                                    class="input input-bordered input-sm w-full"
                                                >
                                                <span class="text-sm text-base-content/60">—</span>
                                                <input
                                                    v-model="selectedDateTo"
                                                    type="date"
                                                    class="input input-bordered input-sm w-full"
                                                >
                                            </div>
                                            <div class="flex justify-end mt-3">
                                                <button type="button" class="btn btn-ghost btn-sm" @click="customPeriodDropdownOpen = false">
                                                    Закрыть
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="selectedPeriodPreset === 'all' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                        @click="setPeriodPreset('all')"
                                    >
                                        Все
                                    </button>
                                </div>

                                <div class="flex md:hidden items-start gap-2">
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            tabindex="0"
                                            class="btn btn-sm bg-base-100 border-transparent"
                                        >
                                            Период
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <ul
                                            tabindex="0"
                                            class="dropdown-content z-30 mt-2 menu p-2 shadow bg-base-100 rounded-box w-44 border border-base-300"
                                        >
                                            <li v-for="option in periodPresetOptions" :key="option.value">
                                                <button
                                                    type="button"
                                                    :class="selectedPeriodPreset === option.value ? 'menu-active' : ''"
                                                    @click="setPeriodPreset(option.value)"
                                                >
                                                    {{ option.label }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="dropdown" :class="{ 'dropdown-open': customPeriodDropdownOpen }">
                                        <button
                                            type="button"
                                            class="btn btn-sm"
                                            :class="selectedPeriodPreset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                                            @click="openCustomPeriodDropdown"
                                        >
                                            Свой период
                                        </button>
                                        <div class="dropdown-content z-30 mt-2 w-72 max-w-[calc(100vw-1rem)] bg-base-100 border border-base-300 rounded-box shadow p-3 left-1/2 -translate-x-1/2">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    v-model="selectedDateFrom"
                                                    type="date"
                                                    class="input input-bordered input-sm w-full"
                                                >
                                                <span class="text-sm text-base-content/60">—</span>
                                                <input
                                                    v-model="selectedDateTo"
                                                    type="date"
                                                    class="input input-bordered input-sm w-full"
                                                >
                                            </div>
                                            <div class="flex justify-end mt-3">
                                                <button type="button" class="btn btn-ghost btn-sm" @click="customPeriodDropdownOpen = false">
                                                    Закрыть
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow mt-4 pt-4 pb-7 px-6 pl-3">
                        <h2 class="text-base-content/70 text-lg pl-3">{{ activeTabTitle }}</h2>
                        <div ref="chart" class="h-50"></div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="text-sm text-base-content/70">Траст баланс</div>
                            <div class="text-xl font-bold mt-1">
                                {{ financeOverview.trustAmount }} {{ financeOverview.primaryCurrency }}
                            </div>
                            <div class="text-sm text-base-content/60 mt-1">
                                ≈ {{ financeOverview.trustAmountSecondary }} {{ financeOverview.secondaryCurrency }}
                            </div>
                            <div class="text-xs text-base-content/70 mt-2">
                                Резерв: {{ financeOverview.trustReserveAmount }} {{ financeOverview.primaryCurrency }}
                            </div>
                            <div class="text-xs text-base-content/70">
                                Вывод: {{ financeOverview.trustWithdrawalAmount }} {{ financeOverview.primaryCurrency }}
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="text-sm text-base-content/70">Холд в сделках</div>
                            <div class="text-xl font-bold mt-1">
                                {{ financeOverview.escrowOrdersAmount }} {{ financeOverview.primaryCurrency }}
                            </div>
                            <div class="text-sm text-base-content/60 mt-1">
                                ≈ {{ financeOverview.escrowOrdersAmountSecondary }} {{ financeOverview.secondaryCurrency }}
                            </div>
                            <div class="text-xs text-base-content/70 mt-2">
                                Сделок: {{ financeOverview.escrowOrdersCount }}
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow px-5 py-3.5">
                            <div class="text-sm text-base-content/70">Спорные сделки</div>
                            <div class="text-xl font-bold mt-1">
                                {{ financeOverview.escrowDisputesAmount }} {{ financeOverview.primaryCurrency }}
                            </div>
                            <div class="text-sm text-base-content/60 mt-1">
                                ≈ {{ financeOverview.escrowDisputesAmountSecondary }} {{ financeOverview.secondaryCurrency }}
                            </div>
                            <div class="text-xs text-base-content/70 mt-2">
                                Споров: {{ financeOverview.escrowDisputesCount }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
