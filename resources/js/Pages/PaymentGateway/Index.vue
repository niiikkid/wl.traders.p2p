<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IsActiveStatus from "@/Components/IsActiveStatus.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import PageToolbar from "@/Components/Table/PageToolbar.vue";
import PageToolbarAction from "@/Components/Table/PageToolbarAction.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import {ref} from "vue";
import {useModalStore} from "@/store/modal.js";
import PaymentGatewayModal from "@/Modals/PaymentGateway/PaymentGatewayModal.vue";
import PaymentGatewayBulkSettingsModal from "@/Modals/PaymentGateway/PaymentGatewayBulkSettingsModal.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import TableActionsHeadCell from "@/Components/Table/TableActionsHeadCell.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

const modalStore = useModalStore();
const payment_gateways = ref(usePage().props.paymentGateways);

router.on('success', () => {
    payment_gateways.value = usePage().props.paymentGateways;
});

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Платежные методы" />

        <MainTableSection
            title="Платежные методы"
            :data="payment_gateways"
        >
            <template v-slot:button>
                <PageToolbar>
                    <PageToolbarAction
                        icon="settings"
                        title="Массовые настройки"
                        desktop-only
                        @click="modalStore.openPaymentGatewayBulkSettingsModal()"
                    />

                    <PageToolbarAction
                        icon="plus"
                        title="Создать метод"
                        label="Создать метод"
                        @click="modalStore.openPaymentGatewayCreateModal()"
                    />
                </PageToolbar>
            </template>
            <template v-slot:header>
                <FiltersPanel name="payment-gateways">
                    <InputFilter
                        name="search"
                        placeholder="Поиск (название или код)"
                    />
                    <DropdownFilter
                        name="currency"
                        title="Валюта"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                            <th scope="col" class="px-6 py-3">
                                ID
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Метод
                            </th>
                            <th scope="col" class="px-6 py-3 text-nowrap">
                                Лимиты для сделок
                            </th>
                            <th scope="col" class="px-6 py-3 text-nowrap">
                                Комиссия %
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Статус
                            </th>
                            <TableActionsHeadCell cell-class="px-6 py-3" />
                        </template>
                        <tr v-for="payment_gateway in payment_gateways.data">
                            <th scope="row" class="px-6 py-3 font-medium whitespace-nowrap">
                                {{ payment_gateway.id }}
                            </th>
                            <td class="px-6 py-3">
                                <div class="flex gap-3 items-center">
                                    <GatewayLogo :img_path="payment_gateway.logo_path" class="w-10 h-10"/>
                                    <div>
                                        <div class="text-nowrap">{{ payment_gateway.name }}</div>
                                        <div class="text-nowrap">{{ payment_gateway.code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <div class="text-nowrap">Max {{ payment_gateway.max_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                                <div class="text-nowrap">Min {{ payment_gateway.min_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                            </td>
                            <td class="px-6 py-3">
                                <div v-if="payment_gateway.use_flexible_trader_commission_for_orders" class="text-nowrap">
                                    Гибкая / Гибкая
                                </div>
                                <div v-else class="text-nowrap">
                                    {{ payment_gateway.trader_commission_rate_for_orders }}% / {{ payment_gateway.total_service_commission_rate_for_orders }}%
                                </div>
                            </td>
                            <td class="px-6 py-3 text-nowrap">
                                <IsActiveStatus :is_active="payment_gateway.is_active"></IsActiveStatus>
                            </td>
                            <td class="px-6 py-3 text-nowrap text-right">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs"
                                    @click="modalStore.openPaymentGatewayEditModal({ paymentGatewayId: payment_gateway.id })"
                                >
                                    <svg class="w-[22px] h-[22px] text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.779 17.779 4.36 19.918 6.5 13.5m4.279 4.279 8.364-8.643a3.027 3.027 0 0 0-2.14-5.165 3.03 3.03 0 0 0-2.14.886L6.5 13.5m4.279 4.279L6.499 13.5m2.14 2.14 6.213-6.504M12.75 7.04 17 11.28"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="payment_gateway in payment_gateways.data"
                            :key="payment_gateway.id"
                        >
                            <!-- Шапка: ID и статус -->
                            <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                <div class="inline-flex items-center">
                                    <span class="text-base-content/70">ID:</span> <span class="font-medium ml-2">{{ payment_gateway.id }}</span>
                                </div>
                                <div class="inline-flex items-center">
                                    <IsActiveStatus :is_active="payment_gateway.is_active"></IsActiveStatus>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-2 py-1">
                                    <div class="flex items-center gap-3">
                                        <GatewayLogo :img_path="payment_gateway.logo_path" class="w-10 h-10"/>
                                        <div>
                                            <div class="text-nowrap text-base-content">{{ payment_gateway.name }}</div>
                                            <div class="text-nowrap text-xs text-base-content/70">{{ payment_gateway.code }}</div>
                                        </div>
                                    </div>
                                    <div>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs"
                                            @click="modalStore.openPaymentGatewayEditModal({ paymentGatewayId: payment_gateway.id })"
                                        >
                                            <svg class="w-[22px] h-[22px] text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.779 17.779 4.36 19.918 6.5 13.5m4.279 4.279 8.364-8.643a3.027 3.027 0 0 0-2.14-5.165 3.03 3.03 0 0 0-2.14.886L6.5 13.5m4.279 4.279L6.499 13.5m2.14 2.14 6.213-6.504M12.75 7.04 17 11.28"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="border-b border-base-content/10 my-2"></div>
                                <div class="flex items-center justify-between text-sm">
                                    <div v-if="payment_gateway.use_flexible_trader_commission_for_orders" class="text-right text-xs text-nowrap">
                                        Гибкая / Гибкая
                                    </div>
                                    <div v-else class="text-right text-xs text-nowrap">
                                        {{ payment_gateway.trader_commission_rate_for_orders }}% / {{ payment_gateway.total_service_commission_rate_for_orders }}%
                                    </div>
                                    <div>
                                        <div class="text-nowrap text-xs"><span class="text-base-content/70">Max</span> {{ payment_gateway.max_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                                        <div class="text-nowrap text-xs"><span class="text-base-content/70">Min</span> {{ payment_gateway.min_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                                    </div>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <PaymentGatewayModal/>
        <PaymentGatewayBulkSettingsModal/>
    </div>
</template>
