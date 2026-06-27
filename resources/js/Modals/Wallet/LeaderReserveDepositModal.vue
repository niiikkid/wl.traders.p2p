<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useModalStore } from '@/store/modal.js';
import { computed, ref } from 'vue';
import axios from 'axios';

const modalStore = useModalStore();
const show = computed(() => modalStore.isOpen('leaderReserveDeposit'));

const amount = ref('');
const error = ref('');
const loading = ref(false);

const close = () => {
    modalStore.close('leaderReserveDeposit');
    amount.value = '';
    error.value = '';
};

const submit = async () => {
    error.value = '';

    if (!amount.value || Number(amount.value) <= 0) {
        error.value = 'Укажите сумму';

        return;
    }

    try {
        loading.value = true;
        const { data } = await axios.post(
            route('leader.deposit.invoices.store'),
            { amount: amount.value },
            { withCredentials: true, headers: { Accept: 'application/json' } },
        );

        if (!data?.payment_url) {
            throw new Error('Не получена ссылка на оплату');
        }

        close();
        window.location.href = data.payment_url;
    } catch (e) {
        const response = e.response?.data;
        const firstError = response?.errors ? Object.values(response.errors).flat()[0] : null;
        error.value = response?.message || firstError || e.message || 'Не удалось создать инвойс';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Modal :show="show" size="sm" @close="close">
        <ModalHeader title="Пополнение общего страхового резерва" @close="close" />

        <ModalBody>
            <div class="space-y-4">
                <p class="text-sm text-base-content/70">
                    Средства зачисляются только на резервный баланс Team Leader и не попадают на баланс дохода.
                </p>
                <div>
                    <InputLabel for="leader_reserve_amount" value="Сумма" :error="!!error" />
                    <TextInput
                        id="leader_reserve_amount"
                        v-model="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-1 w-full"
                        :error="!!error"
                        @input="error = ''"
                    />
                    <InputError class="mt-2" :message="error" />
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-ghost" @click="close">Отмена</button>
            <button type="button" class="btn btn-primary" :disabled="loading" @click="submit">
                Перейти к оплате
            </button>
        </ModalFooter>
    </Modal>
</template>
