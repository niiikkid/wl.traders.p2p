<script setup>
import {Head, router} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApexCharts from 'apexcharts';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';

defineOptions({layout: AuthenticatedLayout});

const props = defineProps({
    statistics: {
        type: Object,
        default: () => ({}),
    },
    incomeChart: {
        type: Object,
        default: () => ({labels: [], data: []}),
    },
    turnoverChart: {
        type: Object,
        default: () => ({labels: [], data: []}),
    },
    conversionChart: {
        type: Object,
        default: () => ({labels: [], data: []}),
    },
    ordersChart: {
        type: Object,
        default: () => ({labels: [], data: []}),
    },
    averageCheckChart: {
        type: Object,
        default: () => ({labels: [], data: []}),
    },
    selectedPeriodPreset: {
        type: String,
        default: 'month',
    },
    selectedDateFrom: {
        type: String,
        default: '',
    },
    selectedDateTo: {
        type: String,
        default: '',
    },
});

const processing = ref(false);
const isMobile = ref(false);
const activeChartTab = ref('income');
const selectedPeriodPreset = ref(props.selectedPeriodPreset || 'month');
const selectedDateFrom = ref(props.selectedDateFrom || '');
const selectedDateTo = ref(props.selectedDateTo || '');
const selectedPeriodCursor = ref('');
const periodCursors = ref({
    today: '',
    week: '',
    month: '',
});
const customPeriodDropdownOpen = ref(false);
const mobileChartDropdownOpen = ref(false);
const mobilePeriodDropdownOpen = ref(false);
const chart = ref(null);
const apexChart = ref(null);
const mobileChartDropdownRef = ref(null);
const mobilePeriodDropdownRef = ref(null);
const desktopCustomPeriodDropdownRef = ref(null);
const mobileCustomPeriodDropdownRef = ref(null);

const chartTabs = [
    {value: 'income', label: 'Доход', colorToken: 'primary', seriesName: 'Доход ($)'},
    {value: 'turnover', label: 'Оборот', colorToken: 'secondary', seriesName: 'Оборот ($)'},
    {value: 'conversion', label: 'Конверсия', colorToken: 'success', seriesName: 'Конверсия (%)'},
    {value: 'orders', label: 'Количество сделок', colorToken: 'accent', seriesName: 'Сделок'},
    {value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)'},
];

const periodPresetOptions = [
    {value: 'today', label: 'Сегодня'},
    {value: 'week', label: 'Неделя'},
    {value: 'month', label: 'Месяц'},
    {value: 'all', label: 'Все'},
];

const russianMonthFormatter = new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    year: 'numeric',
});
const russianShortDateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'short',
});

const parseIsoDate = (value) => {
    if (!value) {
        return null;
    }
    const [year, month, day] = String(value).split('-').map((item) => Number(item));
    if (!year || !month || !day) {
        return null;
    }
    const parsedDate = new Date(year, month - 1, day);
    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
};

const formatDateToIso = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const getMonthStart = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
const getMonthEnd = (date) => new Date(date.getFullYear(), date.getMonth() + 1, 0);
const getDayStart = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());
const getWeekDayNumber = (date) => {
    const dayNumber = date.getDay();
    return dayNumber === 0 ? 7 : dayNumber;
};
const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
const getWeekStart = (date) => addDays(getDayStart(date), 1 - getWeekDayNumber(date));
const getWeekEnd = (date) => addDays(getWeekStart(date), 6);
const addMonths = (date, months) => new Date(date.getFullYear(), date.getMonth() + months, 1);

const normalizeRuShortDate = (date) => russianShortDateFormatter.format(date).replace('.', '');
const canNavigateByPeriod = computed(() => ['today', 'week', 'month'].includes(selectedPeriodPreset.value));

const getDefaultCursorByPreset = (preset) => {
    const now = new Date();
    if (preset === 'month') {
        return formatDateToIso(getMonthStart(now));
    }
    if (preset === 'week') {
        return formatDateToIso(getWeekStart(now));
    }
    return formatDateToIso(getDayStart(now));
};

const setPresetCursor = (preset, cursor) => {
    if (!['today', 'week', 'month'].includes(preset) || !cursor) {
        return;
    }
    periodCursors.value[preset] = cursor;
    if (selectedPeriodPreset.value === preset) {
        selectedPeriodCursor.value = cursor;
    }
};

const initializePeriodCursors = () => {
    periodCursors.value.today = getDefaultCursorByPreset('today');
    periodCursors.value.week = getDefaultCursorByPreset('week');
    periodCursors.value.month = getDefaultCursorByPreset('month');
};

const resolvePeriodAnchorDate = (cursor = null, preset = selectedPeriodPreset.value) => {
    const cursorDate = parseIsoDate(cursor);
    if (cursorDate) {
        if (preset === 'month') {
            return getMonthStart(cursorDate);
        }
        if (preset === 'week') {
            return getWeekStart(cursorDate);
        }
        return getDayStart(cursorDate);
    }

    const dateFrom = parseIsoDate(selectedDateFrom.value);
    if (dateFrom) {
        if (preset === 'month') {
            return getMonthStart(dateFrom);
        }
        if (preset === 'week') {
            return getWeekStart(dateFrom);
        }
        return getDayStart(dateFrom);
    }

    const dateTo = parseIsoDate(selectedDateTo.value);
    if (dateTo) {
        if (preset === 'month') {
            return getMonthStart(dateTo);
        }
        if (preset === 'week') {
            return getWeekStart(dateTo);
        }
        return getDayStart(dateTo);
    }

    if (preset === 'month') {
        return getMonthStart(new Date());
    }
    if (preset === 'week') {
        return getWeekStart(new Date());
    }
    return getDayStart(new Date());
};

const selectedPeriodLabel = computed(() => {
    if (!canNavigateByPeriod.value) {
        return '';
    }

    if (selectedPeriodPreset.value === 'month') {
        const monthDate = resolvePeriodAnchorDate(selectedPeriodCursor.value, 'month');
        const label = russianMonthFormatter.format(monthDate);
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    if (selectedPeriodPreset.value === 'today') {
        return normalizeRuShortDate(resolvePeriodAnchorDate(selectedPeriodCursor.value, 'today'));
    }

    const weekStartDate = resolvePeriodAnchorDate(selectedPeriodCursor.value, 'week');
    const weekEndDate = getWeekEnd(weekStartDate);
    return `${normalizeRuShortDate(weekStartDate)} — ${normalizeRuShortDate(weekEndDate)}`;
});

const updateIsMobile = () => {
    if (typeof window === 'undefined') {
        return;
    }

    isMobile.value = window.innerWidth < 640;
};

const getLastPoints = (source, limit = 10) => {
    if (!source || !Array.isArray(source.data) || !Array.isArray(source.labels)) {
        return {data: [], labels: []};
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
        return {labels: [], data: []};
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

const incomeChartData = computed(() => props.incomeChart || {labels: [], data: []});
const conversionChartData = computed(() => props.conversionChart || {labels: [], data: []});
const turnoverChartData = computed(() => props.turnoverChart || {labels: [], data: []});
const ordersChartData = computed(() => props.ordersChart || {labels: [], data: []});
const averageCheckChartData = computed(() => props.averageCheckChart || {labels: [], data: []});

const chartDataByTab = computed(() => ({
    income: getLastPoints(normalizeChartLabels(incomeChartData.value, selectedPeriodPreset.value)),
    conversion: getLastPoints(normalizeChartLabels(conversionChartData.value, selectedPeriodPreset.value)),
    turnover: getLastPoints(normalizeChartLabels(turnoverChartData.value, selectedPeriodPreset.value)),
    orders: getLastPoints(normalizeChartLabels(ordersChartData.value, selectedPeriodPreset.value)),
    average_check: getLastPoints(normalizeChartLabels(averageCheckChartData.value, selectedPeriodPreset.value)),
}));

const activeTabConfig = computed(() => {
    return chartTabs.find((tab) => tab.value === activeChartTab.value) || chartTabs[0];
});

const activeTabTitle = computed(() => activeTabConfig.value.label);
const activeData = computed(() => chartDataByTab.value[activeChartTab.value] || {labels: [], data: []});

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
        return {min: 0, max: 100};
    }

    return {};
};

const renderChart = () => {
    if (!chart.value) {
        return;
    }

    const formatter = getYFormatter(activeChartTab.value);
    const {min, max} = getYRange(activeChartTab.value);
    const color = getThemeColor(activeTabConfig.value.colorToken);
    const currentData = activeData.value;

    if (!apexChart.value) {
        apexChart.value = new ApexCharts(chart.value, {
            chart: {
                type: 'line',
                height: '95%',
                background: 'transparent',
                toolbar: {
                    show: false,
                },
            },
            series: [],
            xaxis: {
                categories: [],
            },
            yaxis: {},
            stroke: {
                width: 2,
                curve: 'smooth',
            },
            grid: {
                borderColor: 'rgba(200, 200, 200, 0.1)',
            },
            markers: {
                size: 4,
                strokeColors: '#fff',
                strokeWidth: 2,
            },
            tooltip: {
                theme: 'dark',
            },
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
            labels: {
                style: {
                    colors: '#999',
                },
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        yaxis: {
            min,
            max,
            labels: {
                style: {
                    colors: '#999',
                },
                formatter,
            },
        },
        colors: [color],
        markers: {
            colors: [color],
        },
        tooltip: {
            theme: 'dark',
            y: {
                formatter,
            },
        },
    }, false, false);
};

watch(activeChartTab, () => {
    renderChart();
});

watch(activeData, () => {
    renderChart();
}, {deep: true});

const applyFilter = (options = {}) => {
    if (processing.value) {
        return;
    }

    processing.value = true;
    const requestData = {
        period: selectedPeriodPreset.value,
    };

    if (selectedPeriodPreset.value === 'month') {
        const monthAnchorDate = resolvePeriodAnchorDate(options.periodCursor || selectedPeriodCursor.value, 'month');
        const startDate = getMonthStart(monthAnchorDate);
        const endDate = getMonthEnd(monthAnchorDate);
        const nextCursor = formatDateToIso(startDate);
        setPresetCursor('month', nextCursor);
        selectedDateFrom.value = formatDateToIso(startDate);
        selectedDateTo.value = formatDateToIso(endDate);
        requestData.date_from = selectedDateFrom.value;
        requestData.date_to = selectedDateTo.value;
    } else if (selectedPeriodPreset.value === 'today') {
        const dayAnchorDate = resolvePeriodAnchorDate(options.periodCursor || selectedPeriodCursor.value, 'today');
        const startDate = getDayStart(dayAnchorDate);
        const endDate = getDayStart(dayAnchorDate);
        const nextCursor = formatDateToIso(dayAnchorDate);
        setPresetCursor('today', nextCursor);
        selectedDateFrom.value = formatDateToIso(startDate);
        selectedDateTo.value = formatDateToIso(endDate);
        requestData.date_from = selectedDateFrom.value;
        requestData.date_to = selectedDateTo.value;
    } else if (selectedPeriodPreset.value === 'week') {
        const weekStartDate = resolvePeriodAnchorDate(options.periodCursor || selectedPeriodCursor.value, 'week');
        const weekEndDate = getWeekEnd(weekStartDate);
        const nextCursor = formatDateToIso(weekStartDate);
        setPresetCursor('week', nextCursor);
        selectedDateFrom.value = formatDateToIso(weekStartDate);
        selectedDateTo.value = formatDateToIso(weekEndDate);
        requestData.date_from = selectedDateFrom.value;
        requestData.date_to = selectedDateTo.value;
    } else if (selectedPeriodPreset.value === 'custom' && selectedDateFrom.value && selectedDateTo.value) {
        requestData.date_from = selectedDateFrom.value;
        requestData.date_to = selectedDateTo.value;
    }

    router.visit(route('provider-liquidity.main.index'), {
        data: requestData,
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};

const blurActiveElement = () => {
    if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
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
    if (['today', 'week', 'month'].includes(preset)) {
        const currentCursor = getDefaultCursorByPreset(preset);
        setPresetCursor(preset, currentCursor);
    }
    if (preset !== 'custom') {
        applyFilter();
        return;
    }
    if (selectedDateFrom.value && selectedDateTo.value) {
        applyFilter();
    }
};

const navigatePeriod = (step) => {
    if (!canNavigateByPeriod.value) {
        return;
    }
    const currentAnchorDate = resolvePeriodAnchorDate(selectedPeriodCursor.value, selectedPeriodPreset.value);
    const nextAnchorDate = selectedPeriodPreset.value === 'month'
        ? getMonthStart(addMonths(currentAnchorDate, step))
        : selectedPeriodPreset.value === 'week'
            ? getWeekStart(addDays(currentAnchorDate, step * 7))
            : getDayStart(addDays(currentAnchorDate, step));
    const nextPeriodCursor = formatDateToIso(nextAnchorDate);
    setPresetCursor(selectedPeriodPreset.value, nextPeriodCursor);
    applyFilter({periodCursor: nextPeriodCursor});
};

const openCustomPeriodDropdown = () => {
    selectedPeriodPreset.value = 'custom';
    const nextState = !customPeriodDropdownOpen.value;
    customPeriodDropdownOpen.value = nextState;
    if (customPeriodDropdownOpen.value) {
        mobileChartDropdownOpen.value = false;
        mobilePeriodDropdownOpen.value = false;
    } else {
        blurActiveElement();
    }
};

const toggleMobileChartDropdown = () => {
    const nextState = !mobileChartDropdownOpen.value;
    mobileChartDropdownOpen.value = nextState;
    if (nextState) {
        mobilePeriodDropdownOpen.value = false;
        customPeriodDropdownOpen.value = false;
    } else {
        blurActiveElement();
    }
};

const toggleMobilePeriodDropdown = () => {
    const nextState = !mobilePeriodDropdownOpen.value;
    mobilePeriodDropdownOpen.value = nextState;
    if (nextState) {
        mobileChartDropdownOpen.value = false;
        customPeriodDropdownOpen.value = false;
    } else {
        blurActiveElement();
    }
};

const handleMobileDropdownOutsideClick = (event) => {
    if (
        !mobileChartDropdownOpen.value
        && !mobilePeriodDropdownOpen.value
        && !customPeriodDropdownOpen.value
    ) {
        return;
    }

    const clickTarget = event.target;
    if (!(clickTarget instanceof Node)) {
        return;
    }

    const clickedInsideChart = mobileChartDropdownRef.value instanceof HTMLElement
        && mobileChartDropdownRef.value.contains(clickTarget);
    const clickedInsidePeriod = mobilePeriodDropdownRef.value instanceof HTMLElement
        && mobilePeriodDropdownRef.value.contains(clickTarget);
    const clickedInsideDesktopCustomPeriod = desktopCustomPeriodDropdownRef.value instanceof HTMLElement
        && desktopCustomPeriodDropdownRef.value.contains(clickTarget);
    const clickedInsideMobileCustomPeriod = mobileCustomPeriodDropdownRef.value instanceof HTMLElement
        && mobileCustomPeriodDropdownRef.value.contains(clickTarget);

    let hasClosedDropdown = false;

    if (!clickedInsideChart && mobileChartDropdownOpen.value) {
        mobileChartDropdownOpen.value = false;
        hasClosedDropdown = true;
    }
    if (!clickedInsidePeriod && mobilePeriodDropdownOpen.value) {
        mobilePeriodDropdownOpen.value = false;
        hasClosedDropdown = true;
    }
    if (!clickedInsideDesktopCustomPeriod && !clickedInsideMobileCustomPeriod && customPeriodDropdownOpen.value) {
        customPeriodDropdownOpen.value = false;
        hasClosedDropdown = true;
    }

    if (hasClosedDropdown) {
        blurActiveElement();
    }
};

const statisticsFormated = computed(() => ({
    totalTurnover: props.statistics?.totalTurnover ?? '0.00',
    totalProfit: props.statistics?.totalProfit ?? '0.00',
    conversionRate: props.statistics?.conversionRate ?? '0%',
    successOrderCount: props.statistics?.successOrderCount ?? 0,
}));

watch([selectedDateFrom, selectedDateTo], () => {
    if (
        selectedPeriodPreset.value === 'custom'
        && selectedDateFrom.value
        && selectedDateTo.value
    ) {
        applyFilter();
    }
});

watch(() => props.selectedPeriodPreset, (newValue) => {
    selectedPeriodPreset.value = newValue || 'month';
    if (['today', 'week', 'month'].includes(newValue || 'month')) {
        const nextCursor = formatDateToIso(resolvePeriodAnchorDate(
            props.selectedDateFrom,
            newValue || 'month',
        ));
        setPresetCursor(newValue || 'month', nextCursor);
    }
    if ((newValue || 'month') !== 'custom') {
        customPeriodDropdownOpen.value = false;
    }
});

watch(() => props.selectedDateFrom, (newValue) => {
    selectedDateFrom.value = newValue || '';
    if (['today', 'week', 'month'].includes(selectedPeriodPreset.value)) {
        const nextCursor = formatDateToIso(resolvePeriodAnchorDate(newValue, selectedPeriodPreset.value));
        setPresetCursor(selectedPeriodPreset.value, nextCursor);
    }
});

watch(() => props.selectedDateTo, (newValue) => {
    selectedDateTo.value = newValue || '';
});

let themeObserver = null;
let scheduledThemeUpdate = false;

onMounted(() => {
    initializePeriodCursors();
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
    if (['today', 'week', 'month'].includes(selectedPeriodPreset.value)) {
        const nextCursor = formatDateToIso(resolvePeriodAnchorDate(
            selectedDateFrom.value,
            selectedPeriodPreset.value,
        ));
        setPresetCursor(selectedPeriodPreset.value, nextCursor);
    }
    renderChart();
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
    document.addEventListener('pointerdown', handleMobileDropdownOutsideClick, true);
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
    if (typeof document !== 'undefined') {
        document.removeEventListener('pointerdown', handleMobileDropdownOutsideClick, true);
    }
});
</script>

<template>
    <div>
        <Head title="Панель управления" />

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Панель управления</h2>
                <slot name="button"></slot>
            </div>

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
                                <p class="text-base-content/70 text-xs">Конверсия</p>
                                <p class="text-lg font-semibold text-base-content truncate">{{ statisticsFormated.conversionRate }}</p>
                            </div>
                            <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow px-5 py-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-base-content/70 text-xs">Успешные сделки</p>
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

                    <div ref="mobileChartDropdownRef" class="dropdown md:hidden" :class="{ 'dropdown-open': mobileChartDropdownOpen }">
                        <button
                            type="button"
                            class="btn btn-sm bg-base-100 border-transparent"
                            @click.stop="toggleMobileChartDropdown"
                        >
                            Тип графика: {{ activeTabTitle }}
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                            </svg>
                        </button>
                        <ul
                            class="dropdown-content z-30 mt-2 menu p-2 shadow bg-base-100 rounded-box w-56 max-w-[calc(100vw-1rem)] border border-base-300"
                        >
                            <li v-for="tab in chartTabs" :key="`mobile-chart-tab-${tab.value}`">
                                <button
                                    type="button"
                                    :class="activeChartTab === tab.value ? 'menu-active' : ''"
                                    @click="activeChartTab = tab.value; mobileChartDropdownOpen = false"
                                >
                                    {{ tab.label }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-2 lg:items-end">
                        <div class="flex items-start gap-2">
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
                                <div ref="desktopCustomPeriodDropdownRef" class="dropdown dropdown-end" :class="{ 'dropdown-open': customPeriodDropdownOpen }">
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item"
                                        :class="selectedPeriodPreset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                                        @click.stop="openCustomPeriodDropdown"
                                    >
                                        Свой период
                                    </button>
                                    <div class="dropdown-content z-30 mt-2 w-72 bg-base-100 border border-base-300 rounded-box shadow p-3 right-0 left-auto">
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
                                <div ref="mobilePeriodDropdownRef" class="dropdown" :class="{ 'dropdown-open': mobilePeriodDropdownOpen }">
                                    <button
                                        type="button"
                                        class="btn btn-sm bg-base-100 border-transparent"
                                        @click.stop="toggleMobilePeriodDropdown"
                                    >
                                        Период
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <ul
                                        class="dropdown-content z-30 mt-2 menu p-2 shadow bg-base-100 rounded-box w-44 border border-base-300"
                                    >
                                        <li v-for="option in periodPresetOptions" :key="option.value">
                                            <button
                                                type="button"
                                                :class="selectedPeriodPreset === option.value ? 'menu-active' : ''"
                                                @click="setPeriodPreset(option.value); mobilePeriodDropdownOpen = false"
                                            >
                                                {{ option.label }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>

                                <div ref="mobileCustomPeriodDropdownRef" class="dropdown" :class="{ 'dropdown-open': customPeriodDropdownOpen }">
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="selectedPeriodPreset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                                        @click.stop="openCustomPeriodDropdown"
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

                <div v-if="canNavigateByPeriod" class="mt-4 sm:hidden">
                    <div class="join join-horizontal items-center w-full">
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost join-item"
                            :disabled="processing"
                            @click="navigatePeriod(-1)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                            </svg>
                        </button>
                        <span class="join-item flex-1 px-3 text-sm font-medium text-base-content text-center">
                            {{ selectedPeriodLabel }}
                        </span>
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost join-item"
                            :disabled="processing"
                            @click="navigatePeriod(1)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="card bg-base-100 shadow mt-4 pt-4 pb-7 px-6 pl-3">
                    <div class="flex items-center justify-between gap-3 pl-3">
                        <h2 class="text-base-content/70 text-lg">{{ activeTabTitle }}</h2>
                        <div v-if="canNavigateByPeriod" class="hidden sm:join sm:join-horizontal sm:items-center sm:flex">
                            <button
                                type="button"
                                class="btn btn-sm btn-ghost join-item"
                                :disabled="processing"
                                @click="navigatePeriod(-1)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                                </svg>
                            </button>
                            <span class="join-item px-3 text-sm font-medium text-base-content min-w-36 text-center">
                                {{ selectedPeriodLabel }}
                            </span>
                            <button
                                type="button"
                                class="btn btn-sm btn-ghost join-item"
                                :disabled="processing"
                                @click="navigatePeriod(1)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div ref="chart" class="h-50"></div>
                </div>
            </section>
        </div>
    </div>
</template>
