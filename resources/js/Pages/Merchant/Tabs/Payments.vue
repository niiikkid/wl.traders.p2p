<script setup>
import DateTime from "@/Components/DateTime.vue";
import {usePage} from "@inertiajs/vue3";
import {computed, ref, watch} from "vue";
import Pagination from "@/Components/Pagination/Pagination.vue";
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import AmountModifiedIndicator from "@/Components/AmountModifiedIndicator.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

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

        <div class="mb-5">
            <!-- Desktop/tablet view (table) -->
            <DataTable :loading="loading" loading-text="Загрузка оплаченных сделок...">
                <template #head>
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
                </template>
                <tr v-if="!loading && ordersData.length === 0">
                    <td colspan="5" class="text-center text-sm text-base-content/60 py-6">
                        Сделок пока нет.
                    </td>
                </tr>
                <tr v-for="order in ordersData" :key="order.id" class="hover">
                    <th scope="row" class="font-medium whitespace-nowrap">
                        <CopyableOrderUid :uuid="order.uuid ?? ''" />
                    </th>
                    <td>
                        <div class="flex flex-nowrap items-baseline gap-1.5">
                            <div class="text-nowrap">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                            <AmountModifiedIndicator :modified="order.amount_was_modified" />
                        </div>
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
            </DataTable>

            <!-- Mobile view (cards list) -->
            <DataCardList>
                <DataCard
                    v-for="order in ordersData"
                    :key="order.id"
                >
                    <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                        <div class="min-w-0 flex-1 text-[11px]">
                            <div class="inline-flex items-center gap-1 pl-1 min-w-0">
                                <span class="text-base-content/70">UUID:</span>
                                <CopyableOrderUid :uuid="order.uuid ?? ''" />
                            </div>
                        </div>
                        <div class="shrink-0 text-right leading-tight">
                            <div class="text-[10px] text-base-content/50 uppercase">Создан</div>
                            <DateTime
                                :data="order.created_at"
                                class="justify-end text-[11px]"
                            />
                        </div>
                    </div>

                    <div class="flex items-baseline gap-2 min-w-0 pt-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-nowrap items-baseline gap-1.5">
                                <div class="text-xs font-medium text-base-content text-nowrap">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                <AmountModifiedIndicator :modified="order.amount_was_modified" />
                            </div>
                            <div class="text-[11px] text-base-content/60 leading-snug text-nowrap">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                        </div>
                    </div>

                    <div class="border-b border-base-content/10 my-2 mb-1"></div>

                    <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                        <div>
                            <div class="text-[10px] text-base-content/50 uppercase">Прибыль</div>
                            <div class="font-medium text-xs text-base-content text-nowrap">{{ order.merchant_profit }} {{ order.base_currency.toUpperCase() }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-base-content/50 uppercase">Комиссия</div>
                            <div class="font-medium text-xs text-base-content text-nowrap">{{ order.service_commission_amount_total }} {{ order.base_currency.toUpperCase() }}</div>
                        </div>
                    </div>
                </DataCard>
                <div v-if="!loading && ordersData.length === 0" class="py-6 text-center text-sm text-base-content/60">
                    Сделок пока нет.
                </div>
            </DataCardList>
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
