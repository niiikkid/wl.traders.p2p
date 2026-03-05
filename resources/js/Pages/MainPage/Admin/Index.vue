<script setup>
import {Head, usePage, router} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {ref, onMounted, onBeforeUnmount, computed, watch} from 'vue';
import ApexCharts from 'apexcharts';

const statistics = usePage().props.statistics;
const chartData = usePage().props.chart;
const conversionChartData = usePage().props.conversionChart;
const hourlyConversionChartData = usePage().props.hourlyConversionChart;
const merchants = usePage().props.merchants;
const selectedMerchantId = usePage().props.selectedMerchantId;

const selectedMerchant = ref(selectedMerchantId || '');
const processing = ref(false);

const chart = ref(null);
const conversionChart = ref(null);
const hourlyConversionChart = ref(null);
const apexChart = ref(null);
const conversionApexChart = ref(null);
const hourlyConversionApexChart = ref(null);
const isMobile = ref(false);

// Скрытие/отображение панели фильтра (как в FiltersPanel.vue)
const adminFiltersStorageKey = 'display-filters-admin-main';
const adminInitialDisplay = localStorage.getItem(adminFiltersStorageKey);
const displayFilters = ref(adminInitialDisplay === 'display');
if (adminInitialDisplay === null) {
    localStorage.setItem(adminFiltersStorageKey, 'hide');
}
const toggleFiltersDisplay = () => {
    displayFilters.value = !displayFilters.value;
    localStorage.setItem(adminFiltersStorageKey, displayFilters.value ? 'display' : 'hide');
};

// Получить вычисленный цвет из текущей темы daisyUI по токену (primary, secondary, success)
// Оптимизация: переиспользуем probe-элементы, чтобы избежать лишних синхронных рефлоу/удалений
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

// Обновить цвета линий графиков согласно текущей теме
const lastAppliedColors = { primary: null, success: null, secondary: null };
const applyThemeColorsToCharts = () => {
    const primary = getThemeColor('primary');
    const success = getThemeColor('success');
    const secondary = getThemeColor('secondary');

    // Если цвета не изменились, пропускаем тяжелые обновления графиков
    if (
        lastAppliedColors.primary === primary &&
        lastAppliedColors.success === success &&
        lastAppliedColors.secondary === secondary
    ) {
        return;
    }

    if (apexChart.value) {
        apexChart.value.updateOptions({
            colors: [primary],
            markers: { colors: [primary] },
        }, false, false);
    }

    if (conversionApexChart.value) {
        conversionApexChart.value.updateOptions({
            colors: [success],
            markers: { colors: [success] },
        }, false, false);
    }

    if (hourlyConversionApexChart.value) {
        hourlyConversionApexChart.value.updateOptions({
            colors: [secondary],
            markers: { colors: [secondary] },
        }, false, false);
    }

    // Кэшируем примененные цвета
    lastAppliedColors.primary = primary;
    lastAppliedColors.success = success;
    lastAppliedColors.secondary = secondary;
};

let themeObserver = null;
let scheduledThemeUpdate = false;
const updateIsMobile = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 640;
};
const getLastPoints = (source, limit = 10) => {
    if (!source || !Array.isArray(source.data) || !Array.isArray(source.labels)) {
        return { data: [], labels: [] };
    }
    if (!isMobile.value) {
        return {
            data: source.data,
            labels: source.labels,
        };
    }
    const startIndex = Math.max(source.data.length - limit, 0);
    return {
        data: source.data.slice(startIndex),
        labels: source.labels.slice(startIndex),
    };
};
const responsiveChartData = computed(() => getLastPoints(chartData));
const responsiveConversionChartData = computed(() => getLastPoints(conversionChartData));
const responsiveHourlyConversionChartData = computed(() => getLastPoints({
    data: hourlyConversionChartData.data,
    labels: hourlyConversionChartData.labels,
}, 12));
const incomeChartTitle = computed(() =>
    isMobile.value ? 'Доходы за 10 дней' : 'Доходы за месяц'
);
const conversionChartTitle = computed(() =>
    isMobile.value ? 'Конверсия за 10 дней' : 'Конверсия за месяц'
);
const hourlyChartTitle = computed(() =>
    isMobile.value ? 'Конверсия за 12 часов' : 'Конверсия за 24 часа'
);
// Поллифилл для requestIdleCallback, чтобы не блокировать главный поток на слабых устройствах
const requestIdle =
    typeof window !== 'undefined' && window.requestIdleCallback
        ? window.requestIdleCallback
        : (cb) => setTimeout(() => cb({ didTimeout: false, timeRemaining: () => 50 }), 0);

const refreshIncomeChart = () => {
    if (!apexChart.value) return;
    const { data, labels } = responsiveChartData.value;
    apexChart.value.updateOptions({
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
const refreshConversionChart = () => {
    if (!conversionApexChart.value) return;
    const { data, labels } = responsiveConversionChartData.value;
    conversionApexChart.value.updateOptions({
        series: [{
            name: 'Конверсия (%)',
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
const refreshHourlyChart = () => {
    if (!hourlyConversionApexChart.value) return;
    const { data, labels } = responsiveHourlyConversionChartData.value;
    hourlyConversionApexChart.value.updateOptions({
        series: [{
            name: 'Конверсия по часам (%)',
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
    refreshIncomeChart();
}, { deep: true });
watch(responsiveConversionChartData, () => {
    refreshConversionChart();
}, { deep: true });
watch(responsiveHourlyConversionChartData, () => {
    refreshHourlyChart();
}, { deep: true });

// Функция для обновления статистики при нажатии на кнопку "Применить"
const applyFilter = () => {
    processing.value = true;

    // Используем location.href для добавления параметра в URL
    const baseUrl = route('admin.main.index');
    const url = selectedMerchant.value
        ? `${baseUrl}?merchant_id=${selectedMerchant.value}`
        : baseUrl;

    window.location.href = url;
};

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
        totalTurnover: formatNumber(statistics.totalTurnover),
        totalProfit: formatNumber(statistics.totalProfit),
        totalOrderCount: statistics.totalOrderCount,
        successOrderCount: statistics.successOrderCount,
        failedOrderCount: statistics.failedOrderCount,
        pendingOrderCount: statistics.pendingOrderCount,
        conversionRate: statistics.conversionRate,
    }
});

onMounted(() => {
    updateIsMobile();
    // Текущие цвета темы
    const primaryColor = getThemeColor('primary');
    const successColor = getThemeColor('success');
    const secondaryColor = getThemeColor('secondary');
    const { data: incomeData, labels: incomeLabels } = responsiveChartData.value;
    const { data: conversionData, labels: conversionLabels } = responsiveConversionChartData.value;
    const { data: hourlyData, labels: hourlyLabels } = responsiveHourlyConversionChartData.value;

    // График доходов
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
            data: incomeData,
        }],
        xaxis: {
            categories: incomeLabels, // Дни месяца
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

    apexChart.value = new ApexCharts(chart.value, options);
    apexChart.value.render();

    // График конверсии
    const conversionOptions = {
        chart: {
            type: 'line',
            height: '95%',
            background: 'transparent',
            toolbar: {
                show: false,
            },
        },
        series: [{
            name: 'Конверсия (%)',
            data: conversionData,
        }],
        xaxis: {
            categories: conversionLabels, // Дни месяца
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
                formatter: function (value) {
                    return value + '%';
                }
            },
            min: 0,
            max: 100,
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
        },
        stroke: {
            width: 2,
            curve: 'smooth',
        },
        colors: [successColor],
        markers: {
            size: 4,
            colors: [successColor],
            strokeColors: '#fff',
            strokeWidth: 2,
        },
        tooltip: {
            theme: 'dark',
            y: {
                formatter: function(value) {
                    return value + '%';
                }
            }
        },
    };

    conversionApexChart.value = new ApexCharts(conversionChart.value, conversionOptions);
    conversionApexChart.value.render();

    // График конверсии за 24 часа
    const hourlyConversionOptions = {
        chart: {
            type: 'line',
            height: '95%',
            background: 'transparent',
            toolbar: {
                show: false,
            },
        },
        series: [{
            name: 'Конверсия по часам (%)',
            data: hourlyData,
        }],
        xaxis: {
            categories: hourlyLabels, // Часы (0-23)
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
                formatter: function (value) {
                    return value + '%';
                }
            },
            min: 0,
            max: 100,
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
        },
        stroke: {
            width: 2,
            curve: 'smooth',
        },
        colors: [secondaryColor],
        markers: {
            size: 4,
            colors: [secondaryColor],
            strokeColors: '#fff',
            strokeWidth: 2,
        },
        tooltip: {
            theme: 'dark',
            y: {
                formatter: function(value) {
                    return value + '%';
                }
            }
        },
    };

    hourlyConversionApexChart.value = new ApexCharts(hourlyConversionChart.value, hourlyConversionOptions);
    hourlyConversionApexChart.value.render();

    // Наблюдать смену темы и применять новые цвета (с дебаунсом через rAF + idle)
    themeObserver = new MutationObserver(() => {
        if (scheduledThemeUpdate) return;
        scheduledThemeUpdate = true;
        // Скрываем графики на время применения темы, чтобы избежать "половинчатого" состояния
        if (chart.value) chart.value.style.visibility = 'hidden';
        if (conversionChart.value) conversionChart.value.style.visibility = 'hidden';
        if (hourlyConversionChart.value) hourlyConversionChart.value.style.visibility = 'hidden';

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                requestIdle(() => {
                    applyThemeColorsToCharts();
                    if (chart.value) chart.value.style.visibility = '';
                    if (conversionChart.value) conversionChart.value.style.visibility = '';
                    if (hourlyConversionChart.value) hourlyConversionChart.value.style.visibility = '';
                    scheduledThemeUpdate = false;
                });
            });
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    // На всякий случай применим цвета сразу после рендера
    applyThemeColorsToCharts();
    window.addEventListener('resize', updateIsMobile);
});

onBeforeUnmount(() => {
    if (themeObserver) {
        themeObserver.disconnect();
        themeObserver = null;
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile);
    }
    // Удаляем probe-элементы
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

            <!-- Кнопка показать фильтры (как в FiltersPanel.vue) -->
            <div
                class="w-full flex justify-end mr-1"
                :class="displayFilters ? 'mb-1' : 'mb-6'"
            >
                <button
                    v-if="!displayFilters"
                    @click.prevent="toggleFiltersDisplay"
                    type="button"
                    class="btn btn-sm btn-square btn-primary"
                    aria-pressed="false"
                    title="Показать фильтры"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                    </svg>
                </button>
            </div>

            <!-- Фильтр по мерчантам -->
            <div class="card bg-base-100 shadow w-full" v-show="displayFilters">
                <div class="card-body p-6">
                    <h3 class="text-base-content/70 text-lg">Показывать для мерчанта</h3>
                    <div class="flex items-center space-x-3 max-w-md">
                        <div class="flex-grow">
                            <select
                                id="merchant-filter"
                                v-model="selectedMerchant"
                                class="select select-bordered w-full"
                            >
                                <option value="">Все мерчанты</option>
                                <option v-for="merchant in merchants" :key="merchant.id" :value="merchant.id">
                                    {{ merchant.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <button
                                @click="applyFilter"
                                :disabled="processing"
                                class="btn btn-primary whitespace-nowrap"
                            >
                                {{ processing ? 'Загрузка...' : 'Применить' }}
                            </button>
                        </div>
                    </div>
                    <div class="w-full flex justify-end mb-1">
                        <button
                            v-if="displayFilters"
                            @click.prevent="toggleFiltersDisplay"
                            type="button"
                            class="btn btn-sm btn-square btn-ghost text-base-content border-base-content/30"
                            aria-pressed="true"
                            title="Скрыть фильтры"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <section>
                    <!-- Карточки статистики -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        <!-- Заработано -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Оборот</p>
                                    <p class="text-2xl font-bold text-base-content">${{ statisticsFormated.totalTurnover }}</p>
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
                                    <p class="text-base-content/70 text-lg">Доход</p>
                                    <p class="text-2xl font-bold text-base-content">${{ statisticsFormated.totalProfit }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Сделки -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Все сделки</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.totalOrderCount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- График доходов -->
                    <div class="card bg-base-100 shadow p-6 mt-8 pl-3">
                        <h2 class="text-base-content/70 text-lg pl-3">{{ incomeChartTitle }}</h2>
                        <div ref="chart" class="h-50"></div>
                    </div>

                    <!-- Панель конверсии -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 2xl:grid-cols-4 gap-6 mt-8">
                        <!-- Успешные сделки -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Успешные сделки</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.successOrderCount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Неуспешные сделки -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Неуспешные сделки</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.failedOrderCount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Активные сделки -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Активные сделки</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.pendingOrderCount }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Конверсия -->
                        <div class="card bg-base-100 shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-base-content/70 text-lg">Конверсия</p>
                                    <p class="text-2xl font-bold text-base-content">{{ statisticsFormated.conversionRate }}</p>
                                </div>
                                <div class="bg-base-200 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- График конверсии -->
                    <div class="card bg-base-100 shadow p-6 mt-8 pl-3">
                        <h2 class="text-base-content/70 text-lg pl-3">{{ conversionChartTitle }}</h2>
                        <div ref="conversionChart" class="h-50"></div>
                    </div>

                    <!-- График конверсии за 24 часа -->
                    <div class="card bg-base-100 shadow p-6 mt-8 pl-3">
                        <h2 class="text-base-content/70 text-lg pl-3">{{ hourlyChartTitle }}</h2>
                        <div ref="hourlyConversionChart" class="h-50"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
