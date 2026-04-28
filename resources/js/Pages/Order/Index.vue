<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderStatus from "@/Components/OrderStatus.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import OrderModal from "@/Modals/OrderModal.vue";
import {useModalStore} from "@/store/modal.js";
import DateTime from "@/Components/DateTime.vue";
import {useViewStore} from "@/store/view.js";
import {computed, ref, unref, watch} from "vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import EditOrderAmountModal from "@/Modals/Order/EditOrderAmountModal.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import RefreshTableData from "@/Components/Table/RefreshTableData.vue";
import DateFilter from "@/Components/Filters/Pertials/DateFilter.vue";
import TempVipBanner from "@/Pages/MainPage/Trader/TempVipBanner.vue";
import DisputeModal from "@/Modals/DisputeModal.vue";
import CancelDisputeModal from "@/Modals/CancelDisputeModal.vue";
import TraderExportModal from "@/Components/Export/TraderExportModal.vue";
import {useHasActiveTableFilters} from "@/composables/useHasActiveTableFilters.js";
//import MoneyTreeGame from "@/Components/AprilFools/MoneyTreeGame.vue";

const viewStore = useViewStore();
const orders = ref(usePage().props.orders);
const tempVip = usePage().props.auth?.user?.temp_vip_progress || null;
const modalStore = useModalStore();

const displayShortDetail = ref(getCookieValue('displayShortDetail', false));

function getCookieValue(name, defaultValue) {
    const currentRoute = route().current();
    const cookieName = `${name}_${currentRoute}`;
    const match = document.cookie.match(new RegExp('(^| )' + cookieName + '=([^;]+)'));
    return match ? match[2] === 'true' : defaultValue;
}

function updateDisplayShortDetailCookie() {
    const currentRoute = route().current();
    const cookieName = `displayShortDetail_${currentRoute}`;
    document.cookie = `${cookieName}=${displayShortDetail.value}; path=/; max-age=31536000`; // 1 год
}

// Следим за изменениями и обновляем cookie
watch(displayShortDetail, () => {
    updateDisplayShortDetailCookie();
});

const filtersVariants = ref(usePage().props.filtersVariants);
const showExportModal = ref(false);

router.on('success', (event) => {
    orders.value = usePage().props.orders;
})

const reloadingTableData = ref(false);
const filtersPanelRef = ref(null);
const hasActiveOrderFilters = useHasActiveTableFilters();

const filtersPanelOpen = computed(() => unref(filtersPanelRef.value?.displayFilters) ?? false);

const toggleFiltersFromToolbar = () => {
    filtersPanelRef.value?.toggleFiltersDisplay?.();
};

const openOrderModal = (order) => {
    if (reloadingTableData.value) {
        return;
    }
    modalStore.openOrderModal({order_id: order.id})
}

const confirmAcceptOrder = (order) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите  закрыть сделку как оплаченную?',
        confirm_button_name: 'Платеж поступил',
        confirm: () => {
            useForm({}).patch(route('orders.accept', order.id), {
                preserveScroll: true,
                onSuccess: () => {
                    modalStore.closeAll()
                    router.visit(route(viewStore.adminPrefix + 'orders.index'), {
                        only: ['orders'],
                    })
                },
            })
        }
    });
}

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
                        class="inline-flex max-w-full flex-wrap items-center justify-end gap-2 rounded-xl border border-base-300/80 bg-base-300/80 px-2.5 py-1.5 shadow-sm"
                    >
                        <div class="relative inline-flex shrink-0">
                            <button
                                type="button"
                                class="btn btn-sm btn-square btn-primary btn-outline rounded-lg"
                                :class="{ 'btn-active': filtersPanelOpen }"
                                title="Фильтры"
                                aria-label="Показать или скрыть фильтры"
                                @click.prevent="toggleFiltersFromToolbar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                </svg>
                            </button>
                            <span
                                v-if="hasActiveOrderFilters"
                                class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border border-base-100 bg-error"
                                aria-hidden="true"
                                title="Есть применённые фильтры"
                            />
                        </div>

                        <button
                            v-if="viewStore.isTraderViewMode"
                            type="button"
                            class="btn btn-sm btn-square btn-primary btn-outline shrink-0 rounded-lg"
                            title="Выгрузить в Excel"
                            aria-label="Выгрузить сделки в Excel"
                            @click="openExportModal"
                        >
                            <svg
                                class="h-5 w-5 shrink-0 text-primary"
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

                    <TempVipBanner
                        v-if="!viewStore.isAdminViewMode && tempVip?.enabled"
                        :temp-vip="tempVip"
                    />
                    <FiltersPanel
                        ref="filtersPanelRef"
                        name="orders"
                        omit-default-toggle-button
                    >
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
                    </FiltersPanel>
                </div>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div
                            class="card sticky top-0 left-0 bg-base-100/50 z-10 flex items-center justify-center backdrop-blur-sm transition-all duration-300 ease-in-out opacity-0 pointer-events-none"
                            :class="{'opacity-0 pointer-events-none': !reloadingTableData, 'opacity-100': reloadingTableData}"
                            style="position: absolute; inset: 0; width: 100%; height: 100%;"
                        >
                            <div class="flex flex-col items-center transition-transform duration-300" :class="{'scale-90 opacity-0': !reloadingTableData, 'scale-100 opacity-100': reloadingTableData}">
                                <div class="animate-spin inline-block w-8 h-8 border-[3px] border-current border-t-transparent text-primary rounded-full" role="status" aria-label="loading">
                                    <span class="sr-only">Загрузка...</span>
                                </div>
                                <div class="mt-2 text-sm font-medium text-base-content">Загрузка данных...</div>
                            </div>
                        </div>

                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm" :class="{'pointer-events-none': reloadingTableData}">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            UUID
                                        </th>
                                        <th scope="col">
                                            Сумма
                                        </th>
                                        <th scope="col" class="flex items-center">
                                            Реквизит
                                            <label class="swap swap-rotate inline-grid place-items-center ml-2 cursor-pointer w-6 h-6">
                                                <input type="checkbox" v-model="displayShortDetail" class="sr-only" />
                                                <!-- Коротко (скрываем детали) -->
                                                <svg class="swap-on w-5 h-5 text-base-content/70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                                <!-- Полностью (показываем), праймари -->
                                                <svg class="swap-off w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </label>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in orders.data" class="bg-base-100 border-b last:border-none border-base-200">
                                    <th scope="row" class="font-medium whitespace-nowrap text-gray-900 dark:text-gray-200">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span
                                                v-if="viewStore.isAdminViewMode && order.manual_control_acquiring"
                                                class="badge badge-primary badge-xs"
                                                title="Manual Control Acquiring"
                                            >
                                                MC
                                            </span>
                                            <DisplayUUID :uuid="order.uuid"/>
                                        </div>
                                    </th>
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
                                                :short="displayShortDetail"
                                            ></PaymentDetail>
                                        </div>
                                    </td>
                                    <td v-if="viewStore.isAdminViewMode">
                                        <div>
                                            <div class="flex items-center gap-2 text-nowrap">
                                                <svg class="w-5 h-5 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="1.5" d="M7 17v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3Zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                                <span class="text-base-content">{{ order.trader_email }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-nowrap">
                                                <svg class="w-4 h-4 ml-0.5 mr-0.5 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                </svg>
                                                <span class="text-base-content/70">{{ order.device_name ?? 'Без устройства' }}</span>
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
                                                v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode && !viewStore.isAnalystViewMode"
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
                                            <button
                                                class="btn btn-primary btn-outline btn-xs"
                                                @click.prevent="openOrderModal(order)"
                                                :disabled="reloadingTableData"
                                                aria-label="Открыть сделку"
                                            >
                                                <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                    <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                            </button>
                                        </div>
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
                                            <DisplayUUID :uuid="order.uuid"/>
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
                                            ></PaymentDetail>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                            <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
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
                                                v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode && !viewStore.isAnalystViewMode"
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
                                            <button
                                                type="button"
                                                class="btn btn-square btn-primary btn-outline btn-xs"
                                                @click.prevent="openOrderModal(order)"
                                                :disabled="reloadingTableData"
                                                aria-label="Открыть сделку"
                                            >
                                                <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                    <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                            </button>
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
                                                ></PaymentDetail>
                                            </div>
                                            <div>
                                                <OrderStatus :status="order.status" :status_name="order.status_name"></OrderStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2">

                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="inline-flex gap-3">
                                                <div class="text-nowrap text-xs text-base-content">{{ order.amount }} {{ order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ order.total_profit }} {{ order.base_currency.toUpperCase() }}</div>
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
                                                    v-if="!order.has_dispute && (order.status === 'pending' || order.status === 'fail') && !viewStore.isSupportViewMode && !viewStore.isAnalystViewMode"
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
                                                <button
                                                    type="button"
                                                    class="btn btn-square btn-primary btn-outline btn-xs"
                                                    @click.prevent="openOrderModal(order)"
                                                    :disabled="reloadingTableData"
                                                    aria-label="Открыть сделку"
                                                >
                                                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                        <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    </svg>
                                                </button>
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

        <OrderModal/>
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
    </div>
</template>
