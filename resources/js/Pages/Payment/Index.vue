<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import OrderModal from "@/Modals/OrderModal.vue";
import DateTime from "@/Components/DateTime.vue";
import CopyableOrderUid from "@/Components/CopyableOrderUid.vue";
import AmountModifiedIndicator from "@/Components/AmountModifiedIndicator.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import { ref } from "vue";

const orders = ref(usePage().props.orders);
const expandedCards = ref({});

const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

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
            <template #header>
                <div class="space-y-4">
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
                </div>
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
                                        <span class="ml-2">UUID</span>
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
                                    <th scope="col">
                                        
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="order in orders.data" class="bg-base-100 border-b last:border-none">
                                    <th scope="row" class="font-medium whitespace-nowrap text-base-content">
                                        <div class="flex max-w-full flex-nowrap items-center gap-3 ml-2">
                                            <div class="w-[4rem] min-w-[4rem] shrink-0 overflow-hidden">
                                                <CopyableOrderUid :uuid="order.uuid ?? ''" class="block max-w-full truncate text-left text-base-content" />
                                            </div>
                                        </div>
                                    </th>
                                    <td>
                                        <div class="flex flex-nowrap items-baseline gap-1.5">
                                            <div class="text-nowrap text-base-content">{{ order.amount }} <span class="text-primary/70">{{ order.currency.toUpperCase() }}</span></div>
                                            <AmountModifiedIndicator :modified="order.amount_was_modified" />
                                        </div>
                                        <div class="text-nowrap text-xs"><span class="text-base-content/50">{{ order.total_profit }}</span> <span class="text-primary/50">{{ order.base_currency.toUpperCase() }}</span></div>
                                    </td>
                                    <td>
                                        <div class="text-nowrap">{{ order.merchant_profit }} <span class="text-primary/70">{{ order.base_currency.toUpperCase() }}</span></div>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ order.service_commission_amount_total }} <span class="text-primary/70">{{ order.base_currency.toUpperCase() }}</span>
                                    </td>
                                    <td class="text-nowrap text-base-content">
                                        {{ order.conversion_price }}
                                        <span class="text-primary/70">{{ order.currency.toUpperCase() }}</span>
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
                                    <td class="text-right">
                                        <TableActionsDropdown>
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
                                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 border-b border-base-content/10 mb-2">
                                        <div class="flex min-w-0 max-w-full flex-nowrap items-start gap-3">
                                            <span class="text-base-content/70 shrink-0 pt-0.5">UUID:</span>
                                            <div class="w-[10rem] min-w-[10rem] shrink-0 overflow-hidden">
                                                <CopyableOrderUid :uuid="order.uuid ?? ''" class="block max-w-full truncate text-left text-base-content" />
                                            </div>
                                        </div>
                                        <div class="inline-flex shrink-0 items-center">
                                            <DateTime class="justify-start" :data="order.created_at"/>
                                        </div>
                                    </div>


                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-start justify-between">
                                            <div class="text-base-content/70 text-sm">Сумма </div>
                                            <div>
                                                <div class="flex flex-nowrap items-baseline justify-end gap-1.5">
                                                    <div class="text-nowrap text-base-content">{{ order.amount }} <span class="text-primary/70">{{ order.currency.toUpperCase() }}</span></div>
                                                    <AmountModifiedIndicator :modified="order.amount_was_modified" />
                                                </div>
                                                <div class="text-nowrap text-xs"><span class="text-base-content/50">{{ order.total_profit }}</span> <span class="text-primary/50">{{ order.base_currency.toUpperCase() }}</span></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-base-content/70 text-sm">Прибыль</div>
                                            <div>
                                                <div class="text-nowrap text-base-content">{{ order.merchant_profit }} <span class="text-primary/70">{{ order.base_currency.toUpperCase() }}</span></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                            <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                            <div class="flex items-center gap-2">
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
                                                <div class="text-nowrap">{{ order.merchant_profit }} <span class="text-primary/70">{{ order.base_currency.toUpperCase() }}</span></div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="text-base-content/70 text-sm">Комиссия</div>
                                                <div class="text-nowrap">{{ order.service_commission_amount_total }} <span class="text-primary/70">{{ order.base_currency.toUpperCase() }}</span></div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="text-base-content/70 text-sm">Курс</div>
                                                <div class="text-nowrap text-base-content">
                                                    {{ order.conversion_price }}
                                                    <span class="text-primary/70">{{ order.currency.toUpperCase() }}</span>
                                                </div>
                                            </div>
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
    </div>
</template>
