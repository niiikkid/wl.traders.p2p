<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaymentDetail from "@/Components/PaymentDetail.vue";
import PaymentDetailLimit from "@/Components/PaymentDetailLimit.vue";
import PaymentDetailOrdersLimit from "@/Components/PaymentDetailOrdersLimit.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import {computed, onBeforeUnmount, onMounted, ref, watch} from "vue";
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
import PaymentDetailResetLimitsModal from "@/Modals/PaymentDetail/PaymentDetailResetLimitsModal.vue";
import PaymentDetailScheduleStatus from "@/Components/PaymentDetail/PaymentDetailScheduleStatus.vue";
import PaymentDetailScheduleServerClock from "@/Components/PaymentDetail/PaymentDetailScheduleServerClock.vue";
import PaymentDetailScheduleSummary from "@/Components/PaymentDetail/PaymentDetailScheduleSummary.vue";
import DateTime from "@/Components/DateTime.vue";
import {usePaymentDetailScheduleTableTick} from "@/composables/usePaymentDetailScheduleTableTick.js";

const modalStore = useModalStore();
const openCreateModal = () => {
    modalStore.openPaymentDetailCreateModal();
};
const openEditModal = (paymentDetail) => {
    modalStore.openPaymentDetailEditModal({ paymentDetail });
};
const openResetLimitsModal = (paymentDetail) => {
    modalStore.openPaymentDetailResetLimitsModal({ paymentDetail });
};
const openBulkEditModal = () => {
    if (selectionModeEnabled.value && selectedDetailIds.value.length) {
        const selectedPreview = paymentDetails.value.data
            .filter((detail) => selectedDetailIds.value.includes(Number(detail.id)))
            .map((detail) => `#${detail.id} ${detail.name || detail.detail || ''}`.trim());

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
const currentTab = ref('active');
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

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
};

const formatInteger = (value) => {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 0,
    }).format(Math.trunc(number));
};

const formatMoneyAmount = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const normalized = String(value).replace(/\s/g, '').replace(',', '.');
    const number = Number(normalized);

    if (!Number.isFinite(number)) {
        return String(value);
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
};

const percentFrom = (current, limit) => {
    const current_value = normalizeNumber(current);
    const limit_value = normalizeNumber(limit);

    if (limit_value <= 0) {
        return 0;
    }

    return Math.min(100, (current_value / limit_value) * 100);
};

const hasLimit = (limit) => {
    return normalizeNumber(limit) > 0;
};

const progressClass = (percent, has_limit = true) => {
    if (!has_limit) {
        return 'text-base-content/40';
    }

    if (percent < 40) {
        return 'text-success';
    }

    if (percent < 80) {
        return 'text-warning';
    }

    return 'text-error';
};

const percentLabel = (percent) => {
    if (!Number.isFinite(percent)) {
        return '0%';
    }

    return `${Math.round(percent)}%`;
};

const radialStyle = (value) => {
    return {
        '--value': value,
        '--size': '2.4rem',
        '--thickness': '3px',
    };
};

const toggleActive = (detail_id) => {
    detailActiveToggleForm.patch(route('payment-details.toggle-active', detail_id), {
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
        title: 'Вы уверены что хотите архивировать реквизит #' + detail.id + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Архивировать',
        confirm: () => {
            router.post(route('payment-details.archive', detail.id), {}, {
                preserveScroll: true
            });
        }
    });
};

const confirmUnarchiveDetail = (detail) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите вернуть реквизит из архива #' + detail.id + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Вернуть',
        confirm: () => {
            router.delete(route('payment-details.unarchive', detail.id), {}, {
                preserveScroll: true
            });
        }
    });
};

const openPage = (tab) => {
    tableFiltersStore.setTab(tab);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route(route().current()), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    })
}

const openScheduleManagerModal = () => {
    modalStore.openPaymentDetailScheduleManagerModal();
};

const detailUsesManualProcessing = (paymentDetail) => {
    return !paymentDetail.user_device_id;
};

const shouldShowProcessingIndicator = (paymentDetail) => {
    return viewStore.isAdminViewMode || !!paymentDetail.owner_can_work_without_device;
};

const processingModeBadgeClass = (paymentDetail) => {
    return detailUsesManualProcessing(paymentDetail)
        ? 'badge-warning badge-outline'
        : 'badge-success badge-outline';
};

const processingModeLabel = (paymentDetail) => {
    return detailUsesManualProcessing(paymentDetail) ? 'Ручной' : 'Автоматика';
};

const currentPageDetailIds = computed(() => {
    return (paymentDetails.value?.data || [])
        .map((detail) => Number(detail.id))
        .filter((id) => Number.isFinite(id));
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

const toggleDetailSelection = (detailId) => {
    const normalizedId = Number(detailId);
    if (!Number.isFinite(normalizedId)) {
        return;
    }

    if (selectedDetailIds.value.includes(normalizedId)) {
        selectedDetailIds.value = selectedDetailIds.value.filter((id) => id !== normalizedId);
        return;
    }

    selectedDetailIds.value = [...selectedDetailIds.value, normalizedId];
};

onMounted(() => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('active');
    }
    currentTab.value = tableFiltersStore.getTab
})

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Реквизиты" />

        <MainTableSection
            title="Реквизиты"
            :data="paymentDetails"
        >
            <template v-slot:header>
                <div class="flex w-full min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <a @click.prevent="openPage('active')" href="#" :class="currentTab === 'active' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" aria-current="page">
                                <svg class="w-4 h-4 sm:mr-2 mr-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023"/>
                                </svg>
                                <span class="sm:block hidden">Активные</span>
                            </a>
                        </li>
                        <li class="me-2">
                            <a @click.prevent="openPage('archived')" href="#" :class="currentTab === 'archived' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" aria-current="page">
                                <svg class="w-4 h-4 sm:mr-2 mr-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M10 12v1h4v-1m4 7H6a1 1 0 0 1-1-1V9h14v9a1 1 0 0 1-1 1ZM4 5h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>
                                </svg>
                                <span class="sm:block hidden">Архив</span>
                            </a>
                        </li>
                    </ul>

                    <div class="flex w-full max-w-full min-w-0 flex-wrap items-center justify-end gap-2 sm:ms-auto sm:w-auto">
                        <div
                            class="inline-flex max-w-full flex-wrap items-center justify-end gap-2 rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm"
                        >
                            <button
                                v-if="viewStore.isAdminViewMode"
                                type="button"
                                class="btn btn-sm btn-square btn-primary btn-outline shrink-0 rounded-lg"
                                title="Включенные реквизиты"
                                aria-label="Включенные реквизиты"
                                @click="router.visit(route('admin.enabled-cards.index'), { preserveScroll: true })"
                            >
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="4" y="14" width="3.5" height="6" rx="1.75" stroke="currentColor" stroke-width="1.3"/>
                                <rect x="10.25" y="9" width="3.5" height="11" rx="1.75" stroke="currentColor" stroke-width="1.3"/>
                                <rect x="16.5" y="4" width="3.5" height="16" rx="1.75" stroke="currentColor" stroke-width="1.3"/>
                            </svg>
                      
                            </button>

                            <button
                                type="button"
                                class="hidden md:inline-flex btn btn-sm btn-square btn-accent btn-outline shrink-0 rounded-lg"
                                title="Создать реквизиты"
                                aria-label="Создать реквизиты"
                                @click="openCreateModal"
                            >
                                <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M21 12.5V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V17C3 18.1046 3.89543 19 5 19H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M18.5 15V17.5M18.5 20V17.5M18.5 17.5H16M18.5 17.5H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M3 10H20.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M7 15H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                        <AddMobileIcon variant="accent" @click="openCreateModal" />
                    </div>
                </div>
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="payment-details">
                    <InputFilter
                        name="id"
                        placeholder="ID реквизита"
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
                        class="mb-2 flex flex-wrap items-center gap-1"
                    >
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
                        <PaymentDetailScheduleServerClock v-if="displayDetailSchedule" />
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
                    </div>

                    <PaymentDetailScheduleSummary
                        v-if="displayDetailSchedule && displayScheduleSummary && scheduleSummary"
                        :summary="scheduleSummary"
                    />

                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
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
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
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
                                                    <TableActionsDropdown
                                                        buttonClass="swap swap-rotate cursor-pointer inline-grid place-items-center w-6 h-6 text-primary"
                                                    >
                                                        <template #icon>
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                                            </svg>
                                                        </template>
                                                        <TableAction
                                                            v-if="isTraderView"
                                                            @click="openScheduleManagerModal"
                                                        >
                                                            Расписания работы
                                                        </TableAction>
                                                        <TableAction @click="openBulkEditModal">
                                                            Массовая настройка
                                                        </TableAction>
                                                    </TableActionsDropdown>
                                                </div>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="payment_detail in paymentDetails.data" :key="payment_detail.id">
                                        <tr>
                                            <th scope="row" class="font-medium whitespace-nowrap">{{ payment_detail.id }}</th>
                                            <td v-if="selectionModeEnabled">
                                                <label class="label cursor-pointer justify-center p-0">
                                                    <input
                                                        type="checkbox"
                                                        class="checkbox checkbox-xs"
                                                        :checked="selectedDetailIds.includes(Number(payment_detail.id))"
                                                        @change="toggleDetailSelection(payment_detail.id)"
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
                                                        <div class="flex items-center gap-2">
                                                            <div class="relative grid place-items-center">
                                                                <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                                <div
                                                                    class="radial-progress absolute inset-0"
                                                                    :class="progressClass(
                                                                        percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity),
                                                                        hasLimit(payment_detail.max_pending_orders_quantity)
                                                                    )"
                                                                    :style="radialStyle(percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity))"
                                                                    role="progressbar"
                                                                    :aria-valuenow="percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity)"
                                                                >
                                                                    <span class="text-[10px] leading-none">
                                                                        {{ percentLabel(percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity)) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="relative grid place-items-center">
                                                                <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                                <div
                                                                    class="radial-progress absolute inset-0"
                                                                    :class="progressClass(
                                                                        percentFrom(payment_detail.current_daily_successful_orders_count, payment_detail.daily_successful_orders_limit),
                                                                        hasLimit(payment_detail.daily_successful_orders_limit)
                                                                    )"
                                                                    :style="radialStyle(percentFrom(payment_detail.current_daily_successful_orders_count, payment_detail.daily_successful_orders_limit))"
                                                                    role="progressbar"
                                                                    :aria-valuenow="percentFrom(payment_detail.current_daily_successful_orders_count, payment_detail.daily_successful_orders_limit)"
                                                                >
                                                                    <span class="text-[10px] leading-none">
                                                                        {{ percentLabel(percentFrom(payment_detail.current_daily_successful_orders_count, payment_detail.daily_successful_orders_limit)) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="relative grid place-items-center">
                                                                <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                                <div
                                                                    class="radial-progress absolute inset-0"
                                                                    :class="progressClass(
                                                                        percentFrom(payment_detail.current_daily_limit, payment_detail.daily_limit),
                                                                        hasLimit(payment_detail.daily_limit)
                                                                    )"
                                                                    :style="radialStyle(percentFrom(payment_detail.current_daily_limit, payment_detail.daily_limit))"
                                                                    role="progressbar"
                                                                    :aria-valuenow="percentFrom(payment_detail.current_daily_limit, payment_detail.daily_limit)"
                                                                >
                                                                    <span class="text-[10px] leading-none">
                                                                        {{ percentLabel(percentFrom(payment_detail.current_daily_limit, payment_detail.daily_limit)) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div class="grid gap-2 text-sm">
                                                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                                <span class="text-[11px] font-semibold uppercase tracking-wide text-base-content/60">В день</span>
                                                                <span class="badge badge-xs badge-outline">суточные</span>
                                                            </div>
                                                            <div class="grid gap-2">
                                                                <div class="grid gap-1">
                                                                    <div class="flex min-w-0 flex-nowrap items-center justify-between gap-2">
                                                                        <div class="min-w-0 truncate text-xs text-base-content/70">Активных сделок</div>
                                                                        <div class="relative shrink-0 text-nowrap">
                                                                            <span
                                                                                class="text-xs font-semibold"
                                                                                :class="{
                                                                                    'text-success': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 40,
                                                                                    'text-warning': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 40 && percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 80,
                                                                                    'text-error': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 80
                                                                                }"
                                                                            >
                                                                                {{ payment_detail.pending_orders_count }}
                                                                            </span>
                                                                            <span class="mx-1 opacity-70">из</span>
                                                                            <span class="text-xs font-semibold">
                                                                                {{ payment_detail.max_pending_orders_quantity }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <progress
                                                                        class="progress w-full"
                                                                        :class="{
                                                                            'progress-success': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 40,
                                                                            'progress-warning': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 40 && percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 80,
                                                                            'progress-error': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 80
                                                                        }"
                                                                        :value="percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity)"
                                                                        max="100"
                                                                    ></progress>
                                                                </div>
                                                                <div class="grid gap-1">
                                                                    <PaymentDetailOrdersLimit
                                                                        label="Количество сделок"
                                                                        :current_daily_successful_orders_count="payment_detail.current_daily_successful_orders_count"
                                                                        :daily_successful_orders_limit="payment_detail.daily_successful_orders_limit"
                                                                    />
                                                                </div>
                                                                <div class="grid gap-1">
                                                                    <PaymentDetailLimit
                                                                        label="Объём сделок"
                                                                        :current_daily_limit="payment_detail.current_daily_limit"
                                                                        :daily_limit="payment_detail.daily_limit"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                                <span class="text-[11px] font-semibold uppercase tracking-wide text-base-content/60">В месяц</span>
                                                                <span class="badge badge-xs badge-outline">сброс {{ payment_detail.monthly_limit_reset_day ?? '—' }}</span>
                                                            </div>
                                                            <div class="grid gap-2">
                                                                <div v-if="hasLimit(payment_detail.monthly_limit)" class="grid gap-1">
                                                                    <PaymentDetailLimit
                                                                        label="Объём сделок"
                                                                        :current_daily_limit="payment_detail.current_monthly_limit"
                                                                        :daily_limit="payment_detail.monthly_limit"
                                                                    />
                                                                </div>
                                                                <div class="grid gap-1">
                                                                    <PaymentDetailOrdersLimit
                                                                        label="Количество сделок"
                                                                        :current_daily_successful_orders_count="payment_detail.current_monthly_successful_orders_count"
                                                                        :daily_successful_orders_limit="payment_detail.monthly_successful_orders_limit"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
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
                                                        <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success toggle-sm" @change="toggleActive(payment_detail.id)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
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
                                                        <div class="grid gap-2 text-sm">
                                                            <div v-if="viewStore.isAdminViewMode" class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Профиль:</span>
                                                                <span class="text-right">{{ payment_detail.owner_email }}</span>
                                                            </div>
                                                            <div v-if="shouldShowProcessingIndicator(payment_detail)" class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Обработка:</span>
                                                                <span class="badge badge-sm" :class="processingModeBadgeClass(payment_detail)">
                                                                    {{ processingModeLabel(payment_detail) }}
                                                                </span>
                                                            </div>
                                                            <div v-if="payment_detail.user_device_id" class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Устройство:</span>
                                                                <span class="text-right">{{ payment_detail.device_name }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Интервал:</span>
                                                                <span class="text-right">{{ payment_detail.order_interval_minutes !== null ? payment_detail.order_interval_minutes + ' мин' : '-' }}</span>
                                                            </div>
                                                            <div v-if="viewStore.isAdminViewMode || canSetOrderAmountLimits" class="grid gap-1">
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/70">Мин:</span>
                                                                    <span class="text-right">{{ payment_detail.min_order_amount !== null ? payment_detail.min_order_amount : '∞' }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/70">Макс:</span>
                                                                    <span class="text-right">{{ payment_detail.max_order_amount !== null ? payment_detail.max_order_amount : '∞' }}</span>
                                                                </div>
                                                            </div>
                                                            <div class="divider my-1"></div>
                                                            <div class="grid gap-1.5">
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/50">Сделок:</span>
                                                                    <span class="text-right font-medium">{{ formatInteger(payment_detail.successful_orders_total_count) }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/50">Оборот:</span>
                                                                    <span class="text-right font-medium">{{ formatMoneyAmount(payment_detail.successful_orders_total_turnover_fiat) }} <span class="text-primary">{{ payment_detail.currency?.toUpperCase?.() }}</span></span>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/50">Оборот:</span>
                                                                    <span class="text-right font-medium">{{ formatMoneyAmount(payment_detail.successful_orders_total_turnover_usdt) }} <span class="text-primary">USDT</span></span>
                                                                </div>
                                                                <div class="pt-1 text-[11px] text-base-content/50 text-center">
                                                                    Обновляется раз в 15 минут
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TableInfoDropdown>
                                                    <TableActionsDropdown v-if="currentTab === 'active'">
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
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="payment_detail in paymentDetails.data"
                                :key="payment_detail.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <div class="flex justify-between items-center gap-2">
                                        <div class="inline-flex items-center gap-2 min-w-0 text-xs">
                                            <span class="text-base-content/70 shrink-0">ID:</span>
                                            <span class="font-medium text-base-content truncate">{{ payment_detail.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-0 shrink-0 gap-3">
                                            <label class="label cursor-pointer justify-start gap-2 p-0">
                                                <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success toggle-xs" @change="toggleActive(payment_detail.id)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
                                            </label>
                                            <TableActionsDropdown button-class="btn btn-ghost btn-circle btn-xs">
                                                <template v-if="currentTab === 'active'">
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

                                    <div class="border-b border-base-content/10"></div>

                                    <div class="flex items-start gap-2 min-w-0">
                                        <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10 shrink-0"/>
                                        <PaymentDetail
                                            :detail="payment_detail.detail"
                                            :type="payment_detail.detail_type"
                                            :name="payment_detail.name"
                                            :show-processing-indicator="shouldShowProcessingIndicator(payment_detail)"
                                            :uses-manual-processing="detailUsesManualProcessing(payment_detail)"
                                        />
                                    </div>

                                    <template v-if="displayDetailSchedule">
                                        <div class="border-b border-base-content/10"></div>

                                        <div class="text-xs">
                                            <div class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60 mb-1">
                                                Расписание
                                            </div>
                                            <PaymentDetailScheduleStatus
                                                :schedule="payment_detail.schedule"
                                                compact
                                            />
                                        </div>
                                    </template>

                                    <template v-if="isTraderView && displayDetailLastDeal">
                                        <div class="border-b border-base-content/10"></div>

                                        <div class="text-xs">
                                            <div class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60 mb-1">
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
                                    </template>

                                    <div class="border-b border-base-content/10"></div>

                                    <div class="flex items-start justify-between gap-2 text-xs text-base-content/80">
                                        <span class="text-nowrap pt-1">
                                            <span
                                                class="font-semibold"
                                                :class="{
                                                    'text-success': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 40,
                                                    'text-warning': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 40 && percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 80,
                                                    'text-error': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 80
                                                }"
                                            >
                                                {{ payment_detail.pending_orders_count }}
                                            </span>
                                            <span class="mx-0.5 opacity-70">из</span>
                                            <span class="font-semibold">{{ payment_detail.max_pending_orders_quantity }}</span>
                                            <span class="text-base-content/50 ml-1">активных</span>
                                        </span>

                                        <TableInfoDropdown button-class="btn btn-ghost btn-circle btn-xs">
                                            <div class="grid gap-1.5 text-xs leading-tight">
                                                <div v-if="viewStore.isAdminViewMode" class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/70">Профиль:</span>
                                                    <span class="text-right">{{ payment_detail.owner_email }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/70">Обработка:</span>
                                                    <span class="badge badge-xs" :class="processingModeBadgeClass(payment_detail)">
                                                        {{ processingModeLabel(payment_detail) }}
                                                    </span>
                                                </div>
                                                <div v-if="payment_detail.user_device_id" class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/70">Устройство:</span>
                                                    <span class="text-right">{{ payment_detail.device_name }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/70">Интервал:</span>
                                                    <span class="text-right">{{ payment_detail.order_interval_minutes !== null ? payment_detail.order_interval_minutes + ' мин' : '-' }}</span>
                                                </div>
                                                <div v-if="viewStore.isAdminViewMode || canSetOrderAmountLimits" class="grid gap-1">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/70">Мин:</span>
                                                        <span class="text-right">{{ payment_detail.min_order_amount !== null ? payment_detail.min_order_amount : '∞' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/70">Макс:</span>
                                                        <span class="text-right">{{ payment_detail.max_order_amount !== null ? payment_detail.max_order_amount : '∞' }}</span>
                                                    </div>
                                                </div>
                                                <div class="divider my-0.5"></div>
                                                <div class="rounded-box border border-base-200 bg-base-100 p-2">
                                                    <div class="mb-1.5 flex items-center justify-between gap-2">
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">В день</span>
                                                        <span class="badge badge-xs badge-outline">суточные</span>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <div class="grid gap-1">
                                                            <div class="flex min-w-0 flex-nowrap items-center justify-between gap-2">
                                                                <div class="min-w-0 truncate text-[10px] text-base-content/70">Активных сделок</div>
                                                                <div class="relative shrink-0 text-nowrap">
                                                                    <span
                                                                        class="text-xs font-semibold"
                                                                        :class="{
                                                                            'text-success': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 40,
                                                                            'text-warning': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 40 && percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 80,
                                                                            'text-error': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 80
                                                                        }"
                                                                    >
                                                                        {{ payment_detail.pending_orders_count }}
                                                                    </span>
                                                                    <span class="mx-1 opacity-70">из</span>
                                                                    <span class="text-xs font-semibold">
                                                                        {{ payment_detail.max_pending_orders_quantity }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <progress
                                                                class="progress w-full"
                                                                :class="{
                                                                    'progress-success': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 40,
                                                                    'progress-warning': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 40 && percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) < 80,
                                                                    'progress-error': percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity) >= 80
                                                                }"
                                                                :value="percentFrom(payment_detail.pending_orders_count, payment_detail.max_pending_orders_quantity)"
                                                                max="100"
                                                            ></progress>
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <PaymentDetailOrdersLimit
                                                                label="Количество сделок"
                                                                :current_daily_successful_orders_count="payment_detail.current_daily_successful_orders_count"
                                                                :daily_successful_orders_limit="payment_detail.daily_successful_orders_limit"
                                                            />
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <PaymentDetailLimit
                                                                label="Объём сделок"
                                                                :current_daily_limit="payment_detail.current_daily_limit"
                                                                :daily_limit="payment_detail.daily_limit"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="rounded-box border border-base-200 bg-base-100 p-2">
                                                    <div class="mb-1.5 flex items-center justify-between gap-2">
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">В месяц</span>
                                                        <span class="badge badge-xs badge-outline">сброс {{ payment_detail.monthly_limit_reset_day ?? '—' }}</span>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <div v-if="hasLimit(payment_detail.monthly_limit)" class="grid gap-1">
                                                            <PaymentDetailLimit
                                                                label="Объём сделок"
                                                                :current_daily_limit="payment_detail.current_monthly_limit"
                                                                :daily_limit="payment_detail.monthly_limit"
                                                            />
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <PaymentDetailOrdersLimit
                                                                label="Количество сделок"
                                                                :current_daily_successful_orders_count="payment_detail.current_monthly_successful_orders_count"
                                                                :daily_successful_orders_limit="payment_detail.monthly_successful_orders_limit"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="divider my-0.5"></div>
                                                <div class="grid gap-1">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/50">Сделок:</span>
                                                        <span class="text-right font-medium">{{ formatInteger(payment_detail.successful_orders_total_count) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/50">Оборот:</span>
                                                        <span class="text-right font-medium">{{ formatMoneyAmount(payment_detail.successful_orders_total_turnover_fiat) }} <span class="text-primary">{{ payment_detail.currency?.toUpperCase?.() }}</span></span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/50">Оборот:</span>
                                                        <span class="text-right font-medium">{{ formatMoneyAmount(payment_detail.successful_orders_total_turnover_usdt) }} <span class="text-primary">USDT</span></span>
                                                    </div>
                                                    <div class="pt-0.5 text-[10px] text-base-content/50 text-center">
                                                        Обновляется раз в 15 минут
                                                    </div>
                                                </div>
                                            </div>
                                        </TableInfoDropdown>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <PaymentDetailCreateModal />
        <PaymentDetailEditModal />
        <PaymentDetailBulkEditModal />
        <PaymentDetailResetLimitsModal />
        <ConfirmModal/>
    </div>
</template>
