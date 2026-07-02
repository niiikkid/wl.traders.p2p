<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatsModeNav from '@/Components/MainPage/StatsModeNav.vue';
import StatCard from '@/Components/MainPage/StatCard.vue';
import ChartTypeTabs from '@/Components/MainPage/ChartTypeTabs.vue';
import PeriodPresetControls from '@/Components/MainPage/PeriodPresetControls.vue';
import PeriodNavigator from '@/Components/MainPage/PeriodNavigator.vue';
import StatsFilterDropdown from '@/Components/MainPage/StatsFilterDropdown.vue';
import DashboardChartCard from '@/Components/MainPage/DashboardChartCard.vue';
import DashboardChart from '@/Components/Charts/DashboardChart.vue';
import { useDashboardStats } from '@/composables/useDashboardStats.js';
import { getLastPoints, normalizeChartLabels } from '@/utils/dashboardChart.js';

const page = usePage();
const activeStatsMode = computed(() => (page.props.activeStatsMode === 'payouts' ? 'payouts' : 'deals'));
const statistics = computed(() => page.props.statistics || {});

const chartSources = {
    income: computed(() => page.props.chart || { labels: [], data: [] }),
    conversion: computed(() => page.props.conversionChart || { labels: [], data: [] }),
    turnover: computed(() => page.props.turnoverChart || { labels: [], data: [] }),
    orders: computed(() => page.props.ordersChart || { labels: [], data: [] }),
    average_check: computed(() => page.props.averageCheckChart || { labels: [], data: [] }),
};

const stats = useDashboardStats({
    routeName: 'trader.main.index',
    filterOptionsRouteName: 'trader.main.filter-options',
    activeStatsMode,
    filterTypesByMode: {
        deals: [
            { key: 'payment_method', label: 'Метод', requestKey: 'payment_method_ids', placeholder: 'Поиск платежного метода...' },
            { key: 'payment_detail', label: 'Реквизит', requestKey: 'payment_detail_ids', placeholder: 'Поиск реквизита...' },
        ],
        payouts: [],
    },
    filterPropMap: {
        paymentMethodIds: 'payment_method',
        paymentDetailIds: 'payment_detail',
    },
});

const {
    processing,
    isMobile,
    selectedPeriodPreset,
    selectedDateFrom,
    selectedDateTo,
    canNavigateByPeriod,
    selectedPeriodLabel,
    setPeriodPreset,
    navigatePeriod,
    gearFilterTypes,
} = stats;

const activeChartTab = ref('income');

const statisticsFormatted = computed(() => ({
    totalTurnover: statistics.value?.totalTurnover ?? '0.00',
    totalProfit: statistics.value?.totalProfit ?? '0.00',
    balance: statistics.value?.balance ?? '0.00',
    successOrderCount: statistics.value?.successOrderCount ?? 0,
    successPayoutCount: statistics.value?.successPayoutCount ?? 0,
}));

const chartTabs = computed(() => {
    const baseTabs = [
        { value: 'income', label: 'Доход', colorToken: 'primary', seriesName: 'Доход ($)', valueType: 'money' },
        { value: 'turnover', label: 'Оборот', colorToken: 'secondary', seriesName: 'Оборот ($)', valueType: 'money' },
    ];
    if (activeStatsMode.value === 'deals') {
        return [
            ...baseTabs,
            { value: 'conversion', label: 'Конверсия', colorToken: 'success', seriesName: 'Конверсия (%)', valueType: 'percent' },
            { value: 'orders', label: 'Количество сделок', colorToken: 'accent', seriesName: 'Сделок', valueType: 'count' },
            { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)', valueType: 'money' },
        ];
    }
    return [
        ...baseTabs,
        { value: 'orders', label: 'Количество выплат', colorToken: 'accent', seriesName: 'Выплат', valueType: 'count' },
        { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)', valueType: 'money' },
    ];
});

const activeTabConfig = computed(() => chartTabs.value.find((tab) => tab.value === activeChartTab.value) || chartTabs.value[0]);
const activeTabTitle = computed(() => activeTabConfig.value.label);

const activeData = computed(() => getLastPoints(
    normalizeChartLabels(chartSources[activeChartTab.value].value, selectedPeriodPreset.value),
    isMobile.value,
));

const activeSeries = computed(() => [{
    name: activeTabConfig.value.seriesName,
    data: activeData.value.data,
    colorToken: activeTabConfig.value.colorToken,
}]);

const activeYMax = computed(() => (activeTabConfig.value.valueType === 'percent' ? 100 : null));

watch(chartTabs, (tabs) => {
    if (!tabs.some((tab) => tab.value === activeChartTab.value)) {
        activeChartTab.value = 'income';
    }
}, { deep: true });

const switchStatsMode = (mode) => {
    if (activeStatsMode.value === mode || processing.value) {
        return;
    }
    processing.value = true;
    router.visit(route('trader.main.index'), {
        data: { mode, period: 'month' },
        replace: true,
        preserveScroll: true,
        preserveState: false,
        onFinish: () => {
            processing.value = false;
        },
    });
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Панель управления" />

        <div class="mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-base-content sm:text-3xl">Панель управления</h2>
                <slot name="button"></slot>
            </div>

            <StatsModeNav :current="activeStatsMode" @switch="switchStatsMode" />

            <section>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Оборот" prefix="$" :value="statisticsFormatted.totalTurnover" color="success">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Доход" prefix="$" :value="statisticsFormatted.totalProfit" color="info">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Общий баланс" prefix="$" :value="statisticsFormatted.balance" color="primary">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        :label="activeStatsMode === 'payouts' ? 'Выплаты' : 'Сделки'"
                        :value="activeStatsMode === 'payouts' ? statisticsFormatted.successPayoutCount : statisticsFormatted.successOrderCount"
                        color="warning"
                    >
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </template>
                    </StatCard>
                </div>

                <div class="mt-8 flex flex-col gap-3 xl:flex-row lg:items-start xl:justify-between">
                    <ChartTypeTabs v-model="activeChartTab" :tabs="chartTabs" />

                    <div class="flex flex-col gap-2 lg:items-end">
                        <div class="flex items-start gap-2">
                            <StatsFilterDropdown v-if="gearFilterTypes.length" :controller="stats" :show-bulk-actions="true" />
                            <PeriodPresetControls
                                :preset="selectedPeriodPreset"
                                v-model:date-from="selectedDateFrom"
                                v-model:date-to="selectedDateTo"
                                @select-preset="setPeriodPreset"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="canNavigateByPeriod" class="mt-4 sm:hidden">
                    <PeriodNavigator
                        block
                        :label="selectedPeriodLabel"
                        :disabled="processing"
                        @prev="navigatePeriod(-1)"
                        @next="navigatePeriod(1)"
                    />
                </div>

                <DashboardChartCard
                    :title="activeTabTitle"
                    :can-navigate="canNavigateByPeriod"
                    :period-label="selectedPeriodLabel"
                    :processing="processing"
                    @prev="navigatePeriod(-1)"
                    @next="navigatePeriod(1)"
                >
                    <DashboardChart
                        :labels="activeData.labels"
                        :series="activeSeries"
                        :value-type="activeTabConfig.valueType"
                        :y-min="0"
                        :y-max="activeYMax"
                    />
                </DashboardChartCard>
            </section>
        </div>
    </div>
</template>
