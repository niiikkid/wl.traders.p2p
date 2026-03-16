<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DateFilter from "@/Components/Filters/Pertials/DateFilter.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import OrderStatus from "@/Components/OrderStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import DateTime from "@/Components/DateTime.vue";

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
                                <th>Мерчант</th>
                                <th>Сумма</th>
                                <th>Реквизит</th>
                                <th>Статус</th>
                                <th>Создан</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders.data" :key="order.id" class="hover">
                                <th class="font-medium whitespace-nowrap">
                                    <DisplayUUID :uuid="order.uuid"/>
                                </th>
                                <td class="text-nowrap">
                                    {{ order.merchant_email || '-' }}
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                    <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <GatewayLogo :img_path="order.payment_gateway_logo_path" :name="order.payment_gateway_name" class="w-10 h-10 text-base-content/50"/>
                                        <PaymentDetail
                                            :detail="order.payment_detail"
                                            :type="order.payment_detail_type"
                                            :name="order.payment_detail_name"
                                            :copyable="false"
                                        />
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

