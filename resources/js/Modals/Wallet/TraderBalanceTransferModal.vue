<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import { truncateTrustBalanceForTransfer } from '@/utils/truncateTrustBalanceForTransfer.js';
import { useModalStore } from '@/store/modal.js';
import { router, usePage } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const page = usePage();
const modalStore = useModalStore();
const { traderBalanceTransferModal } = storeToRefs(modalStore);

const recipientLogin = ref('');
const amount = ref('');
const oneTimePassword = ref('');
const recipientPreview = ref(null);
const fieldErrors = ref({});
const recipientError = ref('');
const checkingRecipient = ref(false);
const submitting = ref(false);

const transferConfig = computed(() => page.props.traderBalanceTransfer ?? null);

const maxTransferAmount = computed(() => {
    if (!transferConfig.value?.trust_balance) {
        return '0.00';
    }

    return truncateTrustBalanceForTransfer(transferConfig.value.trust_balance);
});

const canTransferAll = computed(() => Number(maxTransferAmount.value) > 0);

const has2fa = computed(() => Boolean(transferConfig.value?.has_2fa));

const recipientChecked = computed(() => recipientPreview.value !== null);

const canSubmit = computed(() => (
    recipientChecked.value
    && amount.value !== ''
    && Number(amount.value) > 0
    && (!has2fa.value || oneTimePassword.value !== '')
    && !checkingRecipient.value
    && !submitting.value
));

const avatarUrl = (preview) => (
    `https://api.dicebear.com/9.x/${preview.avatar_style}/svg?seed=${preview.avatar_uuid}`
);

const resetRecipientPreview = () => {
    recipientPreview.value = null;
    recipientError.value = '';
    fieldErrors.value = {};
};

watch(recipientLogin, () => {
    resetRecipientPreview();
});

watch(amount, (value) => {
    const sanitized = sanitizeAmountInput(value);

    if (sanitized !== value) {
        amount.value = sanitized;

        return;
    }

    if (fieldErrors.value.amount) {
        fieldErrors.value = { ...fieldErrors.value, amount: undefined };
    }
});

const close = () => {
    modalStore.closeModal('traderBalanceTransfer');
    recipientLogin.value = '';
    amount.value = '';
    oneTimePassword.value = '';
    resetRecipientPreview();
    checkingRecipient.value = false;
    submitting.value = false;
};

const fieldErrorMessage = (errors, field) => {
    const value = errors?.[field];

    if (Array.isArray(value)) {
        return value[0] ?? '';
    }

    if (typeof value === 'string') {
        return value;
    }

    return '';
};

const applyFieldErrors = (errors) => {
    fieldErrors.value = errors ?? {};
};

const checkRecipient = async () => {
    recipientError.value = '';
    applyFieldErrors({});

    const login = recipientLogin.value.trim();

    if (!login) {
        recipientError.value = 'Укажите логин получателя.';
        return;
    }

    checkingRecipient.value = true;

    try {
        const { data } = await axios.get(route('wallet.trader-transfer.recipient'), {
            params: { login },
            headers: { Accept: 'application/json' },
        });

        recipientPreview.value = data;
    } catch (error) {
        resetRecipientPreview();
        recipientError.value = error.response?.data?.message
            ?? error.response?.data?.errors?.login?.[0]
            ?? 'Трейдер не найден или недоступен для перевода.';
    } finally {
        checkingRecipient.value = false;
    }
};

const fillMaxAmount = () => {
    if (!canTransferAll.value) {
        return;
    }

    amount.value = maxTransferAmount.value;
    fieldErrors.value = { ...fieldErrors.value, amount: undefined };
};

const sanitizeAmountInput = (value) => {
    let next = String(value ?? '').replace(',', '.');

    if (next === '') {
        return '';
    }

    if (!/^\d*\.?\d{0,2}$/.test(next)) {
        return amount.value;
    }

    return next;
};

const submitTransfer = async () => {
    fieldErrors.value = {};
    recipientError.value = '';
    submitting.value = true;

    try {
        await axios.post(
            route('wallet.trader-transfer.store'),
            {
                recipient_login: recipientPreview.value.login,
                amount: amount.value,
                one_time_password: has2fa.value ? oneTimePassword.value : undefined,
            },
            { headers: { Accept: 'application/json' } },
        );

        close();
        router.reload({ only: ['walletStats', 'invoices', 'transactions', 'traderBalanceTransfer'], preserveScroll: true });
    } catch (error) {
        const response = error.response?.data;

        if (response?.errors && Object.keys(response.errors).length > 0) {
            applyFieldErrors(response.errors);
        } else if (response?.message) {
            recipientError.value = response.message;
        } else {
            recipientError.value = 'Перевод недоступен.';
        }
    } finally {
        submitting.value = false;
    }
};

const confirmTransfer = () => {
    if (!canSubmit.value || !recipientPreview.value) {
        return;
    }

    modalStore.openConfirmModal({
        title: 'Подтвердите перевод',
        body: `Перевести ${amount.value} USDT трейдеру ${recipientPreview.value.login}?`,
        confirm_button_name: 'Перевести',
        cancel_button_name: 'Отмена',
        confirm: () => submitTransfer(),
    });
};
</script>

<template>
    <Modal :show="traderBalanceTransferModal.showed" max-width="md" @close="close">
        <ModalHeader title="Перевод средств трейдеру" @close="close" />
        <ModalBody>
            <div class="space-y-4">
                <div>
                    <InputLabel for="recipient_login" value="Логин получателя" :error="!!recipientError || !!fieldErrors.login" />
                    <div class="mt-1 flex w-full items-stretch gap-2">
                        <TextInput
                            id="recipient_login"
                            v-model="recipientLogin"
                            type="text"
                            class="min-w-0 flex-1"
                            :error="!!recipientError || !!fieldErrors.login"
                            :disabled="checkingRecipient || submitting"
                        />
                        <button
                            type="button"
                            class="btn btn-neutral shrink-0"
                            :class="{ 'btn-disabled': checkingRecipient || submitting || !recipientLogin.trim() }"
                            :disabled="checkingRecipient || submitting || !recipientLogin.trim()"
                            @click="checkRecipient"
                        >
                            Проверить
                        </button>
                    </div>
                    <InputError class="mt-2" :message="recipientError || fieldErrorMessage(fieldErrors, 'login')" />
                </div>

                <div v-if="recipientPreview" class="flex items-center gap-3 rounded-lg border border-base-300 p-3">
                    <img
                        :src="avatarUrl(recipientPreview)"
                        class="h-10 w-10 rounded-full"
                        alt="recipient photo"
                    >
                    <span class="font-medium text-base-content">{{ recipientPreview.login }}</span>
                </div>

                <div>
                    <InputLabel for="transfer_amount" value="Сумма" :error="!!fieldErrors.amount" />
                    <div class="mt-1 flex w-full items-stretch gap-2">
                        <TextInput
                            id="transfer_amount"
                            v-model="amount"
                            type="text"
                            class="min-w-0 flex-1"
                            :error="!!fieldErrors.amount"
                            :disabled="!recipientChecked || submitting"
                        />
                        <button
                            type="button"
                            class="btn btn-secondary shrink-0 whitespace-nowrap"
                            :class="{ 'btn-disabled': !recipientChecked || !canTransferAll || submitting }"
                            :disabled="!recipientChecked || !canTransferAll || submitting"
                            @click="fillMaxAmount"
                        >
                            Перевести всё
                        </button>
                    </div>
                    <InputError class="mt-2" :message="fieldErrorMessage(fieldErrors, 'amount')" />
                </div>

                <div v-if="has2fa">
                    <InputLabel for="one_time_password" value="Код 2FA" :error="!!fieldErrors.one_time_password" />
                    <TextInput
                        id="one_time_password"
                        v-model="oneTimePassword"
                        type="text"
                        inputmode="numeric"
                        class="mt-1 w-full"
                        :error="!!fieldErrors.one_time_password"
                        :disabled="!recipientChecked || submitting"
                    />
                    <InputError class="mt-2" :message="fieldErrorMessage(fieldErrors, 'one_time_password')" />
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button
                type="button"
                class="btn btn-secondary min-w-28"
                :disabled="submitting"
                @click="close"
            >
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-primary min-w-28"
                :class="{ 'btn-disabled': !canSubmit }"
                :disabled="!canSubmit"
                @click="confirmTransfer"
            >
                Перевести
            </button>
        </ModalFooter>
    </Modal>

    <ConfirmModal />
</template>
