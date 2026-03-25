<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminTraderAnalyticsPage from '@/Pages/Admin/TraderAnalytics/Index.vue';

defineOptions({layout: AuthenticatedLayout});

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
    currencyOptions: {
        type: Array,
        default: () => [],
    },
    routes: {
        type: Object,
        default: () => ({}),
    },
    amountRanges: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    enabledDetailsByDay: {
        type: Array,
        default: () => [],
    },
    topTraders: {
        type: Array,
        default: () => [],
    },
    activeTraders: {
        type: Array,
        default: () => [],
    },
    traderAmountRangeStats: {
        type: Array,
        default: () => [],
    },
    individualTrader: {
        type: Object,
        default: null,
    },
    individualByDay: {
        type: Array,
        default: () => [],
    },
    individualSummary: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <AdminTraderAnalyticsPage v-bind="props" />
</template>
