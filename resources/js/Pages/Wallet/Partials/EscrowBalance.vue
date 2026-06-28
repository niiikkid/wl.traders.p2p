<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import BalanceCard from "@/Pages/Wallet/Partials/BalanceCard.vue";

const walletStats = ref(usePage().props.walletStats);
const escrowBalance = ref({
    primary: walletStats.value.escrowBalances.orders.balance.primary,
    secondary: walletStats.value.escrowBalances.orders.balance.secondary,
    count: walletStats.value.escrowBalances.orders.count,
});
const currency = ref({
    primary: walletStats.value.currency.primary.toUpperCase(),
    secondary: walletStats.value.currency.secondary.toUpperCase(),
});

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
    escrowBalance.value = {
        primary: walletStats.value.escrowBalances.orders.balance.primary,
        secondary: walletStats.value.escrowBalances.orders.balance.secondary,
        count: walletStats.value.escrowBalances.orders.count,
    };
    currency.value = {
        primary: walletStats.value.currency.primary.toUpperCase(),
        secondary: walletStats.value.currency.secondary.toUpperCase(),
    };
})
</script>

<template>
    <BalanceCard title="Холд по сделкам" accent="neutral" :amount="escrowBalance.primary" :currency="currency.primary">
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        </template>

        <template #badge>
            <span class="badge badge-ghost badge-sm">{{ escrowBalance.count }} сделок</span>
        </template>

        <template #meta>
            <div class="inline-flex items-center gap-1.5 text-sm">
                <span class="text-base-content/50">≈</span>
                <span class="font-medium">{{ escrowBalance.secondary }} {{ currency.secondary }}</span>
            </div>
        </template>
    </BalanceCard>
</template>
