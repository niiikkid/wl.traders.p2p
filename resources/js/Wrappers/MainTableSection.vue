<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref, watch} from "vue";
import Pagination from "@/Components/Pagination/Pagination.vue";
import PerPageSelect from "@/Components/Pagination/PerPageSelect.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import AlertError from "@/Components/Alerts/AlertError.vue";
import AlertInfo from "@/Components/Alerts/AlertInfo.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import AppTooltip from '@/Components/AppTooltip.vue';

const tableFiltersStore = useTableFiltersStore();
const page = usePage();

const props = defineProps({
    title: {
        type: String,
    },
    data: {
        default: null,
        validator: (value) => value == null || typeof value === 'object',
    },
    paginate: {
        type: Boolean,
        default: true
    },
    displayPagination: {
        type: Boolean,
        default: true
    },
    info: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: '',
    },
    visitExtraData: {
        type: Object,
        default: () => ({}),
    },
    alwaysShowBody: {
        type: Boolean,
        default: false,
    },
});

tableFiltersStore.setMeta(props.data?.meta);

watch(
    () => props.data?.meta,
    (meta) => {
        if (meta && props.paginate) {
            tableFiltersStore.setMeta(meta);
        }
    },
);
tableFiltersStore.setFilters(usePage().props.filters);
tableFiltersStore.setTab(new URL(window.location.href).searchParams.get('tab') || '');
tableFiltersStore.setFiltersVariants(usePage().props.filtersVariants);

const items = computed(() => {
    if (props.paginate) {
        return props.data?.data ?? [];
    }

    if (props.data == null) {
        return [];
    }

    return props.data;
});

const changeCurrentPage = (value) => {
    tableFiltersStore.setCurrentPage(value ?? 1);

    openPage();
}

const changePerPage = (value) => {
    tableFiltersStore.setCurrentPage(1);
    tableFiltersStore.setPerPage(value ?? 10);

    openPage();
}

const openPage = () => {
    router.visit(page.url?.split('?')[0] || window.location.pathname, {
        data: {...tableFiltersStore.getQueryData, ...props.visitExtraData},
        preserveScroll: true
    })
}


const hasPendingDisputes = ref(usePage().props.data?.hasPendingDisputes);
const pendingDisputesCount = computed(() => Number(usePage().props.menu?.pendingDisputesCount ?? 0));

const isOnOrdersPage = computed(() => route().current('orders.*'));

const pendingDisputeBannerMessage = computed(() => {
    if (pendingDisputesCount.value <= 1) {
        return 'У вас есть незакрытый спор.';
    }

    return `У вас есть незакрытые споры (${pendingDisputesCount.value}).`;
});

const openPendingDisputePrimary = () => {
    router.visit(route('orders.index'));
};

router.on('success', () => {
    hasPendingDisputes.value = usePage().props.data?.hasPendingDisputes;
});
</script>

<template>
    <div>
        <div>
            <div class="mx-auto space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl sm:text-3xl font-bold text-base-content">{{ title }}</h2>
                            <AppTooltip v-if="info" :tip="info" placement="bottom" wrapper-class="hidden sm:inline-block">
                                <span class="badge badge-info badge-soft gap-2 cursor-help">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    Инфо
                                </span>
                            </AppTooltip>
                        </div>
                        <p v-if="subtitle" class="text-sm sm:text-base text-base-content/60 mt-0.5">
                            {{ subtitle }}
                        </p>
                    </div>
                    <slot name="button"></slot>
                </div>

                <div
                    v-if="hasPendingDisputes"
                    role="alert"
                    class="alert alert-error flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="min-w-0">{{ pendingDisputeBannerMessage }}</span>
                    <button
                        v-if="!isOnOrdersPage"
                        type="button"
                        class="btn btn-sm btn-outline shrink-0"
                        @click.prevent="openPendingDisputePrimary"
                    >
                        Посмотреть
                    </button>
                </div>
                <AlertError :message="$page.props.flash.error"></AlertError>
                <AlertInfo :message="$page.props.flash.message"></AlertInfo>

                <div>
                    <slot name="header"/>
                </div>
                <div>
                    <slot name="table-filters"/>
                </div>
                <div>
                    <slot v-if="items.length || alwaysShowBody" name="body"/>
                    <TableEmptyState
                        v-else
                        title="Пока ничего нет"
                        description="Записей пока нет — когда появятся данные, они отобразятся здесь."
                    />
                </div>
                <div v-if="paginate && displayPagination && items.length" class="flex justify-between items-center">
                    <Pagination
                        v-model="tableFiltersStore.page"
                        :total-items="tableFiltersStore.getTotal"
                        previous-label="Назад"
                        next-label="Вперед"
                        :per-page="tableFiltersStore.getPerPage"
                        @page-changed="changeCurrentPage"
                    />

                    <PerPageSelect
                        :model-value="tableFiltersStore.getPerPage"
                        @change="changePerPage"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
