<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { format, addDays, startOfMonth, endOfMonth, parseISO } from 'date-fns';
import { ru } from 'date-fns/locale';
import ApexCharts from 'apexcharts';
import { router, usePage } from '@inertiajs/vue3';

// Получаем данные из контроллера
const props = defineProps({
    chartData: {
        type: Object,
        required: true
    },
    currentMonth: {
        type: String,
        required: true
    },
    prevMonth: {
        type: String,
        required: true
    },
    nextMonth: {
        type: String,
        required: true
    },
    initialChartType: {
        type: String,
        default: 'turnover'
    }
});

const emit = defineEmits(['chart-type-changed']);

// Тип графика (оборот, количество сделок, доход)
const chartType = ref(props.initialChartType); // Используем переданный тип графика

const MOBILE_BREAKPOINT = 640;
const MOBILE_CHUNK_SIZE = 4;

// Функция форматирования чисел
const formatNumber = (num) => {
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// Переключение месяца для графиков
const prevMonth = () => {
    // Получаем текущие параметры URL
    const urlParams = new URLSearchParams(window.location.search);
    const tableType = urlParams.get('tableType') || 'payment-details';

    router.visit(route(route().current()), {
        data: {
            month: props.prevMonth,
            chartType: chartType.value,
            tableType: tableType,
            page: 1 // Сбрасываем пагинацию при смене месяца
        },
        preserveScroll: true,
        preserveState: false // Сбрасываем состояние для корректного обновления данных
    });
};

const nextMonth = () => {
    // Получаем текущие параметры URL
    const urlParams = new URLSearchParams(window.location.search);
    const tableType = urlParams.get('tableType') || 'payment-details';

    router.visit(route(route().current()), {
        data: {
            month: props.nextMonth,
            chartType: chartType.value,
            tableType: tableType,
            page: 1 // Сбрасываем пагинацию при смене месяца
        },
        preserveScroll: true,
        preserveState: false // Сбрасываем состояние для корректного обновления данных
    });
};

// Форматирование текущего месяца
const currentMonthDisplay = computed(() => {
    // 1) Пробуем взять месяц из пропса currentMonth (формат Y-m)
    if (props.currentMonth) {
        const [year, month] = props.currentMonth.split('-');
        if (year && month) {
            return format(new Date(parseInt(year), parseInt(month) - 1, 1), 'LLLL yyyy', { locale: ru });
        }
    }

    // 2) Фолбэк: если пропса нет/пустой, пробуем восстановить месяц из данных графика (fullDates)
    if (props.chartData?.fullDates?.length) {
        // fullDates в формате d.m.Y – преобразуем в Y-m-d
        const [day, month, year] = props.chartData.fullDates[0].split('.');
        if (day && month && year) {
            const isoDate = `${year}-${month}-${day}`;
            const date = parseISO(isoDate);
            try {
                return format(date, 'LLLL yyyy', { locale: ru });
            } catch (e) {
                // В крайнем случае вернём пустую строку
                return '';
            }
        }
    }

    return '';
});

// Ссылка на DOM-элемент для графика
const chart = ref(null);

const isMobile = ref(false);
const updateIsMobile = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < MOBILE_BREAKPOINT;
};

const sumRange = (array, start, end) => {
    if (!Array.isArray(array)) return 0;
    let sum = 0;
    for (let i = start; i < end; i++) {
        sum += Number(array[i] ?? 0);
    }
    return sum;
};

const normalizeChartData = computed(() => {
    const data = props.chartData || {};
    return {
        labels: Array.isArray(data.labels) ? data.labels : [],
        fullDates: Array.isArray(data.fullDates) ? data.fullDates : [],
        turnoverData: Array.isArray(data.turnoverData) ? data.turnoverData : [],
        incomeData: Array.isArray(data.incomeData) ? data.incomeData : [],
        ordersCountData: Array.isArray(data.ordersCountData) ? data.ordersCountData : [],
        totalOrders: data.totalOrders ?? 0,
        totalIncome: data.totalIncome ?? 0,
        totalTurnover: data.totalTurnover ?? 0,
    };
});

const aggregateChartData = (data) => {
    const aggregated = {
        labels: [],
        fullDates: [],
        turnoverData: [],
        incomeData: [],
        ordersCountData: [],
        totalOrders: data.totalOrders,
        totalIncome: data.totalIncome,
        totalTurnover: data.totalTurnover,
    };

    const length = data.labels.length;

    for (let i = 0; i < length; i += MOBILE_CHUNK_SIZE) {
        const end = Math.min(i + MOBILE_CHUNK_SIZE, length);

        const labelStart = data.labels[i];
        const labelEnd = data.labels[end - 1];
        aggregated.labels.push(
            labelStart === labelEnd || !labelEnd ? labelStart : `${labelStart}-${labelEnd}`
        );

        const fullStart = data.fullDates[i];
        const fullEnd = data.fullDates[end - 1];
        aggregated.fullDates.push(
            fullStart && fullEnd
                ? (fullStart === fullEnd ? fullStart : `${fullStart} – ${fullEnd}`)
                : (fullStart || fullEnd || '')
        );

        aggregated.turnoverData.push(sumRange(data.turnoverData, i, end));
        aggregated.incomeData.push(sumRange(data.incomeData, i, end));
        aggregated.ordersCountData.push(sumRange(data.ordersCountData, i, end));
    }

    return aggregated;
};

const displayChartData = computed(() => {
    const baseData = normalizeChartData.value;
    if (!isMobile.value) {
        return baseData;
    }
    return aggregateChartData(baseData);
});

// Получение настроек графика в зависимости от выбранного типа
const getChartOptions = () => {
    const dataSource = displayChartData.value;
    let seriesName, seriesData, color, formatter;

    switch(chartType.value) {
        case 'orders':
            seriesName = 'Сделок';
            seriesData = dataSource.ordersCountData;
            color = '#f59e0b'; // Оранжевый/Янтарный
            formatter = (value) => Math.round(value);
            break;
        case 'income':
            seriesName = 'Доход ($)';
            seriesData = dataSource.incomeData;
            color = '#3b82f6'; // Синий
            formatter = (value) => '$' + value;
            break;
        case 'turnover':
        default:
            seriesName = 'Оборот ($)';
            seriesData = dataSource.turnoverData;
            color = '#10b981'; // Зеленый
            formatter = (value) => '$' + value;
            break;
    }

    return {
        chart: {
            type: 'line',
            height: '95%',
            background: 'transparent',
            toolbar: {
                show: false,
            },
        },
        series: [{
            name: seriesName,
            data: seriesData,
        }],
        xaxis: {
            categories: dataSource.labels,
            labels: {
                style: {
                    colors: '#999',
                },
                rotateAlways: false,
                hideOverlappingLabels: true,
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
                formatter: formatter
            },
        },
        grid: {
            borderColor: 'rgba(200, 200, 200, 0.1)',
        },
        stroke: {
            width: 2,
            curve: 'smooth',
        },
        colors: [color],
        markers: {
            size: 4,
            colors: [color],
            strokeColors: '#fff',
            strokeWidth: 2,
        },
        tooltip: {
            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
            x: {
                formatter: (value, { dataPointIndex }) => dataSource.fullDates?.[dataPointIndex] || value
            },
            y: {
                formatter: formatter
            }
        },
    };
};

// Функция для рендеринга графика
const renderChart = () => {
    // Уничтожаем предыдущий график, если он существует
    if (chart.value && chart.value.__chartInstance) {
        chart.value.__chartInstance.destroy();
    }

    // Создаем новый график
    const options = getChartOptions();
    const apexChart = new ApexCharts(chart.value, options);
    chart.value.__chartInstance = apexChart;
    apexChart.render();
};

// Следим за изменением типа графика
watch(chartType, (newType) => {
    renderChart();
    emit('chart-type-changed', newType);

    // Обновляем URL параметры без перезагрузки страницы
    const urlParams = new URLSearchParams(window.location.search);
    const month = urlParams.get('month') || props.currentMonth;
    const tableType = urlParams.get('tableType') || 'payment-details';

    router.visit(route(route().current()), {
        data: {
            month: month,
            chartType: newType,
            tableType: tableType,
            page: 1 // Сбрасываем пагинацию при смене типа графика
        },
        preserveScroll: true,
        preserveState: true,
        only: []
    });
});

// Следим за изменением initialChartType из props
watch(() => props.initialChartType, (newType) => {
    if (newType !== chartType.value) {
        chartType.value = newType;
    }
});

// Следим за изменением данных графика
watch(displayChartData, () => {
    renderChart();
}, { deep: true });

watch(isMobile, () => {
    renderChart();
});

// Рендерим график при монтировании компонента
onMounted(() => {
    // Проверяем URL параметры при загрузке
    const urlParams = new URLSearchParams(window.location.search);
    const chartTypeParam = urlParams.get('chartType');

    if (chartTypeParam && ['turnover', 'income', 'orders'].includes(chartTypeParam)) {
        chartType.value = chartTypeParam;
    }

    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);

    renderChart();
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile);
    }
});

// Изменение типа графика
const setChartType = (type) => {
    chartType.value = type;
};

// Получение иконки для типа графика
const getIconForType = (type) => {
    switch(type) {
        case 'orders':
            return 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'; // График
        case 'income':
            return 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'; // Деньги
        case 'turnover':
        default:
            return 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; // Монета
    }
};

// Получение цвета для типа графика
const getColorForType = (type) => {
    switch(type) {
        case 'orders':
            return 'yellow'; // Желтый
        case 'income':
            return 'blue'; // Синий
        case 'turnover':
        default:
            return 'green'; // Зеленый
    }
};

// Получение заголовка для типа графика
const getTitleForType = (type) => {
    switch(type) {
        case 'orders':
            return 'Сделок';
        case 'income':
            return 'Доход';
        case 'turnover':
        default:
            return 'Оборот';
    }
};

// Получение значения для типа графика
const getValueForType = (type) => {
    switch(type) {
        case 'orders':
            return props.chartData.totalOrders;
        case 'income':
            return '$' + formatNumber(props.chartData.totalIncome);
        case 'turnover':
        default:
            return '$' + formatNumber(props.chartData.totalTurnover);
    }
};

const chartTypeOptions = computed(() => {
    return ['turnover', 'income', 'orders'].map((type) => ({
        value: type,
        label: `${getTitleForType(type)}: ${getValueForType(type)}`
    }));
});
</script>

<template>
    <section>
        <!-- Переключатели типа графика -->
        <div class="sm:flex justify-between items-end sm:gap-4 flex-wrap md:flex-nowrap">
            <div class="w-full sm:w-auto">
                <div class="join sm:mb-6 hidden sm:inline-flex">
                    <button class="btn btn-sm join-item" :class="{ 'btn-active btn-primary': chartType === 'turnover' }" @click="setChartType('turnover')">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Оборот: ${{ formatNumber(chartData.totalTurnover) }}
                    </button>
                    <button class="btn btn-sm join-item" :class="{ 'btn-active btn-primary': chartType === 'income' }" @click="setChartType('income')">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Доход: ${{ formatNumber(chartData.totalIncome) }}
                    </button>
                    <button class="btn btn-sm join-item" :class="{ 'btn-active btn-primary': chartType === 'orders' }" @click="setChartType('orders')">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Сделки: {{ chartData.totalOrders }}
                    </button>
                </div>
                <div class="mb-3 sm:hidden">
                    <select class="select select-bordered select-sm w-full" v-model="chartType">
                        <option v-for="option in chartTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="flex justify-between items-center mb-3">
                <div class="flex items-center space-x-2">
                    <button @click="prevMonth" class="btn btn-ghost btn-square btn-sm">
                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button @click="nextMonth" class="btn btn-ghost btn-square btn-sm">
                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- График -->
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h4 class="card-title">
                    {{ chartType === 'turnover' ? 'Оборот' : chartType === 'orders' ? 'Сделок' : 'Доходы' }} за {{ currentMonthDisplay }}
                </h4>
                <div ref="chart" class="h-50"></div>
            </div>
        </div>
    </section>
</template>
