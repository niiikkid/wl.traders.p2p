<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useModalStore} from "@/store/modal.js";
import DateTime from "@/Components/DateTime.vue";
import {ref} from "vue";
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import DateFilter from "@/Components/Filters/Partials/DateFilter.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import RefreshTableData from "@/Components/Table/RefreshTableData.vue";
import PageToolbar from "@/Components/Table/PageToolbar.vue";
import PageToolbarAction from "@/Components/Table/PageToolbarAction.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import TableActionsHeadCell from "@/Components/Table/TableActionsHeadCell.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import DisputeModal from "@/Modals/DisputeModal.vue";
import CancelDisputeModal from "@/Modals/CancelDisputeModal.vue";
import {useConfirmAcceptOrder} from '@/composables/useConfirmAcceptOrder.js';
import OrderDetailsOpenButton from "@/Components/Order/OrderDetailsOpenButton.vue";
import OrderModal from "@/Modals/OrderModal.vue";

const orders = ref(usePage().props.orders);
const modalStore = useModalStore();
const { confirmAcceptOrder } = useConfirmAcceptOrder();
const canUseManualControlAcq = Boolean(usePage().props.auth?.user?.support_can_use_manual_control_acq);

const prioritizePendingDisputes = (paginatedOrders) => {
    if (!paginatedOrders?.data || !Array.isArray(paginatedOrders.data)) {
        return paginatedOrders;
    }

    paginatedOrders.data = [...paginatedOrders.data].sort((left, right) => {
        if (Boolean(left.has_pending_dispute) === Boolean(right.has_pending_dispute)) {
            return right.id - left.id;
        }

        return left.has_pending_dispute ? -1 : 1;
    });

    return paginatedOrders;
};

prioritizePendingDisputes(orders.value);

router.on('success', (event) => {
    orders.value = usePage().props.orders;
    prioritizePendingDisputes(orders.value);
})

const reloadingTableData = ref(false);

const openOrderModal = (order) => {
    modalStore.openOrderModal({order_id: order.id});
};

const confirmAcceptDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите принять спор #' + dispute?.id + '?',
        body: 'В таком случае, сделка будет закрыта как оплаченная.',
        confirm_button_name: 'Принять спор',
        confirm: () => {
            useForm({}).patch(route('support.disputes.accept', dispute.uuid), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route('support.orders.index'), {
                        only: ['orders'],
                    })
                },
            });
        }
    });
}

const confirmRollbackDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите открыть спор #' + dispute?.id + '?',
        body: 'Референтная сделка не изменит свой статус.',
        confirm_button_name: 'Открыть спор',
        confirm: () => {
            useForm({}).patch(route('support.disputes.rollback', dispute.uuid), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route('support.orders.index'), {
                        only: ['orders'],
                    })
                },
            });
        }
    });
};

const openManualControlAcqPage = () => {
    if (!canUseManualControlAcq) {
        return;
    }

    window.open(route('support.manual-control-acq.show'), '_blank', 'noopener');
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Сделки" />

        <MainTableSection
            title="Сделки"
            :data="orders"
        >
            <template #button>
                <PageToolbar :loading="reloadingTableData">
                    <PageToolbarAction
                        v-if="canUseManualControlAcq"
                        title="Manual Control ACQ"
                        @click="openManualControlAcqPage"
                    >
                        <span class="text-[11px] font-semibold tracking-tight">ACQ</span>
                    </PageToolbarAction>

                    <RefreshTableData
                        icon-only
                        @refresh-started="reloadingTableData = true"
                        @refresh-finished="reloadingTableData = false"
                    />
                </PageToolbar>
            </template>
            <template v-slot:header>
                <FiltersPanel name="orders">
                        <DateFilter name="startDate" title="Начальная дата"/>
                        <DateFilter name="endDate" title="Конечная дата"/>
                        <InputFilter
                            name="uuid"
                            placeholder="UUID"
                        />
                        <InputFilter
                            name="amount"
                            placeholder="Сумма"
                        />
                        <InputFilter
                            name="paymentDetail"
                            placeholder="Реквизит"
                        />
                        <DropdownFilter
                            name="detailTypes"
                            title="Тип реквизита"
                        />
                        <InputFilter
                            name="paymentGateway"
                            placeholder="Платежный метод"
                        />
                        <InputFilter
                            name="user"
                            placeholder="Пользователь"
                        />
                        <DropdownFilter
                            name="orderStatuses"
                            title="Статусы"
                        />
                        <DropdownFilter
                            name="hasDispute"
                            title="Наличие спора"
                        />
                        <DropdownFilter
                            name="disputeStatuses"
                            title="Статусы споров"
                        />
                    </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable :loading="reloadingTableData">
                        <template #head>
                                        <th scope="col">
                                            UUID
                                        </th>
                                        <th scope="col">
                                            Сумма
                                        </th>
                                        <th scope="col">
                                            Реквизит
                                        </th>
                                        <th scope="col">
                                            Трейдер
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col">
                                            Создан
                                        </th>
                                        <TableActionsHeadCell />
                        </template>
                                    <tr
                                        v-for="order in orders.data"
                                        :key="order.id"
                                        class="border-b last:border-none border-base-200"
                                        :class="order.has_pending_dispute ? 'bg-error/10 border-l-2 border-l-error' : 'bg-base-100'"
                                    >
                                    <th scope="row" class=" font-medium whitespace-nowrap">
                                        <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                    </th>
                                    <td>
                                        <div class="text-nowrap">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                        <div class="text-nowrap text-xs">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <GatewayLogo :img_path="order.payment_gateway_logo_path" class="w-10 h-10"/>
                                            <div>
                                                <PaymentDetail
                                                    :detail="order.payment_detail"
                                                    :type="order.payment_detail_type"
                                                    :copyable="false"
                                                    class=""
                                                ></PaymentDetail>
                                                <div class="text-xs text-nowrap">{{ order.payment_detail_name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {{ order.trader_email }}
                                    </td>
                                    <td>
                                        <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                    </td>
                                    <td>
                                        <DateTime class="justify-start" :data="order.created_at"/>
                                    </td>
                                    <td class=" text-right">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <button
                                                v-if="order.dispute"
                                                @click.prevent="modalStore.openDisputeModal({dispute: order.dispute})"
                                                type="button"
                                                class="btn btn-error btn-outline btn-xs"
                                                :disabled="reloadingTableData"
                                                aria-label="Открыть споры"
                                            >
                                                <svg class="w-3.5 h-3.5" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail')"
                                                @click.prevent="confirmAcceptOrder(order)"
                                                type="button"
                                                class="btn btn-success btn-outline btn-xs"
                                                :disabled="reloadingTableData"
                                                aria-label="Оплачен"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </button>
                                            <OrderDetailsOpenButton
                                                :disabled="reloadingTableData"
                                                @click="openOrderModal(order)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="order in orders.data"
                                :key="order.id"
                                :class="order.has_pending_dispute ? 'ring-1 ring-error/40 rounded-box' : ''"
                                :body-class="order.has_pending_dispute ? 'p-4 pt-2 pb-3 bg-error/10 rounded-box' : 'p-4 pt-2 pb-3'"
                            >
                                    <!-- Шапка: UUID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-2">
                                        <div class="inline-flex items-center">
                                            <span class="text-base-content/70">UUID:</span> <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="order.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Для экранов sm и больше -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <GatewayLogo :img_path="order.payment_gateway_logo_path" class="w-10 h-10"/>
                                            <div>
                                                <PaymentDetail
                                                    :detail="order.payment_detail"
                                                    :type="order.payment_detail_type"
                                                    :copyable="false"
                                                ></PaymentDetail>
                                                <div class="text-xs text-nowrap">{{ order.payment_detail_name }}</div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                            <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                        </div>
                                        <div>
                                            <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                        </div>
                                        <div>
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    v-if="order.dispute"
                                                    @click.prevent="modalStore.openDisputeModal({dispute: order.dispute})"
                                                    type="button"
                                                    class="btn btn-error btn-outline btn-xs"
                                                    :disabled="reloadingTableData"
                                                    aria-label="Открыть споры"
                                                >
                                                    <svg class="w-4 h-4" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                    </svg>
                                                </button>
                                                <button
                                                    v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail')"
                                                    @click.prevent="confirmAcceptOrder(order)"
                                                    type="button"
                                                    class="btn btn-success btn-outline btn-xs"
                                                    :disabled="reloadingTableData"
                                                    aria-label="Оплачен"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </button>
                                                <OrderDetailsOpenButton
                                                    square
                                                    :disabled="reloadingTableData"
                                                    @click="openOrderModal(order)"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Для экранов меньше sm -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <GatewayLogo :img_path="order.payment_gateway_logo_path" class="w-10 h-10"/>
                                                <div>
                                                    <PaymentDetail
                                                        :detail="order.payment_detail"
                                                        :type="order.payment_detail_type"
                                                        :copyable="false"
                                                    ></PaymentDetail>
                                                    <div class="text-xs text-nowrap">{{ order.payment_detail_name }}</div>
                                                </div>
                                            </div>
                                            <div>
                                                <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-nowrap text-sm text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
                                            </div>
                                            <div>
                                                <div class="inline-flex items-center gap-2">
                                                    <button
                                                        v-if="order.dispute"
                                                        @click.prevent="modalStore.openDisputeModal({dispute: order.dispute})"
                                                        type="button"
                                                        class="btn btn-error btn-outline btn-xs"
                                                        :disabled="reloadingTableData"
                                                        aria-label="Открыть споры"
                                                    >
                                                        <svg class="w-4 h-4" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail')"
                                                        @click.prevent="confirmAcceptOrder(order)"
                                                        type="button"
                                                        class="btn btn-success btn-outline btn-xs"
                                                        :disabled="reloadingTableData"
                                                        aria-label="Оплачен"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </button>
                                                    <OrderDetailsOpenButton
                                                        square
                                                        :disabled="reloadingTableData"
                                                        @click="openOrderModal(order)"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <DisputeModal
            @accept="confirmAcceptDispute"
            @cancel="modalStore.openDisputeCancelModal({dispute:$event})"
            @rollback="confirmRollbackDispute"
        />
        <CancelDisputeModal/>
        <OrderModal/>
        <ConfirmModal/>
    </div>
</template>
