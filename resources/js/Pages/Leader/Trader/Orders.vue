<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DateFilter from "@/Components/Filters/Partials/DateFilter.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import DateTime from "@/Components/DateTime.vue";
import MoneyValue from "@/Components/MoneyValue.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import TraderCardHeader from "@/Components/Leader/TraderCardHeader.vue";

const trader = ref(usePage().props.trader);
const orders = ref(usePage().props.orders);
const filtersVariants = ref(usePage().props.filtersVariants);

router.on("success", () => {
    trader.value = usePage().props.trader;
    orders.value = usePage().props.orders;
    filtersVariants.value = usePage().props.filtersVariants;
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`${trader.email} — Сделки`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="orders"
        >
            <template #header>
                <TraderCardHeader :trader="trader" current="orders" />
            </template>

            <template #table-filters>
                <FiltersPanel name="leader-trader-orders">
                    <DateFilter name="startDate" title="Начальная дата"/>
                    <DateFilter name="endDate" title="Конечная дата"/>
                    <InputFilter name="uuid" placeholder="UUID"/>
                    <InputFilter name="amount" placeholder="Сумма"/>
                    <InputFilter name="paymentDetail" placeholder="Реквизит"/>
                    <DropdownFilter name="detailTypes" title="Тип реквизита"/>
                    <InputFilter name="paymentGateway" placeholder="Платежный метод"/>
                    <DropdownFilter name="orderStatuses" :options="filtersVariants.orderStatuses" title="Статусы"/>
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                            <th>UUID</th>
                            <th>Сумма</th>
                            <th>Реквизит</th>
                            <th>Статус</th>
                            <th>Создан</th>
                        </template>
                        <tr v-for="order in orders.data" :key="order.id" class="hover">
                            <th class="font-medium whitespace-nowrap">
                                <CopyableOrderUid :uuid="order.uuid ?? ''" />
                            </th>
                            <td>
                                <MoneyValue :value="order.amount" :currency="order.currency" block />
                                <MoneyValue
                                    :value="order.total_profit"
                                    :currency="order.base_currency"
                                    secondary
                                    block
                                />
                            </td>
                            <td>
                                <div class="flex items-center gap-2 min-w-0">
                                    <GatewayLogo
                                        :img_path="order.payment_gateway_logo_path"
                                        :name="order.payment_gateway_name"
                                        class="w-6 h-6 shrink-0 text-base-content/50"
                                    />
                                    <div class="min-w-0">
                                        <PaymentDetail
                                            :detail="order.payment_detail"
                                            :type="order.payment_detail_type"
                                            :name="order.payment_detail_name"
                                            :copyable="false"
                                            short
                                        />
                                        <div class="text-xs text-base-content/60 truncate">
                                            {{ order.payment_gateway_name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <OrderStatus :status="order.status" :status_name="order.status_name" />
                            </td>
                            <td>
                                <DateTime class="justify-start" :data="order.created_at" />
                            </td>
                        </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="order in orders.data"
                            :key="order.id"
                        >
                            <div class="flex justify-between items-center border-b border-base-content/10 mb-2">
                                <div class="inline-flex items-center">
                                    <span class="text-base-content/70">UUID:</span> <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                </div>
                                <div class="inline-flex items-center">
                                    <DateTime class="justify-start" :data="order.created_at" />
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <GatewayLogo
                                        :img_path="order.payment_gateway_logo_path"
                                        :name="order.payment_gateway_name"
                                        class="w-10 h-10 shrink-0 text-base-content/50"
                                    />
                                    <div class="min-w-0">
                                        <PaymentDetail
                                            :detail="order.payment_detail"
                                            :type="order.payment_detail_type"
                                            :name="order.payment_detail_name"
                                            :copyable="false"
                                            short
                                        />
                                        <div class="text-xs text-base-content/60 truncate">
                                            {{ order.payment_gateway_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <OrderStatus :status="order.status" :status_name="order.status_name" />
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2"></div>

                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                <div class="min-w-0">
                                    <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                    <div class="font-medium text-xs text-base-content">
                                        <MoneyValue :value="order.amount" :currency="order.currency" block />
                                        <MoneyValue
                                            :value="order.total_profit"
                                            :currency="order.base_currency"
                                            secondary
                                            block
                                        />
                                    </div>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

