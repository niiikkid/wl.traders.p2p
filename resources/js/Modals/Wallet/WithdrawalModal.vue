<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import InputHelper from '@/Components/InputHelper.vue';
import NumberInput from '@/Components/NumberInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import { useModalStore } from '@/store/modal.js';
import { useViewStore } from '@/store/view.js';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const modalStore = useModalStore();
const viewStore = useViewStore();
const { copy, copied } = useAppClipboard();

const show = computed(() => modalStore.isOpen('withdrawal'));
const params = computed(() => modalStore.paramsOf('withdrawal'));
const balanceType = computed(() => params.value.balanceType ?? 'trust');
const merchant = computed(() => params.value.merchant ?? null);
const showAddressForm = ref(false);

const walletStats = computed(() => page.props.walletStats);
const maxWithdrawableAmount = computed(() => {
    if (balanceType.value === 'merchant' && merchant.value) {
        return merchant.value.available_balance;
    }

    return walletStats.value?.totalAvailableBalances?.[balanceType.value]?.primary ?? null;
});

const isSelfWithdrawal = computed(() => (
    viewStore.isTraderViewMode || viewStore.isMerchantViewMode || viewStore.isTeamLeaderViewMode
));

const title = computed(() => {
    if (balanceType.value === 'merchant' && merchant.value) {
        return `Вывод с магазина «${merchant.value.name}»`;
    }

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
    withdrawal_address_id: null,
    balance_type: null,
    merchant_id: null,
});

const addressForm = useForm({
    name: '',
    address: '',
    one_time_password: '',
});

const withdrawalAddresses = computed(() => page.props.withdrawalAddresses?.items ?? []);
const has2fa = computed(() => Boolean(page.props.withdrawalAddresses?.has_2fa));
const hasAddresses = computed(() => withdrawalAddresses.value.length > 0);

const selectedAddress = computed(() => (
    withdrawalAddresses.value.find((item) => item.id === form.withdrawal_address_id) ?? null
));

const close = () => {
    modalStore.close('withdrawal');
    form.reset();
    form.clearErrors();
    addressForm.reset();
    addressForm.clearErrors();
    showAddressForm.value = false;
};

const storeAddress = () => {
    addressForm.post(route('wallet.withdrawal-addresses.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            addressForm.reset();
            showAddressForm.value = false;
        },
        onError: () => {
            showAddressForm.value = true;
        },
    });
};

const withdraw = () => {
    const target = viewStore.isAdminViewMode
        ? route('admin.users.wallet.withdraw', params.value.user.id)
        : route('invoice.store');

    form
        .transform((data) => ({
            ...data,
            balance_type: balanceType.value,
            merchant_id: merchant.value?.id ?? null,
        }))
        .post(target, {
            preserveScroll: true,
            onSuccess: () => {
                modalStore.closeAll();
                form.reset();
                showAddressForm.value = false;
            },
        });
};
</script>

<template>
    <Modal :show="show" size="md" @close="close">
        <ModalHeader :title="title" @close="close" />

        <ModalBody>
            <div class="space-y-3">
                <div>
                    <InputLabel for="amount" value="Сумма вывода" :error="!!form.errors.amount" />
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
                    <InputError class="mt-1.5" :message="form.errors.amount" />
                    <InputError class="mt-1.5" :message="form.errors.merchant_id" />
                    <InputHelper
                        v-if="!form.errors.amount && maxWithdrawableAmount"
                        :model-value="`Максимум: ${maxWithdrawableAmount} USDT`"
                    />
                </div>

                <div v-if="isSelfWithdrawal" class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <InputLabel for="withdrawal_address_id" value="Адрес вывода" :error="!!form.errors.withdrawal_address_id" />
                        <span class="badge badge-outline whitespace-nowrap">USDT TRC20</span>
                    </div>

                    <div v-if="hasAddresses" class="flex gap-2">
                        <select
                            id="withdrawal_address_id"
                            v-model="form.withdrawal_address_id"
                            class="select select-bordered min-w-0 flex-1"
                            required
                            :class="{ 'select-error': !!form.errors.withdrawal_address_id }"
                            @change="form.clearErrors('withdrawal_address_id')"
                        >
                            <option :value="null" disabled>Выберите адрес</option>
                            <option
                                v-for="address in withdrawalAddresses"
                                :key="address.id"
                                :value="address.id"
                            >
                                {{ address.name || 'Без названия' }} · {{ address.masked_address }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="btn btn-outline shrink-0"
                            @click="showAddressForm = !showAddressForm"
                        >
                            {{ showAddressForm ? 'Скрыть' : 'Добавить' }}
                        </button>
                    </div>

                    <div v-else class="flex flex-col gap-2 sm:flex-row">
                        <div class="alert alert-info py-2 text-sm sm:flex-1">
                            <span>Нет сохранённых адресов. Добавьте USDT TRC20 адрес для вывода.</span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline shrink-0"
                            @click="showAddressForm = !showAddressForm"
                        >
                            Добавить
                        </button>
                    </div>

                    <InputError :message="form.errors.withdrawal_address_id" />

                    <div
                        v-if="selectedAddress"
                        class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-base-300/70 bg-base-200/40 px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium">
                                {{ selectedAddress.name || 'Адрес вывода' }}
                            </div>
                            <div class="truncate font-mono text-sm text-base-content/60">
                                {{ selectedAddress.masked_address }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-ghost shrink-0"
                            @click="copy(selectedAddress.address)"
                        >
                            {{ copied ? 'Скопировано' : 'Копировать' }}
                        </button>
                    </div>

                    <form
                        v-if="showAddressForm"
                        class="rounded-lg border border-base-300 bg-base-100 p-3 space-y-3"
                        @submit.prevent="storeAddress"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel for="withdrawal_address_name" value="Название" :error="!!addressForm.errors.name" />
                                <TextInput
                                    id="withdrawal_address_name"
                                    v-model="addressForm.name"
                                    class="mt-1 block w-full"
                                    placeholder="Binance"
                                    :error="!!addressForm.errors.name"
                                    @input="addressForm.clearErrors('name')"
                                />
                                <InputError class="mt-1" :message="addressForm.errors.name" />
                            </div>

                            <div v-if="has2fa">
                                <InputLabel for="withdrawal_address_2fa" value="Код 2FA" :error="!!addressForm.errors.one_time_password" />
                                <TextInput
                                    id="withdrawal_address_2fa"
                                    v-model="addressForm.one_time_password"
                                    class="mt-1 block w-full"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    placeholder="Код"
                                    required
                                    :error="!!addressForm.errors.one_time_password"
                                    @input="addressForm.clearErrors('one_time_password')"
                                />
                                <InputError class="mt-1" :message="addressForm.errors.one_time_password" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="withdrawal_address" value="USDT TRC20 адрес" :error="!!addressForm.errors.address" />
                            <TextInput
                                id="withdrawal_address"
                                v-model="addressForm.address"
                                class="mt-1 block w-full font-mono"
                                placeholder="T..."
                                required
                                :error="!!addressForm.errors.address"
                                @input="addressForm.clearErrors('address')"
                            />
                            <InputError class="mt-1" :message="addressForm.errors.address" />
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                class="btn btn-ghost"
                                @click="showAddressForm = false"
                            >
                                Отмена
                            </button>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="addressForm.processing"
                            >
                                Сохранить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-ghost" @click="close">Отмена</button>
            <button
                type="button"
                class="btn btn-error"
                :disabled="form.processing || (isSelfWithdrawal && !form.withdrawal_address_id)"
                @click="withdraw"
            >
                Вывести
            </button>
        </ModalFooter>
    </Modal>
</template>
