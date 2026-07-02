<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import WidgetHeader from '@/Components/MainPage/WidgetHeader.vue';

const chartData = ref({ labels: [], series: [] });
const loading = ref(false);
const loaded = ref(false);
const errored = ref(false);

const chartEl = ref(null);
const apexChart = ref(null);

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
    return getComputedStyle(span).color || '#6366f1';
};

const hasData = () => chartData.value.series.some((serie) => (serie.data || []).some((value) => value > 0));

const renderChart = async () => {
    await nextTick();
    if (!chartEl.value) {
        return;
    }

    const colors = [getThemeColor('primary'), getThemeColor('secondary'), getThemeColor('error')];

    if (apexChart.value) {
        apexChart.value.updateOptions({
            series: chartData.value.series,
            xaxis: { categories: chartData.value.labels },
            colors,
        }, false, false);
        return;
    }

    const { default: ApexCharts } = await import('apexcharts');
    apexChart.value = new ApexCharts(chartEl.value, {
        chart: { type: 'bar', height: 280, background: 'transparent', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        series: chartData.value.series,
        xaxis: {
            categories: chartData.value.labels,
            labels: { style: { colors: '#999' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: '#999' } } },
        grid: { borderColor: 'rgba(200, 200, 200, 0.1)' },
        legend: { labels: { colors: '#999' } },
        colors,
        tooltip: { theme: 'dark' },
    });
    apexChart.value.render();
};

const load = async () => {
    if (loading.value) {
        return;
    }
    loading.value = true;
    errored.value = false;

    try {
        const { data } = await axios.get(route('admin.main.anti-fraud-stats'));
        chartData.value = data.chart || { labels: [], series: [] };
        loaded.value = true;
        await renderChart();
    } catch (error) {
        errored.value = true;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    load();
});

onBeforeUnmount(() => {
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
    Object.values(colorProbeSpans).forEach((span) => span?.parentNode?.removeChild(span));
});
</script>

<template>
    <div class="space-y-3">
        <WidgetHeader title="Антифрод за 24 часа" :loading="loading" @refresh="load" />

        <div class="card border border-base-300 bg-base-100 p-4 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base-content/70 text-lg">Антифрод за 24 часа</h3>
                <div class="flex items-center gap-2 text-xs">
                    <span class="badge badge-primary badge-sm">Уникальные</span>
                    <span class="badge badge-secondary badge-sm">Повторные</span>
                    <span class="badge badge-error badge-sm">Блокировки</span>
                </div>
            </div>

            <div v-if="loading && !loaded" class="skeleton h-60 w-full"></div>
            <div v-else-if="errored" class="flex h-60 items-center justify-center text-sm text-error">
                Не удалось загрузить статистику
            </div>
            <div v-else-if="loaded && !hasData()" class="flex h-60 items-center justify-center text-sm text-base-content/60">
                Нет данных за последние 24 часа
            </div>
            <div v-show="loaded && hasData()" ref="chartEl" class="h-60"></div>
        </div>
    </div>
</template>
