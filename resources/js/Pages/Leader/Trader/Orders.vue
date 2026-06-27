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
        <Head :title="`Трейдер #${trader.id} - Сделки`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="orders"
            :info="`Трейдер: ${trader.email}`"
        >
            <template #header>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="breadcrumbs text-sm">
                        <ul>
                            <li>
                                <button class="link link-hover" @click="router.visit(route('leader.traders.index'))">Трейдеры</button>
                            </li>
                            <li>{{ trader.email }}</li>
                        </ul>
                    </div>

                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.payment-details.index', {trader: trader.id}))">Реквизиты</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-primary">Сделки</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.disputes.index', {trader: trader.id}))">Споры</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.finances.index', {trader: trader.id}))">Финансы</button>
                        </li>
                    </ul>
                </div>
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
                <div class="overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>UUID</th>
                                <th>Сумма</th>
                                <th>Реквизит</th>
                                <th>Статус</th>
                                <th>Создан</th>
                            </tr>
                        </thead>
                        <tbody>
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
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

