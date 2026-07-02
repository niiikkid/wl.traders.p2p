<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import WidgetHeader from '@/Components/MainPage/WidgetHeader.vue';
import DashboardChart from '@/Components/Charts/DashboardChart.vue';

const chartData = ref({ labels: [], series: [] });
const loading = ref(false);
const loaded = ref(false);
const errored = ref(false);

const SERIES_TOKENS = ['primary', 'secondary', 'error'];

const antiFraudSeries = computed(() => chartData.value.series.map((serie, index) => ({
    name: serie.name,
    data: serie.data || [],
    colorToken: SERIES_TOKENS[index] || 'primary',
})));

const hasData = computed(() => chartData.value.series.some((serie) => (serie.data || []).some((value) => value > 0)));

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
    } catch (error) {
        errored.value = true;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    load();
});
</script>

<template>
    <div class="space-y-4">
        <WidgetHeader title="Антифрод за 24 часа" :loading="loading" @refresh="load" />

        <div class="rounded-box border border-base-300/60 bg-base-100 p-4 sm:p-5">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-medium text-base-content/60">Активность клиентов по часам</h3>
                <div class="flex items-center gap-2 text-xs">
                    <span class="badge badge-primary badge-soft badge-sm">Уникальные</span>
                    <span class="badge badge-secondary badge-soft badge-sm">Повторные</span>
                    <span class="badge badge-error badge-soft badge-sm">Блокировки</span>
                </div>
            </div>

            <div v-if="loading && !loaded" class="skeleton mt-3 h-60 w-full"></div>
            <div v-else-if="errored" class="flex h-60 items-center justify-center text-sm text-error">
                Не удалось загрузить статистику
            </div>
            <div v-else-if="loaded && !hasData" class="flex h-60 items-center justify-center text-sm text-base-content/60">
                Нет данных за последние 24 часа
            </div>
            <DashboardChart
                v-else
                type="bar"
                :labels="chartData.labels"
                :series="antiFraudSeries"
                value-type="count"
                :y-min="0"
                height="15rem"
            />
        </div>
    </div>
</template>
