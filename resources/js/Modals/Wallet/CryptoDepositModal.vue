<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useModalStore } from '@/store/modal.js';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modalName: { type: String, required: true },
    storeRoute: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const POLL_INTERVAL_MS = 10000;

const modalStore = useModalStore();
const show = computed(() => modalStore.isOpen(props.modalName));
const modalParams = computed(() => modalStore.paramsOf(props.modalName));

const step = ref('amount');
const amount = ref('');
const error = ref('');
const loading = ref(false);
const invoice = ref(null);
const copied = ref(false);
const now = ref(Date.now());

let pollTimer = null;
let clockTimer = null;

const STATUS_MAP = {
    pending: { label: 'Ожидание оплаты', badge: 'badge-info' },
    processing: { label: 'Оплата обнаружена, ждём подтверждений', badge: 'badge-warning' },
    paid: { label: 'Зачислено', badge: 'badge-success' },
    expired: { label: 'Инвойс истёк', badge: 'badge-neutral' },
    cancelled: { label: 'Отменён', badge: 'badge-error' },
    amount_mismatch: { label: 'Неверная сумма — нужна проверка', badge: 'badge-error' },
    failed: { label: 'Ошибка — обратитесь в поддержку', badge: 'badge-error' },
};

const statusInfo = computed(() => STATUS_MAP[invoice.value?.status] ?? { label: invoice.value?.status ?? '', badge: 'badge-neutral' });
const isFinal = computed(() => ['paid', 'expired', 'cancelled', 'amount_mismatch', 'failed'].includes(invoice.value?.status));

const remainingSeconds = computed(() => {
    if (!invoice.value?.expires_at) {
        return 0;
    }

    const diff = Math.floor((new Date(invoice.value.expires_at).getTime() - now.value) / 1000);

    return Math.max(0, diff);
});

const countdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const stopTimers = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (clockTimer) {
        clearInterval(clockTimer);
        clockTimer = null;
    }
};

const reset = () => {
    stopTimers();
    step.value = 'amount';
    amount.value = '';
    error.value = '';
    loading.value = false;
    invoice.value = null;
    copied.value = false;
};

const close = () => {
    modalStore.close(props.modalName);
};

const poll = async () => {
    if (!invoice.value) {
        return;
    }

    try {
        const { data } = await axios.get(route('deposit.invoices.show', invoice.value.id), {
            headers: { Accept: 'application/json' },
        });

        const previousStatus = invoice.value.status;
        invoice.value = data.invoice;

        if (previousStatus !== 'paid' && invoice.value.status === 'paid') {
            router.reload({ only: ['walletStats'] });
        }

        if (isFinal.value) {
            stopTimers();
        }
    } catch (e) {
        // Transient polling error — keep trying on the next tick.
    }
};

const startPaymentView = () => {
    step.value = 'payment';
    now.value = Date.now();

    stopTimers();
    clockTimer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    pollTimer = setInterval(poll, POLL_INTERVAL_MS);
};

const submit = async () => {
    error.value = '';

    const numeric = Number(amount.value);

    if (!Number.isInteger(numeric) || numeric < 1) {
        error.value = 'Укажите целую сумму в USDT (минимум 1).';

        return;
    }

    try {
        loading.value = true;
        const { data } = await axios.post(
            route(props.storeRoute),
            { amount: numeric },
            { withCredentials: true, headers: { Accept: 'application/json' } },
        );

        invoice.value = data.invoice;
        startPaymentView();
    } catch (e) {
        const response = e.response?.data;
        const firstError = response?.errors ? Object.values(response.errors).flat()[0] : null;
        error.value = response?.message || firstError || 'Не удалось создать инвойс.';
    } finally {
        loading.value = false;
    }
};

const openExistingInvoice = async () => {
    const existingInvoice = modalParams.value.invoice;
    const invoiceId = modalParams.value.invoiceId ?? existingInvoice?.id;

    if (existingInvoice) {
        invoice.value = existingInvoice;
        startPaymentView();
    }

    if (!invoiceId) {
        return;
    }

    try {
        loading.value = true;
        const { data } = await axios.get(route('deposit.invoices.show', invoiceId), {
            headers: { Accept: 'application/json' },
        });

        invoice.value = data.invoice;
        startPaymentView();
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось открыть инвойс.';
        step.value = 'amount';
    } finally {
        loading.value = false;
    }
};

const copyAddress = async () => {
    if (!invoice.value?.address) {
        return;
    }

    try {
        await navigator.clipboard.writeText(invoice.value.address);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (e) {
        // Clipboard not available — user can copy manually.
    }
};

watch(show, (visible) => {
    if (visible) {
        reset();
        openExistingInvoice();
    } else {
        stopTimers();
    }
});

onBeforeUnmount(stopTimers);
</script>

<template>
    <Modal :show="show" size="sm" @close="close">
        <ModalHeader :title="title" @close="close" />

        <ModalBody>
            <div v-if="step === 'amount'" class="space-y-3">
                <p v-if="description" class="text-sm text-base-content/70">{{ description }}</p>
                <div>
                    <InputLabel :for="`${modalName}_amount`" value="Сумма пополнения (USDT)" :error="!!error" />
                    <TextInput
                        :id="`${modalName}_amount`"
                        v-model="amount"
                        type="number"
                        step="1"
                        min="1"
                        class="mt-1 w-full"
                        :error="!!error"
                        @input="error = ''"
                    />
                    <InputError class="mt-2" :message="error" />
                </div>
                <div class="alert alert-warning alert-soft text-sm">
                    Пополнение принимается только в <b>USDT (сеть TRON / TRC20)</b>.
                </div>
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="badge" :class="statusInfo.badge">{{ statusInfo.label }}</span>
                    <span v-if="!isFinal" class="font-mono text-sm text-base-content/70">Осталось {{ countdown }}</span>
                </div>

                <div v-if="invoice.status === 'paid'" class="alert alert-success alert-soft text-sm">
                    Средства успешно зачислены на баланс.
                </div>

                <template v-else-if="invoice.status === 'pending' || invoice.status === 'processing'">
                    <div class="flex justify-center">
                        <img :src="invoice.qr_url" alt="QR" class="size-44 rounded-box bg-white p-2" />
                    </div>

                    <div>
                        <div class="text-xs text-base-content/60 mb-1">Сумма (отправьте точную сумму)</div>
                        <div class="font-mono text-lg font-semibold">{{ invoice.amount }} USDT</div>
                    </div>

                    <div>
                        <div class="text-xs text-base-content/60 mb-1">Адрес (сеть TRON / TRC20)</div>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 break-all rounded-box bg-base-200 px-2 py-1 text-xs">{{ invoice.address }}</code>
                            <button type="button" class="btn btn-sm btn-square btn-ghost" title="Скопировать" @click="copyAddress">
                                <span v-if="copied" class="text-success text-xs">✓</span>
                                <svg v-else class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="invoice.status === 'processing'" class="text-sm text-base-content/70">
                        Подтверждений: {{ invoice.confirmations }} / {{ invoice.required_confirmations }}
                    </div>

                    <ul class="text-xs text-base-content/60 space-y-1">
                        <li>• Отправьте <b>точную</b> сумму, иначе потребуется проверка администратором.</li>
                        <li>• Используйте только сеть <b>TRON (TRC20)</b>.</li>
                        <li>• Оплатите до истечения таймера.</li>
                    </ul>
                </template>

                <div v-else class="alert alert-error alert-soft text-sm">
                    <template v-if="invoice.status === 'expired'">Время оплаты истекло. Создайте новый инвойс.</template>
                    <template v-else-if="invoice.status === 'amount_mismatch'">Получена другая сумма — платёж передан на проверку администратору.</template>
                    <template v-else>Инвойс завершён. При вопросах обратитесь в поддержку.</template>
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <template v-if="step === 'amount'">
                <button type="button" class="btn btn-ghost" @click="close">Отмена</button>
                <button type="button" class="btn btn-primary" :disabled="loading" @click="submit">
                    <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                    Создать инвойс
                </button>
            </template>
            <template v-else>
                <button
                    v-if="invoice && ['expired', 'cancelled', 'failed'].includes(invoice.status)"
                    type="button"
                    class="btn btn-primary"
                    @click="reset"
                >
                    Новый инвойс
                </button>
                <button type="button" class="btn btn-ghost" @click="close">Закрыть</button>
            </template>
        </ModalFooter>
    </Modal>
</template>
