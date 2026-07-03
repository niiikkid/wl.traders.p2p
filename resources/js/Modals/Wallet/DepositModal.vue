<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputHelper from '@/Components/InputHelper.vue';
import NumberInput from '@/Components/NumberInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useModalStore } from '@/store/modal.js';
import { useViewStore } from '@/store/view.js';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const modalStore = useModalStore();
const viewStore = useViewStore();

const show = computed(() => modalStore.isOpen('deposit'));
const params = computed(() => modalStore.paramsOf('deposit'));
const balanceType = computed(() => params.value.balanceType ?? 'trust');
const merchant = computed(() => params.value.merchant ?? null);

const title = computed(() => {
    const titles = {
        trust: 'Пополнение траст баланса',
        merchant: 'Пополнение мерчант баланса',
        teamleader: 'Пополнение баланса тимлидера',
        reserve: 'Пополнение общего страхового резерва',
        provider: viewStore.isAdminViewMode ? 'Пополнение баланса провайдера' : 'Пополнение баланса',
    };

    return titles[balanceType.value] ?? 'Пополнение баланса';
});

const trustDepositHelper = computed(() => {
    const insurance = page.props.teamLeaderInsurance;

    if (insurance?.role === 'trader' && insurance?.uses_shared_reserve) {
        return 'Пополнение зачисляется на основной баланс. Резерв трейдера не используется.';
    }

    return 'Если резерв меньше 1000 USDT, то часть депозита зачислится в резерв.';
});

const form = useForm({
    amount: null,
    balance_type: null,
    tx_hash: null,
    merchant_id: null,
});

const close = () => {
    modalStore.close('deposit');
    form.reset();
    form.clearErrors();
};

const deposit = () => {
    form
        .transform((data) => ({
            ...data,
            balance_type: balanceType.value,
            merchant_id: merchant.value?.id ?? null,
        }))
        .post(route('admin.users.wallet.deposit', params.value.user.id), {
            preserveScroll: true,
            onSuccess: () => {
                modalStore.closeAll();
                form.reset();
            },
        });
};
</script>

<template>
    <Modal :show="show" size="sm" @close="close">
        <ModalHeader :title="title" @close="close" />

        <ModalBody>
            <p class="text-sm text-base-content/70">
                Введите сумму пополнения в USDT и нажмите «Пополнить».
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <InputLabel for="amount" value="Сумма пополнения" :error="!!form.errors.amount" />
                    <div v-if="merchant" class="mb-2 rounded-lg bg-base-200/60 px-3 py-2 text-sm">
                        <div class="font-medium">{{ merchant.name }}</div>
                        <div class="font-mono text-xs text-base-content/60">{{ merchant.uuid }}</div>
                    </div>
                    <NumberInput
                        id="amount"
                        v-model="form.amount"
                        class="mt-1 block w-full"
                        placeholder="Сумма в USDT"
                        required
                        autofocus
                        :error="!!form.errors.amount"
                        @input="form.clearErrors('amount')"
                    />
                    <InputError class="mt-2" :message="form.errors.amount" />
                    <InputError class="mt-2" :message="form.errors.merchant_id" />
                    <InputHelper
                        v-if="!form.errors.amount && balanceType === 'trust'"
                        :model-value="trustDepositHelper"
                    />
                    <InputHelper
                        v-if="!form.errors.amount && balanceType === 'reserve'"
                        model-value="Средства зачисляются только на резервный баланс Team Leader."
                    />
                </div>

                <div>
                    <InputLabel for="tx_hash" value="Хэш транзакции" :error="!!form.errors.tx_hash" />
                    <TextInput
                        id="tx_hash"
                        v-model="form.tx_hash"
                        class="mt-1 block w-full"
                        placeholder="Хэш транзакции (опционально)"
                        :error="!!form.errors.tx_hash"
                        @input="form.clearErrors('tx_hash')"
                    />
                    <InputError class="mt-2" :message="form.errors.tx_hash" />
                    <InputHelper
                        v-if="!form.errors.tx_hash"
                        model-value="Необязательное поле. Укажите хэш транзакции, если есть."
                    />
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-ghost" @click="close">Отмена</button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="form.processing"
                @click="deposit"
            >
                Пополнить
            </button>
        </ModalFooter>
    </Modal>
</template>
