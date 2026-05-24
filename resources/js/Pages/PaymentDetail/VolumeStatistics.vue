<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import TraderSearchSelect from '@/Pages/Admin/TraderAnalytics/Components/TraderSearchSelect.vue';
import ApexCharts from 'apexcharts';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

/** MainTableSection renders the body slot only when items.length > 0. */
const mainSectionBodyPlaceholder = [1];

const page = usePage();

const chartData = computed(() => page.props.chart ?? { labels: [], series: [], colors: [], volumes: [] });
const meta = computed(() => page.props.meta ?? {});
const filters = computed(() => page.props.filters ?? {});
const periodOptions = computed(() => page.props.periodOptions ?? []);
const barsLimitPresets = computed(() => page.props.barsLimitPresets ?? []);
const bankOptions = computed(() => page.props.bankOptions ?? []);
const defaultBarsLimit = computed(() => page.props.defaultBarsLimit ?? '100');
const selectedBank = computed(() => page.props.selectedBank ?? null);
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

syncBarsLimitFields(filters.value.bars_limit);

const chartRef = ref(null);
const apexChart = ref(null);
const chartContainerHeight = ref(400);
const processing = ref(false);

const volumeStatisticsRoute = computed(() => (
    isAdmin.value
        ? route('admin.payment-details.volume-statistics')
        : route('payment-details.volume-statistics')
));

const buildRequestData = () => {
    const data = {
        period: selectedPeriod.value,
        bars_limit: barsLimitPreset.value,
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

    return data;
};

const barsLimitSummary = computed(() => meta.value.bars_limit ?? defaultBarsLimit.value);

const applyFilters = () => {
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

const selectPeriod = (period) => {
    selectedPeriod.value = period;
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const hasCustomPeriod = computed(() => Boolean(dateFrom.value || dateTo.value));

const hasChartData = computed(() => chartData.value.labels?.length > 0);

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
        return;
    }

    apexChart.value = new ApexCharts(chartRef.value, options);
    apexChart.value.render();
};

watch(
    () => chartData.value,
    () => {
        nextTick(() => mountChart());
    },
    { deep: true },
);

router.on('success', () => {
    selectedPeriod.value = filters.value.period ?? 'all';
    dateFrom.value = filters.value.date_from ?? '';
    dateTo.value = filters.value.date_to ?? '';
    selectedTraderId.value = filters.value.trader_id ? String(filters.value.trader_id) : '';
    syncBarsLimitFields(filters.value.bars_limit);
    includeArchived.value = parseTruthyFilter(filters.value.include_archived);
    selectedPaymentGatewayId.value = filters.value.payment_gateway_id
        ? String(filters.value.payment_gateway_id)
        : '';
});

onMounted(() => {
    nextTick(() => mountChart());
});

onBeforeUnmount(() => {
    destroyChart();
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
                        Реквизиты с нулевым объёмом за выбранный период не отображаются на графике.
                    </p>

                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body gap-3 p-3 lg:p-4">
                            <div
                                class="grid grid-cols-1 gap-3"
                                :class="isAdmin && traderSearchRoute ? 'sm:grid-cols-2' : ''"
                            >
                                <div
                                    v-if="isAdmin && traderSearchRoute"
                                    class="min-w-0"
                                >
                                    <TraderSearchSelect
                                        v-model="selectedTraderId"
                                        :search-route="traderSearchRoute"
                                    />
                                </div>

                                <label class="form-control min-w-0 w-full">
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
                            </div>

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

                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                                <label class="form-control w-full shrink-0 sm:max-w-[11rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Столбиков на графике</span>
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
                                    <span class="label-text text-xs sm:text-sm">Включать архивированные реквизиты</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="form-control w-full">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Период с (необязательно)</span>
                                    </div>
                                    <input
                                        v-model="dateFrom"
                                        type="date"
                                        class="input input-sm input-bordered w-full"
                                    >
                                </label>
                                <label class="form-control w-full">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Период по (необязательно)</span>
                                    </div>
                                    <input
                                        v-model="dateTo"
                                        type="date"
                                        class="input input-sm input-bordered w-full"
                                    >
                                </label>
                                <div class="flex items-end">
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

                            <p
                                v-if="hasCustomPeriod"
                                class="text-xs text-base-content/60"
                            >
                                Используется собственный период. Пресеты игнорируются, пока задана дата «с» или «по».
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="selectedBank"
                        class="alert alert-info text-sm"
                    >
                        <span>Фильтр по банку: <strong>{{ selectedBank.label }}</strong></span>
                    </div>

                    <div
                        v-if="meta.scope_all_traders"
                        class="alert alert-info text-sm"
                    >
                        <span>
                            Фильтр по трейдеру не выбран — на графике топ-{{ barsLimitSummary }}
                            реквизитов по объёму среди <strong>всех</strong>
                            <template v-if="meta.include_archived">реквизитов платформы (включая архив)</template>
                            <template v-else>активных реквизитов платформы</template>.
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                            <span class="badge badge-outline badge-sm">
                                {{ meta.include_archived ? 'В выборке' : 'Активных' }} реквизитов: {{ formatInteger(meta.active_payment_details_count) }}
                            </span>
                            <span class="badge badge-primary badge-outline badge-sm">
                                На графике: {{ formatInteger(meta.displayed_count) }}
                            </span>
                            <span
                                v-if="meta.hidden_zero_volume_count > 0"
                                class="badge badge-warning badge-outline badge-sm"
                            >
                                Скрыто с нулевым объёмом: {{ formatInteger(meta.hidden_zero_volume_count) }}
                            </span>
                            <span
                                v-if="meta.excluded_over_limit_count > 0"
                                class="badge badge-error badge-outline badge-sm"
                            >
                                Не вошли в лимит ({{ barsLimitSummary }}): {{ formatInteger(meta.excluded_over_limit_count) }}
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
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg text-base-content/70">
                                Объём успешных сделок (USDT)
                            </h3>
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
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
