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

const syncBarsLimitFields = (barsLimitValue) => {
    const value = String(barsLimitValue ?? defaultBarsLimit.value);

    if (presetBarsLimitValues.value.includes(value)) {
        barsLimitPreset.value = value;
        barsLimitCustom.value = '';
        return;
    }

    barsLimitPreset.value = defaultBarsLimit.value;
    barsLimitCustom.value = value;
};

const selectedPeriod = ref(filters.value.period ?? 'all');
const dateFrom = ref(filters.value.date_from ?? '');
const dateTo = ref(filters.value.date_to ?? '');
const selectedTraderId = ref(filters.value.trader_id ? String(filters.value.trader_id) : '');
const barsLimitPreset = ref(defaultBarsLimit.value);
const barsLimitCustom = ref('');
const includeArchived = ref(Boolean(filters.value.include_archived));
const selectedPaymentGatewayId = ref(
    filters.value.payment_gateway_id ? String(filters.value.payment_gateway_id) : '',
);

syncBarsLimitFields(filters.value.bars_limit);

const chartRef = ref(null);
const apexChart = ref(null);
const processing = ref(false);

const volumeStatisticsRoute = computed(() => (
    isAdmin.value
        ? route('admin.payment-details.volume-statistics')
        : route('payment-details.volume-statistics')
));

const resolveBarsLimitForRequest = () => {
    const custom = Number.parseInt(String(barsLimitCustom.value).trim(), 10);

    if (!Number.isNaN(custom) && custom > 0) {
        return String(custom);
    }

    return barsLimitPreset.value;
};

const buildRequestData = () => {
    const data = {
        period: selectedPeriod.value,
        bars_limit: resolveBarsLimitForRequest(),
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

    if (includeArchived.value) {
        data.include_archived = true;
    }

    if (selectedPaymentGatewayId.value) {
        data.payment_gateway_id = selectedPaymentGatewayId.value;
    }

    return data;
};

const barsLimitSummary = computed(() => {
    if (meta.value.bars_limit_is_all) {
        return 'все найденные';
    }

    return meta.value.bars_limit ?? defaultBarsLimit.value;
});

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
    const labelFontSize = barCount > 50 ? '7px' : barCount > 30 ? '8px' : barCount > 15 ? '9px' : '10px';
    const longestLabelChars = labels.reduce(
        (max, label) => Math.max(max, String(label).length),
        0,
    );
    const xAxisLabelPadding = Math.min(360, Math.max(80, Math.ceil(longestLabelChars * 5.2)));
    const chartHeight = Math.min(820, 300 + xAxisLabelPadding);

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
            labels: {
                style: {
                    colors: '#999',
                },
                formatter: (value) => Number(value).toLocaleString('ru-RU', {
                    maximumFractionDigits: 2,
                }),
            },
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
            padding: {
                left: 8,
                right: 8,
                bottom: xAxisLabelPadding - 20,
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
    includeArchived.value = Boolean(filters.value.include_archived);
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
                    <div class="alert alert-info text-sm">
                        <span v-if="meta.include_archived">
                            В выборку входят <strong>активные и архивированные</strong> реквизиты.
                            Архивные отмечены в подписи «(архив)».
                        </span>
                        <span v-else>
                            По умолчанию только <strong>активные</strong> реквизиты (не из архива).
                            Включите опцию ниже, чтобы добавить архивированные.
                        </span>
                    </div>

                    <div class="alert alert-warning text-sm">
                        <span>
                            Реквизиты с нулевым объёмом за выбранный период <strong>не отображаются</strong> на графике.
                        </span>
                    </div>

                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body gap-4 p-4 lg:p-5">
                            <div
                                v-if="isAdmin && traderSearchRoute"
                                class="max-w-md"
                            >
                                <TraderSearchSelect
                                    v-model="selectedTraderId"
                                    :search-route="traderSearchRoute"
                                />
                            </div>

                            <label class="form-control w-full max-w-md">
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

                            <div class="flex flex-wrap items-end gap-3">
                                <label class="form-control w-full max-w-[11rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Столбиков на графике</span>
                                    </div>
                                    <select
                                        v-model="barsLimitPreset"
                                        class="select select-bordered select-sm w-full"
                                        :disabled="processing"
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
                                <label class="form-control w-full max-w-[9rem] sm:w-auto">
                                    <div class="label py-0">
                                        <span class="label-text text-xs">Своё число</span>
                                    </div>
                                    <input
                                        v-model="barsLimitCustom"
                                        type="number"
                                        min="1"
                                        max="10000"
                                        placeholder="100"
                                        class="input input-bordered input-sm w-full"
                                        :disabled="processing"
                                    >
                                </label>
                            </div>

                            <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 px-3 py-2">
                                <input
                                    v-model="includeArchived"
                                    type="checkbox"
                                    class="checkbox checkbox-sm checkbox-primary"
                                    :disabled="processing"
                                >
                                <span class="label-text text-sm">Включать архивированные реквизиты</span>
                            </label>

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
                            <p class="text-xs text-base-content/60">
                                Если заполнено «Своё число», оно имеет приоритет над списком. По умолчанию — {{ defaultBarsLimit }} столбиков.
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
                            Фильтр по трейдеру не выбран — на графике
                            <template v-if="meta.bars_limit_is_all">все найденные</template>
                            <template v-else>топ-{{ barsLimitSummary }}</template>
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
                            class="w-full min-w-0"
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
