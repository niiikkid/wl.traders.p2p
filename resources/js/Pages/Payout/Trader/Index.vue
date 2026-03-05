<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import BankManualIcon from '@/Components/BankManualIcon.vue';
import DateTime from '@/Components/DateTime.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import Modal from '@/Components/Modals/Modal.vue';
import { formatDistanceStrict } from 'date-fns';
import DisplayUUID from "../../../Components/DisplayUUID.vue";

const props = defineProps({
    orderBook: {
        type: Array,
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
});

const page = usePage();

const trader = computed(() => page.props.auth?.user ?? {});
const orderBook = computed(() => props.orderBook ?? []);
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

const refreshInterval = ref(props.refresh.interval ?? 5);
const refreshStorageKey = 'trader-payouts-refresh-interval';
const refreshOptions = computed(() => props.refresh?.options ?? []);
const refreshProgress = ref(0);
let refreshProgressAnimationId = null;
const autoRefreshTimer = ref(null);
const isRefreshing = ref(false);
const historyPage = ref(history.value?.meta?.current_page ?? 1);

watch(
    () => history.value?.meta?.current_page,
    (value) => {
        historyPage.value = value ?? 1;
    },
);

const canTakeMore = computed(() => (props.limits?.currentActive ?? 0) < (props.limits?.maxActive ?? 1));

const refreshOptionLabel = (value) => (value === 0 ? 'Не обновлять' : `Каждые ${value}с`);
const currentRefreshOptionLabel = computed(() => refreshOptionLabel(refreshInterval.value));

const persistRefreshInterval = (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(refreshStorageKey, String(value));
};

const selectRefreshInterval = (value) => {
    if (!refreshOptions.value.includes(value) || refreshInterval.value === value) {
        return;
    }

    refreshInterval.value = value;
};

const syncRefreshIntervalFromStorage = () => {
    if (typeof window === 'undefined') {
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

const reloadData = (targetHistoryPage = historyPage.value, replace = true) => {
    isRefreshing.value = true;
    router.visit(route('trader.payouts.index'), {
        method: 'get',
        data: {
            refresh_interval: refreshInterval.value,
            history_page: targetHistoryPage,
        },
        preserveScroll: true,
        preserveState: true,
        replace,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const refreshNow = () => {
    if (isRefreshing.value) {
        return;
    }

    startAutoRefresh();
    reloadData(historyPage.value, false);
};

const startAutoRefresh = () => {
    stopAutoRefresh();

    if (refreshInterval.value > 0) {
        animateRefreshProgress(refreshInterval.value * 1000);
        autoRefreshTimer.value = setInterval(() => {
            animateRefreshProgress(refreshInterval.value * 1000);
            reloadData(historyPage.value, false);
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
    if (!options.includes(refreshInterval.value)) {
        refreshInterval.value = options[0] ?? 0;
    }
});

watch(refreshInterval, (value) => {
    persistRefreshInterval(value);
    startAutoRefresh();

    if (value > 0) {
        reloadData(historyPage.value, false);
    }
});

onMounted(() => {
    syncRefreshIntervalFromStorage();
    startAutoRefresh();
});

onBeforeUnmount(() => {
    stopAutoRefresh();
    stopRefreshProgressAnimation();
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

const changeHistoryPage = (pageNumber) => {
    historyPage.value = pageNumber;
    reloadData(pageNumber, true);
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

const payoutEmptyState = computed(() => orderBookList.value.length === 0);
const activeEmptyState = computed(() => activePayoutsList.value.length === 0);

const receiptModal = ref({
    open: false,
    payout: null,
    file: null,
    error: null,
    processing: false,
});

const receiptInputRef = ref(null);

const openReceiptModal = (payout) => {
    receiptModal.value = {
        open: true,
        payout,
        file: null,
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
    receiptModal.value.file = null;
    receiptModal.value.error = null;
    receiptModal.value.processing = false;
    if (receiptInputRef.value) {
        receiptInputRef.value.value = '';
    }
};

const handleReceiptChange = (event) => {
    const [file] = event.target.files ?? [];
    receiptModal.value.file = file ?? null;
    receiptModal.value.error = null;
};

const submitReceipt = () => {
    if (receiptModal.value.processing) {
        return;
    }

    if (! receiptModal.value.file) {
        receiptModal.value.error = 'Загрузите чек в формате JPG, PNG или PDF.';
        return;
    }

    receiptModal.value.processing = true;

    router.post(
        route('trader.payouts.mark-sent', receiptModal.value.payout.uuid),
        {
            receipt: receiptModal.value.file,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                stopAutoRefresh();
            },
            onError: (errors) => {
                receiptModal.value.error = errors?.receipt ?? 'Не удалось загрузить чек, попробуйте ещё раз.';
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

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Выплаты" />

        <MainTableSection
            title="Выплаты"
            :data="history"
        >
            <template #header>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="block sm:inline-flex sm:gap-4 space-y-4 sm:space-y-0">
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
                        <div class="inline-flex items-end gap-3">
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
                            <button
                                class="btn btn-sm btn-outline"
                                :disabled="isRefreshing"
                                @click="refreshNow"
                            >
                            <span class="flex items-center gap-2">
                                <span>Обновить</span>
                                <span v-if="isRefreshing" class="loading loading-spinner loading-xs"></span>
                            </span>
                            </button>
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
                                    class="card bg-base-100 shadow"
                                >
                                    <div class="card-body space-y-4">
                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                            <div class="flex flex-wrap items-center gap-4 sm:gap-7">
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
                                                        <div class="text-nowrap font-semibold">
                                                            {{ payout.requisites }}
                                                        </div>
                                                        <div class="text-xs text-base-content/60">
                                                            {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Сумма</div>
                                                    <div class="font-semibold">
                                                        {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Получатель</div>
                                                    <div class="font-semibold">{{ payout.initials }}</div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                                <div class="badge badge-outline badge-sm">
                                                    {{ payout.status_label }}
                                                </div>
                                                <button
                                                    class="btn btn-sm btn-success"
                                                    v-if="payout.status === 'taken'"
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
                                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 bg-base-300/80 py-3 px-4 rounded-box text-sm">
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
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold">Стакан доступных выплат</h2>
                                <span v-if="payoutEmptyState" class="text-sm text-base-content/60">Пока нет заявок</span>
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
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Mobile (cards list) -->
                                <div class="xl:hidden space-y-3">
                                    <div
                                        v-for="payout in orderBookList"
                                        :key="payout.id"
                                        class="card bg-base-100 shadow-sm border border-base-200"
                                    >
                                        <div class="card-body space-y-4">
                                            <div class="flex flex-wrap gap-3 items-center justify-between">
                                                <div class="inline-flex items-center gap-3">
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
                                                        <div class="font-semibold text-base-content">{{ payout.requisites }}</div>
                                                        <div class="text-sm text-base-content/60">
                                                            {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <button
                                                    class="btn btn-primary btn-sm"
                                                    @click="takePayout(payout)"
                                                    :disabled="!canTakeMore || isRefreshing"
                                                >
                                                    Взять
                                                </button>
                                            </div>
                                            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Отправляете</div>
                                                    <div class="font-semibold">
                                                        {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Получаете</div>
                                                    <div class="font-semibold">
                                                        {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Курс</div>
                                                    <div class="font-semibold">
                                                        {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Доход</div>
                                                    <div class="font-semibold">{{ payout.commissions.trader_fee }} USDT</div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-base-content/60 text-xs uppercase">Истекает</div>
                                                    <DateTime :data="payout.timings.expires_at" simple class="justify-start font-semibold" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #body>
                <div class="space-y-4">
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
                                    <th>Сумма</th>
                                    <th>Зачислено</th>
                                    <th>Доход</th>
                                    <th>Курс</th>
                                    <th>Статус</th>
                                    <th>Завершено</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="payout in history.data" :key="payout.id">
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
                                    </td>
                                    <td>
                                        <DateTime :data="payout.timings.completed_at" simple class="justify-start" />
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="xl:hidden space-y-3">
                            <div
                                v-for="payout in history.data"
                                :key="payout.id"
                                class="card bg-base-100 shadow-sm border border-base-200"
                            >
                                <div class="card-body space-y-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="inline-flex items-center gap-2 text-sm text-base-content/70">
                                        <span class="uppercase">UUID</span>
                                        <DisplayUUID :uuid="payout.uuid" />
                                    </div>
                                    <div class="text-right">
                                        <div class="text-base-content/60 text-xs uppercase">Завершено</div>
                                        <DateTime :data="payout.timings.completed_at" simple class="justify-end" />
                                    </div>
                                </div>
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
                                            <div class="font-semibold text-base-content text-sm sm:text-base">
                                                {{ payout.requisites }}
                                            </div>
                                            <div class="text-xs text-base-content/60">
                                                {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                        <div class="space-y-1">
                                            <div class="text-base-content/60 text-xs uppercase">Сумма</div>
                                            <div class="font-semibold">
                                                {{ payout.amount.fiat }} {{ payout.amount.currency }}
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-base-content/60 text-xs uppercase">Зачислено</div>
                                            <div class="font-semibold">
                                                {{ payout.trader_credit.value }} {{ payout.trader_credit.currency }}
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-base-content/60 text-xs uppercase">Курс</div>
                                            <div class="font-semibold">
                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-base-content/60 text-xs uppercase">Доход</div>
                                            <div class="font-semibold">{{ payout.commissions.trader_fee }} USDT</div>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-base-content/60 text-xs uppercase">Статус</div>
                                            <div class="badge badge-outline badge-sm">{{ payout.status_label }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="(history?.data?.length ?? 0) === 0" class="py-6 text-center text-sm text-base-content/60">
                                История пока пуста.
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex justify-end">
                    <Pagination
                        v-if="history?.meta"
                        :model-value="historyPage"
                        :total-pages="history.meta.last_page"
                        :per-page="history.meta.per_page"
                        :total-items="history.meta.total"
                        @page-changed="changeHistoryPage"
                    />
                </div>
            </template>
        </MainTableSection>
        <Modal :show="receiptModal.open" max-width="md" @close="closeReceiptModal">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-base-content">
                    Подтверждение отправки средств
                </h3>
                <p class="text-sm text-base-content/70">
                    Загрузите чек перевода (JPG, PNG или PDF). Он будет доступен администраторам и вам.
                </p>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-sm font-semibold">Чек выплаты</span>
                    </label>
                    <input
                        type="file"
                        class="file-input file-input-bordered w-full"
                        accept=".jpg,.jpeg,.png,.pdf"
                        @change="handleReceiptChange"
                        ref="receiptInputRef"
                    />
                    <p class="text-xs text-base-content/60 mt-2">Макс. размер — 10 МБ.</p>
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
    </div>
</template>

