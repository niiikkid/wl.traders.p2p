<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaymentDetail from "@/Components/PaymentDetail.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import PageToolbar from "@/Components/Table/PageToolbar.vue";
import PageToolbarAction from "@/Components/Table/PageToolbarAction.vue";
import {computed, onBeforeUnmount, ref, watch} from "vue";
import PaymentDetailsNav from '@/Components/Admin/PaymentDetailsNav.vue';
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import FilterCheckbox from "@/Components/Filters/Partials/FilterCheckbox.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import TableInfoDropdown from "@/Components/Table/TableInfoDropdown.vue";
import TableCellPopover from "@/Components/Table/TableCellPopover.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import PaymentDetailCreateModal from "@/Modals/PaymentDetail/PaymentDetailCreateModal.vue";
import PaymentDetailEditModal from "@/Modals/PaymentDetail/PaymentDetailEditModal.vue";
import PaymentDetailBulkEditModal from "@/Modals/PaymentDetail/PaymentDetailBulkEditModal.vue";
import PaymentDetailVolumeStatisticsModal from "@/Modals/PaymentDetail/PaymentDetailVolumeStatisticsModal.vue";
import PaymentDetailResetLimitsModal from "@/Modals/PaymentDetail/PaymentDetailResetLimitsModal.vue";
import PaymentDetailScheduleStatus from "@/Components/PaymentDetail/PaymentDetailScheduleStatus.vue";
import PaymentDetailScheduleServerClock from "@/Components/PaymentDetail/PaymentDetailScheduleServerClock.vue";
import PaymentDetailScheduleSummary from "@/Components/PaymentDetail/PaymentDetailScheduleSummary.vue";
import DateTime from "@/Components/DateTime.vue";
import {usePaymentDetailScheduleTableTick} from "@/composables/usePaymentDetailScheduleTableTick.js";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import CopyableOrderUid from "@/Components/CopyableOrderUid.vue";
import PaymentDetailLimitRings from "@/Components/PaymentDetail/PaymentDetailLimitRings.vue";
import PaymentDetailLimitsPanel from "@/Components/PaymentDetail/PaymentDetailLimitsPanel.vue";
import PaymentDetailMetaPanel from "@/Components/PaymentDetail/PaymentDetailMetaPanel.vue";
import PaymentDetailTotalsPanel from "@/Components/PaymentDetail/PaymentDetailTotalsPanel.vue";

const detailUuidLabel = (detail) => detail?.uuid_short ?? detail?.uuid?.slice(0, 8) ?? '';

const modalStore = useModalStore();
const openCreateModal = () => {
    modalStore.openPaymentDetailCreateModal();
};
const openEditModal = (paymentDetail) => {
    modalStore.openPaymentDetailEditModal({ paymentDetail });
};
const openVolumeStatisticsModal = (paymentDetail) => {
    modalStore.openPaymentDetailVolumeStatisticsModal({ uuid: paymentDetail.uuid, paymentDetail });
};
const openResetLimitsModal = (paymentDetail) => {
    modalStore.openPaymentDetailResetLimitsModal({ paymentDetail });
};
const openBulkEditModal = () => {
    if (selectionModeEnabled.value && selectedDetailIds.value.length) {
        const selectedPreview = paymentDetails.value.data
            .filter((detail) => selectedDetailIds.value.includes(detail.uuid))
            .map((detail) => `${detailUuidLabel(detail)} ${detail.name || detail.detail || ''}`.trim());

        modalStore.openPaymentDetailBulkEditModal({
            scope: 'selected',
            selected_ids: [...selectedDetailIds.value],
            selected_preview: selectedPreview,
        });

        return;
    }

    modalStore.openPaymentDetailBulkEditModal();
};
const viewStore = useViewStore();
const paymentDetails = ref(usePage().props.paymentDetails)
const scheduleServerClock = ref(usePage().props.scheduleServerClock)
const scheduleSummary = ref(usePage().props.scheduleSummary)
usePaymentDetailScheduleTableTick(paymentDetails, scheduleServerClock);
const detailActiveToggleForm = useForm({});
const currentTab = computed(() => tableFiltersStore.getTab || 'active');
const tableFiltersStore = useTableFiltersStore();
const toggleBlocked = ref(false);
const isTraderView = computed(() => viewStore.isTraderViewMode);
const selectionModeEnabled = ref(false);
const selectedDetailIds = ref([]);

const displayDetailLastDeal = ref(getCookieValue('displayDetailLastDeal', true));
const displayDetailSchedule = ref(getCookieValue('displayDetailSchedule', true));
const displayScheduleSummary = ref(false);

function getCookieValue(name, defaultValue) {
    const currentRoute = route().current();
    const cookieName = `${name}_${currentRoute}`;
    const match = document.cookie.match(new RegExp('(^| )' + cookieName + '=([^;]+)'));
    return match ? match[2] === 'true' : defaultValue;
}

const updateDisplayDetailLastDealCookie = () => {
    const currentRoute = route().current();
    const cookieName = `displayDetailLastDeal_${currentRoute}`;
    document.cookie = `${cookieName}=${displayDetailLastDeal.value}; path=/; max-age=31536000`;
};

watch(displayDetailLastDeal, () => {
    updateDisplayDetailLastDealCookie();
});

const updateDisplayDetailScheduleCookie = () => {
    const currentRoute = route().current();
    const cookieName = `displayDetailSchedule_${currentRoute}`;
    document.cookie = `${cookieName}=${displayDetailSchedule.value}; path=/; max-age=31536000`;
};

watch(displayDetailSchedule, (visible) => {
    updateDisplayDetailScheduleCookie();

    if (!visible) {
        displayScheduleSummary.value = false;
    }
});

let scheduleSummaryTimer = null;

const reloadScheduleSummary = () => {
    router.reload({
        only: ['scheduleSummary'],
        preserveScroll: true,
    });
};

watch(displayScheduleSummary, (visible) => {
    if (scheduleSummaryTimer) {
        clearInterval(scheduleSummaryTimer);
        scheduleSummaryTimer = null;
    }

    if (!visible) {
        return;
    }

    reloadScheduleSummary();
    scheduleSummaryTimer = setInterval(reloadScheduleSummary, 30_000);
});

onBeforeUnmount(() => {
    if (scheduleSummaryTimer) {
        clearInterval(scheduleSummaryTimer);
        scheduleSummaryTimer = null;
    }
});

const showTableColumnToggles = computed(() => viewStore.isAdminViewMode || isTraderView.value);

const columnToggleBadgeClass = (active) => (
    active
        ? 'badge-primary border-primary text-primary-content'
        : 'badge-outline border-primary/70 bg-base-100 text-base-content hover:border-primary hover:bg-primary/10'
);

const currentUser = usePage().props.auth?.user;

// Определяем, может ли трейдер настраивать лимиты суммы сделки
const canSetOrderAmountLimits = computed(() => {
    return currentUser?.can_set_order_amount_limits === true || currentUser?.can_set_order_amount_limits === 1;
});

const toggleActive = (detailUuid) => {
    detailActiveToggleForm.patch(route('payment-details.toggle-active', detailUuid), {
        preserveScroll: true,
        onSuccess: (result) => {
            paymentDetails.value = result.props.paymentDetails;
            // Блокируем тоггл на дополнительные 300 миллисекунд после получения ответа
            toggleBlocked.value = true;
            setTimeout(() => {
                toggleBlocked.value = false;
            }, 300);
        },
    });
};

router.on('success', (event) => {
    paymentDetails.value = usePage().props.paymentDetails;
    scheduleServerClock.value = usePage().props.scheduleServerClock;
    scheduleSummary.value = usePage().props.scheduleSummary;
    selectedDetailIds.value = [];
})

const confirmArchiveDetail = (detail) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите архивировать реквизит ' + detailUuidLabel(detail) + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Архивировать',
        confirm: () => {
            router.post(route('payment-details.archive', detail.uuid), {}, {
                preserveScroll: true
            });
        }
    });
};

const confirmUnarchiveDetail = (detail) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите вернуть реквизит из архива ' + detailUuidLabel(detail) + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Вернуть',
        confirm: () => {
            router.delete(route('payment-details.unarchive', detail.uuid), {}, {
                preserveScroll: true
            });
        }
    });
};

const openScheduleManagerModal = () => {
    modalStore.openPaymentDetailScheduleManagerModal();
};

const detailUsesManualProcessing = (paymentDetail) => {
    return !paymentDetail.user_device_id;
};

const shouldShowProcessingIndicator = (paymentDetail) => {
    return viewStore.isAdminViewMode || !!paymentDetail.owner_can_work_without_device;
};

const currentPageDetailIds = computed(() => {
    return (paymentDetails.value?.data || [])
        .map((detail) => detail.uuid)
        .filter((uuid) => typeof uuid === 'string' && uuid.length > 0);
});

const allCurrentPageSelected = computed(() => {
    if (!currentPageDetailIds.value.length) {
        return false;
    }

    return currentPageDetailIds.value.every((id) => selectedDetailIds.value.includes(id));
});

const selectedOnCurrentPageCount = computed(() => {
    return currentPageDetailIds.value.filter((id) => selectedDetailIds.value.includes(id)).length;
});

const toggleSelectionMode = () => {
    selectionModeEnabled.value = !selectionModeEnabled.value;
    if (!selectionModeEnabled.value) {
        selectedDetailIds.value = [];
    }
};

const toggleSelectAllOnPage = () => {
    if (allCurrentPageSelected.value) {
        selectedDetailIds.value = selectedDetailIds.value.filter((id) => !currentPageDetailIds.value.includes(id));
        return;
    }

    selectedDetailIds.value = Array.from(new Set([
        ...selectedDetailIds.value,
        ...currentPageDetailIds.value,
    ]));
};

const toggleDetailSelection = (detailUuid) => {
    const normalizedUuid = String(detailUuid ?? '').trim();
    if (!normalizedUuid) {
        return;
    }

    if (selectedDetailIds.value.includes(normalizedUuid)) {
        selectedDetailIds.value = selectedDetailIds.value.filter((uuid) => uuid !== normalizedUuid);
        return;
    }

    selectedDetailIds.value = [...selectedDetailIds.value, normalizedUuid];
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Реквизиты" />

        <MainTableSection
            title="Реквизиты"
            :data="paymentDetails"
        >
            <template #button>
                <PageToolbar>
                    <PageToolbarAction
                        v-if="isTraderView"
                        icon="schedule"
                        title="Расписание работы"
                        @click="openScheduleManagerModal"
                    />

                    <PageToolbarAction
                        icon="bulk-settings"
                        title="Массовая настройка"
                        @click="openBulkEditModal"
                    />

                    <PageToolbarAction
                        icon="create-requisite"
                        title="Создать реквизиты"
                        @click="openCreateModal"
                    />
                </PageToolbar>
            </template>
            <template v-slot:header>
                <div class="flex w-full min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <PaymentDetailsNav :current="currentTab" />
                </div>
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="payment-details">
                    <InputFilter
                        name="id"
                        placeholder="UUID реквизита"
                    />
                    <InputFilter
                        name="name"
                        placeholder="Название"
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
                        name="paymentDetail"
                        placeholder="Реквизит"
                    />
                    <InputFilter
                        v-if="viewStore.isAdminViewMode"
                        name="user"
                        placeholder="Пользователь"
                    />
                    <FilterCheckbox
                        name="active"
                        title="Включенные"
                    />
                    <FilterCheckbox
                        v-if="viewStore.isAdminViewMode"
                        name="multipliedDetails"
                        title="Размноженные"
                    />
                    <FilterCheckbox
                        v-if="viewStore.isAdminViewMode"
                        name="online"
                        title="Онлайн"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <div
                        v-if="showTableColumnToggles"
                        class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1.5"
                    >
                        <span class="text-xs font-medium text-base-content/50">Показывать:</span>
                        <button
                            v-if="isTraderView"
                            type="button"
                            class="badge badge-sm cursor-pointer border font-medium transition-colors"
                            :class="columnToggleBadgeClass(displayDetailLastDeal)"
                            :title="displayDetailLastDeal ? 'Скрыть время последней сделки' : 'Показать время последней сделки'"
                            @click="displayDetailLastDeal = !displayDetailLastDeal"
                        >
                            Последняя сделка
                        </button>
                        <button
                            type="button"
                            class="badge badge-sm cursor-pointer border font-medium transition-colors"
                            :class="columnToggleBadgeClass(displayDetailSchedule)"
                            :title="displayDetailSchedule ? 'Скрыть колонку расписания' : 'Показать колонку расписания'"
                            @click="displayDetailSchedule = !displayDetailSchedule"
                        >
                            Расписание
                        </button>
                        <button
                            v-if="displayDetailSchedule"
                            type="button"
                            class="badge badge-sm cursor-pointer border font-medium transition-colors"
                            :class="columnToggleBadgeClass(displayScheduleSummary)"
                            :title="displayScheduleSummary ? 'Скрыть сводку по расписанию' : 'Показать сводку по расписанию'"
                            @click="displayScheduleSummary = !displayScheduleSummary"
                        >
                            Сводка
                        </button>
                        <PaymentDetailScheduleServerClock v-if="displayDetailSchedule" class="ms-auto" />
                    </div>

                    <PaymentDetailScheduleSummary
                        v-if="displayDetailSchedule && displayScheduleSummary && scheduleSummary"
                        :summary="scheduleSummary"
                    />

                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #before>
                        <div v-if="selectionModeEnabled" class="mb-3 flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-sm">
                            <div class="min-w-0">
                                <div class="text-sm font-medium">
                                    Выберите реквизиты, которые хотите отредактировать
                                </div>
                                <div class="text-xs text-base-content/60">
                                    Выбрано: {{ selectedDetailIds.length }}
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary"
                                :class="{ 'btn-disabled': !selectedDetailIds.length }"
                                :disabled="!selectedDetailIds.length"
                                @click="openBulkEditModal"
                            >
                                Редактировать
                            </button>
                        </div>
                        </template>
                        <template #head>
                                        <th scope="col">
                                            UUID
                                        </th>
                                        <th v-if="selectionModeEnabled" scope="col" class="w-10">
                                            <label class="label cursor-pointer justify-center p-0">
                                                <input
                                                    type="checkbox"
                                                    class="checkbox checkbox-xs"
                                                    :checked="allCurrentPageSelected"
                                                    @change="toggleSelectAllOnPage"
                                                />
                                            </label>
                                        </th>
                                        <th scope="col">
                                            Реквизит
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Лимиты
                                        </th>
                                        <th v-if="displayDetailSchedule" scope="col" class="text-nowrap">
                                            Расписание
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Статус
                                        </th>
                                        <th v-if="isTraderView && displayDetailLastDeal" scope="col" class="text-nowrap">
                                            Последняя сделка
                                        </th>
                                        <th scope="col" class="text-right">
                                            <span class="sr-only">Действия</span>
                                            <div v-if="isTraderView" class="flex justify-end">
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="swap swap-rotate cursor-pointer inline-grid place-items-center w-7 h-7"
                                                        :class="selectionModeEnabled ? 'text-primary' : 'text-secondary'"
                                                        :title="selectionModeEnabled ? 'Режим выбора включен' : 'Выбрать реквизиты вручную'"
                                                        aria-label="Выбрать реквизиты вручную"
                                                        @click="toggleSelectionMode"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </th>
                        </template>
                                    <template v-for="payment_detail in paymentDetails.data" :key="payment_detail.uuid">
                                        <tr>
                                            <th scope="row" class="font-medium whitespace-nowrap">
                                                <CopyableOrderUid :uuid="payment_detail.uuid ?? ''" />
                                            </th>
                                            <td v-if="selectionModeEnabled">
                                                <label class="label cursor-pointer justify-center p-0">
                                                    <input
                                                        type="checkbox"
                                                        class="checkbox checkbox-xs"
                                                        :checked="selectedDetailIds.includes(payment_detail.uuid)"
                                                        @change="toggleDetailSelection(payment_detail.uuid)"
                                                    />
                                                </label>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10"/>
                                                    <PaymentDetail
                                                        :detail="payment_detail.detail"
                                                        :type="payment_detail.detail_type"
                                                        :name="payment_detail.name"
                                                        :show-processing-indicator="shouldShowProcessingIndicator(payment_detail)"
                                                        :uses-manual-processing="detailUsesManualProcessing(payment_detail)"
                                                    ></PaymentDetail>
                                                </div>
                                            </td>
                                            <td class="text-nowrap">
                                                <TableCellPopover>
                                                    <template #trigger>
                                                        <PaymentDetailLimitRings :payment-detail="payment_detail" />
                                                    </template>
                                                    <PaymentDetailLimitsPanel :payment-detail="payment_detail" />
                                                </TableCellPopover>
                                            </td>
                                            <td v-if="displayDetailSchedule" class="min-w-[9rem]">
                                                <PaymentDetailScheduleStatus
                                                    :schedule="payment_detail.schedule"
                                                    compact
                                                />
                                            </td>
                                            <td>
                                                <div class="flex items-center">
                                                    <label class="label cursor-pointer justify-start gap-2 py-0 min-h-0">
                                                        <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success toggle-sm" @change="toggleActive(payment_detail.uuid)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
                                                    </label>
                                                </div>
                                            </td>
                                            <td v-if="isTraderView && displayDetailLastDeal" class="text-nowrap text-xs">
                                                <DateTime
                                                    v-if="payment_detail.last_deal_at"
                                                    :data="payment_detail.last_deal_at"
                                                    :plural="true"
                                                    :copyable="false"
                                                />
                                                <span v-else class="text-base-content/50">—</span>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <TableInfoDropdown>
                                                        <div class="grid gap-2">
                                                            <PaymentDetailMetaPanel
                                                                :payment-detail="payment_detail"
                                                                :is-admin="viewStore.isAdminViewMode"
                                                                :can-set-order-amount-limits="canSetOrderAmountLimits"
                                                                :show-processing="shouldShowProcessingIndicator(payment_detail)"
                                                            />
                                                            <div class="divider my-0"></div>
                                                            <PaymentDetailTotalsPanel :payment-detail="payment_detail" />
                                                        </div>
                                                    </TableInfoDropdown>
                                                    <TableActionsDropdown v-if="currentTab === 'active'">
                                                        <TableAction @click="openVolumeStatisticsModal(payment_detail)">
                                                            Статистика
                                                        </TableAction>
                                                        <TableAction @click="openEditModal(payment_detail)">
                                                            Редактировать
                                                        </TableAction>
                                                        <TableAction @click="confirmArchiveDetail(payment_detail)">
                                                            Архивировать
                                                        </TableAction>
                                                        <TableAction @click="openResetLimitsModal(payment_detail)">
                                                            Сбросить лимиты
                                                        </TableAction>
                                                    </TableActionsDropdown>
                                                    <TableActionsDropdown v-else>
                                                        <TableAction @click="confirmUnarchiveDetail(payment_detail)">
                                                            Вернуть из архива
                                                        </TableAction>
                                                    </TableActionsDropdown>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="payment_detail in paymentDetails.data"
                                :key="payment_detail.uuid"
                                body-class="p-4 space-y-3"
                            >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10 shrink-0"/>
                                            <PaymentDetail
                                                :detail="payment_detail.detail"
                                                :type="payment_detail.detail_type"
                                                :name="payment_detail.name"
                                                :show-processing-indicator="shouldShowProcessingIndicator(payment_detail)"
                                                :uses-manual-processing="detailUsesManualProcessing(payment_detail)"
                                            ></PaymentDetail>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success toggle-sm" @change="toggleActive(payment_detail.uuid)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
                                            <TableActionsDropdown button-class="btn btn-ghost btn-circle btn-sm">
                                                <template v-if="currentTab === 'active'">
                                                    <TableAction @click="openVolumeStatisticsModal(payment_detail)">
                                                        Статистика
                                                    </TableAction>
                                                    <TableAction @click="openEditModal(payment_detail)">
                                                        Редактировать
                                                    </TableAction>
                                                    <TableAction @click="confirmArchiveDetail(payment_detail)">
                                                        Архивировать
                                                    </TableAction>
                                                    <TableAction @click="openResetLimitsModal(payment_detail)">
                                                        Сбросить лимиты
                                                    </TableAction>
                                                </template>
                                                <template v-else>
                                                    <TableAction @click="confirmUnarchiveDetail(payment_detail)">
                                                        Вернуть из архива
                                                    </TableAction>
                                                </template>
                                            </TableActionsDropdown>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 rounded-box bg-base-200/40 px-3 py-2">
                                        <PaymentDetailLimitRings :payment-detail="payment_detail" size="2.4rem" />
                                        <TableInfoDropdown button-class="btn btn-ghost btn-circle btn-sm">
                                            <div class="grid gap-2">
                                                <PaymentDetailMetaPanel
                                                    :payment-detail="payment_detail"
                                                    :is-admin="viewStore.isAdminViewMode"
                                                    :can-set-order-amount-limits="canSetOrderAmountLimits"
                                                    show-processing
                                                />
                                                <div class="divider my-0"></div>
                                                <PaymentDetailLimitsPanel :payment-detail="payment_detail" />
                                                <div class="divider my-0"></div>
                                                <PaymentDetailTotalsPanel :payment-detail="payment_detail" />
                                            </div>
                                        </TableInfoDropdown>
                                    </div>

                                    <div
                                        v-if="displayDetailSchedule || (isTraderView && displayDetailLastDeal)"
                                        class="grid gap-3 border-t border-base-content/10 pt-3 text-xs"
                                        :class="{ 'sm:grid-cols-2': displayDetailSchedule && isTraderView && displayDetailLastDeal }"
                                    >
                                        <div v-if="displayDetailSchedule" class="min-w-0">
                                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-base-content/50">
                                                Расписание
                                            </div>
                                            <PaymentDetailScheduleStatus
                                                :schedule="payment_detail.schedule"
                                                compact
                                            />
                                        </div>
                                        <div v-if="isTraderView && displayDetailLastDeal" class="min-w-0">
                                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-base-content/50">
                                                Последняя сделка
                                            </div>
                                            <DateTime
                                                v-if="payment_detail.last_deal_at"
                                                :data="payment_detail.last_deal_at"
                                                :plural="true"
                                                :copyable="false"
                                            />
                                            <span v-else class="text-base-content/50">—</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 border-t border-base-content/10 pt-2 text-xs">
                                        <span class="shrink-0 text-base-content/50">UUID</span>
                                        <CopyableOrderUid :uuid="payment_detail.uuid ?? ''" class="truncate" />
                                    </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <PaymentDetailCreateModal />
        <PaymentDetailEditModal />
        <PaymentDetailBulkEditModal />
        <PaymentDetailVolumeStatisticsModal />
        <PaymentDetailResetLimitsModal />
        <ConfirmModal/>
    </div>
</template>
