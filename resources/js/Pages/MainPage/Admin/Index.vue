<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppTooltip from '@/Components/AppTooltip.vue';
import StatsTabsNav from '@/Components/MainPage/StatsTabsNav.vue';
import StatCard from '@/Components/MainPage/StatCard.vue';
import ChartTypeTabs from '@/Components/MainPage/ChartTypeTabs.vue';
import PeriodPresetControls from '@/Components/MainPage/PeriodPresetControls.vue';
import PeriodNavigator from '@/Components/MainPage/PeriodNavigator.vue';
import StatsFilterDropdown from '@/Components/MainPage/StatsFilterDropdown.vue';
import DashboardChartCard from '@/Components/MainPage/DashboardChartCard.vue';
import DashboardChart from '@/Components/Charts/DashboardChart.vue';
import AntiFraudStatsWidget from './Components/AntiFraudStatsWidget.vue';
import MerchantApiStatsWidget from '@/Components/MainPage/MerchantApiStatsWidget.vue';
import EnabledCardsStatsWidget from './Components/EnabledCardsStatsWidget.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';
import DisputesIcon from '@/Layouts/Partials/Icons/DisputesIcon.vue';
import LogsIcon from '@/Layouts/Partials/Icons/LogsIcon.vue';
import AntiFraudIcon from '@/Layouts/Partials/Icons/AntiFraudIcon.vue';
import PaymentDetailsIcon from '@/Layouts/Partials/Icons/PaymentDetailsIcon.vue';
import { useDashboardStats } from '@/composables/useDashboardStats.js';
import { getLastPoints, normalizeChartLabels } from '@/utils/dashboardChart.js';

const page = usePage();
const activeStatsMode = computed(() => (page.props.activeStatsMode === 'payouts' ? 'payouts' : 'deals'));
const statistics = computed(() => page.props.statistics || {});
const disputeStatistics = computed(() => page.props.disputeStatistics || {});
const merchantChartSeries = computed(() => page.props.merchantChartSeries || {});

const chartSources = {
    income: computed(() => page.props.chart || { labels: [], data: [] }),
    conversion: computed(() => page.props.conversionChart || { labels: [], data: [] }),
    turnover: computed(() => page.props.turnoverChart || { labels: [], data: [] }),
    orders: computed(() => page.props.ordersChart || { labels: [], data: [] }),
    average_check: computed(() => page.props.averageCheckChart || { labels: [], data: [] }),
};

const disputeSources = {
    total_disputes: computed(() => page.props.disputesChart || { labels: [], data: [] }),
    rejected_disputes: computed(() => page.props.rejectedDisputesChart || { labels: [], data: [] }),
    total_dispute_volume: computed(() => page.props.disputesVolumeChart || { labels: [], data: [] }),
    rejected_dispute_volume: computed(() => page.props.rejectedDisputesVolumeChart || { labels: [], data: [] }),
};

const statTabs = [
    { key: 'deals', label: 'Сделки', icon: OrdersIcon, mode: 'deals' },
    { key: 'payouts', label: 'Выплаты', icon: PayoutsIcon, mode: 'payouts' },
    { key: 'disputes', label: 'Споры', icon: DisputesIcon, mode: 'deals' },
    { key: 'api-logs', label: 'API-логи', icon: LogsIcon },
    { key: 'anti-fraud', label: 'Антифрод', icon: AntiFraudIcon },
    { key: 'enabled-cards', label: 'Реквизиты', icon: PaymentDetailsIcon },
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

const stats = useDashboardStats({
    routeName: 'admin.main.index',
    filterOptionsRouteName: 'admin.main.filter-options',
    activeStatsMode,
    filterTypesByMode: {
        deals: [
            { key: 'trader', label: 'Трейдер', requestKey: 'trader_ids', placeholder: 'Поиск по имени трейдера...' },
            { key: 'payment_method', label: 'Метод', requestKey: 'payment_method_ids', placeholder: 'Поиск платежного метода...' },
            { key: 'payment_detail', label: 'Реквизит', requestKey: 'payment_detail_ids', placeholder: 'Поиск реквизита...' },
            { key: 'merchant', label: 'Мерчант', requestKey: 'merchant_ids', placeholder: 'Поиск магазина мерчанта...' },
        ],
        payouts: [
            { key: 'trader', label: 'Трейдер', requestKey: 'trader_ids', placeholder: 'Поиск по имени трейдера...' },
            { key: 'merchant', label: 'Мерчант', requestKey: 'merchant_ids', placeholder: 'Поиск магазина мерчанта...' },
        ],
    },
    filterPropMap: {
        traderIds: 'trader',
        paymentMethodIds: 'payment_method',
        paymentDetailIds: 'payment_detail',
        merchantIds: 'merchant',
    },
    extraRequestData: () => ({ stab: activeTab.value }),
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
    selectedFilters,
} = stats;

const activeChartTab = ref('income');
const chartDisplayMode = ref('total');
const activeDisputeChartTab = ref('total_disputes');

const apiRequestStatsTooltip = 'К данному показателю применяются только фильтры по временному промежутку. Фильтры по трейдеру, методу, реквизитам и мерчантам не учитываются.';

const statisticsFormatted = computed(() => ({
    totalTurnover: statistics.value?.totalTurnover ?? '0.00',
    totalProfit: statistics.value?.totalProfit ?? '0.00',
    totalOrderCount: statistics.value?.totalOrderCount ?? 0,
    successOrderCount: statistics.value?.successOrderCount ?? 0,
    failedOrderCount: statistics.value?.failedOrderCount ?? 0,
    pendingOrderCount: statistics.value?.pendingOrderCount ?? 0,
    conversionRate: statistics.value?.conversionRate ?? '0%',
    apiRequestStats: statistics.value?.apiRequestStats ?? {
        processing_rate_formatted: '0%',
        success_count: 0,
        failed_count: 0,
        total_count: 0,
    },
}));

const disputeStatisticsFormatted = computed(() => ({
    totalDisputeCount: disputeStatistics.value?.totalDisputeCount ?? 0,
    acceptedDisputeCount: disputeStatistics.value?.acceptedDisputeCount ?? 0,
    rejectedDisputeCount: disputeStatistics.value?.rejectedDisputeCount ?? 0,
    rejectedDisputeRate: disputeStatistics.value?.rejectedDisputeRate ?? '0%',
    totalDisputeVolume: disputeStatistics.value?.totalDisputeVolume ?? '0.00',
    rejectedDisputeVolume: disputeStatistics.value?.rejectedDisputeVolume ?? '0.00',
}));

const chartTabs = computed(() => {
    const baseTabs = [
        { value: 'income', label: 'Доход', colorToken: 'primary', seriesName: 'Доход ($)', valueType: 'money' },
        { value: 'turnover', label: 'Оборот', colorToken: 'secondary', seriesName: 'Оборот ($)', valueType: 'money' },
        { value: 'conversion', label: 'Конверсия', colorToken: 'success', seriesName: 'Конверсия (%)', valueType: 'percent' },
    ];
    const tail = activeStatsMode.value === 'deals'
        ? [
            { value: 'orders', label: 'Количество сделок', colorToken: 'accent', seriesName: 'Сделок', valueType: 'count' },
            { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)', valueType: 'money' },
        ]
        : [
            { value: 'orders', label: 'Количество выплат', colorToken: 'accent', seriesName: 'Выплат', valueType: 'count' },
            { value: 'average_check', label: 'Средний чек', colorToken: 'info', seriesName: 'Средний чек ($)', valueType: 'money' },
        ];
    return [...baseTabs, ...tail];
});

const disputeChartTabs = [
    { value: 'total_disputes', label: 'Все споры', colorToken: 'warning', seriesName: 'Споров', valueType: 'count' },
    { value: 'rejected_disputes', label: 'Отклонённые споры', colorToken: 'error', seriesName: 'Отклонённых споров', valueType: 'count' },
    { value: 'total_dispute_volume', label: 'Объём споров', colorToken: 'info', seriesName: 'Объём споров ($)', valueType: 'money' },
    { value: 'rejected_dispute_volume', label: 'Объём отклонённых', colorToken: 'secondary', seriesName: 'Объём отклонённых ($)', valueType: 'money' },
];

const activeTabConfig = computed(() => chartTabs.value.find((tab) => tab.value === activeChartTab.value) || chartTabs.value[0]);
const activeTabTitle = computed(() => activeTabConfig.value.label);

const activeData = computed(() => getLastPoints(
    normalizeChartLabels(chartSources[activeChartTab.value].value, selectedPeriodPreset.value),
    isMobile.value,
));

const hasSelectedMerchants = computed(() => (selectedFilters.value.merchant || []).length > 0);
const activeMerchantSeries = computed(() => {
    const series = merchantChartSeries.value?.[activeChartTab.value];
    return Array.isArray(series) ? series.filter((item) => Array.isArray(item?.data)) : [];
});
const canShowMerchantSeries = computed(() => hasSelectedMerchants.value && activeMerchantSeries.value.length > 0);
const isMerchantSplitMode = computed(() => canShowMerchantSeries.value && chartDisplayMode.value === 'by_merchant');

const activeSeries = computed(() => {
    if (isMerchantSplitMode.value) {
        return activeMerchantSeries.value.map((item) => ({ name: item.name, data: item.data }));
    }
    const series = [{
        name: activeTabConfig.value.seriesName,
        data: activeData.value.data,
        colorToken: activeTabConfig.value.colorToken,
    }];
    if (Array.isArray(activeData.value.shadowData) && activeData.value.shadowData.length > 0) {
        series.push({
            name: `${activeTabConfig.value.seriesName} · пред. период`,
            data: activeData.value.shadowData,
            dashed: true,
        });
    }
    return series;
});

const activeYMax = computed(() => (activeTabConfig.value.valueType === 'percent' ? 100 : null));
const chartHeight = computed(() => (isMerchantSplitMode.value ? '18.75rem' : '13rem'));

const activeDisputeTabConfig = computed(() => disputeChartTabs.find((tab) => tab.value === activeDisputeChartTab.value) || disputeChartTabs[0]);
const activeDisputeTabTitle = computed(() => activeDisputeTabConfig.value.label);
const activeDisputeData = computed(() => getLastPoints(
    normalizeChartLabels(disputeSources[activeDisputeChartTab.value].value, selectedPeriodPreset.value),
    isMobile.value,
));
const disputeSeries = computed(() => {
    const series = [{
        name: activeDisputeTabConfig.value.seriesName,
        data: activeDisputeData.value.data,
        colorToken: activeDisputeTabConfig.value.colorToken,
    }];
    if (Array.isArray(activeDisputeData.value.shadowData) && activeDisputeData.value.shadowData.length > 0) {
        series.push({
            name: `${activeDisputeTabConfig.value.seriesName} · пред. период`,
            data: activeDisputeData.value.shadowData,
            dashed: true,
        });
    }
    return series;
});

watch(chartTabs, (tabs) => {
    if (!tabs.some((tab) => tab.value === activeChartTab.value)) {
        activeChartTab.value = 'income';
    }
}, { deep: true });

watch(canShowMerchantSeries, (canShow) => {
    if (!canShow) {
        chartDisplayMode.value = 'total';
    }
});

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
        router.visit(route('admin.main.index'), {
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
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

                        <StatCard
                            :label="activeStatsMode === 'payouts' ? 'Все выплаты' : 'Все сделки'"
                            :value="statisticsFormatted.totalOrderCount"
                            color="warning"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            :label="activeStatsMode === 'payouts' ? 'Успешные выплаты' : 'Успешные сделки'"
                            :value="statisticsFormatted.successOrderCount"
                            color="success"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            :label="activeStatsMode === 'payouts' ? 'Отменённые выплаты' : 'Неуспешные сделки'"
                            :value="statisticsFormatted.failedOrderCount"
                            color="error"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            v-if="activeStatsMode === 'payouts'"
                            label="Активные выплаты"
                            :value="statisticsFormatted.pendingOrderCount"
                            color="warning"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            v-else
                            label="Обработка API"
                            :value="statisticsFormatted.apiRequestStats.processing_rate_formatted"
                            color="info"
                        >
                            <template #label-suffix>
                                <AppTooltip :tip="apiRequestStatsTooltip" placement="top" wrapper-class="inline-flex shrink-0">
                                    <svg class="h-3.5 w-3.5 cursor-help text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0" />
                                    </svg>
                                </AppTooltip>
                            </template>
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 13h6m-6 4h10a2 2 0 002-2V7a2 2 0 00-2-2H8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            :label="activeStatsMode === 'payouts' ? 'Конверсия выплат' : 'Конверсия'"
                            :value="statisticsFormatted.conversionRate"
                            color="primary"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </template>
                        </StatCard>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 xl:flex-row lg:items-start xl:justify-between">
                        <ChartTypeTabs v-model="activeChartTab" :tabs="chartTabs" />

                        <div class="flex flex-col gap-2 lg:items-end">
                            <div class="flex items-start gap-2">
                                <StatsFilterDropdown :controller="stats" />
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
                        :height="chartHeight"
                        @prev="navigatePeriod(-1)"
                        @next="navigatePeriod(1)"
                    >
                        <DashboardChart
                            :labels="activeData.labels"
                            :series="activeSeries"
                            :value-type="activeTabConfig.valueType"
                            :y-min="0"
                            :y-max="activeYMax"
                            :show-legend="isMerchantSplitMode"
                        />
                    </DashboardChartCard>

                    <div v-if="canShowMerchantSeries" class="mt-2 flex justify-end">
                        <div class="join join-horizontal">
                            <button
                                type="button"
                                class="btn btn-sm join-item"
                                :class="chartDisplayMode === 'total' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                @click="chartDisplayMode = 'total'"
                            >
                                Общее
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm join-item"
                                :class="chartDisplayMode === 'by_merchant' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                                @click="chartDisplayMode = 'by_merchant'"
                            >
                                По мерчантам
                            </button>
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'disputes'">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <StatCard label="Всего споров" :value="disputeStatisticsFormatted.totalDisputeCount" color="warning">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-2v4l-4-4H9a2 2 0 0 1-1.414-.586m0 0L11 14h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2v4z" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard label="Принято споров" :value="disputeStatisticsFormatted.acceptedDisputeCount" color="success">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 12l2 2l4-4m6 2a9 9 0 1 1-18 0a9 9 0 0 1 18 0" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard label="Отклонено споров" :value="disputeStatisticsFormatted.rejectedDisputeCount" color="error">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 1 1-18 0a9 9 0 0 1 18 0" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard label="Доля отклонённых" :value="disputeStatisticsFormatted.rejectedDisputeRate" color="primary">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1 0 20.945 13H11z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.03 9.03 0 0 1 20.488 9" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard label="Объём споров" prefix="$" :value="disputeStatisticsFormatted.totalDisputeVolume" color="info">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2s3 .895 3 2s-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0" />
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard label="Объём отклонённых" prefix="$" :value="disputeStatisticsFormatted.rejectedDisputeVolume" color="secondary">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 0 0-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v16l4-2l4 2l4-2z" />
                                </svg>
                            </template>
                        </StatCard>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <ChartTypeTabs v-model="activeDisputeChartTab" :tabs="disputeChartTabs" />
                    </div>

                    <DashboardChartCard :title="activeDisputeTabTitle">
                        <DashboardChart
                            :labels="activeDisputeData.labels"
                            :series="disputeSeries"
                            :value-type="activeDisputeTabConfig.valueType"
                            :y-min="0"
                        />
                    </DashboardChartCard>
                </div>

                <div v-if="activatedTabs.includes('api-logs')" v-show="activeTab === 'api-logs'">
                    <MerchantApiStatsWidget />
                </div>

                <div v-if="activatedTabs.includes('anti-fraud')" v-show="activeTab === 'anti-fraud'">
                    <AntiFraudStatsWidget />
                </div>

                <div v-if="activatedTabs.includes('enabled-cards')" v-show="activeTab === 'enabled-cards'">
                    <EnabledCardsStatsWidget />
                </div>
            </section>
        </div>
    </div>
</template>
