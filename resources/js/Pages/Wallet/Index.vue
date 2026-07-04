<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoBackButton from "@/Components/GoBackButton.vue";
import Select from "@/Components/Select.vue";
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
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import { useModalStore } from '@/store/modal.js';

const page = usePage();
const user = page.props.user;
const walletStats = page.props.walletStats;
const viewStore = useViewStore();

/** На admin.users.wallet приходит из бэка; на своём кошельке отсутствует — используем только viewStore. */
const walletSurfaces = computed(() => page.props.walletSurfaces ?? null);

const traderBalanceTransfer = computed(() => page.props.traderBalanceTransfer ?? null);
const teamLeaderInsurance = computed(() => page.props.teamLeaderInsurance ?? null);
const withdrawalAddresses = computed(() => page.props.withdrawalAddresses?.items ?? []);
const merchantWalletMode = computed(() => Boolean(page.props.merchantWalletMode));
const merchantWallets = computed(() => page.props.merchantWallets ?? []);
const { copy, copied } = useAppClipboard();

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

const openMerchantHistory = (merchant, tab = 'invoices') => {
    const merchantKey = String(merchant.id);

    router.visit(route(route().current(), route().params), {
        data: {
            tab,
            currentFilters: {
                invoices: {
                    invoiceTypes: 'all',
                    merchants: merchantKey,
                },
                transactions: {
                    merchants: merchantKey,
                },
            },
        },
        preserveScroll: true,
    });
};

const openMerchantWithdrawal = (merchant) => {
    useModalStore().open('withdrawal', {
        user,
        balanceType: 'merchant',
        merchant,
    });
};

const openMerchantDeposit = (merchant) => {
    useModalStore().open('deposit', {
        user,
        balanceType: 'merchant',
        merchant,
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
                <div class="w-28">
                    <Select
                        v-model="fiatCurrencyForm.fiat_currency"
                        :items="availableFiatCurrencies"
                        value="code"
                        name="label"
                        :required="true"
                        size="sm"
                        currency-icons
                        :disabled="fiatCurrencyForm.processing"
                        @change="updateFiatCurrency"
                    />
                </div>
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

        <div
            v-if="viewStore.isAdminViewMode"
            class="collapse collapse-arrow rounded-box border border-base-300 bg-base-100"
        >
            <input type="checkbox" />
            <div class="collapse-title flex items-center justify-between gap-3 text-base font-medium">
                <span>Адреса вывода</span>
                <span class="badge badge-outline">USDT TRC20</span>
            </div>
            <div class="collapse-content">
                <div v-if="withdrawalAddresses.length" class="space-y-2 pt-1">
                    <div
                        v-for="address in withdrawalAddresses"
                        :key="address.id"
                        class="rounded-box border border-base-300 bg-base-200/40 p-3"
                    >
                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate font-medium">
                                    {{ address.name || 'Без названия' }}
                                </div>
                                <div class="truncate font-mono text-xs text-base-content/70">
                                    {{ address.masked_address }}
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs shrink-0"
                                @click="copy(address.address)"
                            >
                                {{ copied ? 'Скопировано' : 'Копировать' }}
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <div class="rounded bg-base-100 p-2">
                                <div class="text-xs text-base-content/60">Выводов</div>
                                <div class="font-medium">{{ address.withdrawals_count ?? 0 }}</div>
                            </div>
                            <div class="rounded bg-base-100 p-2">
                                <div class="text-xs text-base-content/60">Сумма</div>
                                <div class="font-medium">{{ address.withdrawals_amount ?? '0' }} USDT</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-sm text-base-content/60">
                    У пользователя пока нет сохранённых адресов вывода.
                </div>
            </div>
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

        <div
            v-if="merchantWalletMode"
            class="card bg-base-100 border border-base-300/60 shadow-sm rounded-2xl"
        >
            <div class="card-body gap-4 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Кошельки магазинов</h3>
                        <p class="text-sm text-base-content/60">Вывод и админские движения доступны только с конкретного магазина.</p>
                    </div>
                    <span class="badge badge-outline">USDT</span>
                </div>

                <div v-if="merchantWallets.length" class="grid gap-3">
                    <div
                        v-for="merchant in merchantWallets"
                        :key="merchant.id"
                        class="rounded-box border border-base-300 bg-base-200/40 p-3"
                    >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate font-medium">{{ merchant.name }}</div>
                                    <span v-if="merchant.wallet_missing" class="badge badge-error badge-sm">Нет кошелька</span>
                                </div>
                                <div class="mt-1 font-mono text-xs text-base-content/60">
                                    {{ merchant.uuid }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                                <div class="rounded bg-base-100 px-3 py-2 text-right">
                                    <div class="text-xs text-base-content/60">Баланс</div>
                                    <div class="font-semibold">{{ merchant.balance }} {{ merchant.currency }}</div>
                                </div>
                                <div class="rounded bg-base-100 px-3 py-2 text-right">
                                    <div class="text-xs text-base-content/60">В выводе</div>
                                    <div class="font-semibold">{{ merchant.locked_for_withdrawal }} {{ merchant.currency }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-base-300/70 pt-3">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                @click="openMerchantHistory(merchant, 'invoices')"
                            >
                                История
                            </button>
                            <button
                                v-if="viewStore.isAdminViewMode"
                                type="button"
                                class="btn btn-outline btn-sm"
                                :disabled="merchant.wallet_missing"
                                @click="openMerchantDeposit(merchant)"
                            >
                                Пополнить
                            </button>
                            <button
                                type="button"
                                class="btn btn-error btn-sm"
                                :disabled="merchant.wallet_missing"
                                @click="openMerchantWithdrawal(merchant)"
                            >
                                Вывести
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="text-sm text-base-content/60">
                    У пользователя пока нет магазинов с кошельками.
                </div>
            </div>
        </div>

        <OperationsHistory/>

        <ConfirmModal />
    </div>
</template>

