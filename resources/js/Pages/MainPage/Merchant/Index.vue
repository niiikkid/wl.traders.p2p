<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatsTabsNav from '@/Components/MainPage/StatsTabsNav.vue';
import StatCard from '@/Components/MainPage/StatCard.vue';
import ChartTypeTabs from '@/Components/MainPage/ChartTypeTabs.vue';
import PeriodPresetControls from '@/Components/MainPage/PeriodPresetControls.vue';
import PeriodNavigator from '@/Components/MainPage/PeriodNavigator.vue';
import StatsFilterDropdown from '@/Components/MainPage/StatsFilterDropdown.vue';
import DashboardChartCard from '@/Components/MainPage/DashboardChartCard.vue';
import DashboardChart from '@/Components/Charts/DashboardChart.vue';
import MerchantApiStatsWidget from '@/Components/MainPage/MerchantApiStatsWidget.vue';
import MinAmountStatsSection from './Components/MinAmountStatsSection.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';
import LogsIcon from '@/Layouts/Partials/Icons/LogsIcon.vue';
import { useDashboardStats } from '@/composables/useDashboardStats.js';
import { getLastPoints, normalizeChartLabels } from '@/utils/dashboardChart.js';

const page = usePage();
const activeStatsMode = computed(() => (page.props.activeStatsMode === 'payouts' ? 'payouts' : 'deals'));
const statistics = computed(() => page.props.statistics || {});
const enabledCardsMinAmountStatistics = computed(() => page.props.enabledCardsMinAmountStatistics || { availableCurrencies: [], minAmountStats: {} });

const chartSources = {
    income: computed(() => page.props.chart || { labels: [], data: [] }),
    conversion: computed(() => page.props.conversionChart || { labels: [], data: [] }),
    turnover: computed(() => page.props.turnoverChart || { labels: [], data: [] }),
    orders: computed(() => page.props.ordersChart || { labels: [], data: [] }),
    average_check: computed(() => page.props.averageCheckChart || { labels: [], data: [] }),
};

const stats = useDashboardStats({
    routeName: 'merchant.main.index',
    filterOptionsRouteName: 'merchant.main.filter-options',
    activeStatsMode,
    filterTypesByMode: {
        deals: [
            { key: 'payment_method', label: 'Метод', requestKey: 'payment_method_ids', placeholder: 'Поиск платежного метода...' },
            { key: 'merchant', label: 'Мерчант', requestKey: 'merchant_ids', placeholder: 'Поиск магазина мерчанта...' },
        ],
        payouts: [
            { key: 'merchant', label: 'Мерчант', requestKey: 'merchant_ids', placeholder: 'Поиск магазина мерчанта...' },
        ],
    },
    filterPropMap: {
        paymentMethodIds: 'payment_method',
        merchantIds: 'merchant',
    },
    isSingleSelect: (typeKey) => activeStatsMode.value === 'payouts' && typeKey === 'merchant',
    buildFilterOptionParams: (typeKey, context) => (typeKey === 'payment_method'
        ? {
            merchant_ids: context.selectedFilters.merchant || [],
            date_from: context.dateFrom || null,
            date_to: context.dateTo || null,
        }
        : {}),
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

const activeChartTab = ref(activeStatsMode.value === 'payouts' ? 'turnover' : 'income');

const statisticsFormatted = computed(() => ({
    totalTurnover: statistics.value?.totalTurnover ?? '0.00',
    totalProfit: statistics.value?.totalProfit ?? '0.00',
    conversionRate: statistics.value?.conversionRate ?? '0%',
    successOrderCount: statistics.value?.successOrderCount ?? 0,
}));

const chartTabs = computed(() => {
    const tabs = [
        { value: 'turnover', label: 'Оборот', colorToken: 'secondary', seriesName: 'Оборот ($)', valueType: 'money' },
        { value: 'conversion', label: 'Конверсия', colorToken: 'success', seriesName: 'Конверсия (%)', valueType: 'percent' },
        {
            value: 'orders',
            label: activeStatsMode.value === 'payouts' ? 'Количество выплат' : 'Количество сделок',
            colorToken: 'accent',
            seriesName: activeStatsMode.value === 'payouts' ? 'Выплат' : 'Сделок',
            valueType: 'count',
        },
        { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)', valueType: 'money' },
    ];
    return activeStatsMode.value === 'payouts'
        ? tabs
        : [{ value: 'income', label: 'Доход', colorToken: 'primary', seriesName: 'Доход ($)', valueType: 'money' }, ...tabs];
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
        activeChartTab.value = tabs[0]?.value || 'turnover';
    }
}, { deep: true, immediate: true });

const statTabs = [
    { key: 'deals', label: 'Сделки', icon: OrdersIcon, mode: 'deals' },
    { key: 'payouts', label: 'Выплаты', icon: PayoutsIcon, mode: 'payouts' },
    { key: 'api-logs', label: 'API-логи', icon: LogsIcon },
];

const readInitialTab = () => {
    if (typeof window !== 'undefined') {
        const requested = new URLSearchParams(window.location.search).get('stab');
        if (requested && statTabs.some((tab) => tab.key === requested)) {
            return requested;
        }
    }
    return activeStatsMode.value === 'payouts' ? 'payouts' : 'deals';
};

const activeTab = ref(readInitialTab());
const activatedTabs = ref([activeTab.value]);
const isPrimaryTab = computed(() => activeTab.value === 'deals' || activeTab.value === 'payouts');

const syncStabQuery = (key) => {
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('stab', key);
    window.history.replaceState(window.history.state, '', url);
};

const switchTab = (key) => {
    if (key === activeTab.value) {
        return;
    }
    const tab = statTabs.find((item) => item.key === key);
    if (tab?.mode && tab.mode !== activeStatsMode.value) {
        processing.value = true;
        router.visit(route('merchant.main.index'), {
            data: { mode: tab.mode, period: 'month', stab: key },
            replace: true,
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                processing.value = false;
            },
        });
        return;
    }
    if (!activatedTabs.value.includes(key)) {
        activatedTabs.value.push(key);
    }
    activeTab.value = key;
    syncStabQuery(key);
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

            <StatsTabsNav :current="activeTab" :items="statTabs" @switch="switchTab" />

            <section>
                <div v-show="isPrimaryTab">
                <div
                    class="grid gap-3"
                    :class="activeStatsMode === 'payouts' ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-2 lg:grid-cols-4'"
                >
                    <StatCard label="Оборот" prefix="$" :value="statisticsFormatted.totalTurnover" color="success">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard v-if="activeStatsMode === 'deals'" label="Доход" prefix="$" :value="statisticsFormatted.totalProfit" color="info">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Конверсия" :value="statisticsFormatted.conversionRate" color="primary">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard
                        :label="activeStatsMode === 'payouts' ? 'Успешные выплаты' : 'Успешные сделки'"
                        :value="statisticsFormatted.successOrderCount"
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
                            <StatsFilterDropdown v-if="gearFilterTypes.length" :controller="stats" />
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

                <MinAmountStatsSection class="mt-6" :statistics="enabledCardsMinAmountStatistics" />
                </div>

                <div v-if="activatedTabs.includes('api-logs')" v-show="activeTab === 'api-logs'">
                    <MerchantApiStatsWidget
                        stats-route-name="merchant.main.api-log-stats"
                        amount-distribution-route-name="merchant.merchant-api-logs.amount-distribution"
                    />
                </div>
            </section>
        </div>
    </div>
</template>
