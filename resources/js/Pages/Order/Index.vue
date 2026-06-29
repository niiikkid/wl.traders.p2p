<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useModalStore} from "@/store/modal.js";
import DateTime from "@/Components/DateTime.vue";
import {useViewStore} from "@/store/view.js";
import {ref} from "vue";
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import EditOrderAmountModal from "@/Modals/Order/EditOrderAmountModal.vue";
import OrderModal from "@/Modals/OrderModal.vue";
import OrderDetailsOpenButton from "@/Components/Order/OrderDetailsOpenButton.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import RefreshTableData from "@/Components/Table/RefreshTableData.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import DateFilter from "@/Components/Filters/Partials/DateFilter.vue";
import DisputeModal from "@/Modals/DisputeModal.vue";
import CancelDisputeModal from "@/Modals/CancelDisputeModal.vue";
import TraderExportModal from "@/Components/Export/TraderExportModal.vue";
import MoneyValue from "@/Components/MoneyValue.vue";
import {useConfirmAcceptOrder} from '@/composables/useConfirmAcceptOrder.js';
import PaymentDetailInfoDropdown from "@/Components/PaymentDetailInfoDropdown.vue";
import PaymentDetailEditModal from "@/Modals/PaymentDetail/PaymentDetailEditModal.vue";
import IncomingSmsLogsModal from "@/Modals/Order/IncomingSmsLogsModal.vue";
//import MoneyTreeGame from "@/Components/AprilFools/MoneyTreeGame.vue";

const viewStore = useViewStore();
const orders = ref(usePage().props.orders);
const modalStore = useModalStore();
const { confirmAcceptOrder } = useConfirmAcceptOrder();

const filtersVariants = ref(usePage().props.filtersVariants);
const showExportModal = ref(false);
const incomingSmsLogsModalOpen = ref(false);
const incomingSmsLogsUnlinkedCount = ref(usePage().props.incomingSmsLogsUnlinkedCount ?? 0);

router.on('success', (event) => {
    orders.value = usePage().props.orders;
    incomingSmsLogsUnlinkedCount.value = usePage().props.incomingSmsLogsUnlinkedCount ?? incomingSmsLogsUnlinkedCount.value;
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
            useForm({}).patch(route('disputes.accept', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route(viewStore.adminPrefix + 'orders.index'), {
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
            useForm({}).patch(route('disputes.rollback', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route(viewStore.adminPrefix + 'orders.index'), {
                        only: ['orders'],
                    })
                },
            });
        }
    });
};

const openExportModal = () => {
    showExportModal.value = true;
};

const closeExportModal = () => {
    showExportModal.value = false;
};

const openManualControlAcqPage = () => {
    window.open(route('admin.manual-control-acq.show'), '_blank', 'noopener');
};

const openIncomingSmsLogsModal = () => {
    incomingSmsLogsModalOpen.value = true;
};

const closeIncomingSmsLogsModal = () => {
    incomingSmsLogsModalOpen.value = false;
};

const handleIncomingSmsLogsCountUpdated = (count) => {
    incomingSmsLogsUnlinkedCount.value = count;
    router.reload({
        only: ['orders', 'incomingSmsLogsUnlinkedCount'],
        preserveScroll: true,
    });
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
                <div class="flex max-w-full min-w-0 flex-wrap items-center justify-end gap-2">
                    <div
                        v-if="reloadingTableData"
                        class="flex w-full items-center justify-end gap-2 text-sm text-base-content/80 xl:hidden"
                        aria-live="polite"
                    >
                        <span class="loading loading-spinner loading-sm text-primary" role="status" aria-label="Загрузка" />
                        <span class="hidden sm:inline">Загрузка данных…</span>
                        <span class="sm:hidden">Загрузка…</span>
                    </div>

                    <div
                        v-if="viewStore.isTraderViewMode || viewStore.isAdminViewMode"
                        class="inline-flex shrink-0 items-center rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm"
                    >
                        <button
                            type="button"
                            class="btn btn-sm h-8 min-h-8 gap-2 rounded-lg px-3 btn-primary btn-outline"
                            title="Сообщения"
                            aria-label="Открыть поступления"
                            @click="openIncomingSmsLogsModal"
                        >
                            <svg class="h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <span class="hidden sm:inline">Сообщения</span>
                            <span
                                v-if="incomingSmsLogsUnlinkedCount > 0"
                                class="badge badge-warning badge-xs"
                            >
                                {{ incomingSmsLogsUnlinkedCount }}
                            </span>
                        </button>
                    </div>

                    <div
                        class="inline-flex max-w-full flex-wrap items-center justify-end gap-2 rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm"
                    >
                        <button
                            v-if="viewStore.isTraderViewMode"
                            type="button"
                            class="btn btn-sm btn-square btn-primary btn-outline shrink-0 rounded-lg"
                            title="Выгрузить в Excel"
                            aria-label="Выгрузить сделки в Excel"
                            @click="openExportModal"
                        >
                            <svg
                                class="h-5 w-5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M9.29289 1.29289C9.48043 1.10536 9.73478 1 10 1H18C19.6569 1 21 2.34315 21 4V9C21 9.55228 20.5523 10 20 10C19.4477 10 19 9.55228 19 9V4C19 3.44772 18.5523 3 18 3H11V8C11 8.55228 10.5523 9 10 9H5V20C5 20.5523 5.44772 21 6 21H7C7.55228 21 8 21.4477 8 22C8 22.5523 7.55228 23 7 23H6C4.34315 23 3 21.6569 3 20V8C3 7.73478 3.10536 7.48043 3.29289 7.29289L9.29289 1.29289ZM6.41421 7H9V4.41421L6.41421 7ZM19 12C19.5523 12 20 12.4477 20 13V19H23C23.5523 19 24 19.4477 24 20C24 20.5523 23.5523 21 23 21H19C18.4477 21 18 20.5523 18 20V13C18 12.4477 18.4477 12 19 12ZM11.8137 12.4188C11.4927 11.9693 10.8682 11.8653 10.4188 12.1863C9.96935 12.5073 9.86526 13.1318 10.1863 13.5812L12.2711 16.5L10.1863 19.4188C9.86526 19.8682 9.96935 20.4927 10.4188 20.8137C10.8682 21.1347 11.4927 21.0307 11.8137 20.5812L13.5 18.2205L15.1863 20.5812C15.5073 21.0307 16.1318 21.1347 16.5812 20.8137C17.0307 20.4927 17.1347 19.8682 16.8137 19.4188L14.7289 16.5L16.8137 13.5812C17.1347 13.1318 17.0307 12.5073 16.5812 12.1863C16.1318 11.8653 15.5073 11.9693 15.1863 12.4188L13.5 14.7795L11.8137 12.4188Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </button>

                        <button
                            v-if="viewStore.isAdminViewMode"
                            type="button"
                            class="btn btn-sm shrink-0 rounded-lg btn-primary btn-outline px-3 min-h-8 h-8 font-semibold tracking-tight"
                            title="Manual Control ACQ"
                            @click="openManualControlAcqPage"
                        >
                            ACQ
                        </button>

                        <RefreshTableData
                            icon-only
                            @refresh-started="reloadingTableData = true"
                            @refresh-finished="reloadingTableData = false"
                        />
                    </div>
                </div>
            </template>
            <template v-slot:header>
                <div class="space-y-4">
<!--                    <div class="w-full max-w-md">
                        <MoneyTreeGame />
                    </div>-->

                    <FiltersPanel name="orders">
                        <DateFilter name="startDate" title="Начальная дата"/>
                        <DateFilter name="endDate" title="Конечная дата"/>
                        <InputFilter
                            v-if="viewStore.isAdminViewMode"
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
                            v-if="viewStore.isAdminViewMode"
                            name="user"
                            placeholder="Пользователь"
                        />
                        <DropdownFilter
                            name="orderStatuses"
                            :options="filtersVariants.orderStatuses"
                            title="Статусы"
                        />
                        <DropdownFilter
                            v-if="viewStore.isTraderViewMode"
                            name="hasDispute"
                            title="Наличие спора"
                        />
                        <DropdownFilter
                            v-if="viewStore.isTraderViewMode"
                            name="disputeStatuses"
                            title="Статусы споров"
                        />
                    </FiltersPanel>
                </div>
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
                                        <th scope="col" v-if="viewStore.isAdminViewMode">
                                            Профиль
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col">
                                            Создан
                                        </th>
                                        <th scope="col">
                                           
                                        </th>
                        </template>
                                    <tr
                                        v-for="order in orders.data"
                                        :key="order.id"
                                        class="border-b last:border-none border-base-200"
                                        :class="order.has_pending_dispute ? 'bg-error/10 border-l-2 border-l-error' : 'bg-base-100'"
                                    >
                                    <th scope="row" class="font-medium whitespace-nowrap text-gray-900 dark:text-gray-200">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span
                                                v-if="viewStore.isAdminViewMode && order.manual_control_acquiring"
                                                class="badge badge-primary badge-xs"
                                                title="Manual Control Acquiring"
                                            >
                                                MC
                                            </span>
                                            <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                        </div>
                                    </th>
                                    <td>
                                        <MoneyValue :value="order.amount" :currency="order.currency" block />
                                        <MoneyValue
                                            v-if="viewStore.isAdminViewMode"
                                            :value="order.total_profit"
                                            :currency="order.base_currency"
                                            secondary
                                            block
                                        />
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <GatewayLogo :img_path="order.payment_gateway_logo_path" :name="order.payment_gateway_name" class="w-10 h-10 text-base-content/50"/>
                                            <PaymentDetail
                                                :detail="order.payment_detail"
                                                :type="order.payment_detail_type"
                                                :name="order.payment_detail_name"
                                            >
                                                <template #actions>
                                                    <PaymentDetailInfoDropdown
                                                        v-if="viewStore.isTraderViewMode && order.payment_detail_uuid"
                                                        :payment-detail-uuid="order.payment_detail_uuid"
                                                    />
                                                </template>
                                            </PaymentDetail>
                                        </div>
                                    </td>
                                    <td v-if="viewStore.isAdminViewMode" class="min-w-0">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 text-nowrap">
                                                <svg class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="1.5" d="M7 17v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3Zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                                <span class="text-base-content">{{ order.trader_email }}</span>
                                            </div>
                                            <div class="flex min-w-0 items-center gap-2">
                                                <svg class="ml-0.5 mr-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                </svg>
                                                <span
                                                    class="min-w-0 max-w-[8rem] flex-1 truncate text-base-content/70"
                                                    :title="order.device_name ?? 'Без устройства'"
                                                >{{ order.device_name ?? 'Без устройства' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                    </td>
                                    <td>
                                        <DateTime class="justify-start" :data="order.created_at"/>
                                    </td>
                                    <td class="text-right">
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
                                                v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode"
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
                                                :has-order-sms="order.has_order_sms"
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
                                    <!-- Компактная шапка: логотип, короткий реквизит, сумма и переключатель -->
                                    <div class="flex justify-between items-center border-b border-base-content/10">
                                        <div class="inline-flex items-center">
                                            <span
                                                v-if="viewStore.isAdminViewMode && order.manual_control_acquiring"
                                                class="badge badge-primary badge-xs mr-1.5"
                                                title="Manual Control Acquiring"
                                            >
                                                MC
                                            </span>
                                            <span class="text-base-content/70">UUID:</span>
                                            <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="order.created_at"/>
                                        </div>
                                    </div> 
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <GatewayLogo :img_path="order.payment_gateway_logo_path" :name="order.payment_gateway_name" class="w-10 h-10 text-base-content/50"/>
                                            <PaymentDetail
                                                :detail="order.payment_detail"
                                                :type="order.payment_detail_type"
                                                :name="order.payment_detail_name"
                                            >
                                                <template #actions>
                                                    <PaymentDetailInfoDropdown
                                                        v-if="viewStore.isTraderViewMode && order.payment_detail_uuid"
                                                        :payment-detail-uuid="order.payment_detail_uuid"
                                                    />
                                                </template>
                                            </PaymentDetail>
                                        </div>
                                        <div>
                                            <MoneyValue :value="order.amount" :currency="order.currency" block />
                                            <MoneyValue
                                                v-if="viewStore.isAdminViewMode"
                                                :value="order.total_profit"
                                                :currency="order.base_currency"
                                                secondary
                                                block
                                            />
                                        </div>
                                        <div>
                                            <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                        </div>
                                        <div class="inline-flex shrink-0 items-center justify-end gap-1 w-15">
                                            <button
                                                v-if="order.dispute"
                                                type="button"
                                                class="btn btn-square btn-error btn-outline btn-xs"
                                                @click.prevent="modalStore.openDisputeModal({dispute: order.dispute})"
                                                :disabled="reloadingTableData"
                                                aria-label="Открыть спор"
                                            >
                                                <svg class="h-3 w-3" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode"
                                                type="button"
                                                class="btn btn-square btn-success btn-outline btn-xs"
                                                @click.prevent="confirmAcceptOrder(order)"
                                                :disabled="reloadingTableData"
                                                aria-label="Оплачено"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </button>
                                            <OrderDetailsOpenButton
                                                square
                                                :has-order-sms="order.has_order_sms"
                                                :disabled="reloadingTableData"
                                                @click="openOrderModal(order)"
                                            />
                                        </div>
                                    </div>
                                    <!--Для всего что меньше sm size-->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <GatewayLogo :img_path="order.payment_gateway_logo_path" :name="order.payment_gateway_name" class="w-10 h-10 text-base-content/50"/>
                                                <PaymentDetail
                                                    :detail="order.payment_detail"
                                                    :type="order.payment_detail_type"
                                                    :name="order.payment_detail_name"
                                                >
                                                    <template #actions>
                                                        <PaymentDetailInfoDropdown
                                                            v-if="viewStore.isTraderViewMode && order.payment_detail_uuid"
                                                            :payment-detail-uuid="order.payment_detail_uuid"
                                                        />
                                                    </template>
                                                </PaymentDetail>
                                            </div>
                                            <div>
                                                <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2">

                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="inline-flex gap-3">
                                                <MoneyValue :value="order.amount" :currency="order.currency" compact />
                                                <MoneyValue
                                                    v-if="viewStore.isAdminViewMode"
                                                    :value="order.total_profit"
                                                    :currency="order.base_currency"
                                                    secondary
                                                />
                                            </div>
                                            <div class="inline-flex shrink-0 items-center gap-1">
                                                <button
                                                    v-if="order.dispute"
                                                    type="button"
                                                    class="btn btn-square btn-error btn-outline btn-xs"
                                                    @click.prevent="modalStore.openDisputeModal({dispute: order.dispute})"
                                                    :disabled="reloadingTableData"
                                                    aria-label="Открыть спор"
                                                >
                                                    <svg class="h-3 w-3" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                    </svg>
                                                </button>
                                                <button
                                                    v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode"
                                                    type="button"
                                                    class="btn btn-square btn-success btn-outline btn-xs"
                                                    @click.prevent="confirmAcceptOrder(order)"
                                                    :disabled="reloadingTableData"
                                                    aria-label="Оплачено"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </button>
                                                <OrderDetailsOpenButton
                                                    square
                                                    :has-order-sms="order.has_order_sms"
                                                    :disabled="reloadingTableData"
                                                    @click="openOrderModal(order)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <OrderModal/>
        <PaymentDetailEditModal/>
        <EditOrderAmountModal/>
        <DisputeModal
            @accept="confirmAcceptDispute"
            @cancel="modalStore.openDisputeCancelModal({dispute:$event})"
            @rollback="confirmRollbackDispute"
        />
        <CancelDisputeModal/>
        <ConfirmModal/>
        <TraderExportModal
            :show="showExportModal"
            route-name="trader.export.orders"
            entity-label="сделки"
            @close="closeExportModal"
        />
        <IncomingSmsLogsModal
            :show="incomingSmsLogsModalOpen"
            @close="closeIncomingSmsLogsModal"
            @count-updated="handleIncomingSmsLogsCountUpdated"
        />
    </div>
</template>
