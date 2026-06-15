<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoBackButton from "@/Components/GoBackButton.vue";
import DepositModal from "@/Modals/Wallet/DepositModal.vue";
import WithdrawalModal from "@/Modals/Wallet/WithdrawalModal.vue";
import TraderDepositModal from "@/Modals/Wallet/TraderDepositModal.vue";
import LeaderReserveDepositModal from "@/Modals/Wallet/LeaderReserveDepositModal.vue";
import MerchantBalance from "@/Pages/Wallet/Partials/MerchantBalance.vue";
import {useViewStore} from "@/store/view.js";
import OperationsHistory from "@/Pages/Wallet/Partials/OperationsHistory.vue";
import {computed, ref} from "vue";
import EscrowBalance from "@/Pages/Wallet/Partials/EscrowBalance.vue";
import DisputeBalance from "@/Pages/Wallet/Partials/DisputeBalance.vue";
import TrustBalance from "@/Pages/Wallet/Partials/TrustBalance.vue";
import TeamleaderBalance from "@/Pages/Wallet/Partials/TeamleaderBalance.vue";
import TeamLeaderSharedReserveBalance from "@/Pages/Wallet/Partials/TeamLeaderSharedReserveBalance.vue";
import TraderBalanceTransferModal from "@/Modals/Wallet/TraderBalanceTransferModal.vue";

const page = usePage();
const user = page.props.user;
const walletStats = page.props.walletStats;
const viewStore = useViewStore();

/** На admin.users.wallet приходит из бэка; на своём кошельке отсутствует — используем только viewStore. */
const walletSurfaces = computed(() => page.props.walletSurfaces ?? null);

const traderBalanceTransfer = computed(() => page.props.traderBalanceTransfer ?? null);
const teamLeaderInsurance = computed(() => page.props.teamLeaderInsurance ?? null);

const showTrustBalanceCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.trust;
    }
    return viewStore.isTraderViewMode || viewStore.isAdminViewMode;
});

const showMerchantBalanceCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.merchant;
    }
    return viewStore.isMerchantViewMode || viewStore.isAdminViewMode;
});

const showTeamleaderBalanceCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.teamleader;
    }
    return viewStore.isTeamLeaderViewMode || viewStore.isAdminViewMode;
});

const showTeamLeaderSharedReserveCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.reserve === true;
    }

    return teamLeaderInsurance.value?.uses_shared_reserve === true
        && teamLeaderInsurance.value?.role === 'team_leader';
});

const showEscrowBalanceCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.escrow;
    }
    return viewStore.isTraderViewMode || viewStore.isAdminViewMode;
});

const showDisputeBalanceCard = computed(() => {
    const ws = walletSurfaces.value;
    if (ws) {
        return ws.dispute;
    }
    return viewStore.isTraderViewMode || viewStore.isAdminViewMode;
});

const balanceType = ref('trust');
const fiatCurrencyForm = useForm({
    fiat_currency: walletStats.currency.secondary,
});

const availableFiatCurrencies = computed(() => {
    return (page.props.data?.rates ?? []).map((rate) => ({
        code: rate.code,
        label: rate.code.toUpperCase(),
    }));
});

const setBalanceType = (type) => {
    balanceType.value = type;
}

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

        <div
            v-if="viewStore.isTraderViewMode || (showTrustBalanceCard && viewStore.isAdminViewMode)"
            class="mb-4 flex items-center justify-end gap-2"
        >
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
            class="mb-3"
        >
            <h2 class="text-xl text-base-content sm:text-2xl">
                Пользователь: <span class="text-primary">{{user.email}}</span>
            </h2>
        </div>

        <div
            v-if="!viewStore.isAdminViewMode && showTeamLeaderSharedReserveCard"
            role="alert"
            class="alert alert-info mb-6 text-sm"
        >
            <span>
                В истории операций можно отфильтровать движения по доходу тимлидера и по общему страховому резерву.
            </span>
        </div>

        <div
            v-if="viewStore.isAdminViewMode && teamLeaderInsurance?.uses_shared_reserve && teamLeaderInsurance?.role === 'team_leader'"
            role="alert"
            class="alert alert-info mb-6 text-sm"
        >
            <span>
                Режим «{{ teamLeaderInsurance.mode_label }}».
                Доход тимлидера и общий страховой резерв — отдельные балансы; в истории операций можно отфильтровать по типу.
            </span>
        </div>

        <div
            v-if="viewStore.isAdminViewMode && teamLeaderInsurance?.uses_shared_reserve && teamLeaderInsurance?.role === 'trader'"
            role="alert"
            class="alert alert-info mb-6 text-sm"
        >
            <span>
                Трейдер подключён к Team Leader
                <template v-if="teamLeaderInsurance.team_leader_email">
                    ({{ teamLeaderInsurance.team_leader_email }})
                </template>
                с общим страховым резервом. Личный резерв трейдера не используется.
            </span>
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
            <TrustBalance
                v-show="showTrustBalanceCard"
                :trader-balance-transfer="traderBalanceTransfer"
                :team-leader-insurance="teamLeaderInsurance"
                @setBalanceType="setBalanceType"
            />
            <MerchantBalance v-show="showMerchantBalanceCard" @setBalanceType="setBalanceType"/>
            <TeamleaderBalance v-show="showTeamleaderBalanceCard" @setBalanceType="setBalanceType"/>
            <TeamLeaderSharedReserveBalance
                v-if="teamLeaderInsurance && showTeamLeaderSharedReserveCard"
                :team-leader-insurance="teamLeaderInsurance"
                @setBalanceType="setBalanceType"
            />
            <EscrowBalance v-show="showEscrowBalanceCard" @setBalanceType="setBalanceType"/>
            <DisputeBalance v-show="showDisputeBalanceCard" @setBalanceType="setBalanceType"/>
        </div>

        <OperationsHistory/>

        <DepositModal :balanceType="balanceType"/>
        <TraderDepositModal :balanceType="balanceType"/>
        <LeaderReserveDepositModal />
        <TraderBalanceTransferModal />
        <WithdrawalModal :balanceType="balanceType"/>
    </div>
</template>

