<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import InputHelper from '@/Components/InputHelper.vue';
import NumberInput from '@/Components/NumberInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useModalStore } from '@/store/modal.js';
import { useViewStore } from '@/store/view.js';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const modalStore = useModalStore();
const viewStore = useViewStore();

const show = computed(() => modalStore.isOpen('withdrawal'));
const params = computed(() => modalStore.paramsOf('withdrawal'));
const balanceType = computed(() => params.value.balanceType ?? 'trust');

const totalTrustWithdrawable = computed(() => page.props.total_trust_withdrawable_amount);
const totalMerchantWithdrawable = computed(() => page.props.total_merchant_withdrawable_amount);

const isSelfWithdrawal = computed(() => (
    viewStore.isTraderViewMode || viewStore.isMerchantViewMode || viewStore.isTeamLeaderViewMode
));

const title = computed(() => {
    const titles = {
        trust: 'Вывод с траст баланса',
        merchant: 'Вывод с мерчант баланса',
        teamleader: 'Вывод с баланса тимлидера',
        reserve: 'Вывод общего страхового резерва',
        provider: 'Вывод с баланса провайдера',
    };

    return titles[balanceType.value] ?? 'Вывод средств';
});

const form = useForm({
    amount: null,
    address: null,
    balance_type: null,
});

const close = () => {
    modalStore.close('withdrawal');
    form.reset();
    form.clearErrors();
};

const withdraw = () => {
    const target = viewStore.isAdminViewMode
        ? route('admin.users.wallet.withdraw', params.value.user.id)
        : route('invoice.store');

    form
        .transform((data) => ({ ...data, balance_type: balanceType.value }))
        .post(target, {
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
                Введите сумму, которую хотите вывести с баланса в USDT, и нажмите «Вывести».
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <InputLabel for="amount" value="Сумма вывода" :error="!!form.errors.amount" />
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
                    <InputHelper
                        v-if="!form.errors.amount && balanceType === 'trust'"
                        :model-value="`Максимум: ${totalTrustWithdrawable} USDT`"
                    />
                    <InputHelper
                        v-if="!form.errors.amount && balanceType === 'merchant'"
                        :model-value="`Максимум: ${totalMerchantWithdrawable} USDT`"
                    />
                </div>

                <div v-if="isSelfWithdrawal">
                    <InputLabel for="address" value="Адрес" :error="!!form.errors.address" />
                    <TextInput
                        id="address"
                        v-model="form.address"
                        class="mt-1 block w-full"
                        placeholder="Ваш USDT TRC-20 адрес"
                        required
                        :error="!!form.errors.address"
                        @input="form.clearErrors('address')"
                    />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-ghost" @click="close">Отмена</button>
            <button
                type="button"
                class="btn btn-error"
                :disabled="form.processing"
                @click="withdraw"
            >
                Вывести
            </button>
        </ModalFooter>
    </Modal>
</template>
