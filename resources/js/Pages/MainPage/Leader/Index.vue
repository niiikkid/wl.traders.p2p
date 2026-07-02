<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/MainPage/StatCard.vue';
import DashboardChartCard from '@/Components/MainPage/DashboardChartCard.vue';
import DashboardChart from '@/Components/Charts/DashboardChart.vue';

const page = usePage();
const statistics = computed(() => page.props.statistics || {});
const chartData = computed(() => page.props.chart || { labels: [], data: [] });

const formatNumber = (value) => {
    const rounded = Math.round(Number(value ?? 0) * 100) / 100;
    return rounded.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const statisticsFormatted = computed(() => ({
    totalProfit: formatNumber(statistics.value.totalProfit),
    balance: formatNumber(statistics.value.balance),
    successOrderCount: statistics.value.successOrderCount ?? 0,
    referralsCount: statistics.value.referralsCount ?? 0,
    referralRate: statistics.value.referralRate ?? 0,
}));

const isMobile = ref(false);
const updateIsMobile = () => {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 640;
    }
};

const responsiveChart = computed(() => {
    const source = chartData.value;
    if (!Array.isArray(source.data) || !Array.isArray(source.labels)) {
        return { labels: [], data: [] };
    }
    if (!isMobile.value) {
        return { labels: source.labels, data: source.data };
    }
    const startIndex = Math.max(source.data.length - 10, 0);
    return {
        labels: source.labels.slice(startIndex),
        data: source.data.slice(startIndex),
    };
});

const chartTitle = computed(() => (isMobile.value ? 'Доходы за 10 дней' : 'Доходы за месяц'));

const chartSeries = computed(() => [{
    name: 'Доход ($)',
    data: responsiveChart.value.data,
    colorToken: 'primary',
}]);

onMounted(() => {
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile);
    }
});

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

            <section>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <StatCard label="Доход" prefix="$" :value="statisticsFormatted.totalProfit" color="success">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Комиссия" suffix="%" :value="statisticsFormatted.referralRate" color="error">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Баланс" prefix="$" :value="statisticsFormatted.balance" color="primary">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Трейдеров" :value="statisticsFormatted.referralsCount" color="info">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </template>
                    </StatCard>

                    <StatCard label="Сделки" :value="statisticsFormatted.successOrderCount" color="warning">
                        <template #icon>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </template>
                    </StatCard>
                </div>

                <DashboardChartCard :title="chartTitle">
                    <DashboardChart
                        :labels="responsiveChart.labels"
                        :series="chartSeries"
                        value-type="money"
                        :y-min="0"
                    />
                </DashboardChartCard>
            </section>
        </div>
    </div>
</template>
