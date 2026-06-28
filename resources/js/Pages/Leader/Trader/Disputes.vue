<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import DisputeStatus from "@/Components/DisputeStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import DateTime from "@/Components/DateTime.vue";
import MoneyValue from "@/Components/MoneyValue.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import TraderCardHeader from "@/Components/Leader/TraderCardHeader.vue";

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
        <Head :title="`${trader.email} — Споры`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="disputes"
        >
            <template #header>
                <TraderCardHeader :trader="trader" current="disputes" />
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
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                            <th scope="col">ID</th>
                            <th scope="col">Сумма</th>
                            <th scope="col">Реквизит</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Создан</th>
                        </template>
                        <tr v-for="dispute in disputes.data" :key="dispute.id" class="hover">
                            <th class="font-medium whitespace-nowrap">{{ dispute.id }}</th>
                            <td>
                                <MoneyValue :value="dispute.order.amount" :currency="dispute.order.currency" block />
                                <MoneyValue
                                    :value="dispute.order.total_profit"
                                    :currency="dispute.order.base_currency"
                                    secondary
                                    block
                                />
                            </td>
                            <td>
                                <div class="flex items-center gap-2 min-w-0">
                                    <GatewayLogo
                                        :img_path="dispute.payment_gateway.logo_path"
                                        :name="dispute.payment_gateway.name"
                                        class="w-6 h-6 shrink-0 text-base-content/50"
                                    />
                                    <div class="min-w-0">
                                        <PaymentDetail
                                            :detail="dispute.payment_detail.detail"
                                            :type="dispute.payment_detail.type"
                                            :name="dispute.payment_detail.name"
                                            :copyable="false"
                                            short
                                        />
                                        <div class="text-xs text-base-content/60 truncate">
                                            {{ dispute.payment_gateway.name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <DisputeStatus :status="dispute.status" />
                            </td>
                            <td>
                                <DateTime :data="dispute.created_at" />
                            </td>
                        </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="dispute in disputes.data"
                            :key="dispute.id"
                        >
                            <!-- Компактная шапка: ID и дата -->
                            <div class="flex justify-between items-center border-b border-base-content/10">
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-base-content/70">ID:</span>
                                    <span class="font-medium text-base-content">{{ dispute.id }}</span>
                                </div>
                                <div class="inline-flex items-center">
                                    <DateTime class="justify-start" :data="dispute.created_at"/>
                                </div>
                            </div>

                            <!-- Реквизит и платежный метод -->
                            <div class="flex items-center gap-2 min-w-0 mt-2">
                                <GatewayLogo
                                    :img_path="dispute.payment_gateway.logo_path"
                                    :name="dispute.payment_gateway.name"
                                    class="w-10 h-10 shrink-0 text-base-content/50"
                                />
                                <div class="min-w-0">
                                    <PaymentDetail
                                        :detail="dispute.payment_detail.detail"
                                        :type="dispute.payment_detail.type"
                                        :name="dispute.payment_detail.name"
                                        :copyable="false"
                                        short
                                    />
                                    <div class="text-xs text-base-content/60 truncate">
                                        {{ dispute.payment_gateway.name }}
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2"></div>

                            <!-- Остальные поля -->
                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px]">
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                    <div class="font-medium text-xs">
                                        <MoneyValue :value="dispute.order.amount" :currency="dispute.order.currency" block />
                                        <MoneyValue
                                            :value="dispute.order.total_profit"
                                            :currency="dispute.order.base_currency"
                                            secondary
                                            block
                                        />
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Статус</div>
                                    <div class="font-medium text-xs">
                                        <DisputeStatus :status="dispute.status" />
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

