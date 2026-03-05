<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {ref, onMounted, onBeforeUnmount, computed, watch} from 'vue';
import ApexCharts from 'apexcharts';

const statistics = usePage().props.statistics;
const chartData = usePage().props.chart;

const formatNumber = (num) => { //TODO move to utils
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const statisticsFormated = computed(() => {
    return {
        totalProfit: formatNumber(statistics.totalProfit),
        totalWithdrawalAmount: formatNumber(statistics.totalWithdrawalAmount),
        balance: formatNumber(statistics.balance),
        successOrderCount: statistics.successOrderCount,
    }
});


const chart = ref(null);
const isMobile = ref(false);

// Получение цвета из активной темы daisyUI (с переиспользованием probe-элементов)
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
    const color = getComputedStyle(span).color || '#6366f1';
    return color;
};

let themeObserver = null;
let scheduledThemeUpdate = false;
let apexChartInstance = null;
const requestIdle =
    typeof window !== 'undefined' && window.requestIdleCallback
        ? window.requestIdleCallback
        : (cb) => setTimeout(() => cb({ didTimeout: false, timeRemaining: () => 50 }), 0);

const updateIsMobile = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 640;
};

const responsiveChartData = computed(() => {
    if (!chartData || !Array.isArray(chartData.data) || !Array.isArray(chartData.labels)) {
        return { data: [], labels: [] };
    }
    if (!isMobile.value) {
        return {
            data: chartData.data,
            labels: chartData.labels,
        };
    }
    const startIndex = Math.max(chartData.data.length - 10, 0);
    return {
        data: chartData.data.slice(startIndex),
        labels: chartData.labels.slice(startIndex),
    };
});

const chartTitle = computed(() =>
    isMobile.value ? 'Доходы за 10 дней' : 'Доходы за месяц'
);

const refreshChart = () => {
    if (!apexChartInstance) return;
    const { data, labels } = responsiveChartData.value;
    apexChartInstance.updateOptions({
        series: [{
            name: 'Доходы ($)',
            data,
        }],
        xaxis: {
            categories: labels,
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
    }, false, false);
};

watch(responsiveChartData, () => {
    refreshChart();
}, { deep: true });

onMounted(() => {
    updateIsMobile();
    const primaryColor = getThemeColor('primary');
    const { data: initialData, labels: initialLabels } = responsiveChartData.value;

    const options = {
        chart: {
            type: 'line',
            height: '95%',
            background: 'transparent',
            toolbar: {
                show: false,
            },
        },
        series: [{
            name: 'Доходы ($)',
            data: initialData,
        }],
        xaxis: {
            categories: initialLabels, // Дни месяца
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
            labels: {
                style: {
                    colors: '#999',
                },
            },
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
        },
        stroke: {
            width: 2,
            curve: 'smooth',
        },
        colors: [primaryColor],
        markers: {
            size: 4,
            colors: [primaryColor],
            strokeColors: '#fff',
            strokeWidth: 2,
        },
        tooltip: {
            theme: 'dark',
        },
    };

    apexChartInstance = new ApexCharts(chart.value, options);
    apexChartInstance.render();
    window.addEventListener('resize', updateIsMobile);

    // Реакция на смену темы (батчим обновление и скрываем график во время применения темы)
    themeObserver = new MutationObserver(() => {
        if (scheduledThemeUpdate) return;
        scheduledThemeUpdate = true;
        if (chart.value) chart.value.style.visibility = 'hidden';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                requestIdle(() => {
                    const newPrimary = getThemeColor('primary');
                    apexChartInstance?.updateOptions({
                        colors: [newPrimary],
                        markers: { colors: [newPrimary] },
                    }, false, false);
                    if (chart.value) chart.value.style.visibility = '';
                    scheduledThemeUpdate = false;
                });
            });
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

onBeforeUnmount(() => {
    if (themeObserver) {
        themeObserver.disconnect();
        themeObserver = null;
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile);
    }
    if (apexChartInstance) {
        apexChartInstance.destroy();
        apexChartInstance = null;
    }
    Object.values(colorProbeSpans).forEach((span) => {
        if (span && span.parentNode) {
            span.parentNode.removeChild(span);
        }
    });
});



defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Главная"/>

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Главная</h2>
                <slot name="button"></slot>
            </div>
            <div>
                <section>
                    <!-- Карточки статистики -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 2xl:grid-cols-4 gap-6">
                        <!-- Заработано -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Заработано</p>
                                    <p class="text-2xl font-bold text-base-content">${{ statisticsFormated.totalProfit }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Выплачено -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Выплачено</p>
                                    <p class="text-2xl font-bold text-base-content">${{ statisticsFormated.totalWithdrawalAmount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Баланс -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Баланс</p>
                                    <p class="text-2xl font-bold text-base-content">${{ statisticsFormated.balance }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Сделки -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Сделки</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.successOrderCount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- График -->


                    <div class="card bg-base-100 shadow p-6 mt-8 pl-3">
                        <h2 class="text-base-content/70 text-lg pl-3">{{ chartTitle }}</h2>
                        <div ref="chart" class="h-50"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
