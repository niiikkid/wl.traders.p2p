<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";

import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import {router, useForm} from "@inertiajs/vue3";
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";
import TextInput from "@/Components/TextInput.vue";

const props = defineProps({
    balanceType: {
        type: String,
    },
});

const modalStore = useModalStore();
const { depositModal } = storeToRefs(modalStore);

const close = () => {
    modalStore.closeModal('deposit')
};

const form = useForm({
    amount: null,
    balance_type: null,
    tx_hash: null,
});

const deposit = () => {
    form
        .transform((data) => {
            data.balance_type = props.balanceType;

            return data;
        })
        .post(route('admin.users.wallet.deposit', depositModal.value.params.user.id), {
            preserveScroll: true,
            onSuccess: () => {
                modalStore.closeAll();
                form.reset();
            },
        });
}
</script>

<template>
    <Modal :show="depositModal.showed" @close="close" maxWidth="sm">
        <template v-if="balanceType === 'trust'">
            <ModalHeader
                title="Пополнение траст баланса"
                @close="close"
            />
        </template>
        <template v-if="balanceType === 'merchant'">
            <ModalHeader
                title="Пополнение мерчант баланса"
                @close="close"
            />
        </template>
        <template v-if="balanceType === 'teamleader'">
            <ModalHeader
                title="Пополнение баланса тимлидера"
                @close="close"
            />
        </template>
        <ModalBody>
            <h1 class="text-base-content/70 text-sm">Введите сумму пополнения в USDT и нажмите «Продолжить»</h1>
            <form action="#" class="mx-auto max-w-screen-xl 2xl:px-0 mt-8 mb-5">
                <div class="mx-auto max-w-3xl">
                    <div>
                        <div>
                            <InputLabel
                                for="amount"
                                value="Сумма пополнения"
                                :error="!!form.errors.amount"
                            />

                            <NumberInput
                                id="amount"
                                class="mt-1 block w-full"
                                v-model="form.amount"
                                placeholder="Сумма в USDT"
                                required
                                autofocus
                                :error="!!form.errors.amount"
                                @input="form.clearErrors('amount')"
                            />

                            <InputError class="mt-2" :message="form.errors.amount" />
                            <template v-if="balanceType === 'trust'">
                                <InputHelper v-if="! form.errors.amount" model-value="Если резерв меньше 1000 USDT, то часть депозита зачислится в резерв."></InputHelper>
                            </template>
                        </div>

                        <div class="mt-4">
                            <InputLabel
                                for="tx_hash"
                                value="Хэш транзакции"
                                :error="!!form.errors.tx_hash"
                            />

                            <TextInput
                                id="tx_hash"
                                class="mt-1 block w-full"
                                v-model="form.tx_hash"
                                placeholder="Хэш транзакции (опционально)"
                                :error="!!form.errors.tx_hash"
                                @input="form.clearErrors('tx_hash')"
                            />

                            <InputError class="mt-2" :message="form.errors.tx_hash" />
                            <InputHelper v-if="! form.errors.tx_hash" model-value="Необязательное поле. Укажите хэш транзакции, если есть."></InputHelper>
                        </div>
                    </div>
                </div>
            </form>
        </ModalBody>
        <ModalFooter>
            <div class="flex justify-center items-center w-full">
                <button
                    @click.prevent="deposit"
                    :disabled="form.processing"
                    type="button"
                    class="btn btn-primary"
                >
                    Пополнить
                </button>
            </div>
        </ModalFooter>
    </Modal>
</template>

<style scoped>

</style>
