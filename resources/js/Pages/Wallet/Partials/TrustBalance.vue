<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {computed, ref} from "vue";
import BalanceCard from "@/Pages/Wallet/Partials/BalanceCard.vue";

const props = defineProps({
    traderBalanceTransfer: {
        type: Object,
        default: null,
    },
    teamLeaderInsurance: {
        type: Object,
        default: null,
    },
});

const viewStore = useViewStore();
const modalStore = useModalStore();

const walletStats = ref(usePage().props.walletStats);
const user = usePage().props.user;
const primaryCurrency = walletStats.value.currency.primary.toUpperCase();

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
})

const openTraderDepositModal = () => {
    modalStore.open('traderDeposit');
};

const showTransferButton = computed(() => (
    !viewStore.isAdminViewMode
    && props.traderBalanceTransfer?.available === true
));

const usesTeamLeaderSharedReserve = computed(() => (
    props.teamLeaderInsurance?.uses_shared_reserve === true
    && props.teamLeaderInsurance?.role === 'trader'
));

const openTraderBalanceTransferModal = () => {
    modalStore.open('traderBalanceTransfer');
};
</script>

<template>
    <BalanceCard title="Траст баланс" accent="primary" :amount="walletStats.base.trustAmount" :currency="primaryCurrency">
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
            </svg>
        </template>

        <template #actions>
            <div class="flex items-center gap-1.5">
                <button
                    v-if="showTransferButton"
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-info"
                    title="Перевести средства"
                    @click.prevent="openTraderBalanceTransferModal"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-9L21 3m0 0-4.5 4.5M21 3H7.5" />
                    </svg>
                    <span class="sr-only">Перевести средства</span>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-error"
                    title="Вывести"
                    @click.prevent="modalStore.open('withdrawal', { user, balanceType: 'trust' })"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span class="sr-only">Вывести</span>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-primary"
                    title="Пополнить"
                    @click.prevent="viewStore.isAdminViewMode ? modalStore.open('deposit', { user, balanceType: 'trust' }) : openTraderDepositModal()"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span class="sr-only">Пополнить</span>
                </button>
            </div>
        </template>

        <template v-if="!usesTeamLeaderSharedReserve" #badge>
            <span class="badge badge-neutral badge-sm gap-1">
                <svg class="size-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z" />
                </svg>
                {{ walletStats.maxReserveBalance }} {{ primaryCurrency }}
            </span>
        </template>

        <template #meta>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <div v-if="!usesTeamLeaderSharedReserve" class="inline-flex items-center gap-1.5">
                    <span class="text-base-content/50">Резерв</span>
                    <span class="font-medium">{{ walletStats.base.trustReserveAmount }} {{ primaryCurrency }}</span>
                </div>
                <div class="inline-flex items-center gap-1.5">
                    <span class="text-base-content/50">Вывод</span>
                    <span class="font-medium">{{ walletStats.lockedForWithdrawalBalances.trust.primary }} {{ primaryCurrency }}</span>
                </div>
            </div>
        </template>

        <div
            v-if="usesTeamLeaderSharedReserve"
            class="alert alert-info text-sm py-2"
        >
            <template v-if="viewStore.isAdminViewMode">
                Трейдер работает через общий страховой резерв Team Leader
                <template v-if="teamLeaderInsurance?.team_leader_email">
                    ({{ teamLeaderInsurance.team_leader_email }})
                </template>.
                Пополнения зачисляются на траст-баланс; личный резерв трейдера не используется.
            </template>
            <template v-else>
                Вы работаете через общий страховой резерв Team Leader. Пополнения зачисляются на основной баланс, резервный баланс трейдера не используется.
            </template>
        </div>
    </BalanceCard>
</template>
