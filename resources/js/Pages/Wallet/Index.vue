<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoBackButton from "@/Components/GoBackButton.vue";
import DepositModal from "@/Modals/Wallet/DepositModal.vue";
import WithdrawalModal from "@/Modals/Wallet/WithdrawalModal.vue";
import TraderDepositModal from "@/Modals/Wallet/TraderDepositModal.vue";
import MerchantBalance from "@/Pages/Wallet/Partials/MerchantBalance.vue";
import {useViewStore} from "@/store/view.js";
import OperationsHistory from "@/Pages/Wallet/Partials/OperationsHistory.vue";
import {computed, ref} from "vue";
import EscrowBalance from "@/Pages/Wallet/Partials/EscrowBalance.vue";
import DisputeBalance from "@/Pages/Wallet/Partials/DisputeBalance.vue";
import TrustBalance from "@/Pages/Wallet/Partials/TrustBalance.vue";
import TeamleaderBalance from "@/Pages/Wallet/Partials/TeamleaderBalance.vue";
import UserNotesModal from "@/Modals/User/UserNotesModal.vue";
import {useModalStore} from "@/store/modal.js";

const user = usePage().props.user;
const walletStats = usePage().props.walletStats;
const viewStore = useViewStore();
const modalStore = useModalStore();

const balanceType = ref('trust');
const fiatCurrencyForm = useForm({
    fiat_currency: walletStats.currency.secondary,
});

const availableFiatCurrencies = computed(() => {
    return (usePage().props.data?.rates ?? []).map((rate) => ({
        code: rate.code,
        label: rate.code.toUpperCase(),
    }));
});

const setBalanceType = (type) => {
    balanceType.value = type;
}

const openUserNotesModal = () => {
    modalStore.openUserNotesModal({user});
};

const updateFiatCurrency = () => {
    fiatCurrencyForm.patch(route('wallet.fiat-currency.update'), {
        preserveScroll: true,
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Финансы"/>

    <div>
        <h2 class="text-3xl font-bold text-base-content mb-6">Финансы</h2>

        <div v-if="viewStore.isTraderViewMode" class="mb-4 flex items-center justify-end gap-2">
            <span class="text-sm opacity-70">Валюта отображения</span>
            <select
                v-model="fiatCurrencyForm.fiat_currency"
                class="select select-bordered select-sm w-20"
                :disabled="fiatCurrencyForm.processing"
                @change="updateFiatCurrency"
            >
                <option
                    v-for="currency in availableFiatCurrencies"
                    :key="currency.code"
                    :value="currency.code"
                >
                    {{ currency.label }}
                </option>
            </select>
            <span v-if="fiatCurrencyForm.errors.fiat_currency" class="text-error text-xs">
                {{ fiatCurrencyForm.errors.fiat_currency }}
            </span>
        </div>

        <div v-if="viewStore.isAdminViewMode" class="mb-3">
            <GoBackButton
                @click="router.visit(route('admin.users.index'))"
            ></GoBackButton>
        </div>

        <div
            v-if="viewStore.isAdminViewMode"
            class="flex items-center justify-between mb-3"
        >
            <h2 class="text-xl text-base-content sm:text-2xl">
                Пользователь: <span class="text-primary">{{user.email}}</span>
            </h2>

            <button
                @click="openUserNotesModal"
                type="button"
                class="btn btn-primary btn-circle"
            >
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5h8m-8 5h8m-8 5h4.5M5 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4Z"/>
                </svg>
            </button>
        </div>

        <div v-if="$page.props.flash.error" role="alert" class="alert alert-error mb-6">
            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
            </svg>
            <span>
                <span class="font-medium">Внимание</span> {{ $page.props.flash.error }}
            </span>
        </div>

        <div class="grid xl:grid-cols-2 grid-cols-1 gap-6 mb-6">
            <TrustBalance v-show="viewStore.isTraderViewMode || viewStore.isAdminViewMode" @setBalanceType="setBalanceType"/>
            <MerchantBalance v-show="viewStore.isMerchantViewMode || viewStore.isAdminViewMode" @setBalanceType="setBalanceType"/>
            <TeamleaderBalance v-show="viewStore.isTeamLeaderViewMode || viewStore.isAdminViewMode" @setBalanceType="setBalanceType"/>
            <EscrowBalance v-show="viewStore.isTraderViewMode || viewStore.isAdminViewMode" @setBalanceType="setBalanceType"/>
            <DisputeBalance v-show="viewStore.isTraderViewMode || viewStore.isAdminViewMode" @setBalanceType="setBalanceType"/>
        </div>

        <OperationsHistory/>

        <DepositModal :balanceType="balanceType"/>
        <TraderDepositModal :balanceType="balanceType"/>
        <WithdrawalModal :balanceType="balanceType"/>
        <UserNotesModal />
    </div>
</template>

