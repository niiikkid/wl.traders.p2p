<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ManualControlLayout from '@/Layouts/ManualControlLayout.vue';

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

const elapsedSeconds = ref(0);
const copiedField = ref('');
const rejectModalDialog = ref(null);
const selectedRejectReason = ref('');
const selectedConfirmationTitle = ref('');
const confirmSecondsRemaining = ref(CONFIRM_COUNTDOWN_SECONDS);
let timerInterval = null;
let copiedFieldTimeout = null;

const fields = {
    payinId: {
        display: '220045893',
        copy: '220045893',
    },
    amount: {
        display: '1,000 UAH',
        copy: '1000',
    },
    cardNumber: {
        display: '4444 3333 2222 1111',
        copy: '4444333322221111',
    },
    expiryDate: {
        display: '07/30',
        copy: '07/30',
    },
    cvv: {
        display: '128',
        copy: '128',
    },
};

const processingTime = computed(() => {
    const minutes = Math.floor(elapsedSeconds.value / 60);
    const seconds = elapsedSeconds.value % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const processingProgress = computed(() => {
    return Math.min(elapsedSeconds.value / PROCESSING_TOTAL_SECONDS, 1);
});

const processingRingDashoffset = computed(() => {
    return processingRingCircumference * (1 - processingProgress.value);
});

const confirmTimeDisplay = computed(() => {
    const minutes = Math.floor(confirmSecondsRemaining.value / 60);
    const seconds = confirmSecondsRemaining.value % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const canConfirm = computed(() => confirmSecondsRemaining.value > 0);

onMounted(() => {
    timerInterval = window.setInterval(() => {
        elapsedSeconds.value += 1;

        if (selectedConfirmationTitle.value && confirmSecondsRemaining.value > 0) {
            confirmSecondsRemaining.value -= 1;
        }
    }, 1000);
});

const selectConfirmationType = (title) => {
    selectedConfirmationTitle.value = title;
    confirmSecondsRemaining.value = CONFIRM_COUNTDOWN_SECONDS;
};

const confirmationButtonClass = (title) => {
    const base = 'btn h-auto min-h-8 w-full whitespace-normal px-3 py-1.5 text-center text-xs font-medium normal-case sm:min-h-9';

    return selectedConfirmationTitle.value === title ? `${base} btn-primary` : `${base} btn-outline`;
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
    const value = fields[fieldKey]?.copy;

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
    if (timerInterval) {
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

        <div class="w-full">
            <div class="mx-auto flex w-full max-w-xl flex-col gap-4">
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
                                    <span>{{ fields.payinId.display }}</span>
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
                                    <span>{{ fields.amount.display }}</span>
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
                                    {{ fields.cardNumber.display }}
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
                                    <span>{{ fields.expiryDate.display }}</span>
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
                                    <span>{{ fields.cvv.display }}</span>
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
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-lg font-semibold text-base-content">
                                Confirmation Type
                            </h2>
                            <span
                                v-if="selectedConfirmationTitle"
                                class="badge badge-primary max-w-full shrink-0 truncate text-xs font-medium normal-case sm:max-w-[min(100%,18rem)] sm:text-sm"
                                :title="selectedConfirmationTitle"
                            >
                                {{ selectedConfirmationTitle }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 items-stretch gap-3 md:grid-cols-3">
                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="option in confirmationCol1"
                                    :key="option.title"
                                    type="button"
                                    :class="confirmationButtonClass(option.title)"
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
                                    @click="selectConfirmationType(option.title)"
                                >
                                    {{ option.title }}
                                </button>
                            </div>

                            <div
                                class="flex min-h-0 w-full flex-col gap-2 md:h-full"
                                :class="selectedConfirmationTitle ? 'justify-center' : 'justify-start'"
                            >
                                <button
                                    v-if="!selectedConfirmationTitle"
                                    type="button"
                                    class="btn btn-error h-auto min-h-8 w-full whitespace-normal px-3 py-1.5 text-center text-xs font-medium normal-case sm:min-h-9"
                                    @click="openRejectModal"
                                >
                                    Reject
                                </button>

                                <div
                                    v-else
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
        </div>

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
    </ManualControlLayout>
</template>
