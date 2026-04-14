<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import {useViewStore} from '@/store/view.js';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import BankManualIcon from '@/Components/BankManualIcon.vue';
import DateTime from '@/Components/DateTime.vue';
import Modal from '@/Components/Modals/Modal.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import { formatDistanceStrict } from 'date-fns';
import DisplayUUID from "../../../Components/DisplayUUID.vue";
import TraderExportModal from '@/Components/Export/TraderExportModal.vue';

const PAYOUT_LIST_PER_PAGE = 10;

const props = defineProps({
    orderBook: {
        type: [Array, Object],
        required: true,
    },
    activePayouts: {
        type: Array,
        required: true,
    },
    history: {
        type: Object,
        required: true,
    },
    refresh: {
        type: Object,
        required: true,
    },
    limits: {
        type: Object,
        required: true,
    },
    activeListTab: {
        type: String,
        default: 'stack',
    },
});

const page = usePage();
const viewStore = useViewStore();

const trader = computed(() => page.props.auth?.user ?? {});
const orderBook = computed(() => props.orderBook ?? { data: [], meta: {} });
const activePayouts = computed(() => props.activePayouts ?? []);
const history = computed(() => props.history ?? { data: [], meta: {} });

const normalizeCollection = (collection) => {
    if (Array.isArray(collection)) {
        return collection;
    }

    if (Array.isArray(collection?.data)) {
        return collection.data;
    }

    return [];
};

const orderBookList = computed(() => normalizeCollection(orderBook.value));
const activePayoutsList = computed(() => normalizeCollection(activePayouts.value));
const historyList = computed(() => normalizeCollection(history.value));

const listTab = computed(() => (props.activeListTab === 'history' ? 'history' : 'stack'));

const orderBookMeta = computed(() => orderBook.value?.meta ?? {});
const historyMeta = computed(() => history.value?.meta ?? {});

const showStackPagination = computed(() => (orderBookMeta.value?.last_page ?? 1) > 1);
const showHistoryPagination = computed(() => (historyMeta.value?.last_page ?? 1) > 1);

/** Пока отключено: игнорируем сохранённый интервал и не ставим setInterval. */
const trader_payouts_auto_refresh_allowed = false;

const refreshInterval = ref(
    trader_payouts_auto_refresh_allowed ? (props.refresh.interval ?? 5) : 0,
);
const refreshStorageKey = 'trader-payouts-refresh-interval';
const refreshOptions = computed(() => props.refresh?.options ?? []);
const refreshProgress = ref(0);
let refreshProgressAnimationId = null;
const autoRefreshTimer = ref(null);
const isRefreshing = ref(false);

const canTakeMore = computed(() => (props.limits?.currentActive ?? 0) < (props.limits?.maxActive ?? 1));

const refreshOptionLabel = (value) => (value === 0 ? 'Не обновлять' : `Каждые ${value}с`);
const currentRefreshOptionLabel = computed(() => refreshOptionLabel(refreshInterval.value));

const persistRefreshInterval = (value) => {
    if (!trader_payouts_auto_refresh_allowed || typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(refreshStorageKey, String(value));
};

const selectRefreshInterval = (value) => {
    if (!trader_payouts_auto_refresh_allowed) {
        return;
    }

    if (!refreshOptions.value.includes(value) || refreshInterval.value === value) {
        return;
    }

    refreshInterval.value = value;
};

const syncRefreshIntervalFromStorage = () => {
    if (!trader_payouts_auto_refresh_allowed || typeof window === 'undefined') {
        return;
    }

    const storedValue = window.localStorage.getItem(refreshStorageKey);
    const parsed = Number(storedValue);

    if (!Number.isNaN(parsed) && refreshOptions.value.includes(parsed) && parsed !== refreshInterval.value) {
        refreshInterval.value = parsed;
    }
};

const getNow = () => {
    if (typeof window !== 'undefined' && window.performance?.now) {
        return window.performance.now();
    }

    return Date.now();
};

const stopRefreshProgressAnimation = () => {
    if (!refreshProgressAnimationId) {
        return;
    }

    if (typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function') {
        window.cancelAnimationFrame(refreshProgressAnimationId);
    }

    refreshProgressAnimationId = null;
};

const animateRefreshProgress = (duration) => {
    if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
        refreshProgress.value = 0;
        return;
    }

    stopRefreshProgressAnimation();

    if (!duration || duration <= 0) {
        refreshProgress.value = 0;
        return;
    }

    const startTime = getNow();

    const step = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        refreshProgress.value = progress * 100;

        if (progress < 1) {
            refreshProgressAnimationId = requestAnimationFrame(step);
            return;
        }

        refreshProgressAnimationId = null;
    };

    refreshProgressAnimationId = window.requestAnimationFrame(step);
};

const refreshProgressOffset = computed(() => 100 - Math.min(Math.max(refreshProgress.value, 0), 100));

const visitIndex = ({
    tab,
    page,
    stack_page,
    replace = true,
    trackRefreshing = false,
}) => {
    const resolved_tab = tab ?? listTab.value;
    const resolved_page = page ?? (historyMeta.value?.current_page ?? 1);
    const resolved_stack_page = stack_page ?? (orderBookMeta.value?.current_page ?? 1);

    if (trackRefreshing) {
        isRefreshing.value = true;
    }

    router.visit(route('trader.payouts.index'), {
        method: 'get',
        data: {
            tab: resolved_tab === 'history' ? 'history' : 'stack',
            page: resolved_page,
            stack_page: resolved_stack_page,
            per_page: PAYOUT_LIST_PER_PAGE,
            refresh_interval: trader_payouts_auto_refresh_allowed ? refreshInterval.value : 0,
        },
        preserveScroll: true,
        preserveState: true,
        replace,
        onFinish: () => {
            if (trackRefreshing) {
                isRefreshing.value = false;
            }
        },
    });
};

const reloadData = (replace = true) => {
    visitIndex({
        tab: listTab.value,
        page: historyMeta.value?.current_page ?? 1,
        stack_page: orderBookMeta.value?.current_page ?? 1,
        replace,
        trackRefreshing: true,
    });
};

const refreshNow = () => {
    if (isRefreshing.value) {
        return;
    }

    startAutoRefresh();
    visitIndex({
        tab: listTab.value,
        page: historyMeta.value?.current_page ?? 1,
        stack_page: orderBookMeta.value?.current_page ?? 1,
        replace: false,
        trackRefreshing: true,
    });
};

const openListTab = (next_tab) => {
    visitIndex({
        tab: next_tab,
        page: 1,
        stack_page: 1,
        replace: false,
        trackRefreshing: false,
    });
};

const onStackPagination = (p) => {
    visitIndex({
        tab: 'stack',
        page: historyMeta.value?.current_page ?? 1,
        stack_page: p,
        replace: false,
        trackRefreshing: false,
    });
};

const onHistoryPagination = (p) => {
    visitIndex({
        tab: 'history',
        page: p,
        stack_page: orderBookMeta.value?.current_page ?? 1,
        replace: false,
        trackRefreshing: false,
    });
};

const startAutoRefresh = () => {
    stopAutoRefresh();

    if (!trader_payouts_auto_refresh_allowed) {
        stopRefreshProgressAnimation();
        return;
    }

    if (refreshInterval.value > 0) {
        animateRefreshProgress(refreshInterval.value * 1000);
        autoRefreshTimer.value = setInterval(() => {
            animateRefreshProgress(refreshInterval.value * 1000);
            reloadData(false);
        }, refreshInterval.value * 1000);
    } else {
        stopRefreshProgressAnimation();
    }
};

const stopAutoRefresh = () => {
    if (autoRefreshTimer.value) {
        clearInterval(autoRefreshTimer.value);
        autoRefreshTimer.value = null;
    }
};

watch(refreshOptions, (options) => {
    if (!trader_payouts_auto_refresh_allowed) {
        return;
    }

    if (!options.includes(refreshInterval.value)) {
        refreshInterval.value = options[0] ?? 0;
    }
});

watch(refreshInterval, (value) => {
    if (!trader_payouts_auto_refresh_allowed) {
        return;
    }

    persistRefreshInterval(value);
    startAutoRefresh();

    if (value > 0) {
        reloadData(false);
    }
});

onMounted(() => {
    if (trader_payouts_auto_refresh_allowed) {
        syncRefreshIntervalFromStorage();
        startAutoRefresh();
    }
});

let active_payout_copied_clear_timer = null;

onBeforeUnmount(() => {
    stopAutoRefresh();
    stopRefreshProgressAnimation();
    if (active_payout_copied_clear_timer) {
        clearTimeout(active_payout_copied_clear_timer);
        active_payout_copied_clear_timer = null;
    }
});

const takePayout = (payout) => {
    router.post(route('trader.payouts.take', payout.uuid), {}, {
        preserveScroll: true,
        onStart: () => {
            stopAutoRefresh();
        },
        onFinish: () => {
            startAutoRefresh();
        },
    });
};

const formatHoldCountdown = (timestamp) => {
    if (!timestamp) {
        return null;
    }

    const target = new Date(timestamp);
    const now = new Date();

    if (target < now) {
        return 'ожидает подтверждения';
    }

    return formatDistanceStrict(now, target, { roundingMethod: 'floor', addSuffix: true });
};

const hasCustomBank = (payout) => !!payout?.bank_name;
const resolveBankName = (payout) => payout?.bank_name ?? payout?.payment_gateway?.name ?? '—';

/** Группы по 4 цифры для отображения номера карты (только визуально, без изменения данных). */
const formatCardDigitsGroups = (raw) => {
    if (raw === null || raw === undefined || raw === '') {
        return '';
    }

    const digits = String(raw).replace(/\D/g, '');

    if (digits.length === 0) {
        return String(raw);
    }

    const groups = [];

    for (let i = 0; i < digits.length; i += 4) {
        groups.push(digits.slice(i, i + 4));
    }

    return groups.join(' ');
};

/** Реквизит в карточке активной выплаты: для типа «Карта» — форматирование PAN пробелами. */
const displayActivePayoutRequisites = (payout) => {
    if (payout?.payout_method_type?.value !== 'card') {
        return payout?.requisites ?? '';
    }

    return formatCardDigitsGroups(payout.requisites);
};

const active_payout_copied_id = ref(null);

/** Сырой номер для буфера: только цифры, без пробелов и форматирования. */
const rawActivePayoutRequisitesForCopy = (payout) => {
    if (payout?.payout_method_type?.value !== 'card') {
        return String(payout?.requisites ?? '');
    }

    return String(payout?.requisites ?? '').replace(/\D/g, '');
};

const copyActivePayoutRawRequisites = async (payout) => {
    const text = rawActivePayoutRequisitesForCopy(payout);

    if (!text) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
        active_payout_copied_id.value = payout.id;

        if (active_payout_copied_clear_timer) {
            clearTimeout(active_payout_copied_clear_timer);
        }

        active_payout_copied_clear_timer = setTimeout(() => {
            active_payout_copied_id.value = null;
            active_payout_copied_clear_timer = null;
        }, 2000);
    } catch {
        // clipboard может быть недоступен (HTTP, права) — без уведомления
    }
};

const payoutEmptyState = computed(() => orderBookList.value.length === 0);
const activeEmptyState = computed(() => activePayoutsList.value.length === 0);

const receiptModal = ref({
    open: false,
    payout: null,
    files: [],
    error: null,
    processing: false,
});

const receiptInputRef = ref(null);
const showExportModal = ref(false);

const openReceiptModal = (payout) => {
    receiptModal.value = {
        open: true,
        payout,
        files: [],
        error: null,
        processing: false,
    };
    if (receiptInputRef.value) {
        receiptInputRef.value.value = '';
    }
};

const closeReceiptModal = () => {
    receiptModal.value.open = false;
    receiptModal.value.payout = null;
    receiptModal.value.files = [];
    receiptModal.value.error = null;
    receiptModal.value.processing = false;
    if (receiptInputRef.value) {
        receiptInputRef.value.value = '';
    }
};

const handleReceiptChange = (event) => {
    const files = Array.from(event.target.files ?? []).slice(0, 5);
    receiptModal.value.files = files;
    receiptModal.value.error = null;
};

const submitReceipt = () => {
    if (receiptModal.value.processing) {
        return;
    }

    if (!receiptModal.value.files.length) {
        receiptModal.value.error = 'Загрузите хотя бы один чек в формате JPG, PNG или PDF.';
        return;
    }

    if (receiptModal.value.files.length > 5) {
        receiptModal.value.error = 'Можно загрузить не более 5 чеков.';
        return;
    }

    receiptModal.value.processing = true;

    router.post(
        route('trader.payouts.mark-sent', receiptModal.value.payout.uuid),
        {
            receipts: receiptModal.value.files,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                stopAutoRefresh();
            },
            onError: (errors) => {
                receiptModal.value.error = errors?.receipts
                    ?? errors?.['receipts.0']
                    ?? errors?.receipt
                    ?? 'Не удалось загрузить чек(и), попробуйте ещё раз.';
            },
            onSuccess: () => {
                closeReceiptModal();
            },
            onFinish: () => {
                receiptModal.value.processing = false;
                startAutoRefresh();
            },
        },
    );
};

const payoutReceiptLinks = (payout) => {
    if (Array.isArray(payout?.receipt_urls)) {
        return payout.receipt_urls;
    }

    if (payout?.receipt_url) {
        return [{ id: null, filename: 'Чек 1', url: payout.receipt_url }];
    }

    return [];
};

const openExportModal = () => {
    showExportModal.value = true;
};

const closeExportModal = () => {
    showExportModal.value = false;
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Выплаты" />

        <MainTableSection
            title="Выплаты"
            :data="[1]"
            :paginate="false"
            :display-pagination="false"
        >
            <template #button>
                <button
                    v-if="viewStore.isTraderViewMode"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="openExportModal"
                >
                    Выгрузить
                </button>
            </template>
            <template #header>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="block inline-flex gap-2 w-full">
                            <div class="p-5 rounded-box shadow bg-base-100 w-full sm:w-auto border-none">
                                <div class="stat-title">Активных выплат</div>
                                <div class="stat-value text-primary text-3xl">{{ limits.currentActive }}</div>
                                <div class="stat-desc">из {{ limits.maxActive }}</div>
                            </div>
                            <div class="p-5 rounded-box shadow bg-base-100 w-full sm:w-auto border-0">
                                <div class="stat-title">Холд для вас</div>
                                <div class="stat-value text-secondary text-3xl">
                                    {{ trader.payout_hold_enabled ? trader.payout_hold_minutes : 0 }}
                                </div>
                                <div class="stat-desc">
                                    {{ trader.payout_hold_enabled ? 'минут ожидания' : 'Холд отключен' }}
                                </div>
                            </div>
                        </div>
                        <div v-if="trader_payouts_auto_refresh_allowed" class="inline-flex items-end gap-3">
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-semibold text-base-content">Автообновление</span>
                                <div class="flex items-center gap-2">
                                    <div v-show="refreshInterval > 0" class="flex justify-center items-center">
                                        <div class="relative w-6 h-6">
                                            <svg class="w-full h-full" viewBox="0 0 36 36">
                                                <path
                                                    class="text-base-300"
                                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="4"
                                                />
                                            </svg>
                                            <svg class="absolute top-0 left-0 w-full h-full" viewBox="0 0 36 36">
                                                <path
                                                    class="text-primary transition-all duration-200"
                                                    :style="{ strokeDashoffset: refreshProgressOffset }"
                                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="4"
                                                    stroke-dasharray="100, 100"
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="dropdown dropdown-end">
                                        <div tabindex="0" role="button" class="btn btn-outline btn-xs sm:btn-sm">
                                            {{ currentRefreshOptionLabel }}
                                            <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                                            </svg>
                                        </div>
                                        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-44">
                                            <li v-for="interval in refreshOptions" :key="interval">
                                                <button
                                                    type="button"
                                                    class="justify-between"
                                                    :class="{'active': interval === refreshInterval}"
                                                    @click="selectRefreshInterval(interval)"
                                                >
                                                    <span>{{ refreshOptionLabel(interval) }}</span>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold">Ваши активные выплаты</h2>
                                <span v-if="activeEmptyState" class="text-sm text-base-content/60">Нет активных выплат</span>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="payout in activePayoutsList"
                                    :key="payout.id"
                                    class="card bg-base-100 shadow-sm xl:shadow"
                                >
                                    <div class="card-body space-y-2 p-4 pt-2 pb-3 xl:space-y-4 xl:p-6">
                                        <div class="xl:hidden flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                            <div class="min-w-0 flex-1 text-[11px]">
                                                <div class="inline-flex items-center text-base-content/70 min-w-0">
                                                    <span>UUID:</span>
                                                    <DisplayUUID :uuid="payout.uuid" />
                                                </div>
                                            </div>
                                            <div class="shrink-0 text-right leading-tight">
                                                <div class="text-[11px] text-base-content/50 uppercase">Взяли в работу</div>
                                                <DateTime
                                                    :data="payout.timings.taken_at"
                                                    simple
                                                    class="justify-end text-[11px]"
                                                />
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-2 min-w-0 xl:flex-row xl:flex-wrap xl:items-center xl:justify-between xl:gap-4">
                                            <div class="flex min-w-0 flex-1 flex-col gap-2 xl:flex-row xl:items-center xl:gap-7">
                                                <div class="flex w-full min-w-0 py-2 px-1 items-start justify-between gap-2 xl:w-auto xl:items-center xl:justify-start xl:gap-3">
                                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                                        <div v-if="hasCustomBank(payout)" class="text-base-content/70 shrink-0">
                                                            <BankManualIcon class="w-8 h-8 xl:w-10 xl:h-10" />
                                                        </div>
                                                        <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative shrink-0">
                                                            <img src="/images/sbp.svg" alt="" class="w-8 h-8 xl:w-10 xl:h-10">
                                                            <GatewayLogo
                                                                :img_path="payout.payment_gateway?.logo"
                                                                :name="payout.payment_gateway?.name"
                                                                class="absolute right-[-2px] bottom-[-2px] w-4 h-4 xl:right-[-3px] xl:bottom-[-3px] xl:w-5 xl:h-5 bg-base-100 border border-base-300 rounded-full"
                                                            />
                                                        </div>
                                                        <div v-else class="shrink-0">
                                                            <GatewayLogo
                                                                :img_path="payout.payment_gateway?.logo"
                                                                :name="payout.payment_gateway?.name"
                                                                class="w-8 h-8 xl:w-10 xl:h-10"
                                                            />
                                                        </div>
                                                        <div class="min-w-0 flex-1 -mt-1">
                                                            <div class="flex items-center justify-start gap-0.5 min-w-0 xl:gap-1">
                                                                <div class="min-w-0 text-xs font-medium leading-snug break-words text-base-content xl:text-base xl:font-semibold xl:text-nowrap">
                                                                    {{ displayActivePayoutRequisites(payout) }}
                                                                </div>
                                                                <div
                                                                    v-if="payout.payout_method_type.value === 'card'"
                                                                    class="tooltip tooltip-top shrink-0"
                                                                    :class="{ 'tooltip-open': active_payout_copied_id === payout.id }"
                                                                    :data-tip="active_payout_copied_id === payout.id ? 'Скопировано!' : 'Скопировать'"
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-ghost btn-circle btn-xs h-5 w-5 min-h-0 p-0 text-base-content/70 hover:text-primary"
                                                                        aria-label="Копировать номер карты"
                                                                        @click="copyActivePayoutRawRequisites(payout)"
                                                                    >
                                                                        <svg
                                                                            xmlns="http://www.w3.org/2000/svg"
                                                                            fill="none"
                                                                            viewBox="0 0 24 24"
                                                                            stroke-width="1.5"
                                                                            stroke="currentColor"
                                                                            class="h-3.5 w-3.5"
                                                                            aria-hidden="true"
                                                                        >
                                                                            <path
                                                                                stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z"
                                                                            />
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="text-[11px] text-base-content/60 leading-snug xl:text-xs">
                                                                {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="shrink-0 self-center xl:hidden">
                                                        <div class="badge badge-primary badge-outline badge-sm font-normal">
                                                            {{ payout.status_label }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="xl:hidden grid grid-cols-2 gap-x-3 gap-y-1 bg-base-300/80 py-2.5 px-3 rounded-box text-[11px] leading-tight">
                                                    <div class="space-y-0.5">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                                        <div class="text-xs font-medium">
                                                            {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                        </div>
                                                    </div>
                                                    <div class="space-y-0.5">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Получатель</div>
                                                        <div class="text-xs font-medium">{{ payout.initials }}</div>
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="payout.status !== 'taken'"
                                                    class="text-[11px] text-base-content/70 xl:hidden"
                                                >
                                                    Холд: {{ formatHoldCountdown(payout.timings.hold_until) ?? 'ожидаем завершения' }}
                                                </div>
                                                <div class="hidden items-center gap-7 xl:flex">
                                                    <div class="space-y-0">
                                                        <div class="text-xs uppercase text-base-content/60">Сумма</div>
                                                        <div class="font-semibold">
                                                            {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                        </div>
                                                    </div>
                                                    <div class="space-y-0">
                                                        <div class="text-xs uppercase text-base-content/60">Получатель</div>
                                                        <div class="font-semibold">{{ payout.initials }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="hidden flex-col gap-3 sm:flex-row sm:items-center xl:flex xl:w-auto">
                                                <div class="badge badge-primary badge-outline badge-md font-normal">
                                                    {{ payout.status_label }}
                                                </div>
                                                <button
                                                    v-if="payout.status === 'taken'"
                                                    type="button"
                                                    class="btn btn-sm btn-success"
                                                    @click="openReceiptModal(payout)"
                                                >
                                                    Отправил средства
                                                </button>
                                                <div
                                                    v-else
                                                    class="text-sm text-base-content/70"
                                                >
                                                    Холд: {{ formatHoldCountdown(payout.timings.hold_until) ?? 'ожидаем завершения' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="payoutReceiptLinks(payout).length" class="pt-0.5 xl:pt-1">
                                            <div class="text-[10px] text-base-content/50 uppercase mb-1.5 xl:text-xs xl:text-base-content/60 xl:normal-case">Чеки выплаты</div>
                                            <div class="flex flex-wrap gap-1 xl:gap-2">
                                                <a
                                                    v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                                    :key="`active-receipt-${payout.id}-${receipt.id ?? index}`"
                                                    :href="receipt.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-xs btn-outline min-h-0 h-7 px-2 leading-none xl:min-h-10 xl:h-10 xl:px-3"
                                                >
                                                    Чек {{ index + 1 }}
                                                </a>
                                            </div>
                                        </div>
                                        <div class="hidden xl:grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 bg-base-300/80 py-3 px-4 rounded-box text-sm">
                                            <div class="space-y-1">
                                                <div class="text-base-content/60 uppercase text-xs">Сумма в USDT</div>
                                                <div class="font-semibold">
                                                    {{ payout.usdt_body.value }} {{ payout.usdt_body.currency }}
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-base-content/60 uppercase text-xs">Будет зачислено</div>
                                                <div class="font-semibold">
                                                    {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-base-content/60 uppercase text-xs">Курс</div>
                                                <div class="font-semibold">
                                                    {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-base-content/60 uppercase text-xs">Ваша прибыль</div>
                                                <div class="font-semibold">{{ payout.commissions.trader_fee }} USDT ({{ payout.commissions.trader_rate }}%)</div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-base-content/60 uppercase text-xs">Взяли в работу</div>
                                                <DateTime :data="payout.timings.taken_at" simple class="justify-start font-semibold" />
                                            </div>
                                        </div>
                                        <div class="xl:hidden grid grid-cols-2 sm:grid-cols-4 gap-x-2 gap-y-2.5 bg-base-300/80 py-2.5 px-3 rounded-box text-[11px] leading-tight">
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Сумма в USDT</div>
                                                <div class="font-medium text-xs text-base-content mt-0.5">
                                                    {{ payout.usdt_body?.value }} {{ payout.usdt_body?.currency }}
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Будет зачислено</div>
                                                <div class="font-medium text-xs text-base-content mt-0.5">
                                                    {{ payout.trader_credit?.value }} {{ payout.trader_credit?.currency }}
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                <div class="font-medium text-xs text-base-content mt-0.5 text-nowrap">
                                                    {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Ваша прибыль</div>
                                                <div class="font-medium text-xs text-base-content mt-0.5">
                                                    {{ payout.commissions.trader_fee }} USDT
                                                    <span class="text-base-content/50 font-normal">({{ payout.commissions.trader_rate }}%)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="payout.status === 'taken'"
                                            class="xl:hidden sm:flex sm:justify-end"
                                        >
                                            <button
                                                type="button"
                                                class="btn btn-success btn-xs w-full sm:w-auto"
                                                @click="openReceiptModal(payout)"
                                            >
                                                Отправил средства
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex w-full items-center justify-between gap-3">
                                <ul class="flex w-full gap-2 text-sm font-medium text-center sm:w-auto sm:flex-wrap sm:gap-0">
                                    <li class="min-w-0 flex-1 sm:flex-none sm:me-2">
                                        <a
                                            href="#"
                                            class="btn btn-sm w-full sm:w-auto"
                                            :class="listTab === 'stack' ? 'btn-primary' : 'btn-outline'"
                                            @click.prevent="openListTab('stack')"
                                        >Доступные выплаты</a>
                                    </li>
                                    <li class="min-w-0 flex-1 sm:flex-none sm:me-2">
                                        <a
                                            href="#"
                                            class="btn btn-sm w-full sm:w-auto"
                                            :class="listTab === 'history' ? 'btn-primary' : 'btn-outline'"
                                            @click.prevent="openListTab('history')"
                                        >История выплат</a>
                                    </li>
                                </ul>
                            </div>

                            <div v-show="listTab === 'stack'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h2 class="text-xl font-semibold">Доступные выплаты</h2>
                                <div class="flex items-center gap-3">
                                    <span v-if="payoutEmptyState" class="text-sm text-base-content/60">Пока нет заявок</span>
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm btn-outline"
                                        :disabled="isRefreshing"
                                        @click="refreshNow"
                                    >
                                        <span class="flex items-center gap-2">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="h-4 w-4 shrink-0"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                                />
                                            </svg>
                                            <span>Обновить</span>
                                            <span v-if="isRefreshing" class="loading loading-spinner loading-xs"></span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <!-- Desktop / tablet (table) -->
                                <div class="hidden xl:block rounded-table relative">
                                    <div class="overflow-x-auto card bg-base-100 shadow">
                                        <table class="table table-sm">
                                            <thead class="text-xs uppercase bg-base-300">
                                            <tr>
                                                <th scope="col">
                                                    Реквизит
                                                </th>
                                                <th scope="col">
                                                    К отправке
                                                </th>
                                                <th scope="col">
                                                    К получению
                                                </th>
                                                <th scope="col">
                                                    Курс
                                                </th>
                                                <th scope="col">
                                                    Доход
                                                </th>
                                                <th scope="col">
                                                    Истекает
                                                </th>
                                                <th scope="col" class="text-right">
                                                    <span class="sr-only">Действия</span>
                                                </th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <template v-if="payoutEmptyState">
                                                <tr>
                                                    <td colspan="7" class="text-center text-sm text-base-content/60 py-6">Пока нет заявок</td>
                                                </tr>
                                            </template>
                                            <template v-else>
                                            <tr
                                                v-for="payout in orderBookList"
                                                :key="payout.id"
                                                class="bg-base-100 border-b last:border-none border-base-200"
                                            >
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div v-if="hasCustomBank(payout)" class="text-base-content/70">
                                                            <BankManualIcon class="w-10 h-10" />
                                                        </div>
                                                        <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative">
                                                            <img src="/images/sbp.svg" class="w-10 h-10">
                                                            <GatewayLogo
                                                                :img_path="payout.payment_gateway?.logo"
                                                                :name="payout.payment_gateway?.name"
                                                                class="absolute right-[-3px] bottom-[-3px] w-5 h-5 bg-base-100 border border-base-300 rounded-full"
                                                            />
                                                        </div>
                                                        <div v-else>
                                                            <GatewayLogo
                                                                :img_path="payout.payment_gateway?.logo"
                                                                :name="payout.payment_gateway?.name"
                                                                class="w-10 h-10"
                                                            />
                                                        </div>
                                                        <div>
                                                            <div class="text-nowrap text-base-content">
                                                                {{ payout.requisites }}
                                                            </div>
                                                            <div class="text-xs text-base-content/60">
                                                                {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-nowrap">
                                                        {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div >
                                                        {{ payout.commissions.trader_fee }} USDT
                                                    </div>
                                                </td>
                                                <td>
                                                    <DateTime :data="payout.timings.expires_at" simple class="justify-start" />
                                                </td>
                                                <td class="text-right">
                                                    <button
                                                        class="btn btn-primary btn-sm"
                                                        @click="takePayout(payout)"
                                                        :disabled="!canTakeMore || isRefreshing"
                                                    >
                                                        Взять
                                                    </button>
                                                </td>
                                            </tr>
                                            </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Mobile (cards list) — компактно как Order / PaymentDetail -->
                                <div class="xl:hidden space-y-3">
                                    <div class="space-y-2">
                                        <div
                                            v-for="payout in orderBookList"
                                            :key="payout.id"
                                            class="card bg-base-100 shadow-sm"
                                        >
                                            <div class="card-body p-4 pt-2 pb-3">
                                                <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                                    <div class="min-w-0 flex-1 text-[11px]">
                                                        <div class="inline-flex items-center text-base-content/70 min-w-0">
                                                            <span>UUID:</span>
                                                            <DisplayUUID :uuid="payout.uuid"/>
                                                        </div>
                                                    </div>
                                                    <div class="shrink-0 text-right leading-tight">
                                                        <div class="text-[11px] text-base-content/50 uppercase">Истекает</div>
                                                        <DateTime
                                                            :data="payout.timings.expires_at"
                                                            simple
                                                            class="justify-end text-[11px]"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 min-w-0 pt-2">
                                                    <div v-if="hasCustomBank(payout)" class="text-base-content/70 shrink-0">
                                                        <BankManualIcon class="w-8 h-8 sm:w-10 sm:h-10" />
                                                    </div>
                                                    <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative shrink-0">
                                                        <img src="/images/sbp.svg" alt="" class="w-8 h-8 sm:w-10 sm:h-10">
                                                        <GatewayLogo
                                                            :img_path="payout.payment_gateway?.logo"
                                                            :name="payout.payment_gateway?.name"
                                                            class="absolute right-[-2px] bottom-[-2px] w-4 h-4 sm:w-5 sm:h-5 bg-base-100 border border-base-300 rounded-full"
                                                        />
                                                    </div>
                                                    <div v-else class="shrink-0">
                                                        <GatewayLogo
                                                            :img_path="payout.payment_gateway?.logo"
                                                            :name="payout.payment_gateway?.name"
                                                            class="w-8 h-8 sm:w-10 sm:h-10"
                                                        />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="text-xs font-medium text-base-content leading-snug break-words">
                                                            {{ payout.requisites }}
                                                        </div>
                                                        <div class="text-[11px] text-base-content/60 leading-snug">
                                                            {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                        </div>
                                                    </div>

                                                    <button
                                                            type="button"
                                                            class="btn btn-primary btn-xs"
                                                            @click="takePayout(payout)"
                                                            :disabled="!canTakeMore || isRefreshing"
                                                        >
                                                        Взять
                                                    </button>
                                                </div>

                                                <div class="border-b border-base-content/10 my-2 mb-1"></div>

                                                <div class="hidden sm:flex items-end justify-between gap-2">
                                                    <div
                                                        class="grid gap-y-1.5 gap-x-5 sm:gap-x-6 text-[11px] leading-tight flex-1 min-w-0 grid-cols-[minmax(0,1.28fr)_minmax(0,0.91fr)_minmax(0,0.91fr)_minmax(0,0.91fr)]"
                                                    >
                                                        <div class="min-w-0">
                                                            <div class="text-[10px] text-base-content/50 uppercase">Отправляете</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.amount.fiat }} {{ payout.amount.currency }} <span class="text-base-content/50 font-normal">({{ payout.usdt_body?.value ?? '—' }} {{ payout.usdt_body?.currency ?? '' }})</span>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div class="text-[10px] text-base-content/50 uppercase">Получаете</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div class="text-[10px] text-base-content/50 uppercase">Доход</div>
                                                            <div class="font-medium text-xs text-base-content">
                                                                {{ payout.commissions.trader_fee }} USDT
                                                                <span class="text-base-content/50 font-normal">({{ payout.commissions.trader_rate }}%)</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="sm:hidden space-y-2">
                                                    <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                                        <div>
                                                            <div class="text-[10px] text-base-content/50 uppercase">Отправляете</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.amount.fiat }} {{ payout.amount.currency }} <span class="text-base-content/50 font-normal">({{ payout.usdt_body?.value ?? '—' }} {{ payout.usdt_body?.currency ?? '' }})</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] text-base-content/50 uppercase">Получаете</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                            <div class="font-medium text-xs text-base-content text-nowrap">
                                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] text-base-content/50 uppercase">Доход</div>
                                                            <div class="font-medium text-xs text-base-content">
                                                                {{ payout.commissions.trader_fee }} USDT
                                                                <span class="text-base-content/50 font-normal">({{ payout.commissions.trader_rate }}%)</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="payoutEmptyState" class="py-6 text-center text-sm text-base-content/60">
                                        Пока нет заявок
                                    </div>
                                </div>
                            </div>
                            <div v-if="showStackPagination" class="flex justify-start mt-2">
                                <Pagination
                                    :model-value="orderBookMeta.current_page"
                                    :total-items="orderBookMeta.total"
                                    :per-page="PAYOUT_LIST_PER_PAGE"
                                    previous-label="Назад"
                                    next-label="Вперед"
                                    :show-icons="false"
                                    @page-changed="onStackPagination"
                                />
                            </div>
                            </div>

                            <div v-show="listTab === 'history'" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold">История выплат</h2>
                            </div>
                            <div class="rounded-table relative">
                        <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th>UUID</th>
                                    <th>Реквизит</th>
                                    <th>Отправленно</th>
                                    <th>Получено</th>
                                    <th>Доход</th>
                                    <th>Курс</th>
                                    <th>Статус</th>
                                    <th>Завершено</th>
                                </tr>
                                </thead>
                                <tbody>
                                <template v-if="historyList.length === 0">
                                    <tr>
                                        <td colspan="8" class="text-center text-sm text-base-content/60 py-6">История пока пуста.</td>
                                    </tr>
                                </template>
                                <template v-else>
                                <tr v-for="payout in historyList" :key="payout.id">
                                    <td class="font-mono text-xs">
                                        <DisplayUUID :uuid="payout.uuid"/>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div v-if="hasCustomBank(payout)" class="text-base-content/70">
                                                <BankManualIcon class="w-8 h-8" />
                                            </div>
                                            <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative">
                                                <img src="/images/sbp.svg" class="w-8 h-8">
                                                <GatewayLogo
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="absolute right-[-3px] bottom-[-3px] w-5 h-5 bg-base-100 border border-base-300 rounded-full"
                                                />
                                            </div>
                                            <div v-else>
                                                <GatewayLogo
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="w-10 h-10"
                                                />
                                            </div>
                                            <div>
                                                <div class="text-nowrap text-base-content">
                                                    {{ payout.requisites }}
                                                </div>
                                                <div class="text-xs text-base-content/60">
                                                    {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ payout.commissions.trader_fee }} USDT
                                    </td>
                                    <td>
                                        <div>
                                            {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge badge-outline badge-sm">{{ payout.status_label }}</div>
                                        <div v-if="payoutReceiptLinks(payout).length" class="mt-2 flex flex-wrap gap-1">
                                            <a
                                                v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                                :key="`history-receipt-${payout.id}-${receipt.id ?? index}`"
                                                :href="receipt.url"
                                                target="_blank"
                                                rel="noopener"
                                                class="link link-primary text-xs"
                                            >
                                                Чек {{ index + 1 }}
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <DateTime :data="payout.timings.completed_at" simple class="justify-start" />
                                    </td>
                                </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="xl:hidden space-y-3">
                            <div class="space-y-2">
                                <div
                                    v-for="payout in historyList"
                                    :key="payout.id"
                                    class="card bg-base-100 shadow-sm"
                                >
                                    <div class="card-body p-4 pt-2 pb-3">
                                        <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                            <div class="min-w-0 flex-1 text-[11px]">
                                                <div class="inline-flex items-center text-base-content/70 min-w-0">
                                                    <span>UUID:</span>
                                                    <DisplayUUID :uuid="payout.uuid" />
                                                </div>
                                            </div>
                                            <div class="shrink-0 text-right leading-tight">
                                                <div class="text-[11px] text-base-content/50 uppercase">Завершено</div>
                                                <DateTime
                                                    :data="payout.timings.completed_at"
                                                    simple
                                                    class="justify-end text-[11px]"
                                                />
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 min-w-0 pt-2">
                                            <div v-if="hasCustomBank(payout)" class="text-base-content/70 shrink-0">
                                                <BankManualIcon class="w-8 h-8 sm:w-10 sm:h-10" />
                                            </div>
                                            <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative shrink-0">
                                                <img src="/images/sbp.svg" alt="" class="w-8 h-8 sm:w-10 sm:h-10">
                                                <GatewayLogo
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="absolute right-[-2px] bottom-[-2px] w-4 h-4 sm:w-5 sm:h-5 bg-base-100 border border-base-300 rounded-full"
                                                />
                                            </div>
                                            <div v-else class="shrink-0">
                                                <GatewayLogo
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="w-8 h-8 sm:w-10 sm:h-10"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-medium text-base-content leading-snug break-words">
                                                    {{ payout.requisites }}
                                                </div>
                                                <div class="text-[11px] text-base-content/60 leading-snug">
                                                    {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                </div>
                                            </div>
                                            <div class="shrink-0 self-center">
                                                <div class="badge badge-primary badge-outline badge-sm font-normal">{{ payout.status_label }}</div>
                                            </div>
                                        </div>

                                        <div class="border-b border-base-content/10 my-2 mb-1"></div>

                                        <div class="hidden sm:flex flex-col gap-2">
                                            <div class="flex items-end justify-between gap-2">
                                                <div
                                                    class="grid gap-y-1.5 gap-x-5 sm:gap-x-6 text-[11px] leading-tight flex-1 min-w-0 grid-cols-[minmax(0,1.28fr)_minmax(0,0.91fr)_minmax(0,0.91fr)_minmax(0,0.91fr)]"
                                                >
                                                    <div class="min-w-0">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Отправленно</div>
                                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                                            {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                            <span class="text-base-content/50 font-normal">({{ payout.usdt_body?.value ?? '—' }} {{ payout.usdt_body?.currency ?? '' }})</span>
                                                        </div>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Получено</div>
                                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                                            {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                        </div>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                                            {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                        </div>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="text-[10px] text-base-content/50 uppercase">Доход</div>
                                                        <div class="font-medium text-xs text-base-content">
                                                            {{ payout.commissions.trader_fee }} USDT
                                                            <span class="text-base-content/50 font-normal">({{ payout.commissions.trader_rate }}%)</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-if="payoutReceiptLinks(payout).length"
                                                class="flex items-center justify-start gap-3 bg-base-200/40 rounded-lg p-1.5 px-2.5"
                                            >
                                                <div class="text-[10px] text-base-content/50 uppercase">
                                                    Чеки:
                                                </div>
                                                <a
                                                    v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                                    :key="`mobile-history-receipt-xs-${payout.id}-${receipt.id ?? index}`"
                                                    :href="receipt.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-xs btn-secondary btn-outline min-h-0 h-5 px-2 leading-none"
                                                >
                                                    Чек {{ index + 1 }}
                                                </a>
                                            </div>
                                        </div>

                                        <div class="sm:hidden space-y-2">
                                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                                <div>
                                                    <div class="text-[10px] text-base-content/50 uppercase">Отправленно</div>
                                                    <div class="font-medium text-xs text-base-content text-nowrap">
                                                        {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                        <span class="text-base-content/50 font-normal">({{ payout.usdt_body?.value ?? '—' }} {{ payout.usdt_body?.currency ?? '' }})</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-base-content/50 uppercase">Получено</div>
                                                    <div class="font-medium text-xs text-base-content text-nowrap">
                                                        {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                    <div class="font-medium text-xs text-base-content text-nowrap">
                                                        {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-base-content/50 uppercase">Доход</div>
                                                    <div class="font-medium text-xs text-base-content">
                                                        {{ payout.commissions.trader_fee }} USDT
                                                        <span class="text-base-content/50 font-normal">({{ payout.commissions.trader_rate }}%)</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-if="payoutReceiptLinks(payout).length"
                                                class="flex items-center justify-start gap-3 bg-base-200/40 rounded-lg p-1.5 px-2.5"
                                            >
                                                <div class="text-[10px] text-base-content/50 uppercase">
                                                    Чеки:
                                                </div>
                                                <a
                                                    v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                                    :key="`mobile-history-receipt-xs-${payout.id}-${receipt.id ?? index}`"
                                                    :href="receipt.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-xs btn-secondary btn-outline min-h-0 h-5 px-2 leading-none"
                                                >
                                                    Чек {{ index + 1 }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="historyList.length === 0" class="py-6 text-center text-sm text-base-content/60">
                                История пока пуста.
                            </div>
                        </div>
                            </div>
                            <div v-if="showHistoryPagination" class="flex justify-start mt-2">
                                <Pagination
                                    :model-value="historyMeta.current_page"
                                    :total-items="historyMeta.total"
                                    :per-page="PAYOUT_LIST_PER_PAGE"
                                    previous-label="Назад"
                                    next-label="Вперед"
                                    :show-icons="false"
                                    @page-changed="onHistoryPagination"
                                />
                            </div>
                            </div>

                        </div>
                    </div>
                </div>
            </template>
            <template #body>
                <div class="hidden" aria-hidden="true" />
            </template>
        </MainTableSection>
        <Modal :show="receiptModal.open" max-width="md" @close="closeReceiptModal">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-base-content">
                    Подтверждение отправки средств
                </h3>
                <p class="text-sm text-base-content/70">
                    Загрузите до 5 чеков перевода (JPG, PNG или PDF). Они будут доступны администраторам, мерчанту и вам.
                </p>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-sm font-semibold">Чеки выплаты</span>
                    </label>
                    <input
                        type="file"
                        class="file-input file-input-bordered w-full"
                        accept=".jpg,.jpeg,.png,.pdf"
                        multiple
                        @change="handleReceiptChange"
                        ref="receiptInputRef"
                    />
                    <p class="text-xs text-base-content/60 mt-2">До 5 файлов, каждый до 10 МБ.</p>
                    <ul v-if="receiptModal.files.length" class="mt-2 text-xs text-base-content/70 space-y-1">
                        <li v-for="(file, index) in receiptModal.files" :key="`${file.name}-${index}`">
                            {{ index + 1 }}. {{ file.name }}
                        </li>
                    </ul>
                    <div v-if="receiptModal.error" class="text-error text-sm mt-2">
                        {{ receiptModal.error }}
                    </div>
                </div>
                <div class="modal-action">
                    <button class="btn btn-sm btn-ghost" type="button" @click="closeReceiptModal">
                        Отмена
                    </button>
                    <button
                        class="btn btn-sm btn-primary"
                        type="button"
                        :disabled="receiptModal.processing"
                        @click="submitReceipt"
                    >
                        <span v-if="receiptModal.processing" class="loading loading-spinner loading-xs mr-2" />
                        <span>Отправить</span>
                    </button>
                </div>
            </div>
        </Modal>
        <TraderExportModal
            :show="showExportModal"
            route-name="trader.export.payouts"
            entity-label="выплаты"
            @close="closeExportModal"
        />
    </div>
</template>

