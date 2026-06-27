<script setup>
import ModalFooterNext from "@/Components/Modals/Next/ModalFooterNext.vue";
import ModalBodyNext from "@/Components/Modals/Next/ModalBodyNext.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import ModalNext from "@/Components/Modals/Next/ModalNext.vue";
import ModalHeaderNext from "@/Components/Modals/Next/ModalHeaderNext.vue";
import { computed } from 'vue';
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import {useViewStore} from "@/store/view.js";
import CopyableOrderUid from "@/Components/CopyableOrderUid.vue";
import DateTime from "@/Components/DateTime.vue";

const emit = defineEmits(['accept', 'cancel', 'rollback']);

const viewStore = useViewStore();
const modalStore = useModalStore();
const { disputeModal } = storeToRefs(modalStore);

const dispute = computed(() => disputeModal.value.params.dispute);

const statusBannerClass = computed(() => {
    switch (dispute.value.status) {
        case 'accepted':
            return 'border-success/25 bg-success/5';
        case 'canceled':
            return 'border-error/25 bg-error/5';
        default:
            return 'border-warning/30 bg-warning/5';
    }
});

const statusHeadline = computed(() => {
    switch (dispute.value.status) {
        case 'accepted':
            return 'Спор принят';
        case 'canceled':
            return 'Спор отклонён';
        default:
            return 'Спор ожидает проверки';
    }
});

const statusIconCircleClass = computed(() => {
    switch (dispute.value.status) {
        case 'accepted':
            return 'bg-success/15 text-success ring-1 ring-success/25 ring-inset';
        case 'canceled':
            return 'bg-error/15 text-error ring-1 ring-error/25 ring-inset';
        default:
            return 'bg-warning/15 text-warning ring-1 ring-warning/30 ring-inset';
    }
});

const close = () => {
    modalStore.closeModal('dispute')
};

const accept = (dispute) => {
    emit('accept', dispute);
};

const cancel = (dispute) => {
    emit('cancel', dispute);
};

const rollback = (dispute) => {
    emit('rollback', dispute);
};

const showReceipt = () => {
    window.open(dispute.value.receipt_url, '_blank').focus();
};

const showBankStatement = () => {
    window.open(dispute.value.bank_statement_url, '_blank').focus();
};

const dispute_footer_actions_visible = computed(
    () =>
        viewStore.isAdminViewMode
        || viewStore.isSupportViewMode
        || dispute.value.status === 'pending'
        || dispute.value.status === 'canceled'
);
</script>

<template>
    <ModalNext :show="disputeModal.showed" @close="close" maxWidth="md">
        <ModalHeaderNext
            :title="'Спор #' + dispute.id"
            @close="close"
        />
        <ModalBodyNext>
            <div class="w-full min-w-0 space-y-2 sm:space-y-3">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 rounded-box border px-2.5 py-2 sm:gap-3 sm:px-3 sm:py-2.5"
                    :class="statusBannerClass"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full sm:size-9"
                            :class="statusIconCircleClass"
                        >
                            <template v-if="dispute.status === 'accepted'">
                                <svg
                                    class="size-6 sm:size-7"
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                            </template>
                            <template v-else-if="dispute.status === 'canceled'">
                                <svg
                                    class="size-6 sm:size-7"
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="m15 9-6 6m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                            </template>
                            <template v-else>
                                <svg
                                    class="size-6 sm:size-7"
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                            </template>
                        </span>
                        <p class="min-w-0 text-xs font-semibold leading-snug text-base-content sm:text-sm">
                            {{ statusHeadline }}
                        </p>
                    </div>
                    <div
                        class="flex shrink-0 items-center text-xs leading-none text-base-content/70 sm:text-sm"
                    >
                        <DateTime :data="dispute.created_at" :copyable="false" />
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 overflow-hidden rounded-box border border-base-300/80 shadow-sm"
                >
                    <div class="flex min-w-0 flex-col justify-center bg-base-300/50 px-2.5 py-2 sm:px-3 sm:py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-base-content/55 sm:text-xs">
                            Сумма спора
                        </p>
                        <p class="mt-1 text-base font-bold tabular-nums leading-none tracking-tight text-base-content sm:text-lg">
                            {{ dispute.order.amount }}
                            <span class="text-xs font-semibold text-primary/70 sm:text-sm">
                                {{ dispute.order.currency.toUpperCase() }}
                            </span>
                        </p>
                    </div>
                    <div class="flex min-w-0 flex-col justify-center bg-base-300/50 px-2.5 py-2 text-end sm:px-3 sm:py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-base-content/55 sm:text-xs">
                            В {{ dispute.order.base_currency.toUpperCase() }}
                        </p>
                        <p class="mt-1 text-base font-bold tabular-nums leading-none tracking-tight text-base-content sm:text-lg">
                            {{ dispute.order.total_profit }}
                            <span class="text-xs font-semibold text-primary/70 sm:text-sm">
                                {{ dispute.order.base_currency.toUpperCase() }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-box border border-base-300/80 bg-base-300/50 shadow-sm">
                    <dl class="divide-y divide-base-300/80">
                        <div class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2">
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Сделка
                            </dt>
                            <dd class="min-w-0 break-all text-end text-xs text-base-content sm:text-sm">
                                <CopyableOrderUid :uuid="dispute.order.uuid" class="text-xs text-base-content sm:text-sm" />
                            </dd>
                        </div>
                        <div class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2">
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Сделка создана
                            </dt>
                            <dd class="min-w-0 shrink text-end text-xs text-base-content sm:text-sm">
                                <DateTime :data="dispute.order.created_at" :copyable="false" />
                            </dd>
                        </div>
                        <div class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2">
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Реквизит
                                <span class="font-normal text-primary/70"><CopyableOrderUid :uuid="dispute.payment_detail.uuid ?? ''" :copyable="false" /></span>
                            </dt>
                            <dd class="min-w-0 text-end">
                                <PaymentDetail
                                    :detail="dispute.payment_detail.detail"
                                    :type="dispute.payment_detail.type"
                                    :copyable="false"
                                    class="text-xs text-base-content sm:text-sm"
                                />
                                <div class="mt-0.5 text-xs text-base-content/65 sm:text-sm">
                                    {{ dispute.payment_detail.name }}
                                </div>
                            </dd>
                        </div>
                        <div
                            v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode"
                            class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2"
                        >
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Трейдер
                                <span class="font-normal text-primary/70">#{{ dispute.user.id }}</span>
                            </dt>
                            <dd class="min-w-0 text-end">
                                <div class="text-xs font-semibold text-base-content sm:text-sm">
                                    {{ dispute.user.name }}
                                </div>
                                <div class="break-all text-xs text-base-content/65 sm:text-sm">
                                    {{ dispute.user.email }}
                                </div>
                            </dd>
                        </div>
                        <div class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2">
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Квитанция
                            </dt>
                            <dd class="flex shrink-0 justify-end">
                                <button
                                    v-if="dispute.receipt_url"
                                    type="button"
                                    class="btn btn-xs btn-outline btn-info touch-manipulation"
                                    @click.prevent="showReceipt"
                                >
                                    Открыть
                                    <svg
                                        class="ms-1 size-3 shrink-0 sm:size-3.5"
                                        aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 12H5m14 0-4 4m4-4-4-4"
                                        />
                                    </svg>
                                </button>
                                <span v-else class="text-xs text-base-content/55 sm:text-sm">Нет файла</span>
                            </dd>
                        </div>
                        <div
                            v-if="dispute.status === 'canceled'"
                            class="flex flex-row items-center justify-between gap-2 px-2.5 py-1.5 sm:gap-3 sm:px-3 sm:py-2"
                        >
                            <dt class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                                Выписка
                            </dt>
                            <dd class="flex shrink-0 justify-end">
                                <button
                                    v-if="dispute.bank_statement_url"
                                    type="button"
                                    class="btn btn-xs btn-outline btn-accent touch-manipulation"
                                    @click.prevent="showBankStatement"
                                >
                                    Выписка
                                    <svg
                                        class="ms-1 size-3 shrink-0 sm:size-3.5"
                                        aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 12H5m14 0-4 4m4-4-4-4"
                                        />
                                    </svg>
                                </button>
                                <span v-else class="text-xs text-base-content/55 sm:text-sm">Нет файла</span>
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="dispute.status === 'canceled'"
                        class="border-t border-base-300/60 bg-base-200/25 px-2.5 py-2 sm:px-3 sm:py-2.5"
                    >
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-base-content/50 sm:text-xs">
                            Причина отклонения
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-base-content/90 sm:text-sm">
                            {{ dispute.reason }}
                        </p>
                    </div>
                </div>
            </div>
        </ModalBodyNext>
        <ModalFooterNext>
            <div
                v-if="dispute_footer_actions_visible"
                class="flex w-full flex-wrap items-center justify-center gap-1.5 sm:gap-2"
            >
                <template v-if="dispute.status === 'pending'">
                    <button
                        type="button"
                        class="btn btn-xs btn-error btn-outline touch-manipulation sm:btn-sm"
                        @click.prevent="cancel(dispute)"
                    >
                        <svg
                            class="me-1 size-3 sm:me-1.5 sm:size-3.5"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18 17.94 6M18 18 6.06 6"
                            />
                        </svg>
                        Отклонить
                    </button>
                    <button
                        type="button"
                        class="btn btn-xs btn-primary btn-outline touch-manipulation sm:btn-sm"
                        @click.prevent="accept(dispute)"
                    >
                        <svg
                            class="me-1 size-3 sm:me-1.5 sm:size-3.5"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 11.917 9.724 16.5 19 7.5"
                            />
                        </svg>
                        Принять
                    </button>
                </template>
                <template v-if="dispute.status !== 'pending'">
                    <button
                        type="button"
                        class="btn btn-xs btn-warning btn-outline touch-manipulation sm:btn-sm"
                        @click.prevent="rollback(dispute)"
                    >
                        <svg
                            class="me-1 size-3 sm:me-1.5 sm:size-3.5"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3"
                            />
                        </svg>
                        Открыть спор
                    </button>
                </template>
            </div>
        </ModalFooterNext>
    </ModalNext>
</template>
