<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import InputHelper from "@/Components/InputHelper.vue";
import {router, useForm} from "@inertiajs/vue3";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import {useViewStore} from "@/store/view.js";
import {computed, ref, watch} from "vue";

const REASON_PRESET_OTHER = 'other';
const REASON_PRESET_WRONG_DETAILS = 'wrong_details';
const REASON_MAX_LENGTH = 120;

const reasonPresets = [
    {
        value: 'wrong_details',
        label: 'Неверные реквизиты',
        reason: 'Неверные реквизиты',
    },
    {
        value: 'fake_receipt',
        label: 'Фейковый чек',
        reason: 'Фейковый чек',
    },
    {
        value: 'payment_return',
        label: 'Нет оплаты(лимит/возврат)',
        reason: 'Нет оплаты(лимит/возврат)',
    },
    {
        value: REASON_PRESET_OTHER,
        label: 'Другая причина',
        reason: null,
    },
];

const modalStore = useModalStore();
const viewStore = useViewStore();
const { disputeCancelModal } = storeToRefs(modalStore);

const bankStatementInputRef = ref(null);

const form = useForm({
    reason_code: '',
    reason: '',
    bank_statement: null,
});

const isCustomReason = computed(() => form.reason_code === REASON_PRESET_OTHER);
const isBankStatementOptional = computed(() => form.reason_code === REASON_PRESET_WRONG_DETAILS);

const remainingReasonChars = computed(() => {
    return Math.max(0, REASON_MAX_LENGTH - (form.reason?.length ?? 0));
});

const selectedBankStatementName = computed(() => form.bank_statement?.name ?? 'Файл не выбран');

const isSubmitDisabled = computed(() => {
    if (form.processing) {
        return true;
    }

    if (!form.reason_code) {
        return true;
    }

    if (isCustomReason.value && !form.reason?.trim()) {
        return true;
    }

    return !isBankStatementOptional.value && !form.bank_statement;
});

const resetForm = () => {
    form.reset();
    form.clearErrors();

    if (bankStatementInputRef.value) {
        bankStatementInputRef.value.value = '';
    }
};

const close = () => {
    resetForm();
    modalStore.closeModal('disputeCancel');
};

/** Trader and Super Admin (admin UI) share PATCH disputes/{dispute}/cancel — no admin.disputes.cancel route. */
const cancelDisputeRouteName = computed(() => {
    if (viewStore.isSupportViewMode) {
        return 'support.disputes.cancel';
    }

    return 'disputes.cancel';
});

const onReasonPresetChange = () => {
    form.clearErrors('reason');

    form.clearErrors('bank_statement');

    if (!isCustomReason.value) {
        form.reason = '';
    }
};

watch(
    () => form.reason,
    (value) => {
        if (!isCustomReason.value || !value || value.length <= REASON_MAX_LENGTH) {
            return;
        }

        form.reason = value.slice(0, REASON_MAX_LENGTH);
    },
);

const onCustomReasonInput = () => {
    form.clearErrors('reason');
};

const selectBankStatement = () => {
    bankStatementInputRef.value?.click();
};

const onBankStatementChange = (event) => {
    const [file] = event.target.files ?? [];
    form.bank_statement = file || null;
    form.clearErrors('bank_statement');
};

const cancel = (dispute) => {
    form.patch(route(cancelDisputeRouteName.value, dispute.uuid), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetForm();
            modalStore.closeAll();
            router.visit(route(route().current()));
        },
    });
};
</script>

<template>
    <Modal :show="disputeCancelModal.showed" @close="close" maxWidth="md">
        <ModalHeader
            :title="'Отклонение спора #' + disputeCancelModal.params.dispute.id"
            @close="close"
        />
        <ModalBody>
            <form action="#" class="py-3 space-y-4">
                <div>
                    <InputLabel
                        for="reason_preset"
                        value="Причина отклонения"
                        :error="!!form.errors.reason"
                    />

                    <select
                        id="reason_preset"
                        v-model="form.reason_code"
                        class="select select-bordered w-full mt-1"
                        :class="{ 'select-error': (!!form.errors.reason || !!form.errors.reason_code) && !form.reason_code }"
                        :disabled="form.processing"
                        @change="onReasonPresetChange"
                    >
                        <option value="" disabled>Выберите причину</option>
                        <option
                            v-for="preset in reasonPresets"
                            :key="preset.value"
                            :value="preset.value"
                        >
                            {{ preset.label }}
                        </option>
                    </select>

                    <InputError :message="form.errors.reason_code || form.errors.reason" class="mt-2" />
                </div>

                <div v-if="isCustomReason">
                    <InputLabel
                        for="reason"
                        value="Своя причина"
                        :error="!!form.errors.reason"
                    />

                    <TextInput
                        id="reason"
                        v-model="form.reason"
                        class="mt-1 block w-full"
                        placeholder="Укажите причину отклонения"
                        :error="!!form.errors.reason"
                        :disabled="form.processing"
                        @input="onCustomReasonInput"
                    />

                    <InputHelper
                        v-if="!form.errors.reason"
                        :model-value="`Осталось символов: ${remainingReasonChars}`"
                    />
                </div>

                <div>
                    <InputLabel
                        for="bank_statement"
                        value="Выписка по карте"
                        :error="!!form.errors.bank_statement"
                    />

                    <input
                        id="bank_statement"
                        ref="bankStatementInputRef"
                        type="file"
                        class="hidden"
                        accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                        :disabled="form.processing"
                        @change="onBankStatementChange"
                    />

                    <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            :disabled="form.processing"
                            @click="selectBankStatement"
                        >
                            Выбрать файл
                        </button>
                        <span class="text-sm text-base-content/70 truncate">{{ selectedBankStatementName }}</span>
                    </div>

                    <InputError :message="form.errors.bank_statement" class="mt-2" />
                    <InputHelper
                        v-if="!form.errors.bank_statement"
                        :model-value="isBankStatementOptional ? 'Для причины «Неверные реквизиты» выписка не обязательна. JPG, PNG или PDF, не более 5 МБ' : 'JPG, PNG или PDF, не более 5 МБ'"
                    />
                </div>
            </form>
        </ModalBody>
        <ModalFooter>
            <div class="w-full flex justify-center space-x-2">
                <button
                    @click.prevent="close"
                    type="button"
                    class="btn btn-sm btn-error btn-outline"
                    :disabled="form.processing"
                >
                    Отмена
                </button>
                <button
                    @click.prevent="cancel(disputeCancelModal.params.dispute)"
                    :disabled="isSubmitDisabled"
                    type="button"
                    class="btn btn-primary btn-sm btn-outline"
                >
                    <span v-if="form.processing" class="loading loading-spinner loading-xs mr-2" />
                    Подтвердить
                </button>
            </div>
        </ModalFooter>
    </Modal>
</template>
