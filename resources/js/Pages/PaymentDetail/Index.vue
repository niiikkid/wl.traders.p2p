<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaymentDetail from "@/Components/PaymentDetail.vue";
import PaymentDetailLimit from "@/Components/PaymentDetailLimit.vue";
import PaymentDetailOrdersLimit from "@/Components/PaymentDetailOrdersLimit.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import {computed, onMounted, ref, watch} from "vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import TableInfoDropdown from "@/Components/Table/TableInfoDropdown.vue";
import TableCellPopover from "@/Components/Table/TableCellPopover.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import PaymentDetailCreateModal from "@/Modals/PaymentDetail/PaymentDetailCreateModal.vue";
import PaymentDetailEditModal from "@/Modals/PaymentDetail/PaymentDetailEditModal.vue";
import PaymentDetailBulkEditModal from "@/Modals/PaymentDetail/PaymentDetailBulkEditModal.vue";
import PaymentDetailTagCreateModal from "@/Modals/PaymentDetailTag/PaymentDetailTagCreateModal.vue";
import PaymentDetailTagManageModal from "@/Modals/PaymentDetailTag/PaymentDetailTagManageModal.vue";

const modalStore = useModalStore();
const openCreateModal = () => {
    modalStore.openPaymentDetailCreateModal();
};
const openEditModal = (paymentDetail) => {
    modalStore.openPaymentDetailEditModal({ paymentDetail });
};
const openBulkEditModal = () => {
    modalStore.openPaymentDetailBulkEditModal();
};
const viewStore = useViewStore();
const paymentDetails = ref(usePage().props.paymentDetails)
const paymentDetailTags = ref(usePage().props.paymentDetailTags || [])
const detailActiveToggleForm = useForm({});
const currentTab = ref('active');
const tableFiltersStore = useTableFiltersStore();
const toggleBlocked = ref(false);
const canWorkWithoutDevice = computed(() => !!usePage().props.auth?.user?.can_work_without_device);
const showDeviceColumn = computed(() => viewStore.isAdminViewMode || !canWorkWithoutDevice.value);
const isTraderView = computed(() => viewStore.isTraderViewMode);

const displayShortDetail = ref(getCookieValue('displayShortDetail', true));
const displayDetailTags = ref(getCookieValue('displayDetailTags', false));

const expandedCards = ref({});
const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

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

const updateDisplayDetailTagsCookie = () => {
    const currentRoute = route().current();
    const cookieName = `displayDetailTags_${currentRoute}`;
    document.cookie = `${cookieName}=${displayDetailTags.value}; path=/; max-age=31536000`;
};

watch(displayDetailTags, () => {
    updateDisplayDetailTagsCookie();
});

const currentUser = usePage().props.auth?.user;

// Определяем, является ли текущий пользователь VIP
const isVipUser = computed(() => {
    return currentUser?.is_vip === true || currentUser?.is_vip === 1 || currentUser?.is_temp_vip_active;
});

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
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
    paymentDetailTags.value = usePage().props.paymentDetailTags || [];
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

const openTagCreateModal = () => {
    modalStore.openPaymentDetailTagCreateModal();
};

const openTagManageModal = () => {
    modalStore.openPaymentDetailTagManageModal();
};

const toggleDisplayDetailTags = () => {
    displayDetailTags.value = !displayDetailTags.value;
};

const tagSyncProcessing = ref({});

const getDetailTagIds = (paymentDetail) => {
    return (paymentDetail.tags || []).map((tag) => tag.id);
};

const isTagSelected = (paymentDetail, tagId) => {
    return getDetailTagIds(paymentDetail).includes(tagId);
};

const isTagDisabled = (paymentDetail, tagId) => {
    const ids = getDetailTagIds(paymentDetail);
    return !ids.includes(tagId) && ids.length >= 3;
};

const tagBadgeStyle = (color) => {
    return {
        backgroundColor: color,
        color: '#ffffff',
    };
};

const syncDetailTags = (paymentDetail, tagId) => {
    if (!isTraderView.value) {
        return;
    }

    const currentIds = getDetailTagIds(paymentDetail);
    let nextIds = [];

    if (currentIds.includes(tagId)) {
        nextIds = currentIds.filter((id) => id !== tagId);
    } else {
        nextIds = [...currentIds, tagId];
    }

    if (nextIds.length > 3) {
        return;
    }

    tagSyncProcessing.value = {
        ...tagSyncProcessing.value,
        [paymentDetail.id]: true,
    };

    axios.patch(route('payment-details.tags.update', paymentDetail.id), {
        tags: nextIds,
    }, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            if (res.data?.success || res.status === 200) {
                router.reload({ only: ['paymentDetails', 'paymentDetailTags'] });
            }
        })
        .finally(() => {
            tagSyncProcessing.value = {
                ...tagSyncProcessing.value,
                [paymentDetail.id]: false,
            };
        });
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
            <template v-slot:button>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="hidden md:block btn btn-sm btn-primary"
                >
                    Создать реквизиты
                </button>
                <AddMobileIcon
                    @click="openCreateModal"
                />
            </template>
            <template v-slot:header>
                <div class="flex items-center justify-between gap-3">
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
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col" class="flex items-center">
                                            Реквизит
                                            <div class="inline-flex items-center ml-2">
                                                <label class="swap swap-rotate cursor-pointer inline-grid place-items-center w-6 h-6">
                                                    <input type="checkbox" v-model="displayShortDetail" class="sr-only" />
                                                    <svg class="swap-on w-5 h-5 text-base-content/70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                    </svg>
                                                    <svg class="swap-off w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                </label>
                                            </div>
                                        </th>
                                        <th v-if="isTraderView && displayDetailTags" scope="col">
                                            Теги
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Лимиты
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col" class="text-right">
                                            <span class="sr-only">Действия</span>
                                            <div v-if="isTraderView" class="flex justify-end">
                                                <TableActionsDropdown
                                                    buttonClass="swap swap-rotate cursor-pointer inline-grid place-items-center w-6 h-6 text-primary"
                                                >
                                                    <template #icon>
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                                        </svg>
                                                    </template>
                                                    <TableAction @click="openTagCreateModal">
                                                        Добавить новый тег
                                                    </TableAction>
                                                    <TableAction @click="openTagManageModal">
                                                        Редактировать теги
                                                    </TableAction>
                                                    <TableAction @click="openBulkEditModal">
                                                        Массовая настройка
                                                    </TableAction>
                                                    <TableAction @click="toggleDisplayDetailTags">
                                                        {{ displayDetailTags ? 'Скрыть теги' : 'Показать теги' }}
                                                    </TableAction>
                                                </TableActionsDropdown>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="payment_detail in paymentDetails.data" :key="payment_detail.id">
                                        <tr>
                                            <th scope="row" class="font-medium whitespace-nowrap">{{ payment_detail.id }}</th>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10"/>
                                                    <PaymentDetail
                                                        :detail="payment_detail.detail"
                                                        :type="payment_detail.detail_type"
                                                        :name="payment_detail.name"
                                                        :short="displayShortDetail"
                                                    ></PaymentDetail>
                                                </div>
                                            </td>
                                            <td v-if="isTraderView && displayDetailTags">
                                                <div class="flex items-center gap-2">
                                                    <TableCellPopover>
                                                        <template #trigger>
                                                            <span class="badge badge-xs badge-primary badge-outline flex items-center justify-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-2.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                                                                </svg>
                                                            </span>
                                                        </template>
                                                        <div class="grid gap-2 text-sm">
                                                            <div v-if="!paymentDetailTags.length" class="text-xs text-base-content/60">
                                                                Теги не созданы
                                                            </div>
                                                            <div v-else class="grid gap-2">
                                                                <label
                                                                    v-for="tag in paymentDetailTags"
                                                                    :key="tag.id"
                                                                    class="label cursor-pointer justify-start gap-2"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        class="checkbox checkbox-xs"
                                                                        :checked="isTagSelected(payment_detail, tag.id)"
                                                                        :disabled="tagSyncProcessing[payment_detail.id] || isTagDisabled(payment_detail, tag.id)"
                                                                        @change="syncDetailTags(payment_detail, tag.id)"
                                                                    />
                                                                    <span class="badge badge-xs border-0" :style="tagBadgeStyle(tag.color)">
                                                                        {{ tag.name }}
                                                                    </span>
                                                                </label>
                                                            </div>
                                                            <div class="text-[11px] text-base-content/60">
                                                                Максимум 3 тега на реквизит
                                                            </div>
                                                        </div>
                                                    </TableCellPopover>
                                                    <div class="flex items-center gap-1">
                                                        <span
                                                            v-for="tag in (payment_detail.tags || [])"
                                                            :key="tag.id"
                                                            class="badge badge-xs border-0 w-fit"
                                                            :style="tagBadgeStyle(tag.color)"
                                                        >
                                                            {{ tag.name }}
                                                        </span>
                                                    </div>
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
                                                    <div class="grid gap-3 text-sm">
                                                        <div class="grid gap-1">
                                                            <div class="text-xs text-base-content/70">Активных сделок</div>
                                                            <div class="flex justify-end mb-1">
                                                                <div class="relative text-nowrap">
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
                                                            <div class="text-xs text-base-content/70">Количество сделок за день</div>
                                                            <PaymentDetailOrdersLimit
                                                                :current_daily_successful_orders_count="payment_detail.current_daily_successful_orders_count"
                                                                :daily_successful_orders_limit="payment_detail.daily_successful_orders_limit"
                                                            />
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <div class="text-xs text-base-content/70">Объём сделок за день</div>
                                                            <PaymentDetailLimit
                                                                :current_daily_limit="payment_detail.current_daily_limit"
                                                                :daily_limit="payment_detail.daily_limit"
                                                            />
                                                        </div>
                                                    </div>
                                                </TableCellPopover>
                                            </td>
                                            <td>
                                                <div class="flex items-center">
                                                    <label class="label cursor-pointer justify-start gap-3">
                                                        <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success" @change="toggleActive(payment_detail.id)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <TableInfoDropdown>
                                                        <div class="grid gap-2 text-sm">
                                                            <div v-if="viewStore.isAdminViewMode" class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Профиль:</span>
                                                                <span class="text-right">{{ payment_detail.owner_email }}</span>
                                                            </div>
                                                            <div v-if="showDeviceColumn" class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Устройство:</span>
                                                                <span class="text-right">{{ payment_detail.device_name ?? 'Без устройства' }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="text-base-content/70">Интервал:</span>
                                                                <span class="text-right">{{ payment_detail.order_interval_minutes !== null ? payment_detail.order_interval_minutes + ' мин' : '-' }}</span>
                                                            </div>
                                                            <div v-if="viewStore.isAdminViewMode || isVipUser" class="grid gap-1">
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/70">Мин:</span>
                                                                    <span class="text-right">{{ payment_detail.min_order_amount !== null ? payment_detail.min_order_amount : '∞' }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-base-content/70">Макс:</span>
                                                                    <span class="text-right">{{ payment_detail.max_order_amount !== null ? payment_detail.max_order_amount : '∞' }}</span>
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
                                    <!-- Шапка: ID и статус-переключатель -->
                                    <div class="flex justify-between items-center">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content">{{ payment_detail.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <label class="label cursor-pointer justify-start gap-2">
                                                <input type="checkbox" :checked="payment_detail.is_active" class="toggle toggle-success toggle-sm" @change="toggleActive(payment_detail.id)" :disabled="detailActiveToggleForm.processing || toggleBlocked || currentTab === 'archived'">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="border-b border-base-content/10"></div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10"/>
                                            <PaymentDetail
                                                :detail="payment_detail.detail"
                                                :type="payment_detail.detail_type"
                                                :name="payment_detail.name"
                                            ></PaymentDetail>
                                        </div>
                                        <div class="text-sm text-nowrap">
                                            <span
                                                class="font-semibold"
                                                :class="{
                                                    'text-success': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity < 0.5,
                                                    'text-warning': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity >= 0.5 && payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity < 0.8,
                                                    'text-error': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity >= 0.8
                                                }"
                                            >
                                                {{ payment_detail.pending_orders_count }}
                                            </span>
                                            <span class="mx-1 opacity-70">из</span>
                                            <span class="font-semibold">
                                                {{ payment_detail.max_pending_orders_quantity }}
                                            </span>
                                        </div>
                                        <div class="text-right" v-if="viewStore.isAdminViewMode || isVipUser">
                                            <div class="text-nowrap text-xs"><span class="opacity-70">min:</span> {{ payment_detail.min_order_amount !== null ? payment_detail.min_order_amount : '∞' }}</div>
                                            <div class="text-nowrap text-xs"><span class="opacity-70">max:</span> {{ payment_detail.max_order_amount !== null ? payment_detail.max_order_amount : '∞' }}</div>
                                        </div>
                                        <div class="text-nowrap text-xs">
                                            {{ payment_detail.order_interval_minutes !== null ? payment_detail.order_interval_minutes + ' мин' : '-' }}
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(payment_detail.id)"
                                                :aria-expanded="!!expandedCards[payment_detail.id]"
                                                :aria-label="!!expandedCards[payment_detail.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[payment_detail.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <GatewayLogo :img_path="payment_detail.payment_gateway.logo_path" :name="payment_detail.payment_gateway.name" class="w-10 h-10"/>
                                                <PaymentDetail
                                                    :detail="payment_detail.detail"
                                                    :type="payment_detail.detail_type"
                                                    :name="payment_detail.name"
                                                ></PaymentDetail>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-nowrap text-xs">
                                                <span
                                                    class="font-semibold"
                                                    :class="{
                                                        'text-success': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity < 0.5,
                                                        'text-warning': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity >= 0.5 && payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity < 0.8,
                                                        'text-error': payment_detail.pending_orders_count / payment_detail.max_pending_orders_quantity >= 0.8
                                                    }"
                                                >
                                                    {{ payment_detail.pending_orders_count }}
                                                </span>
                                                <span class="mx-1 opacity-70">из</span>
                                                <span class="font-semibold">
                                                    {{ payment_detail.max_pending_orders_quantity }}
                                                </span>
                                            </div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(payment_detail.id)"
                                                    :aria-expanded="!!expandedCards[payment_detail.id]"
                                                    :aria-label="!!expandedCards[payment_detail.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[payment_detail.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[payment_detail.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="hidden sm:flex justify-between gap-2">
                                            <div>
                                                <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-2 text-sm">
                                                    <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                    </svg>
                                                    <span class="truncate">{{ payment_detail.owner_email }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                    </svg>
                                                <span class="truncate">
                                                    {{ payment_detail.device_name ?? 'Без устройства' }}
                                                </span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="grid gap-2 text-sm w-35">
                                                    <PaymentDetailLimit
                                                        :current_daily_limit="payment_detail.current_daily_limit"
                                                        :daily_limit="payment_detail.daily_limit"
                                                    />
                                                    <PaymentDetailOrdersLimit
                                                        :current_daily_successful_orders_count="payment_detail.current_daily_successful_orders_count"
                                                        :daily_successful_orders_limit="payment_detail.daily_successful_orders_limit"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sm:hidden grid gap-2">
                                            <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-2 text-sm">
                                                <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                <span class="truncate">{{ payment_detail.owner_email }}</span>
                                            </div>
                                            <div v-if="showDeviceColumn" class="flex items-center gap-2 text-sm">
                                                <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                </svg>
                                                <span class="truncate">
                                                    {{ payment_detail.device_name ?? 'Без устройства' }}
                                                </span>
                                            </div>
                                            <div class="grid gap-1 text-sm" v-if="viewStore.isAdminViewMode || isVipUser">
                                                <span>Сумма сделки:</span>
                                                <div class="text-nowrap text-xs" >
                                                    <span class="opacity-70">min:</span> {{ payment_detail.min_order_amount !== null ? payment_detail.min_order_amount : '∞' }}
                                                    <span class="opacity-70">max:</span> {{ payment_detail.max_order_amount !== null ? payment_detail.max_order_amount : '∞' }}
                                                </div>
                                            </div>
                                            <div class="grid gap-1 text-sm">
                                                <span>Интервал:</span>
                                                <div class="text-nowrap text-xs">
                                                    {{ payment_detail.order_interval_minutes !== null ? payment_detail.order_interval_minutes + ' мин' : '-' }}
                                                </div>
                                            </div>
                                            <div class="grid gap-1 text-sm">
                                                <span>Дневной лимит:</span>
                                                <div class="flex items-center gap-2 w-50">
                                                    <PaymentDetailLimit :current_daily_limit="payment_detail.current_daily_limit" :daily_limit="payment_detail.daily_limit"></PaymentDetailLimit>
                                                </div>
                                            </div>
                                            <div class="grid gap-1 text-sm">
                                                <span>Лимит сделок:</span>
                                                <div class="flex items-center gap-2 w-50">
                                                    <PaymentDetailOrdersLimit
                                                        :current_daily_successful_orders_count="payment_detail.current_daily_successful_orders_count"
                                                        :daily_successful_orders_limit="payment_detail.daily_successful_orders_limit"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center sm:justify-end justify-center gap-2 mt-1">
                                            <template v-if="currentTab === 'active'">
                                                <button class="btn btn-xs sm:btn-sm btn-outline" @click="openEditModal(payment_detail)">Редактировать</button>
                                                <button class="btn btn-xs sm:btn-sm btn-outline" @click="confirmArchiveDetail(payment_detail)">Архивировать</button>
                                            </template>
                                            <template v-else>
                                                <button class="btn btn-xs sm:btn-sm btn-outline" @click="confirmUnarchiveDetail(payment_detail)">Вернуть из архива</button>
                                            </template>
                                        </div>
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
        <PaymentDetailBulkEditModal :tags="paymentDetailTags" />
        <PaymentDetailTagCreateModal />
        <PaymentDetailTagManageModal :tags="paymentDetailTags" />
        <ConfirmModal/>
    </div>
</template>
