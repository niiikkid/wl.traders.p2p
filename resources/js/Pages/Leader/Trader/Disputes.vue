<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import DisputeStatus from "@/Components/DisputeStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import DateTime from "@/Components/DateTime.vue";

const trader = ref(usePage().props.trader);
const disputes = ref(usePage().props.disputes);

router.on("success", () => {
    trader.value = usePage().props.trader;
    disputes.value = usePage().props.disputes;
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`Трейдер #${trader.id} - Споры`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="disputes"
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
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.orders.index', {trader: trader.id}))">Сделки</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-primary">Споры</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.finances.index', {trader: trader.id}))">Финансы</button>
                        </li>
                    </ul>
                </div>
            </template>

            <template #table-filters>
                <FiltersPanel name="leader-trader-disputes">
                    <InputFilter name="uuid" placeholder="UUID"/>
                    <InputFilter name="externalID" placeholder="Внешний ID"/>
                    <InputFilter name="amount" placeholder="Сумма"/>
                    <InputFilter name="paymentDetail" placeholder="Реквизит"/>
                    <DropdownFilter name="disputeStatuses" title="Статусы"/>
                </FiltersPanel>
            </template>

            <template #body>
                <div class="overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>ID</th>
                                <th>Сумма</th>
                                <th>Реквизит</th>
                                <th>Сделка</th>
                                <th>Статус</th>
                                <th>Создан</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="dispute in disputes.data" :key="dispute.id" class="hover">
                                <th class="font-medium whitespace-nowrap">{{ dispute.id }}</th>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ dispute.order.amount }} {{ dispute.order.currency.toUpperCase() }}</div>
                                    <div class="text-nowrap text-base-content/70 text-xs">{{ dispute.order.total_profit }} {{ dispute.order.base_currency.toUpperCase() }}</div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <GatewayLogo :img_path="dispute.payment_gateway.logo_path" :name="dispute.payment_gateway.name" class="w-10 h-10 text-base-content/50"/>
                                        <PaymentDetail
                                            :detail="dispute.payment_detail.detail"
                                            :type="dispute.payment_detail.type"
                                            :name="dispute.payment_detail.name"
                                            :copyable="false"
                                        />
                                    </div>
                                </td>
                                <td>
                                    <DisplayUUID :uuid="dispute.order.uuid" />
                                </td>
                                <td>
                                    <DisputeStatus :status="dispute.status" />
                                </td>
                                <td>
                                    <DateTime :data="dispute.created_at" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

