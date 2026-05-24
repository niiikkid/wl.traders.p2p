<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import TraderSearchSelect from '@/Pages/Admin/TraderAnalytics/Components/TraderSearchSelect.vue';
import ApexCharts from 'apexcharts';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

/** MainTableSection renders the body slot only when items.length > 0. */
const mainSectionBodyPlaceholder = [1];

const page = usePage();

const chartData = computed(() => page.props.chart ?? {
    labels: [],
    series: [],
    colors: [],
    volumes: [],
    ids: [],
});
const dealAmountDistribution = computed(() => page.props.dealAmountDistribution ?? { buckets: [], total_deals: 0 });
const dealAmountDistributionByDetail = computed(() => page.props.dealAmountDistributionByDetail ?? {});
const meta = computed(() => page.props.meta ?? {});
const filters = computed(() => page.props.filters ?? {});
const periodOptions = computed(() => page.props.periodOptions ?? []);
const barsLimitPresets = computed(() => page.props.barsLimitPresets ?? []);
const bankOptions = computed(() => page.props.bankOptions ?? []);
const volumePresets = computed(() => page.props.volumePresets ?? []);
const defaultBarsLimit = computed(() => page.props.defaultBarsLimit ?? '100');
const hasVolumePresets = computed(() => volumePresets.value.length > 0);
const isAdmin = computed(() => Boolean(page.props.isAdmin));
const traderSearchRoute = computed(() => page.props.traderSearchRoute);
const backRoute = computed(() => page.props.backRoute);

const presetBarsLimitValues = computed(() => barsLimitPresets.value.map((option) => option.value));

const parseTruthyFilter = (value) => (
    value === true
    || value === 1
    || value === '1'
    || value === 'true'
    || value === 'on'
);

const syncBarsLimitFields = (barsLimitValue) => {
    const value = String(barsLimitValue ?? defaultBarsLimit.value);

    barsLimitPreset.value = presetBarsLimitValues.value.includes(value)
        ? value
        : defaultBarsLimit.value;
};

const selectedPeriod = ref(filters.value.period ?? 'all');
const dateFrom = ref(filters.value.date_from ?? '');
const dateTo = ref(filters.value.date_to ?? '');
const selectedTraderId = ref(filters.value.trader_id ? String(filters.value.trader_id) : '');
const barsLimitPreset = ref(defaultBarsLimit.value);
const includeArchived = ref(parseTruthyFilter(filters.value.include_archived));
const selectedPaymentGatewayId = ref(
    filters.value.payment_gateway_id ? String(filters.value.payment_gateway_id) : '',
);
const volumeFromPreset = ref(filters.value.volume_from ? String(filters.value.volume_from) : '');
const volumeToPreset = ref(filters.value.volume_to ? String(filters.value.volume_to) : '');
const viewMode = ref(filters.value.view_mode === 'chart' ? 'chart' : 'table');
const currentPage = ref(Number(filters.value.page) > 0 ? Number(filters.value.page) : 1);

syncBarsLimitFields(filters.value.bars_limit);

const chartRef = ref(null);
const pieChartRefChart = ref(null);
const pieChartRefTable = ref(null);

const activePieChartElement = () => (
    isTableView.value ? pieChartRefTable.value : pieChartRefChart.value
);
const apexChart = ref(null);
const pieApexChart = ref(null);
const chartContainerHeight = ref(400);
const processing = ref(false);
const selectedPaymentDetailId = ref('');

const getThemeColor = (cssVariable, fallback) => {
    if (typeof document === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement).getPropertyValue(cssVariable).trim();

    return value || fallback;
};

/** Градиент как у столбчатого графика: мелкие чеки — success, крупные — error. */
const buildPieChartColors = (count) => {
    if (count <= 0) {
        return [];
    }

    return Array.from({ length: count }, (_item, index) => {
        const ratio = count <= 1 ? 0.5 : index / (count - 1);
        const hue = Math.round(120 * (1 - ratio));

        return `hsl(${hue}, 70%, 45%)`;
    });
};

/**
 * @param {Array<{label: string, percent?: number}>} buckets
 */
const createPieSliceDataLabelFormatter = (buckets) => (percent, opts) => {
    const seriesIndex = opts?.seriesIndex ?? 0;
    const bucket = buckets[seriesIndex];

    if (!bucket || percent <= 0) {
        return '';
    }

    const rangeLabel = bucket.label;
    const percentLabel = `${Math.round(percent)}%`;
    const sliceAngle = percent * 3.6;
    const minAngleForLabel = rangeLabel.length > 14
        ? 30
        : rangeLabel.length > 11
            ? 24
            : 20;

    if (sliceAngle < minAngleForLabel || percent < 7) {
        return '';
    }

    return [rangeLabel, percentLabel];
};

const activeDealAmountDistribution = computed(() => {
    if (!selectedPaymentDetailId.value) {
        return dealAmountDistribution.value;
    }

    const detailId = selectedPaymentDetailId.value;

    return dealAmountDistributionByDetail.value[detailId]
        ?? dealAmountDistributionByDetail.value[Number(detailId)]
        ?? { buckets: [], total_deals: 0 };
});

const selectedPaymentDetailLabel = computed(() => {
    if (!selectedPaymentDetailId.value) {
        return null;
    }

    const index = (chartData.value.ids ?? []).findIndex(
        (id) => String(id) === selectedPaymentDetailId.value,
    );

    if (index < 0) {
        return null;
    }

    return chartData.value.labels?.[index] ?? null;
});

const hasPieChartData = computed(() => (activeDealAmountDistribution.value.buckets ?? []).length > 0);

const pieChartColorsList = computed(() => buildPieChartColors(
    (activeDealAmountDistribution.value.buckets ?? []).length,
));

const pieChartColorForIndex = (index) => pieChartColorsList.value[index] ?? 'hsl(120, 70%, 45%)';

const volumeStatisticsRoute = computed(() => (
    isAdmin.value
        ? route('admin.payment-details.volume-statistics')
        : route('payment-details.volume-statistics')
));

const buildRequestData = ({ page = currentPage.value } = {}) => {
    const data = {
        period: selectedPeriod.value,
        bars_limit: barsLimitPreset.value,
        page,
        view_mode: viewMode.value,
    };

    if (dateFrom.value) {
        data.date_from = dateFrom.value;
    }

    if (dateTo.value) {
        data.date_to = dateTo.value;
    }

    if (isAdmin.value && selectedTraderId.value) {
        data.trader_id = selectedTraderId.value;
    }

    data.include_archived = includeArchived.value ? 1 : 0;

    if (selectedPaymentGatewayId.value) {
        data.payment_gateway_id = selectedPaymentGatewayId.value;
    }

    if (volumeFromPreset.value) {
        data.volume_from = volumeFromPreset.value;
    }

    if (volumeToPreset.value) {
        data.volume_to = volumeToPreset.value;
    }

    return data;
};

const barsLimitSummary = computed(() => meta.value.bars_limit ?? defaultBarsLimit.value);
const paginationMeta = computed(() => ({
    currentPage: Number(meta.value.current_page ?? currentPage.value ?? 1),
    lastPage: Number(meta.value.last_page ?? 1),
    perPage: Number(meta.value.per_page ?? barsLimitSummary.value ?? defaultBarsLimit.value),
    totalItems: Number(meta.value.positive_volume_count ?? 0),
}));
const showPagination = computed(() => paginationMeta.value.lastPage > 1);
const isTableView = computed(() => viewMode.value === 'table');
const isChartView = computed(() => viewMode.value === 'chart');
const barsLimitLabel = computed(() => (
    isTableView.value ? 'Реквизитов на странице' : 'Реквизитов на графике'
));
const onViewCountLabel = computed(() => (
    isTableView.value ? 'На странице' : 'На графике'
));

const tableRows = computed(() => {
    const ids = chartData.value.ids ?? [];

    return ids.map((id, index) => {
        const detailId = String(id);
        const distribution = dealAmountDistributionByDetail.value[detailId]
            ?? dealAmountDistributionByDetail.value[Number(id)]
            ?? null;

        return {
            id: detailId,
            label: chartData.value.labels?.[index] ?? '',
            volume: chartData.value.volumes?.[index] ?? '',
            dealsCount: distribution?.total_deals ?? 0,
            color: chartData.value.colors?.[index] ?? '',
        };
    });
});

watch(selectedTraderId, (newTraderId) => {
    if (!isAdmin.value) {
        return;
    }

    const filterTraderId = filters.value.trader_id ? String(filters.value.trader_id) : '';

    if (newTraderId === filterTraderId) {
        return;
    }

    applyFilters();
});

const clearSelectedPaymentDetail = ({ syncVisual = true } = {}) => {
    if (syncVisual && apexChart.value && selectedPaymentDetailId.value) {
        const index = (chartData.value.ids ?? []).findIndex(
            (id) => String(id) === selectedPaymentDetailId.value,
        );

        if (index >= 0 && typeof apexChart.value.toggleDataPointSelection === 'function') {
            const selectedPoints = apexChart.value.w?.globals?.selectedDataPoints?.[0] ?? [];

            if (selectedPoints.includes(index)) {
                apexChart.value.toggleDataPointSelection(0, index);
            }
        }
    }

    selectedPaymentDetailId.value = '';
};

const applyFilters = ({ resetPage = true } = {}) => {
    if (resetPage) {
        currentPage.value = 1;
    }

    clearSelectedPaymentDetail({ syncVisual: false });
    processing.value = true;

    router.get(volumeStatisticsRoute.value, buildRequestData(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};

const goToPage = (page) => {
    const nextPage = Number(page);

    if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage > paginationMeta.value.lastPage) {
        return;
    }

    currentPage.value = nextPage;
    clearSelectedPaymentDetail({ syncVisual: false });
    processing.value = true;

    router.get(volumeStatisticsRoute.value, buildRequestData({ page: nextPage }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};

const setViewMode = (mode) => {
    if (viewMode.value === mode) {
        return;
    }

    viewMode.value = mode;
    applyFilters({ resetPage: false });
};

const selectPaymentDetailFromTable = (paymentDetailId) => {
    if (selectedPaymentDetailId.value === String(paymentDetailId)) {
        clearSelectedPaymentDetail({ syncVisual: isChartView.value });

        return;
    }

    selectedPaymentDetailId.value = String(paymentDetailId);

    if (isChartView.value) {
        nextTick(() => syncBarChartSelection());
    }
};

const resetFilters = () => {
    clearSelectedPaymentDetail({ syncVisual: false });
    currentPage.value = 1;
    viewMode.value = 'table';
    processing.value = true;

    router.get(volumeStatisticsRoute.value, {
        period: 'all',
        bars_limit: defaultBarsLimit.value,
        page: 1,
        view_mode: 'table',
        include_archived: 0,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};

const selectPeriod = (period) => {
    selectedPeriod.value = period;
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const hasCustomPeriod = computed(() => Boolean(dateFrom.value || dateTo.value));

const hasChartData = computed(() => chartData.value.labels?.length > 0);
const showVolumeStatsFooter = computed(() => (
    hasChartData.value || Number(meta.value.positive_volume_count ?? 0) > 0
));

const formatInteger = (value) => Number(value ?? 0).toLocaleString('ru-RU');

const formatYAxisValue = (value) => {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '';
    }

    if (num >= 1_000_000) {
        const scaled = num / 1_000_000;

        return `${Number.isInteger(scaled) ? scaled : scaled.toFixed(1)}M`;
    }

    if (num >= 1000) {
        const scaled = num / 1000;

        return `${Number.isInteger(scaled) ? scaled : scaled.toFixed(1)}k`;
    }

    if (num >= 100) {
        return String(Math.round(num));
    }

    if (num >= 10) {
        return String(Math.round(num));
    }

    if (num >= 1) {
        return num.toFixed(1);
    }

    return num.toFixed(2);
};

const destroyChart = () => {
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
};

const destroyPieChart = () => {
    if (pieApexChart.value) {
        pieApexChart.value.destroy();
        pieApexChart.value = null;
    }
};

const handleBarDataPointSelection = (_event, _chartContext, config) => {
    const index = config?.dataPointIndex;

    if (index === undefined || index < 0) {
        return;
    }

    const paymentDetailId = chartData.value.ids?.[index];

    if (paymentDetailId === undefined) {
        return;
    }

    if (selectedPaymentDetailId.value === String(paymentDetailId)) {
        clearSelectedPaymentDetail({ syncVisual: false });

        return;
    }

    selectedPaymentDetailId.value = String(paymentDetailId);
};

const syncBarChartSelection = () => {
    if (!apexChart.value || !selectedPaymentDetailId.value) {
        return;
    }

    const index = (chartData.value.ids ?? []).findIndex(
        (id) => String(id) === selectedPaymentDetailId.value,
    );

    if (index < 0) {
        selectedPaymentDetailId.value = '';

        return;
    }

    apexChart.value.toggleDataPointSelection(0, index);
};

const mountPieChart = () => {
    const pieChartElement = activePieChartElement();

    if (!pieChartElement) {
        return;
    }

    const buckets = activeDealAmountDistribution.value.buckets ?? [];
    const labels = buckets.map((bucket) => bucket.label);
    const series = buckets.map((bucket) => bucket.count);
    const totalDeals = activeDealAmountDistribution.value.total_deals ?? 0;
    const sliceColors = buildPieChartColors(buckets.length);
    const baseContentMuted = getThemeColor('--color-base-content', '#a3a3a3');
    const baseSurface = getThemeColor('--color-base-100', '#1f2937');

    const options = {
        chart: {
            type: 'donut',
            height: 340,
            background: 'transparent',
            fontFamily: 'inherit',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 450,
            },
        },
        labels,
        series,
        colors: sliceColors,
        stroke: {
            width: 3,
            colors: [baseSurface],
        },
        plotOptions: {
            pie: {
                expandOnClick: true,
                dataLabels: {
                    minAngleToShowLabel: 20,
                },
                donut: {
                    size: '56%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '13px',
                            color: baseContentMuted,
                            offsetY: 20,
                        },
                        value: {
                            show: true,
                            fontSize: '22px',
                            fontWeight: 600,
                            color: getThemeColor('--color-base-content', '#e5e5e5'),
                            offsetY: -12,
                            formatter: (value) => formatInteger(value),
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Сделок',
                            fontSize: '12px',
                            color: baseContentMuted,
                            formatter: () => formatInteger(totalDeals),
                        },
                    },
                },
            },
        },
        dataLabels: {
            enabled: true,
            dropShadow: {
                enabled: false,
            },
            style: {
                fontSize: '10px',
                fontWeight: 600,
                colors: ['#ffffff'],
            },
            formatter: createPieSliceDataLabelFormatter(buckets),
        },
        legend: {
            show: false,
        },
        tooltip: {
            theme: 'dark',
            fillSeriesColor: true,
            y: {
                formatter: (value, opts) => {
                    const bucket = buckets[opts?.seriesIndex ?? 0];
                    const percent = bucket?.percent ?? 0;

                    return `${formatInteger(value)} сделок · ${percent}%`;
                },
            },
        },
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.08,
                },
            },
            active: {
                filter: {
                    type: 'darken',
                    value: 0.12,
                },
            },
        },
        noData: {
            text: 'Нет сделок за выбранный период',
            align: 'center',
            verticalAlign: 'middle',
            style: {
                color: baseContentMuted,
                fontSize: '14px',
            },
        },
        responsive: [
            {
                breakpoint: 640,
                options: {
                    chart: {
                        height: 300,
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '52%',
                            },
                        },
                    },
                },
            },
        ],
    };

    if (pieApexChart.value) {
        if (series.length === 0) {
            pieApexChart.value.updateOptions({
                ...options,
                series: [],
                labels: [],
            }, true, true);

            return;
        }

        pieApexChart.value.updateOptions(options, true, true);
        pieApexChart.value.updateSeries(series, true);

        return;
    }

    pieApexChart.value = new ApexCharts(pieChartElement, options);
    pieApexChart.value.render();
};

const mountChart = () => {
    if (!chartRef.value) {
        return;
    }

    const seriesData = chartData.value.series?.[0]?.data ?? [];
    const colors = chartData.value.colors ?? [];
    const volumes = chartData.value.volumes ?? [];
    const labels = chartData.value.labels ?? [];
    const barCount = seriesData.length;
    const maxValue = seriesData.length > 0 ? Math.max(...seriesData) : 0;

    const columnWidth = barCount > 40 ? '96%' : barCount > 25 ? '90%' : barCount > 12 ? '82%' : '68%';
    const labelFontSizePx = barCount > 50 ? 7 : barCount > 30 ? 8 : barCount > 15 ? 9 : 10;
    const labelFontSize = `${labelFontSizePx}px`;
    const longestLabelChars = labels.reduce(
        (max, label) => Math.max(max, String(label).length),
        0,
    );
    const plotHeight = 300;
    const xAxisLabelsHeight = Math.min(
        200,
        Math.max(48, Math.ceil(longestLabelChars * (labelFontSizePx * 0.48)) + 10),
    );
    const chartHeight = Math.min(520, plotHeight + xAxisLabelsHeight);

    chartContainerHeight.value = chartHeight;

    const options = {
        chart: {
            type: 'bar',
            height: chartHeight,
            width: '100%',
            background: 'transparent',
            toolbar: {
                show: false,
            },
            events: {
                dataPointSelection: handleBarDataPointSelection,
            },
            selection: {
                enabled: true,
            },
        },
        states: {
            active: {
                allowMultipleDataPointsSelection: false,
                filter: {
                    type: 'darken',
                    value: 0.55,
                },
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth,
                distributed: true,
                borderRadius: 1,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: {
            enabled: false,
        },
        series: seriesData.length > 0
            ? (chartData.value.series ?? [])
            : [{ name: 'Объём USDT', data: [] }],
        colors: seriesData.length > 0 ? colors : [],
        noData: {
            text: 'Нет данных за выбранный период',
            align: 'center',
            verticalAlign: 'middle',
            style: {
                color: '#999',
                fontSize: '14px',
            },
        },
        xaxis: {
            categories: labels,
            labels: {
                rotate: -90,
                rotateAlways: true,
                hideOverlappingLabels: false,
                trim: false,
                style: {
                    colors: '#999',
                    fontSize: labelFontSize,
                },
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            min: 0,
            max: maxValue > 0 ? maxValue : undefined,
            forceNiceScale: true,
            tickAmount: 5,
            labels: {
                align: 'left',
                minWidth: 0,
                maxWidth: 36,
                offsetX: -6,
                style: {
                    colors: '#999',
                    fontSize: '10px',
                    fontWeight: 400,
                },
                formatter: formatYAxisValue,
            },
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
            padding: {
                left: 0,
                right: 4,
            },
        },
        legend: {
            show: false,
        },
        tooltip: {
            theme: 'dark',
            x: {
                formatter: (_value, opts) => labels[opts?.dataPointIndex ?? 0] ?? _value,
            },
            y: {
                formatter: (_value, opts) => {
                    const index = opts?.dataPointIndex ?? 0;
                    return `${volumes[index] ?? _value} USDT`;
                },
            },
        },
    };

    if (apexChart.value) {
        apexChart.value.updateOptions(options, true, true);
        nextTick(() => syncBarChartSelection());

        return;
    }

    apexChart.value = new ApexCharts(chartRef.value, options);
    apexChart.value.render();
    nextTick(() => syncBarChartSelection());
};

watch(
    () => chartData.value,
    () => {
        if (isChartView.value) {
            nextTick(() => mountChart());
        }
    },
    { deep: true },
);

watch(viewMode, (mode) => {
    destroyPieChart();

    if (mode === 'chart') {
        nextTick(() => mountChart());
    } else {
        destroyChart();
    }

    nextTick(() => mountPieChart());
});

watch(
    () => activeDealAmountDistribution.value,
    () => {
        nextTick(() => mountPieChart());
    },
    { deep: true },
);

watch(selectedPaymentDetailId, () => {
    nextTick(() => mountPieChart());
});

watch(hasPieChartData, (hasData, hadData) => {
    if (hadData && !hasData) {
        destroyPieChart();
    }

    nextTick(() => mountPieChart());
});

router.on('success', () => {
    const chartIds = new Set((chartData.value.ids ?? []).map((id) => String(id)));

    if (selectedPaymentDetailId.value && !chartIds.has(selectedPaymentDetailId.value)) {
        selectedPaymentDetailId.value = '';
    }
    selectedPeriod.value = filters.value.period ?? 'all';
    dateFrom.value = filters.value.date_from ?? '';
    dateTo.value = filters.value.date_to ?? '';
    selectedTraderId.value = filters.value.trader_id ? String(filters.value.trader_id) : '';
    syncBarsLimitFields(filters.value.bars_limit);
    includeArchived.value = parseTruthyFilter(filters.value.include_archived);
    selectedPaymentGatewayId.value = filters.value.payment_gateway_id
        ? String(filters.value.payment_gateway_id)
        : '';
    volumeFromPreset.value = filters.value.volume_from ? String(filters.value.volume_from) : '';
    volumeToPreset.value = filters.value.volume_to ? String(filters.value.volume_to) : '';
    currentPage.value = Number(filters.value.page) > 0 ? Number(filters.value.page) : 1;
    viewMode.value = filters.value.view_mode === 'chart' ? 'chart' : 'table';
});

onMounted(() => {
    nextTick(() => {
        if (isChartView.value) {
            mountChart();
        }

        mountPieChart();
    });
});

onBeforeUnmount(() => {
    destroyChart();
    destroyPieChart();
});
</script>

<template>
    <div>
        <Head title="Объём по реквизитам" />

        <MainTableSection
            title="Объём по реквизитам"
            :data="mainSectionBodyPlaceholder"
            :paginate="false"
            :display-pagination="false"
        >
            <template #button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline btn-primary"
                    @click="router.visit(route(backRoute), { preserveScroll: true })"
                >
                    К реквизитам
                </button>
            </template>

            <template #body>
                <div class="mb-4 flex flex-col gap-4">
                    <p class="text-[11px] leading-tight text-base-content/45">
                        Реквизиты с нулевым объёмом за выбранный период не отображаются в отчёте.
                    </p>

                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body gap-3 p-3 lg:p-4">
                            <div class="flex flex-wrap items-end gap-3">
                                <TraderSearchSelect
                                    v-if="isAdmin && traderSearchRoute"
                                    v-model="selectedTraderId"
                                    :search-route="traderSearchRoute"
                                    compact
                                    class="w-full shrink-0 sm:max-w-xs sm:w-auto"
                                />

                                <label class="form-control w-full shrink-0 sm:max-w-xs sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Банк</span>
                                    </div>
                                    <select
                                        v-model="selectedPaymentGatewayId"
                                        class="select select-bordered select-sm w-full"
                                        :disabled="processing"
                                    >
                                        <option value="">
                                            Все банки
                                        </option>
                                        <option
                                            v-for="bank in bankOptions"
                                            :key="bank.value"
                                            :value="String(bank.value)"
                                        >
                                            {{ bank.label }}
                                        </option>
                                    </select>
                                </label>

                                <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">{{ barsLimitLabel }}</span>
                                    </div>
                                    <select
                                        v-model="barsLimitPreset"
                                        class="select select-bordered select-sm w-full"
                                        :disabled="processing"
                                        @change="applyFilters"
                                    >
                                        <option
                                            v-for="option in barsLimitPresets"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>

                                <label class="label min-h-8 w-auto shrink-0 cursor-pointer justify-start gap-2 rounded-lg border border-base-300 px-3 py-1.5">
                                    <input
                                        v-model="includeArchived"
                                        type="checkbox"
                                        class="checkbox checkbox-sm checkbox-primary"
                                        :disabled="processing"
                                        @change="applyFilters"
                                    >
                                    <span class="label-text text-xs">С архивом</span>
                                </label>
                            </div>

                            <div class="flex flex-wrap items-end gap-3">
                                <template v-if="hasVolumePresets">
                                    <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                        <div class="label py-0">
                                            <span class="label-text text-xs">Объём от</span>
                                        </div>
                                        <select
                                            v-model="volumeFromPreset"
                                            class="select select-bordered select-sm w-full"
                                            :disabled="processing"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Любой
                                            </option>
                                            <option
                                                v-for="option in volumePresets"
                                                :key="`from-${option.value}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                        <div class="label py-0">
                                            <span class="label-text text-xs">Объём до</span>
                                        </div>
                                        <select
                                            v-model="volumeToPreset"
                                            class="select select-bordered select-sm w-full"
                                            :disabled="processing"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Любой
                                            </option>
                                            <option
                                                v-for="option in volumePresets"
                                                :key="`to-${option.value}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>
                                </template>

                                <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Период с (необязательно)</span>
                                    </div>
                                    <input
                                        v-model="dateFrom"
                                        type="date"
                                        class="input input-sm input-bordered w-full"
                                    >
                                </label>
                                <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Период по (необязательно)</span>
                                    </div>
                                    <input
                                        v-model="dateTo"
                                        type="date"
                                        class="input input-sm input-bordered w-full"
                                    >
                                </label>
                            </div>

                            <p
                                v-if="hasCustomPeriod"
                                class="text-xs text-base-content/60"
                            >
                                Используется собственный период. Пресеты игнорируются, пока задана дата «с» или «по».
                            </p>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="option in periodOptions"
                                        :key="option.value"
                                        type="button"
                                        class="btn btn-xs"
                                        :class="selectedPeriod === option.value && !hasCustomPeriod ? 'btn-primary' : 'btn-outline btn-primary'"
                                        :disabled="processing"
                                        @click="selectPeriod(option.value)"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        :disabled="processing"
                                        @click="resetFilters"
                                    >
                                        Сбросить
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        :disabled="processing"
                                        @click="applyFilters"
                                    >
                                        Применить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                            <span class="badge badge-outline badge-sm">
                                {{ meta.include_archived ? 'В выборке' : 'Активных' }} реквизитов: {{ formatInteger(meta.active_payment_details_count) }}
                            </span>
                            <span
                                v-if="meta.hidden_zero_volume_count > 0"
                                class="badge badge-warning badge-outline badge-sm"
                            >
                                Скрыто с нулевым объёмом: {{ formatInteger(meta.hidden_zero_volume_count) }}
                            </span>
                            <span
                                v-if="meta.excluded_by_volume_count > 0"
                                class="badge badge-info badge-outline badge-sm"
                            >
                                Вне диапазона объёма: {{ formatInteger(meta.excluded_by_volume_count) }}
                            </span>
                            <span
                                v-if="meta.other_pages_count > 0 || meta.excluded_over_limit_count > 0"
                                class="badge badge-error badge-outline badge-sm"
                            >
                                На других страницах: {{ formatInteger(meta.other_pages_count ?? meta.excluded_over_limit_count) }}
                            </span>
                            <span
                                v-if="meta.include_archived && meta.archived_in_scope_count > 0"
                                class="badge badge-secondary badge-outline badge-sm"
                            >
                                Архивных в выборке: {{ formatInteger(meta.archived_in_scope_count) }}
                            </span>
                            <span
                                v-if="meta.include_archived && meta.archived_on_chart_count > 0"
                                class="badge badge-secondary badge-outline badge-sm"
                            >
                                Архивных на графике: {{ formatInteger(meta.archived_on_chart_count) }}
                            </span>
                        </div>

                    <div class="card bg-base-100 shadow p-4 lg:p-6">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg text-base-content/70">
                                Объём успешных сделок (USDT)
                            </h3>
                            <div class="join">
                                <button
                                    type="button"
                                    class="btn btn-xs join-item"
                                    :class="isTableView ? 'btn-primary' : 'btn-outline'"
                                    :disabled="processing"
                                    @click="setViewMode('table')"
                                >
                                    Таблица
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-xs join-item"
                                    :class="isChartView ? 'btn-primary' : 'btn-outline'"
                                    :disabled="processing"
                                    @click="setViewMode('chart')"
                                >
                                    График
                                </button>
                            </div>
                        </div>

                        <template v-if="isChartView">
                            <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                                <div
                                    v-if="hasChartData"
                                    class="flex items-center gap-2 text-xs text-base-content/60"
                                >
                                    <span class="inline-block h-3 w-3 rounded-sm bg-success" />
                                    <span>больше объём</span>
                                    <span class="inline-block h-3 w-3 rounded-sm bg-error" />
                                    <span>меньше объём</span>
                                </div>
                            </div>
                            <div
                                ref="chartRef"
                                class="w-full min-w-0 overflow-hidden"
                                :style="{ height: `${chartContainerHeight}px` }"
                            />
                            <p
                                v-if="!hasChartData"
                                class="mt-2 text-center text-xs text-base-content/60"
                            >
                                Нет реквизитов с объёмом за выбранный период.
                            </p>
                            <p
                                v-else
                                class="mt-2 text-center text-xs text-base-content/50"
                            >
                                Нажмите на столбец, чтобы увидеть распределение сделок по этому реквизиту.
                            </p>
                        </template>

                        <template v-else>
                            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                <div class="min-w-0 space-y-3">
                                    <p class="text-xs text-base-content/50">
                                        Нажмите на строку, чтобы увидеть распределение сделок по реквизиту.
                                    </p>
                                    <div
                                        v-if="hasChartData"
                                        class="overflow-x-auto rounded-box border border-base-300"
                                    >
                                        <table class="table table-sm table-zebra [&_tbody_tr:hover]:bg-inherit">
                                            <thead>
                                                <tr class="text-xs uppercase text-base-content/60">
                                                    <th>Реквизит</th>
                                                    <th class="text-right">
                                                        Объём
                                                    </th>
                                                    <th class="text-right">
                                                        Сделок
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="row in tableRows"
                                                    :key="row.id"
                                                    class="cursor-pointer hover:bg-inherit"
                                                    :class="selectedPaymentDetailId === row.id ? 'bg-primary/10 hover:bg-primary/10' : ''"
                                                    @click="selectPaymentDetailFromTable(row.id)"
                                                >
                                                    <td class="max-w-[14rem] truncate font-medium">
                                                        {{ row.label }}
                                                    </td>
                                                    <td class="text-right tabular-nums whitespace-nowrap">
                                                        {{ row.volume }}
                                                    </td>
                                                    <td class="text-right tabular-nums">
                                                        {{ formatInteger(row.dealsCount) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p
                                        v-else
                                        class="py-6 text-center text-xs text-base-content/60"
                                    >
                                        Нет реквизитов с объёмом за выбранный период.
                                    </p>
                                </div>

                                <div class="flex min-w-0 flex-col gap-4">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h4 class="text-base font-medium text-base-content/75">
                                                Распределение сделок по сумме (USDT)
                                            </h4>
                                            <p class="mt-1 text-xs text-base-content/50">
                                                <template v-if="selectedPaymentDetailLabel">
                                                    По реквизиту: {{ selectedPaymentDetailLabel }}
                                                </template>
                                                <template v-else>
                                                    По всем реквизитам в выборке с учётом фильтров
                                                </template>
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                v-if="hasPieChartData"
                                                class="badge badge-outline badge-sm"
                                            >
                                                Сделок: {{ formatInteger(activeDealAmountDistribution.total_deals) }}
                                            </span>
                                            <button
                                                v-if="selectedPaymentDetailId"
                                                type="button"
                                                class="btn btn-ghost btn-xs"
                                                :disabled="processing"
                                                @click="clearSelectedPaymentDetail"
                                            >
                                                Сбросить
                                            </button>
                                        </div>
                                    </div>
                                    <div
                                        ref="pieChartRefTable"
                                        class="mx-auto w-full min-w-0 max-w-[360px]"
                                    />
                                    <ul
                                        v-if="hasPieChartData"
                                        class="divide-y divide-base-300 rounded-box border border-base-300 bg-base-200/30 text-xs"
                                    >
                                        <li
                                            v-for="(bucket, index) in activeDealAmountDistribution.buckets"
                                            :key="bucket.key"
                                            class="flex items-center justify-between gap-2 px-2.5 py-2"
                                        >
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span
                                                    class="size-2 shrink-0 rounded-full ring-2 ring-base-100"
                                                    :style="{ backgroundColor: pieChartColorForIndex(index) }"
                                                />
                                                <span class="truncate text-base-content/85 leading-tight">
                                                    {{ bucket.label }}
                                                </span>
                                            </span>
                                            <span class="shrink-0 tabular-nums text-[11px] text-base-content/55">
                                                {{ formatInteger(bucket.count) }}
                                                <span class="text-base-content/35">·</span>
                                                {{ bucket.percent }}%
                                            </span>
                                        </li>
                                    </ul>
                                    <p
                                        v-if="!hasPieChartData"
                                        class="text-center text-xs text-base-content/60"
                                    >
                                        Нет успешных сделок за выбранный период.
                                    </p>
                                </div>
                            </div>
                        </template>

                        <div
                            v-if="showVolumeStatsFooter"
                            class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4"
                        >
                            <p class="text-xs text-base-content/55">
                                <template v-if="showPagination">
                                    Страница {{ paginationMeta.currentPage }} из {{ paginationMeta.lastPage }}
                                    <span class="text-base-content/35">·</span>
                                    по {{ paginationMeta.perPage }} реквизитов
                                    <span class="text-base-content/35">·</span>
                                </template>
                                {{ onViewCountLabel }}: {{ formatInteger(meta.displayed_count) }}
                                <span class="text-base-content/35">·</span>
                                С объёмом: {{ formatInteger(meta.positive_volume_count) }}
                            </p>
                            <Pagination
                                v-if="showPagination"
                                :model-value="paginationMeta.currentPage"
                                :total-items="paginationMeta.totalItems"
                                :per-page="paginationMeta.perPage"
                                previous-label="Назад"
                                next-label="Вперёд"
                                :show-icons="false"
                                @page-changed="goToPage"
                            />
                        </div>
                    </div>

                    <div
                        v-if="isChartView"
                        class="card bg-base-100 shadow p-4 lg:p-6"
                    >
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg text-base-content/70">
                                    Распределение сделок по сумме (USDT)
                                </h3>
                                <p class="mt-1 text-xs text-base-content/50">
                                    <template v-if="selectedPaymentDetailLabel">
                                        По реквизиту: {{ selectedPaymentDetailLabel }}
                                    </template>
                                    <template v-else>
                                        По всем реквизитам в выборке с учётом фильтров
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="hasPieChartData"
                                    class="badge badge-outline badge-sm"
                                >
                                    Сделок: {{ formatInteger(activeDealAmountDistribution.total_deals) }}
                                </span>
                                <button
                                    v-if="selectedPaymentDetailId"
                                    type="button"
                                    class="btn btn-ghost btn-xs"
                                    :disabled="processing"
                                    @click="clearSelectedPaymentDetail"
                                >
                                    Сбросить выбор реквизита
                                </button>
                            </div>
                        </div>
                        <div
                            class="grid gap-6"
                            :class="hasPieChartData ? 'lg:grid-cols-[minmax(0,1fr)_15.5rem] lg:items-center' : ''"
                        >
                            <div
                                ref="pieChartRefChart"
                                class="mx-auto w-full min-w-0"
                                :class="hasPieChartData ? 'max-w-[400px] lg:max-w-none' : ''"
                            />
                            <ul
                                v-if="hasPieChartData"
                                class="divide-y divide-base-300 rounded-box border border-base-300 bg-base-200/30 text-xs lg:w-[15.5rem] lg:shrink-0"
                            >
                                <li
                                    v-for="(bucket, index) in activeDealAmountDistribution.buckets"
                                    :key="bucket.key"
                                    class="flex items-center justify-between gap-2 px-2.5 py-2"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="size-2 shrink-0 rounded-full ring-2 ring-base-100"
                                            :style="{ backgroundColor: pieChartColorForIndex(index) }"
                                        />
                                        <span class="truncate text-base-content/85 leading-tight">
                                            {{ bucket.label }}
                                        </span>
                                    </span>
                                    <span class="shrink-0 tabular-nums text-[11px] text-base-content/55">
                                        {{ formatInteger(bucket.count) }}
                                        <span class="text-base-content/35">·</span>
                                        {{ bucket.percent }}%
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <p
                            v-if="!hasPieChartData"
                            class="mt-2 text-center text-xs text-base-content/60"
                        >
                            Нет успешных сделок за выбранный период.
                        </p>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
