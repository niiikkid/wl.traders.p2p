<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {computed, ref} from "vue";
import BalanceCard from "@/Pages/Wallet/Partials/BalanceCard.vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const walletStats = ref(usePage().props.walletStats);
const user = usePage().props.user;
const primaryCurrency = walletStats.value.currency.primary.toUpperCase();

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
})

const teamLeaderUsesSharedReserve = computed(() => {
    const insurance = usePage().props.teamLeaderInsurance;

    if (insurance?.role === 'team_leader') {
        return insurance.uses_shared_reserve === true;
    }

    return usePage().props.auth.user?.team_leader_insurance_mode === 'team_leader_reserve';
});
</script>

<template>
    <BalanceCard
        title="Баланс тимлидера"
        accent="info"
        :amount="walletStats.totalAvailableBalances.teamleader.primary"
        :currency="primaryCurrency"
    >
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
            </svg>
        </template>

        <template #actions>
            <div v-if="viewStore.isAdminViewMode || viewStore.isTeamLeaderViewMode" class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-error"
                    title="Вывести"
                    @click.prevent="modalStore.open('withdrawal', { user, balanceType: 'teamleader' })"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span class="sr-only">Вывести</span>
                </button>
                <button
                    v-if="viewStore.isAdminViewMode"
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-primary"
                    title="Пополнить"
                    @click.prevent="modalStore.open('deposit', { user, balanceType: 'teamleader' })"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span class="sr-only">Пополнить</span>
                </button>
            </div>
        </template>

        <template v-if="!viewStore.isAdminViewMode && teamLeaderUsesSharedReserve" #subtitle>
            <p class="text-xs text-base-content/50 mt-0.5">Доход от подключённых трейдеров</p>
        </template>

        <template #meta>
            <div class="inline-flex items-center gap-1.5 text-sm">
                <span class="text-base-content/50">Вывод</span>
                <span class="font-medium">{{ walletStats.lockedForWithdrawalBalances.teamleader.primary }} {{ primaryCurrency }}</span>
            </div>
        </template>
    </BalanceCard>
</template>
