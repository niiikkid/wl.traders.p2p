<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IsActiveStatus from "@/Components/IsActiveStatus.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import {ref} from "vue";
import {useModalStore} from "@/store/modal.js";
import PaymentGatewayModal from "@/Modals/PaymentGateway/PaymentGatewayModal.vue";
import PaymentGatewayBulkSettingsModal from "@/Modals/PaymentGateway/PaymentGatewayBulkSettingsModal.vue";

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
                <div class="hidden md:flex justify-end gap-2">
                    <button
                        @click="modalStore.openPaymentGatewayBulkSettingsModal()"
                        type="button"
                        class="btn btn-sm btn-secondary"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                    <button
                        @click="modalStore.openPaymentGatewayCreateModal()"
                        type="button"
                        class="btn btn-sm btn-primary"
                    >
                        Создать метод
                    </button>
                </div>
                <AddMobileIcon
                    @click="modalStore.openPaymentGatewayCreateModal()"
                />
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
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
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
                                    <th scope="col" class="px-6 py-3 flex justify-center">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
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
                                        <div class="text-nowrap">{{ payment_gateway.trader_commission_rate_for_orders }}% / {{ payment_gateway.total_service_commission_rate_for_orders }}%</div>
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
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="payment_gateway in payment_gateways.data"
                                :key="payment_gateway.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
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
                                            <div class="text-right text-xs text-nowrap">{{ payment_gateway.trader_commission_rate_for_orders }}% / {{ payment_gateway.total_service_commission_rate_for_orders }}%</div>
                                            <div>
                                                <div class="text-nowrap text-xs"><span class="text-base-content/70">Max</span> {{ payment_gateway.max_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs"><span class="text-base-content/70">Min</span> {{ payment_gateway.min_limit }} {{ payment_gateway.currency.toUpperCase() }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <PaymentGatewayModal/>
        <PaymentGatewayBulkSettingsModal/>
    </div>
</template>
