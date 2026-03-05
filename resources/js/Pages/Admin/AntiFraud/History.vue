<script setup>
import { Head } from '@inertiajs/vue3';
import { usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import ApexCharts from 'apexcharts';

defineOptions({ layout: AuthenticatedLayout });

const logs = usePage().props.logs;
const chartData = usePage().props.chart || { labels: [], series: [] };

const chart = ref(null);
const apexChart = ref(null);

const chartStorageKey = 'display-antifraud-history-chart';
const chartInitialDisplay = localStorage.getItem(chartStorageKey);
const displayChart = ref(chartInitialDisplay === 'display');
if (chartInitialDisplay === null) {
    localStorage.setItem(chartStorageKey, 'hide');
}
const toggleChartDisplay = () => {
    displayChart.value = !displayChart.value;
    localStorage.setItem(chartStorageKey, displayChart.value ? 'display' : 'hide');
};

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

const lastAppliedColors = { primary: null, secondary: null, error: null };
const applyThemeColorsToChart = () => {
    const primary = getThemeColor('primary');
    const secondary = getThemeColor('secondary');
    const error = getThemeColor('error');

    if (
        lastAppliedColors.primary === primary &&
        lastAppliedColors.secondary === secondary &&
        lastAppliedColors.error === error
    ) {
        return;
    }

    if (apexChart.value) {
        apexChart.value.updateOptions({
            colors: [primary, secondary, error],
        }, false, false);
    }

    lastAppliedColors.primary = primary;
    lastAppliedColors.secondary = secondary;
    lastAppliedColors.error = error;
};

let themeObserver = null;
let scheduledThemeUpdate = false;

const mountChart = () => {
    if (!chart.value || apexChart.value) {
        return;
    }

    const primaryColor = getThemeColor('primary');
    const secondaryColor = getThemeColor('secondary');
    const errorColor = getThemeColor('error');

    const options = {
        chart: {
            type: 'bar',
            height: 280,
            background: 'transparent',
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
            },
        },
        dataLabels: {
            enabled: false,
        },
        series: chartData.series,
        xaxis: {
            categories: chartData.labels,
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
        legend: {
            labels: {
                colors: '#999',
            },
        },
        colors: [primaryColor, secondaryColor, errorColor],
        tooltip: {
            theme: 'dark',
        },
    };

    apexChart.value = new ApexCharts(chart.value, options);
    apexChart.value.render();
    applyThemeColorsToChart();
};

watch(displayChart, (value) => {
    if (value) {
        nextTick(() => mountChart());
    }
});

onMounted(() => {
    if (displayChart.value) {
        mountChart();
    }

    themeObserver = new MutationObserver(() => {
        if (scheduledThemeUpdate) return;
        scheduledThemeUpdate = true;
        requestAnimationFrame(() => {
            applyThemeColorsToChart();
            scheduledThemeUpdate = false;
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

onBeforeUnmount(() => {
    if (themeObserver) {
        themeObserver.disconnect();
        themeObserver = null;
    }
    if (apexChart.value) {
        apexChart.value.destroy();
        apexChart.value = null;
    }
    Object.values(colorProbeSpans).forEach((span) => {
        if (span && span.parentNode) {
            span.parentNode.removeChild(span);
        }
    });
});
</script>

<template>
    <div>
        <Head title="История антифрода" />

        <MainTableSection
            title="История антифрода"
            :data="logs"
        >
            <template v-slot:button>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.settings.index'), { preserveScroll: true })"
                    >
                        К настройкам
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.clients.index'), { preserveScroll: true })"
                    >
                        Клиенты
                    </button>
                </div>
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="anti-fraud-history">
                    <InputFilter
                        name="merchant"
                        placeholder="Мерчант (имя или uuid)"
                    />
                    <InputFilter
                        name="clientId"
                        placeholder="Client ID"
                    />
                </FiltersPanel>

                <div class="flex justify-end mb-3">
                    <button
                        type="button"
                        class="btn btn-sm btn-square btn-primary"
                        :title="displayChart ? 'Скрыть график' : 'Показать график'"
                        @click="toggleChartDisplay"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </button>
                </div>

                <div v-show="displayChart" class="card bg-base-100 shadow p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-base-content/70 text-lg">Антифрод за 24 часа</h3>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="badge badge-primary badge-sm">Уникальные</span>
                            <span class="badge badge-secondary badge-sm">Повторные</span>
                            <span class="badge badge-error badge-sm">Блокировки</span>
                        </div>
                    </div>
                    <div ref="chart" class="h-60"></div>
                </div>
            </template>

            <template v-slot:body>
                <div class="relative">
                    <div class="overflow-x-auto card bg-base-100 shadow">
                        <table class="table table-sm">
                            <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>Мерчант</th>
                                <th>Client ID</th>
                                <th>Решение</th>
                                <th>Сообщение</th>
                                <th class="text-right">Дата</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td>
                                    {{ log.merchant?.name || log.merchant?.uuid || `#${log.merchant_id}` }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ log.client_id || '—' }}
                                </td>
                                <td>
                                    <span v-if="log.decision === 'allow'" class="badge badge-success badge-sm">Разрешено</span>
                                    <span v-else class="badge badge-error badge-sm">Отклонено</span>
                                </td>
                                <td class="text-sm text-base-content/80">
                                    {{ log.message || '—' }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <DateTime :data="log.created_at" />
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
