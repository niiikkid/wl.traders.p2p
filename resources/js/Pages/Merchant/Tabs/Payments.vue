<script setup>
import DateTime from "@/Components/DateTime.vue";
import {usePage} from "@inertiajs/vue3";
import {computed, ref, watch} from "vue";
import Pagination from "@/Components/Pagination/Pagination.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";

const emit = defineEmits(['openPage']);

const props = defineProps({
    orders: {
        type: Object,
        default: null,
    },
    merchant: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const orders = computed(() => props.orders ?? (page?.props?.orders ?? { data: [], meta: {} }));
const ordersData = computed(() => orders.value?.data ?? []);
const ordersMeta = computed(() => orders.value?.meta ?? { current_page: 1, total: 0, per_page: 10 });

const currentPage = ref(ordersMeta.value.current_page ?? 1);

watch(
    () => ordersMeta.value.current_page,
    (pageNumber) => {
        currentPage.value = pageNumber ?? 1;
    }
);

const openPage = (pageNumber) => {
    emit("openPage", pageNumber);
};
</script>

<template>
    <div>
        <h2 class="text-xs text-base-content/60 mb-3">Здесь отображаются только оплаченные сделки</h2>

        <div class="overflow-x-auto card bg-base-100 shadow mb-5">
            <div v-if="loading" class="p-6 text-center text-sm text-base-content/60">
                Загрузка оплаченных сделок...
            </div>
            <table class="table table-sm">
                <thead class="text-xs uppercase bg-base-300">
                <tr>
                    <th scope="col">
                        UUID
                    </th>
                    <th scope="col">
                        Сумма
                    </th>
                    <th scope="col">
                        Прибыль
                    </th>
                    <th scope="col">
                        Комиссия
                    </th>
                    <th scope="col">
                        Создан
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!loading && ordersData.length === 0">
                    <td colspan="5" class="text-center text-sm text-base-content/60 py-6">
                        Сделок пока нет.
                    </td>
                </tr>
                <tr v-for="order in ordersData" :key="order.id" class="hover">
                    <th scope="row" class="font-medium whitespace-nowrap">
                        <DisplayUUID :uuid="order.uuid"/>
                    </th>
                    <td>
                        <div class="text-nowrap">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                        <div class="text-nowrap text-xs text-base-content/60">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                    </td>
                    <td>
                        <div class="text-nowrap">{{ order.merchant_profit }} {{ order.base_currency.toUpperCase() }}</div>
                    </td>
                    <td>
                        {{ order.service_commission_amount_total }} {{ order.base_currency.toUpperCase() }}
                    </td>
                    <td>
                        <DateTime class="justify-center" :data="order.created_at"/>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            v-model="currentPage"
            :total-items="ordersMeta.total"
            previous-label="Назад"
            next-label="Вперед"
            @page-changed="openPage"
            :per-page="ordersMeta.per_page"
            :disabled="loading"
        ></Pagination>
    </div>
</template>

<style scoped>

</style>
