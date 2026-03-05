<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import OrderModal from "@/Modals/OrderModal.vue";
import DateTime from "@/Components/DateTime.vue";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import PaymentCreateModal from "@/Modals/Payment/PaymentCreateModal.vue";
import {useModalStore} from "@/store/modal.js";
import { ref } from "vue";

const orders = ref(usePage().props.orders);
const modalStore = useModalStore();
const expandedCards = ref({});

const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const orderPaymentLink = (payment_link) => {
    window.open(payment_link, '_blank')
}

router.on('success', (event) => {
    orders.value = usePage().props.orders;
})

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Платежи" />

        <MainTableSection
            title="Платежи"
            :data="orders"
        >
            <template v-slot:button>
                <button
                    @click="modalStore.openPaymentCreateModal()"
                    type="button"
                    class="hidden md:block btn btn-primary"
                >
                    Создать платеж
                </button>
                <AddMobileIcon
                    @click="modalStore.openPaymentCreateModal()"
                />
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="payments">
                    <DropdownFilter
                        name="orderStatuses"
                        title="Статусы"
                    />
                    <DropdownFilter
                        name="merchantIds"
                        title="Мерчанты"
                    />
                    <InputFilter
                        name="externalID"
                        placeholder="Внешний ID"
                    />
                    <InputFilter
                        name="uuid"
                        placeholder="UUID"
                    />
                    <InputFilter
                        name="amount"
                        placeholder="Сумма"
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
                                        Курс
                                    </th>
                                    <th scope="col">
                                        Статус
                                    </th>
<!--                            <th scope="col" class="px-6 py-3 text-nowrap">
                                Внешний ID
                            </th>-->
                                    <th scope="col">
                                        Создан
                                    </th>
                                    <th scope="col" class="px-0 py-3"></th>
                                    <th scope="col" class="flex justify-center">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="order in orders.data" class="bg-base-100 border-b last:border-none">
                                    <th scope="row" class="font-medium whitespace-nowrap text-gray-900 dark:text-gray-200">
                                        <DisplayUUID :uuid="order.uuid"/>
                                    </th>
                                    <td>
                                        <div class="text-nowrap text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                        <div class="text-nowrap text-xs">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                    </td>
                                    <td>
                                        <div class="text-nowrap">{{ order.merchant_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ order.service_commission_amount_total }} {{ order.base_currency.toUpperCase() }}
                                    </td>
                                    <td>
                                        {{ order.conversion_price }}
                                    </td>
                                    <td>
                                        <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                    </td>
<!--                            <td class="px-6 py-3">
                                {{ order.external_id }}
                            </td>-->
                                    <td>
                                        <DateTime class="justify-center" :data="order.created_at"/>
                                    </td>
                                    <td class="px-0 py-3">
                                        <div>
                                            <button
                                                v-if="order.is_h2h"
                                                @click.prevent="false"
                                                type="button"
                                                class="btn btn-xs btn-outline"
                                            >
                                                H2H
                                            </button>
                                            <button
                                                v-else
                                                @click.prevent="false"
                                                type="button"
                                                class="btn btn-xs btn-outline"
                                            >
                                                Merchant
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <TableActionsDropdown>
                                            <TableAction v-if="!order.is_h2h" @click="orderPaymentLink(order.payment_link)">
                                                Платежная страница
                                            </TableAction>
                                            <TableAction @click="router.post(route('payment.callback.resend', order.id))">
                                                Отправить Callback
                                            </TableAction>
                                        </TableActionsDropdown>
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
                                v-for="order in orders.data"
                                :key="order.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: UUID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-2">
                                        <div class="inline-flex items-center">
                                            <span class="text-base-content/70">UUID:</span> <DisplayUUID :uuid="order.uuid"/>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="order.created_at"/>
                                        </div>
                                    </div>


                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-start justify-between">
                                            <div class="text-base-content/70 text-sm">Сумма </div>
                                            <div>
                                                <div class="text-nowrap text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-base-content/70 text-sm">Прибыль</div>
                                            <div>
                                                <div class="text-nowrap text-base-content">{{ order.merchant_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                            <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    v-if="order.is_h2h"
                                                    @click.prevent="false"
                                                    type="button"
                                                    class="badge badge-sm badge-outline"
                                                >
                                                    H2H
                                                </span>
                                                <span
                                                    v-else
                                                    @click.prevent="false"
                                                    type="button"
                                                    class="badge badge-sm badge-outline"
                                                >
                                                    Merchant
                                                </span>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(order.id)"
                                                    :aria-expanded="!!expandedCards[order.id]"
                                                    :aria-label="!!expandedCards[order.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[order.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть с действиями -->
                                    <div v-show="!!expandedCards[order.id]" class="mt-3 flex flex-col gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex items-center justify-between">
                                                <div class="text-base-content/70 text-sm">Прибыль</div>
                                                <div class="text-nowrap">{{ order.merchant_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="text-base-content/70 text-sm">Комиссия</div>
                                                <div class="text-nowrap">{{ order.service_commission_amount_total }} {{ order.base_currency.toUpperCase() }}</div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="text-base-content/70 text-sm">Курс</div>
                                                <div>{{ order.conversion_price }}</div>
                                            </div>
                                            <button
                                                v-if="!order.is_h2h"
                                                class="btn btn-sm btn-outline w-full"
                                                @click="orderPaymentLink(order.payment_link)"
                                            >
                                                Платежная страница
                                            </button>
                                            <button
                                                class="btn btn-sm btn-outline w-full"
                                                @click="router.post(route('payment.callback.resend', order.id))"
                                            >
                                                Отправить Callback
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <OrderModal/>
        <ConfirmModal/>
        <PaymentCreateModal/>
    </div>
</template>
