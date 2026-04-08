<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import ManualControlLayout from '@/Layouts/ManualControlLayout.vue';

const INTRO_LOADING_MS = 3000;
const TAKE_WORK_COUNTDOWN_SECONDS = 10;

/** @type {import('vue').Ref<'loading' | 'assignment_modal' | 'workspace'>} */
const page_phase = ref('loading');
const newPayInModalDialog = ref(null);
const take_work_seconds_remaining = ref(TAKE_WORK_COUNTDOWN_SECONDS);
let loading_timeout_id = null;
let take_work_interval_id = null;

const confirmationCol1 = [
    { title: 'OTP code' },
    { title: 'In-app confirmation' },
    { title: 'Bank call' },
];

const confirmationCol2 = [
    { title: 'OTP code and PIN code' },
    { title: 'SMS with instructions' },
];

const CONFIRM_COUNTDOWN_SECONDS = 2 * 60;

const rejectReasons = [
    'Ошибка обработки',
    'Недостаточно средств',
    'Недействительные реквизиты карты',
    'Превышен лимит карты',
    'Подозрение на мошенничество',
    'Отменено плательщиком',
];

const PROCESSING_TOTAL_SECONDS = 15 * 60;
const processingRingRadius = 42;
const processingRingCircumference = 2 * Math.PI * processingRingRadius;

/** Сколько последних записей истории отображаем (макет лимита API). */
const HISTORY_DISPLAY_LIMIT = 5;

/**
 * Мок: всего записей в истории у оператора (как с бэкенда).
 * В списке только последние HISTORY_DISPLAY_LIMIT; остальные в UI не подгружаются.
 */
const mock_history_total_count = 47;

/**
 * Мок-очередь: только верстка / фронт, без бэкенда.
 * У каждого элемента свои таймеры processing и опционально confirm.
 *
 * @typedef {object} PayInQueueItem
 * @property {string} id
 * @property {boolean} is_history
 * @property {{ display: string, copy: string }} payin_id
 * @property {{ display: string }} incoming_bank
 * @property {{ display: string, copy: string }} amount
 * @property {{ display: string, copy: string }} card_number
 * @property {{ display: string, copy: string }} expiry_date
 * @property {{ display: string, copy: string }} cvv
 * @property {number} processing_elapsed_seconds
 * @property {string} pending_confirmation_title
 * @property {number} confirm_seconds_remaining
 * @property {{ display: string, copy: string } | null} confirmation_code Код от банка/шлюза (если уже пришёл)
 */

/** @type {import('vue').Ref<PayInQueueItem[]>} */
const pay_in_queue_active = ref([
    {
        id: 'q1',
        is_history: false,
        payin_id: { display: '220045893', copy: '220045893' },
        incoming_bank: { display: 'PrivatBank' },
        amount: { display: '1,000 UAH', copy: '1000' },
        card_number: { display: '4444 3333 2222 1111', copy: '4444333322221111' },
        expiry_date: { display: '07/30', copy: '07/30' },
        cvv: { display: '128', copy: '128' },
        processing_elapsed_seconds: 42,
        pending_confirmation_title: 'OTP code',
        confirm_seconds_remaining: 95,
        confirmation_code: { display: '482 919', copy: '482919' },
    },
    {
        id: 'q2',
        is_history: false,
        payin_id: { display: '220045901', copy: '220045901' },
        incoming_bank: { display: 'Monobank' },
        amount: { display: '2,500 UAH', copy: '2500' },
        card_number: { display: '5555 6666 7777 8888', copy: '5555666677778888' },
        expiry_date: { display: '12/28', copy: '12/28' },
        cvv: { display: '042', copy: '042' },
        processing_elapsed_seconds: 380,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
    {
        id: 'q3',
        is_history: false,
        payin_id: { display: '220045912', copy: '220045912' },
        incoming_bank: { display: 'PrivatBank' },
        amount: { display: '750 UAH', copy: '750' },
        card_number: { display: '4111 1111 1111 9999', copy: '4111111111119999' },
        expiry_date: { display: '03/31', copy: '03/31' },
        cvv: { display: '901', copy: '901' },
        processing_elapsed_seconds: 15,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
]);

/**
 * Последние HISTORY_DISPLAY_LIMIT записей истории (мок ответа API).
 * @type {import('vue').Ref<PayInQueueItem[]>}
 */
const pay_in_queue_history_visible = ref([
    {
        id: 'h1',
        is_history: true,
        payin_id: { display: '220044100', copy: '220044100' },
        incoming_bank: { display: 'PrivatBank' },
        amount: { display: '500 UAH', copy: '500' },
        card_number: { display: '4000 1234 5678 9010', copy: '4000123456789010' },
        expiry_date: { display: '01/29', copy: '01/29' },
        cvv: { display: '000', copy: '000' },
        processing_elapsed_seconds: 14 * 60 + 12,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
    {
        id: 'h2',
        is_history: true,
        payin_id: { display: '220044088', copy: '220044088' },
        incoming_bank: { display: 'Monobank' },
        amount: { display: '3,200 UAH', copy: '3200' },
        card_number: { display: '3782 822463 10005', copy: '378282246310005' },
        expiry_date: { display: '09/27', copy: '09/27' },
        cvv: { display: '321', copy: '321' },
        processing_elapsed_seconds: 8 * 60 + 55,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
    {
        id: 'h3',
        is_history: true,
        payin_id: { display: '220044072', copy: '220044072' },
        incoming_bank: { display: 'PrivatBank' },
        amount: { display: '1,850 UAH', copy: '1850' },
        card_number: { display: '5168 0012 3456 7890', copy: '5168001234567890' },
        expiry_date: { display: '05/31', copy: '05/31' },
        cvv: { display: '456', copy: '456' },
        processing_elapsed_seconds: 11 * 60 + 3,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
    {
        id: 'h4',
        is_history: true,
        payin_id: { display: '220044061', copy: '220044061' },
        incoming_bank: { display: 'Monobank' },
        amount: { display: '420 UAH', copy: '420' },
        card_number: { display: '4242 4242 4242 4242', copy: '4242424242424242' },
        expiry_date: { display: '11/28', copy: '11/28' },
        cvv: { display: '777', copy: '777' },
        processing_elapsed_seconds: 6 * 60 + 40,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
    {
        id: 'h5',
        is_history: true,
        payin_id: { display: '220044055', copy: '220044055' },
        incoming_bank: { display: 'PrivatBank' },
        amount: { display: '9,999 UAH', copy: '9999' },
        card_number: { display: '6011 0009 9013 9424', copy: '6011000990139424' },
        expiry_date: { display: '02/30', copy: '02/30' },
        cvv: { display: '112', copy: '112' },
        processing_elapsed_seconds: 12 * 60 + 18,
        pending_confirmation_title: '',
        confirm_seconds_remaining: 0,
        confirmation_code: null,
    },
]);

const pay_in_queue_all = computed(() => [...pay_in_queue_active.value, ...pay_in_queue_history_visible.value]);

const history_hidden_count = computed(() => Math.max(0, mock_history_total_count - HISTORY_DISPLAY_LIMIT));

/** @type {import('vue').Ref<string | null>} */
const selected_item_id = ref(null);

const selected_item = computed(() => {
    return pay_in_queue_all.value.find((item) => item.id === selected_item_id.value) ?? null;
});

const active_queue_items = computed(() => pay_in_queue_active.value);

const history_queue_items = computed(() => pay_in_queue_history_visible.value);

const is_selected_history = computed(() => selected_item.value?.is_history === true);

const assignment_preview_item = computed(() => active_queue_items.value[0] ?? null);

const copiedField = ref('');
const rejectModalDialog = ref(null);
const selectedRejectReason = ref('');
let timerInterval = null;
let copiedFieldTimeout = null;

const format_mm_ss = (total_seconds) => {
    const minutes = Math.floor(total_seconds / 60);
    const seconds = total_seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

const card_tail_label = (display) => {
    const digits = String(display).replace(/\D/g, '');

    if (digits.length >= 4) {
        return `•••• ${digits.slice(-4)}`;
    }

    return display;
};

const processingTime = computed(() => {
    const seconds = selected_item.value?.processing_elapsed_seconds ?? 0;

    return format_mm_ss(seconds);
});

const processingProgress = computed(() => {
    const seconds = selected_item.value?.processing_elapsed_seconds ?? 0;

    return Math.min(seconds / PROCESSING_TOTAL_SECONDS, 1);
});

const processingRingDashoffset = computed(() => {
    return processingRingCircumference * (1 - processingProgress.value);
});

const processing_progress_ratio_for_item = (item) => {
    if (!item) {
        return 0;
    }

    return Math.min(item.processing_elapsed_seconds / PROCESSING_TOTAL_SECONDS, 1);
};

const processing_ring_dashoffset_for_item = (item) => {
    return processingRingCircumference * (1 - processing_progress_ratio_for_item(item));
};

const confirmTimeDisplay = computed(() => {
    const remaining = selected_item.value?.confirm_seconds_remaining ?? 0;

    return format_mm_ss(remaining);
});

const canConfirm = computed(() => {
    const item = selected_item.value;

    return Boolean(item && !item.is_history && item.confirm_seconds_remaining > 0);
});

const schedule_loading_then_assignment_modal = () => {
    if (loading_timeout_id !== null) {
        window.clearTimeout(loading_timeout_id);
        loading_timeout_id = null;
    }

    page_phase.value = 'loading';
    loading_timeout_id = window.setTimeout(() => {
        loading_timeout_id = null;
        open_assignment_modal_flow();
    }, INTRO_LOADING_MS);
};

const start_workspace_timers = () => {
    if (timerInterval !== null) {
        return;
    }

    timerInterval = window.setInterval(() => {
        pay_in_queue_active.value.forEach((item) => {
            item.processing_elapsed_seconds += 1;

            if (item.pending_confirmation_title && item.confirm_seconds_remaining > 0) {
                item.confirm_seconds_remaining -= 1;
            }
        });
    }, 1000);
};

const clear_take_work_timer = () => {
    if (take_work_interval_id !== null) {
        window.clearInterval(take_work_interval_id);
        take_work_interval_id = null;
    }
};

const open_assignment_modal_flow = () => {
    page_phase.value = 'assignment_modal';
    take_work_seconds_remaining.value = TAKE_WORK_COUNTDOWN_SECONDS;
    clear_take_work_timer();
    take_work_interval_id = window.setInterval(() => {
        if (take_work_seconds_remaining.value > 0) {
            take_work_seconds_remaining.value -= 1;
        }
    }, 1000);

    nextTick(() => {
        newPayInModalDialog.value?.showModal();
    });
};

const on_new_pay_in_dialog_close = () => {
    if (page_phase.value !== 'assignment_modal') {
        return;
    }

    clear_take_work_timer();
    schedule_loading_then_assignment_modal();
};

const on_cancel_new_pay_in = () => {
    clear_take_work_timer();
    schedule_loading_then_assignment_modal();
    newPayInModalDialog.value?.close();
};

const on_take_to_work = () => {
    if (take_work_seconds_remaining.value <= 0) {
        return;
    }

    clear_take_work_timer();
    page_phase.value = 'workspace';
    newPayInModalDialog.value?.close();

    const first_active = pay_in_queue_active.value[0] ?? null;

    selected_item_id.value = first_active?.id ?? pay_in_queue_history_visible.value[0]?.id ?? null;
    start_workspace_timers();
};

const select_queue_item = (item_id) => {
    selected_item_id.value = item_id;
};

onMounted(() => {
    schedule_loading_then_assignment_modal();
});

const selectConfirmationType = (title) => {
    const item = selected_item.value;

    if (!item || item.is_history) {
        return;
    }

    item.pending_confirmation_title = title;
    item.confirm_seconds_remaining = CONFIRM_COUNTDOWN_SECONDS;
};

const confirmationButtonClass = (title) => {
    const base = 'btn h-auto min-h-8 w-full whitespace-normal px-3 py-1.5 text-center text-xs font-medium normal-case sm:min-h-9';
    const active = selected_item.value?.pending_confirmation_title === title;

    return active ? `${base} btn-primary` : `${base} btn-outline`;
};

const onConfirmClick = () => {
    if (!canConfirm.value) {
        return;
    }
};

const openRejectModal = () => {
    selectedRejectReason.value = '';
    rejectModalDialog.value?.showModal();
};

const closeRejectModal = () => {
    rejectModalDialog.value?.close();
    selectedRejectReason.value = '';
};

const pickRejectReason = (reason) => {
    selectedRejectReason.value = reason;
};

const confirmReject = () => {
    if (!selectedRejectReason.value) {
        return;
    }

    closeRejectModal();
};

const copyField = async (fieldKey) => {
    const item = selected_item.value;

    if (!item) {
        return;
    }

    const key_map = {
        payinId: 'payin_id',
        incomingBank: 'incoming_bank',
        amount: 'amount',
        cardNumber: 'card_number',
        expiryDate: 'expiry_date',
        cvv: 'cvv',
    };

    let value;

    if (fieldKey === 'confirmationCode') {
        value = item.confirmation_code?.copy;
    } else {
        const prop = key_map[fieldKey];
        value = prop ? item[prop]?.copy : undefined;
    }

    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copiedField.value = fieldKey;

        if (copiedFieldTimeout) {
            window.clearTimeout(copiedFieldTimeout);
        }

        copiedFieldTimeout = window.setTimeout(() => {
            copiedField.value = '';
        }, 1500);
    } catch (error) {
        // ignored
    }
};

onBeforeUnmount(() => {
    if (loading_timeout_id !== null) {
        window.clearTimeout(loading_timeout_id);
    }

    clear_take_work_timer();

    if (timerInterval !== null) {
        window.clearInterval(timerInterval);
    }

    if (copiedFieldTimeout) {
        window.clearTimeout(copiedFieldTimeout);
    }
});
</script>

<template>
    <ManualControlLayout>
        <Head title="Manual Control ACQ" />

        <div class="flex min-h-0 w-full flex-1 flex-col">
        <div
            v-if="page_phase === 'loading'"
            class="flex w-full flex-1 flex-col items-center justify-center gap-3 px-4 py-8 text-center"
        >
            <h2 class="text-base font-semibold text-base-content sm:text-lg">
                Loading Pay In
            </h2>
            <p class="max-w-sm text-sm text-base-content/60">
                We're paying from merchant
            </p>
            <span class="loading loading-spinner loading-md text-primary" aria-hidden="true" />
        </div>

        <div v-else-if="page_phase === 'workspace'" class="flex min-h-0 w-full flex-1 flex-col lg:flex-row lg:items-stretch">
            <!-- Левая колонка: мок-очередь текущего оператора (DaisyUI menu + badges) -->
            <aside
                class="flex max-h-[40vh] shrink-0 flex-col border-b border-base-300 bg-base-200/80 lg:max-h-none lg:w-80 lg:border-b-0 lg:border-r"
            >
                <div class="border-b border-base-300 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                        Мои подтверждения
                    </p>
                    <p class="mt-0.5 text-[11px] leading-snug text-base-content/45">
                        Макет: несколько активных и история (данные не с бэкенда).
                    </p>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto px-2 py-2" aria-label="Очередь Pay In">
                    <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-base-content/40">
                        Текущие
                    </p>
                    <ul class="menu menu-sm w-full rounded-box bg-base-100 p-0 shadow-sm">
                        <li v-for="item in active_queue_items" :key="item.id" class="w-full">
                            <button
                                type="button"
                                class="flex h-auto min-h-0 w-full flex-col items-stretch gap-1 rounded-lg py-2.5 text-left"
                                :class="selected_item_id === item.id ? 'menu-active' : ''"
                                @click="select_queue_item(item.id)"
                            >
                                <div class="flex w-full items-start justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold tabular-nums text-base-content">
                                        {{ card_tail_label(item.card_number.display) }}
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums text-base-content">
                                        {{ item.amount.display }}
                                    </span>
                                </div>
                                <div class="flex w-full flex-row items-end justify-between gap-2 text-[10px] text-base-content/55">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="tabular-nums">
                                            Processing {{ format_mm_ss(item.processing_elapsed_seconds) }}
                                        </span>
                                        <span
                                            v-if="item.pending_confirmation_title"
                                            class="inline-flex max-w-full items-center gap-1 truncate"
                                        >
                                            <span class="badge badge-primary badge-xs shrink-0 font-medium normal-case">
                                                {{ item.pending_confirmation_title }}
                                            </span>
                                            <span class="tabular-nums text-base-content/70">
                                                {{ format_mm_ss(item.confirm_seconds_remaining) }}
                                            </span>
                                        </span>
                                    </div>
                                    <div
                                        class="pointer-events-none flex size-3 shrink-0 translate-y-px"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="size-3 -rotate-90"
                                            viewBox="0 0 100 100"
                                        >
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-300"
                                                stroke-width="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-primary"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                :stroke-dasharray="processingRingCircumference"
                                                :stroke-dashoffset="processing_ring_dashoffset_for_item(item)"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>

                    <div class="divider my-3 text-[10px] font-semibold uppercase tracking-wider text-base-content/40 before:bg-base-300 after:bg-base-300">
                        История (последние {{ HISTORY_DISPLAY_LIMIT }})
                    </div>

                    <ul class="menu menu-sm w-full rounded-box bg-base-100/60 p-0 opacity-90 shadow-sm">
                        <li v-for="item in history_queue_items" :key="item.id" class="w-full">
                            <button
                                type="button"
                                class="flex h-auto min-h-0 w-full flex-col items-stretch gap-1 rounded-lg py-2.5 text-left"
                                :class="selected_item_id === item.id ? 'menu-active' : ''"
                                @click="select_queue_item(item.id)"
                            >
                                <div class="flex w-full items-start justify-between gap-2">
                                    <span class="font-mono text-xs font-medium tabular-nums text-base-content/70">
                                        {{ card_tail_label(item.card_number.display) }}
                                    </span>
                                    <span class="shrink-0 text-xs font-medium tabular-nums text-base-content/70">
                                        {{ item.amount.display }}
                                    </span>
                                </div>
                                <div class="flex w-full flex-row items-end justify-between gap-2 text-[10px] text-base-content/45">
                                    <div class="min-w-0 flex-1 tabular-nums">
                                        <span>{{ format_mm_ss(item.processing_elapsed_seconds) }}</span>
                                        <span class="ml-1.5">завершено</span>
                                    </div>
                                    <div
                                        class="pointer-events-none flex size-3 shrink-0 translate-y-px opacity-80"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="size-3 -rotate-90"
                                            viewBox="0 0 100 100"
                                        >
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-300/80"
                                                stroke-width="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-content/35"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                :stroke-dasharray="processingRingCircumference"
                                                :stroke-dashoffset="processing_ring_dashoffset_for_item(item)"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>

                    <div
                        v-if="history_hidden_count > 0"
                        class="mt-2 rounded-box border border-dashed border-base-300 bg-base-200/50 px-2.5 py-2 text-[10px] leading-snug text-base-content/50"
                        role="note"
                    >
                        Показаны последние {{ HISTORY_DISPLAY_LIMIT }} из {{ mock_history_total_count }}.
                        Ещё {{ history_hidden_count }} шт. старше в этом списке не подгружаются.
                    </div>
                </nav>
            </aside>

            <!-- Основная рабочая зона -->
            <div v-if="selected_item" class="mx-auto flex min-h-0 w-full min-w-0 max-w-2xl flex-1 flex-col gap-4 overflow-y-auto px-3 py-4 lg:px-6 lg:py-5">
                <header class="card border border-base-300 bg-base-100 shadow">
                    <div class="card-body gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="text-lg font-semibold text-base-content sm:text-xl">
                            Manual Control ACQ
                        </h1>

                        <div class="flex shrink-0 flex-row items-center gap-3 px-1 py-1 sm:gap-4">
                            <div class="relative flex size-[2.7rem] shrink-0 items-center justify-center">
                                <svg
                                    class="absolute inset-0 size-[2.7rem] -rotate-90"
                                    viewBox="0 0 100 100"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="processingRingRadius"
                                        fill="none"
                                        stroke="currentColor"
                                        class="text-base-300"
                                        stroke-width="8"
                                    />
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="processingRingRadius"
                                        fill="none"
                                        stroke="currentColor"
                                        class="text-primary"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="processingRingCircumference"
                                        :stroke-dashoffset="processingRingDashoffset"
                                    />
                                </svg>
                            </div>
                            <div class="flex min-w-0 flex-col items-start justify-center gap-0.5 text-left">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-base-content/55">
                                    Processing Time
                                </p>
                                <p class="text-base font-semibold tabular-nums text-base-content sm:text-lg">
                                    {{ processingTime }}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                <section
                    v-if="!is_selected_history && selected_item.confirmation_code"
                    class="card border-0 bg-warning text-warning-content shadow-md ring-1 ring-warning/35 ring-offset-1 ring-offset-base-100"
                >
                    <div class="card-body flex flex-row flex-wrap items-center justify-between gap-x-3 gap-y-2 px-3 py-2.5 sm:px-4 sm:py-3">
                        <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:gap-2.5">
                            <p class="shrink-0 text-[9px] font-bold uppercase tracking-[0.18em] text-warning-content/85 sm:text-[10px]">
                                Код подтверждения
                            </p>
                            <p
                                class="min-w-0 break-words text-base font-bold tabular-nums tracking-[0.08em] sm:text-lg"
                                :title="selected_item.confirmation_code.display"
                            >
                                {{ selected_item.confirmation_code.display }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-neutral btn-xs h-7 min-h-7 shrink-0 gap-1 border-0 bg-warning-content/15 px-2.5 text-xs text-warning-content hover:bg-warning-content/25"
                            @click="copyField('confirmationCode')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span>{{ copiedField === 'confirmationCode' ? 'Скопировано' : 'Копировать' }}</span>
                        </button>
                    </div>
                </section>

                <section class="card overflow-hidden bg-primary text-primary-content shadow">
                    <div class="card-body gap-4 p-4 sm:gap-7 sm:p-6">
                        <div class="grid gap-3 sm:grid-cols-2 sm:gap-6">
                            <div class="space-y-1 sm:space-y-2">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    PAYIN ID
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                    @click="copyField('payinId')"
                                >
                                    <span>{{ selected_item.payin_id.display }}</span>
                                    <span
                                        class="tooltip tooltip-top inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        :data-tip="copiedField === 'payinId' ? 'Скопировано' : ''"
                                        :class="copiedField === 'payinId' ? 'tooltip-open bg-primary-content/20 text-primary-content' : 'text-primary-content/75'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>

                            <div class="space-y-1 text-left sm:space-y-2 sm:text-right">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    AMOUNT
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:ml-auto sm:justify-end sm:text-lg"
                                    @click="copyField('amount')"
                                >
                                    <span>{{ selected_item.amount.display }}</span>
                                    <span
                                        class="tooltip tooltip-top inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        :data-tip="copiedField === 'amount' ? 'Скопировано' : ''"
                                        :class="copiedField === 'amount' ? 'tooltip-open bg-primary-content/20 text-primary-content' : 'text-primary-content/75'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1 sm:space-y-2">
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                Card Number
                            </p>
                            <button
                                type="button"
                                class="group flex cursor-pointer flex-wrap items-center gap-2 rounded-md text-left transition hover:text-primary-content/80 active:scale-[0.99]"
                                @click="copyField('cardNumber')"
                            >
                                <span class="break-words text-base font-semibold tracking-[0.16em] sm:text-2xl sm:tracking-[0.22em]">
                                    {{ selected_item.card_number.display }}
                                </span>
                                <span
                                    class="tooltip tooltip-top inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                    :data-tip="copiedField === 'cardNumber' ? 'Скопировано' : ''"
                                    :class="copiedField === 'cardNumber' ? 'tooltip-open bg-primary-content/20 text-primary-content' : 'text-primary-content/75'"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </span>
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            <div class="space-y-1 sm:space-y-2">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    Expiry Date
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                    @click="copyField('expiryDate')"
                                >
                                    <span>{{ selected_item.expiry_date.display }}</span>
                                    <span
                                        class="tooltip tooltip-top inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        :data-tip="copiedField === 'expiryDate' ? 'Скопировано' : ''"
                                        :class="copiedField === 'expiryDate' ? 'tooltip-open bg-primary-content/20 text-primary-content' : 'text-primary-content/75'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>

                            <div class="space-y-1 sm:space-y-2">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    CVV
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                    @click="copyField('cvv')"
                                >
                                    <span>{{ selected_item.cvv.display }}</span>
                                    <span
                                        class="tooltip tooltip-top inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        :data-tip="copiedField === 'cvv' ? 'Скопировано' : ''"
                                        :class="copiedField === 'cvv' ? 'tooltip-open bg-primary-content/20 text-primary-content' : 'text-primary-content/75'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card border border-base-300 bg-base-100 shadow">
                    <div class="card-body gap-4 p-4 sm:p-5">
                        <div
                            v-if="is_selected_history"
                            class="alert alert-info py-2 text-sm"
                            role="status"
                        >
                            <span>История: только просмотр (макет).</span>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-lg font-semibold text-base-content">
                                Confirmation Type
                            </h2>
                            <span
                                v-if="selected_item.pending_confirmation_title"
                                class="badge badge-primary max-w-full shrink-0 truncate text-xs font-medium normal-case sm:max-w-[min(100%,18rem)] sm:text-sm"
                                :title="selected_item.pending_confirmation_title"
                            >
                                {{ selected_item.pending_confirmation_title }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 items-stretch gap-3 md:grid-cols-3">
                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="option in confirmationCol1"
                                    :key="option.title"
                                    type="button"
                                    :class="confirmationButtonClass(option.title)"
                                    :disabled="is_selected_history"
                                    @click="selectConfirmationType(option.title)"
                                >
                                    {{ option.title }}
                                </button>
                            </div>

                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="option in confirmationCol2"
                                    :key="option.title"
                                    type="button"
                                    :class="confirmationButtonClass(option.title)"
                                    :disabled="is_selected_history"
                                    @click="selectConfirmationType(option.title)"
                                >
                                    {{ option.title }}
                                </button>
                            </div>

                            <div
                                class="flex min-h-0 w-full flex-col gap-2 md:h-full"
                                :class="selected_item.pending_confirmation_title ? 'justify-center' : 'justify-start'"
                            >
                                <button
                                    v-if="!selected_item.pending_confirmation_title && !is_selected_history"
                                    type="button"
                                    class="btn btn-error h-auto min-h-8 w-full whitespace-normal px-3 py-1.5 text-center text-xs font-medium normal-case sm:min-h-9"
                                    @click="openRejectModal"
                                >
                                    Reject
                                </button>

                                <div
                                    v-else-if="selected_item.pending_confirmation_title && !is_selected_history"
                                    class="flex w-full flex-col gap-2 rounded-box border border-base-300 bg-base-200/40 p-3"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm h-auto min-h-9 w-full whitespace-normal px-3 py-2 text-xs font-medium normal-case"
                                        :class="{ 'btn-disabled pointer-events-none opacity-50': !canConfirm }"
                                        :disabled="!canConfirm"
                                        @click="onConfirmClick"
                                    >
                                        Confirm
                                        <span class="ml-1 tabular-nums opacity-90">
                                            {{ confirmTimeDisplay }}
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-error btn-sm h-auto min-h-9 w-full whitespace-normal px-3 py-2 text-xs font-medium normal-case"
                                        @click="openRejectModal"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-12 text-center text-sm text-base-content/60"
            >
                Нет выбранной заявки (макет очереди пуст).
            </div>
        </div>

        <div
            v-else
            class="w-full flex-1"
            aria-hidden="true"
        />

        <dialog
            ref="newPayInModalDialog"
            class="modal modal-middle"
            tabindex="0"
            @close="on_new_pay_in_dialog_close"
        >
            <div class="modal-box max-w-md p-6">
                <h3 class="text-lg font-bold text-base-content">
                    New Pay In
                </h3>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="rounded-box border border-base-300 bg-base-200/40 px-3 py-2.5">
                        <p class="text-xs font-medium uppercase tracking-wide text-base-content/55">
                            Pay In ID
                        </p>
                        <p class="mt-1 font-semibold tabular-nums text-base-content">
                            {{ assignment_preview_item?.payin_id.display ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-box border border-base-300 bg-base-200/40 px-3 py-2.5">
                        <p class="text-xs font-medium uppercase tracking-wide text-base-content/55">
                            Bank
                        </p>
                        <p class="mt-1 font-semibold text-base-content">
                            {{ assignment_preview_item?.incoming_bank.display ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-box border border-base-300 bg-base-200/40 px-3 py-2.5">
                        <p class="text-xs font-medium uppercase tracking-wide text-base-content/55">
                            Amount
                        </p>
                        <p class="mt-1 font-semibold text-base-content">
                            {{ assignment_preview_item?.amount.display ?? '—' }}
                        </p>
                    </div>
                </div>

                <div class="modal-action mt-6 !justify-stretch gap-2 sm:!justify-end">
                    <button
                        type="button"
                        class="btn btn-error flex-1 sm:flex-none"
                        @click="on_cancel_new_pay_in"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary flex-1 sm:flex-none"
                        aria-label="Take to work"
                        :class="{ 'btn-disabled pointer-events-none opacity-60': take_work_seconds_remaining <= 0 }"
                        :disabled="take_work_seconds_remaining <= 0"
                        @click.stop="on_take_to_work"
                    >
                        <span class="sm:hidden">Take</span>
                        <span class="hidden sm:inline">Take to work</span>
                        <span class="ml-1 tabular-nums opacity-90">
                            ({{ take_work_seconds_remaining }})
                        </span>
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" aria-label="Close">
                    close
                </button>
            </form>
        </dialog>

        <dialog
            ref="rejectModalDialog"
            class="modal modal-bottom sm:modal-middle"
            tabindex="0"
            @close="selectedRejectReason = ''"
        >
            <div class="modal-box max-w-sm p-6">
                <h3 class="text-lg font-bold text-base-content">
                    Reject application?
                </h3>
                <p class="mt-2 text-sm text-base-content/60">
                    Select the reason for rejecting this application
                </p>

                <div class="mt-4 flex max-w-full flex-col gap-2">
                    <button
                        v-for="reason in rejectReasons"
                        :key="reason"
                        type="button"
                        class="rounded-box border px-3 py-2.5 text-left text-sm font-normal leading-snug normal-case transition-colors"
                        :class="
                            selectedRejectReason === reason
                                ? 'border-primary bg-primary/10 text-base-content'
                                : 'border-base-300 bg-base-100 text-base-content hover:border-base-content/30 hover:bg-base-200/80'
                        "
                        @click="pickRejectReason(reason)"
                    >
                        {{ reason }}
                    </button>
                </div>

                <div class="modal-action mt-6 !justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="closeRejectModal">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-error btn-sm"
                        :disabled="!selectedRejectReason"
                        @click="confirmReject"
                    >
                        Reject
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" aria-label="Close">
                    close
                </button>
            </form>
        </dialog>

        <footer class="mt-auto flex w-full shrink-0 justify-center border-t border-base-300 pt-6">
            <ThemeToggle />
        </footer>
        </div>
    </ManualControlLayout>
</template>
