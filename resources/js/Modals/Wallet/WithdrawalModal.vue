<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";

import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import {router, useForm, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import TextInput from "@/Components/TextInput.vue";

const props = defineProps({
    balanceType: {
        type: String,
    },
});

const total_trust_withdrawable_amount = usePage().props.total_trust_withdrawable_amount;
const total_merchant_withdrawable_amount = usePage().props.total_merchant_withdrawable_amount;
const modalStore = useModalStore();
const { withdrawalModal } = storeToRefs(modalStore);
const viewStore = useViewStore();

const close = () => {
    modalStore.closeModal('withdrawal')
};

const form = useForm({
    amount: null,
    address: null,
    balance_type: props.balanceType,
});

const withdraw = () => {
    if (viewStore.isAdminViewMode) {
        form
            .transform((data) => {
                data.balance_type = props.balanceType;

                return data;
            })
            .post(route('admin.users.wallet.withdraw', withdrawalModal.value.params.user.id), {
                preserveScroll: true,
                onSuccess: () => {
                    modalStore.closeAll();
                    form.reset();
                },
            });
    }
    if (viewStore.isTraderViewMode || viewStore.isMerchantViewMode || viewStore.isTeamLeaderViewMode) {
        form
            .transform((data) => {
                data.balance_type = props.balanceType;

                return data;
            })
            .post(route('invoice.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    modalStore.closeAll();
                    form.reset();
                },
            });
    }
}
</script>

<template>
    <Modal :show="withdrawalModal.showed" @close="close" maxWidth="sm">
        <template v-if="balanceType === 'trust'">
            <ModalHeader
                title="Вывод с траст баланса"
                @close="close"
            />
        </template>
        <template v-if="balanceType === 'merchant'">
            <ModalHeader
                title="Вывод с мерчант баланса"
                @close="close"
            />
        </template>
        <template v-if="balanceType === 'teamleader'">
            <ModalHeader
                title="Вывод с баланса тимлидера"
                @close="close"
            />
        </template>
        <ModalBody>
            <h1 class="text-base-content/70 text-sm">Введите сумму которую хотите вывести с баланса в USDT и нажмите «Продолжить»</h1>
            <form action="#" class="mx-auto max-w-screen-xl 2xl:px-0 mt-8 mb-5">
                <div class="mx-auto max-w-3xl">
                    <div>
                        <div>
                            <InputLabel
                                for="amount"
                                value="Сумма вывода"
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
                            <template v-show="balanceType === 'trust'">
                                <InputHelper v-if="! form.errors.amount" :model-value="'Максимум: ' + total_trust_withdrawable_amount + ' USDT'"></InputHelper>
                            </template>
                            <template v-show="balanceType === 'merchant'">
                                <InputHelper v-if="! form.errors.amount" :model-value="'Максимум: ' + total_merchant_withdrawable_amount + ' USDT'"></InputHelper>
                            </template>
                        </div>
                        <div class="mt-3" v-if="viewStore.isTraderViewMode || viewStore.isMerchantViewMode || viewStore.isTeamLeaderViewMode">
                            <InputLabel
                                for="address"
                                value="Адрес"
                                :error="!!form.errors.address"
                            />

                            <TextInput
                                id="address"
                                class="mt-1 block w-full"
                                v-model="form.address"
                                placeholder="Ваш USDT TRC-20 Адрес"
                                required
                                autofocus
                                :error="!!form.errors.address"
                                @input="form.clearErrors('address')"
                            />

                            <InputError class="mt-2" :message="form.errors.address" />
                        </div>
                    </div>
                </div>
            </form>
        </ModalBody>
        <ModalFooter>
            <div class="flex justify-center items-center w-full">
                <button
                    @click.prevent="withdraw"
                    :disabled="form.processing"
                    type="button"
                    class="btn btn-error btn-sm sm:btn-md"
                >
                    Вывести
                </button>
            </div>
        </ModalFooter>
    </Modal>
</template>

<style scoped>

</style>
