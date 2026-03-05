<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {ref} from 'vue'
import axios from 'axios';
import { storeToRefs } from 'pinia'
import {useModalStore} from "@/store/modal.js";

const props = defineProps({
    balanceType: {
        type: String,
    },
});

const modalStore = useModalStore();
const { traderDepositModal } = storeToRefs(modalStore);

const amount = ref('');
const error = ref('');
const loading = ref(false);

function close() {
    modalStore.closeModal('traderDeposit');
    amount.value = '';
    error.value = '';
}

async function submit() {
    error.value = '';
    if (!amount.value || Number(amount.value) <= 0) {
        error.value = 'Укажите сумму';
        return;
    }
    try {
        loading.value = true;
        const { data } = await axios.post(
            route('trader.deposit.invoices.store'),
            { amount: amount.value },
            {
                withCredentials: true,
                headers: {
                    'Accept': 'application/json',
                },
            }
        );

        if (!data?.payment_url) {
            throw new Error('Не получена ссылка на оплату');
        }
        close();
        window.location.href = data.payment_url;
    } catch (e) {
        const response = e.response?.data;
        const firstError = response?.errors
            ? Object.values(response.errors).flat()[0]
            : null;
        error.value = response?.message || firstError || e.message || 'Не удалось создать инвойс';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Modal :show="traderDepositModal.showed" @close="close" maxWidth="sm">
        <ModalHeader title="Пополнение траст баланса" @close="close"/>
        <ModalBody>
            <div class="space-y-4">
                <div>
                    <InputLabel for="amount" value="Сумма" :error="!!error" />
                    <TextInput id="amount" v-model="amount" type="number" step="0.01" min="0" class="mt-1 w-full" :error="!!error" @input="error = ''" />
                    <InputError class="mt-2" :message="error" />
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <div class="flex justify-end gap-2">
                <button class="btn btn-ghost btn-sm sm:btn-md" type="button" @click="close">Отмена</button>
                <PrimaryButton class="btn-sm sm:btn-md" :disabled="loading" @click="submit">Перейти к оплате</PrimaryButton>
            </div>
        </ModalFooter>
    </Modal>
    <form class="hidden" @submit.prevent="submit"></form>
    <div class="hidden">{{ props.balanceType }}</div>
</template>


