<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import BalanceCard from "@/Pages/Wallet/Partials/BalanceCard.vue";

const walletStats = ref(usePage().props.walletStats);
const disputeBalance = ref({
    primary: walletStats.value.escrowBalances.disputes.balance.primary,
    secondary: walletStats.value.escrowBalances.disputes.balance.secondary,
    count: walletStats.value.escrowBalances.disputes.count,
});
const currency = ref({
    primary: walletStats.value.currency.primary.toUpperCase(),
    secondary: walletStats.value.currency.secondary.toUpperCase(),
});

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
    disputeBalance.value = {
        primary: walletStats.value.escrowBalances.disputes.balance.primary,
        secondary: walletStats.value.escrowBalances.disputes.balance.secondary,
        count: walletStats.value.escrowBalances.disputes.count,
    };
    currency.value = {
        primary: walletStats.value.currency.primary.toUpperCase(),
        secondary: walletStats.value.currency.secondary.toUpperCase(),
    };
})
</script>

<template>
    <BalanceCard title="Спорные сделки" accent="info" :amount="disputeBalance.primary" :currency="currency.primary">
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </template>

        <template #badge>
            <span class="badge badge-ghost badge-sm">{{ disputeBalance.count }} споров</span>
        </template>

        <template #meta>
            <div class="inline-flex items-center gap-1.5 text-sm">
                <span class="text-base-content/50">≈</span>
                <span class="font-medium">{{ disputeBalance.secondary }} {{ currency.secondary }}</span>
            </div>
        </template>
    </BalanceCard>
</template>
