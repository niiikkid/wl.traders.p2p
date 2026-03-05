<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref, getCurrentInstance} from "vue";
import Pagination from "@/Components/Pagination/Pagination.vue";
import AlertError from "@/Components/Alerts/AlertError.vue";
import AlertInfo from "@/Components/Alerts/AlertInfo.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const tableFiltersStore = useTableFiltersStore();

const props = defineProps({
    title: {
        type: String,
    },
    data: {
        type: Object,
        default: {}
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
    }
});

tableFiltersStore.setMeta(props.data?.meta);
tableFiltersStore.setFilters(usePage().props.filters);
tableFiltersStore.setTab(new URL(window.location.href).searchParams.get('tab') || '');
tableFiltersStore.setFiltersVariants(usePage().props.filtersVariants);

const items = computed(() => {
    if (props.paginate) {
        return props.data.data;
    } else {
        return props.data;
    }
});

const perPageOptions = [
    { value: 5, name: '5 строк' },
    { value: 10, name: '10 строк' },
    { value: 15, name: '15 строк' },
    { value: 20, name: '20 строк' },
    { value: 25, name: '25 строк' },
    { value: 50, name: '50 строк' },
    { value: 100, name: '100 строк' }
];

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
    router.visit(route(route().current()), {
        data: tableFiltersStore.getQueryData,
        preserveScroll: true
    })
}


const {uid} = getCurrentInstance();

const hasPendingDisputes = ref(usePage().props.data.hasPendingDisputes);

router.on('success', (event) => {
    hasPendingDisputes.value = usePage().props.data.hasPendingDisputes;
})
</script>

<template>
    <div>
        <div>
            <div class="mx-auto space-y-6">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl sm:text-3xl font-bold text-base-content">{{ title }}</h2>
                        <div v-if="info" class="tooltip tooltip-bottom hidden sm:block" :data-tip="info">
                            <span class="badge badge-info badge-soft gap-2 cursor-help">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                Инфо
                            </span>
                        </div>
                    </div>
                    <slot name="button"></slot>
                </div>

                <AlertError v-if="hasPendingDisputes" message="У вас есть не закрытый спор!"></AlertError>
                <AlertError :message="$page.props.flash.error"></AlertError>
                <AlertInfo :message="$page.props.flash.message"></AlertInfo>

                <div>
                    <slot name="header"/>
                </div>
                <div>
                    <slot name="table-filters"/>
                </div>
                <div>
                    <slot v-if="items.length" name="body"/>
                    <h2 v-else class="text-center text-lg font-medium mb-4 text-base-content">
                        Пока что тут пусто
                    </h2>
                </div>
                <div v-if="paginate && displayPagination" class="flex justify-between items-center">
                    <Pagination
                        v-model="tableFiltersStore.page"
                        :total-items="tableFiltersStore.getTotal"
                        previous-label="Назад" next-label="Вперед"
                        @page-changed="changeCurrentPage"
                        :per-page="tableFiltersStore.getPerPage"
                    ></Pagination>

                    <div class="dropdown dropdown-left dropdown-top">
                        <div tabindex="0" role="button" class="btn btn-outline btn-xs">
                            {{ tableFiltersStore.getPerPage }} строк
                            <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                            </svg>
                        </div>
                        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                            <li v-for="(option, index) in perPageOptions" :key="option.value" class="">
                                <label class="label cursor-pointer justify-start gap-3 px-2 py-2">
                                    <input
                                        :id="'perPage-'+uid+'-'+index"
                                        type="radio"
                                        :name="'perPageRadio'+uid"
                                        :value="option.value"
                                        :checked="tableFiltersStore.getPerPage === option.value"
                                        @change="changePerPage(option.value)"
                                        class="radio radio-xs"
                                    >
                                    <span :for="'perPage-'+uid+'-'+index" class="label-text text-xs">{{ option.name }}</span>
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
