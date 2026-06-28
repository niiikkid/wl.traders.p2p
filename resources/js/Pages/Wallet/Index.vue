<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoBackButton from "@/Components/GoBackButton.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MerchantBalance from "@/Pages/Wallet/Partials/MerchantBalance.vue";
import {useViewStore} from "@/store/view.js";
import OperationsHistory from "@/Pages/Wallet/Partials/OperationsHistory.vue";
import {computed} from "vue";
import EscrowBalance from "@/Pages/Wallet/Partials/EscrowBalance.vue";
import DisputeBalance from "@/Pages/Wallet/Partials/DisputeBalance.vue";
import TrustBalance from "@/Pages/Wallet/Partials/TrustBalance.vue";
import TeamleaderBalance from "@/Pages/Wallet/Partials/TeamleaderBalance.vue";
import TeamLeaderSharedReserveBalance from "@/Pages/Wallet/Partials/TeamLeaderSharedReserveBalance.vue";

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

const fiatCurrencyForm = useForm({
    fiat_currency: walletStats.currency.secondary,
});

const availableFiatCurrencies = computed(() => {
    return (page.props.data?.rates ?? []).map((rate) => ({
        code: rate.code,
        label: rate.code.toUpperCase(),
    }));
});

const updateFiatCurrency = () => {
    fiatCurrencyForm.patch(route('wallet.fiat-currency.update'), {
        preserveScroll: true,
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Финансы"/>

    <div class="max-w-5xl mx-auto space-y-5">
        <div v-if="viewStore.isAdminViewMode">
            <GoBackButton @click="router.visit(route('admin.users.index'))"></GoBackButton>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Финансы</h2>
                <p v-if="viewStore.isAdminViewMode" class="text-sm text-base-content/60 mt-1">
                    Кошелёк пользователя <span class="text-primary font-medium">{{ user.email }}</span>
                </p>
            </div>

            <label
                v-if="viewStore.isTraderViewMode || (showTrustBalanceCard && viewStore.isAdminViewMode)"
                class="flex items-center gap-2"
            >
                <span class="text-sm text-base-content/60">Валюта</span>
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
            </label>
        </div>

        <div
            v-if="fiatCurrencyForm.errors.fiat_currency"
            class="text-error text-xs text-right"
        >
            {{ fiatCurrencyForm.errors.fiat_currency }}
        </div>

        <div
            v-if="!viewStore.isAdminViewMode && showTeamLeaderSharedReserveCard"
            role="alert"
            class="alert alert-info text-sm py-2"
        >
            <span>
                В истории операций можно отфильтровать движения по доходу тимлидера и по общему страховому резерву.
            </span>
        </div>

        <div
            v-if="viewStore.isAdminViewMode && teamLeaderInsurance?.uses_shared_reserve && teamLeaderInsurance?.role === 'team_leader'"
            role="alert"
            class="alert alert-info text-sm py-2"
        >
            <span>
                Режим «{{ teamLeaderInsurance.mode_label }}».
                Доход тимлидера и общий страховой резерв — отдельные балансы; в истории операций можно отфильтровать по типу.
            </span>
        </div>

        <div
            v-if="viewStore.isAdminViewMode && teamLeaderInsurance?.uses_shared_reserve && teamLeaderInsurance?.role === 'trader'"
            role="alert"
            class="alert alert-info text-sm py-2"
        >
            <span>
                Трейдер подключён к Team Leader
                <template v-if="teamLeaderInsurance.team_leader_email">
                    ({{ teamLeaderInsurance.team_leader_email }})
                </template>
                с общим страховым резервом. Личный резерв трейдера не используется.
            </span>
        </div>

        <div v-if="$page.props.flash.error" role="alert" class="alert alert-error text-sm py-2">
            <svg class="w-4 h-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
            </svg>
            <span>
                <span class="font-medium">Внимание.</span> {{ $page.props.flash.error }}
            </span>
        </div>

        <div class="grid sm:grid-cols-2 grid-cols-1 gap-4">
            <TrustBalance
                v-show="showTrustBalanceCard"
                :trader-balance-transfer="traderBalanceTransfer"
                :team-leader-insurance="teamLeaderInsurance"
            />
            <MerchantBalance v-show="showMerchantBalanceCard"/>
            <TeamleaderBalance v-show="showTeamleaderBalanceCard"/>
            <TeamLeaderSharedReserveBalance
                v-if="teamLeaderInsurance && showTeamLeaderSharedReserveCard"
                :team-leader-insurance="teamLeaderInsurance"
            />
            <EscrowBalance v-show="showEscrowBalanceCard"/>
            <DisputeBalance v-show="showDisputeBalanceCard"/>
        </div>

        <OperationsHistory/>

        <ConfirmModal />
    </div>
</template>

