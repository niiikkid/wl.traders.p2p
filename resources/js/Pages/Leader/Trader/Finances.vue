<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import OperationsHistory from "@/Pages/Wallet/Partials/OperationsHistory.vue";
import BalanceCard from "@/Pages/Wallet/Partials/BalanceCard.vue";
import TraderCardHeader from "@/Components/Leader/TraderCardHeader.vue";

const trader = ref(usePage().props.trader);
const walletStats = ref(usePage().props.walletStats);

const sectionData = computed(() => (walletStats.value ? [walletStats.value] : []));

router.on("success", () => {
    trader.value = usePage().props.trader;
    walletStats.value = usePage().props.walletStats;
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`${trader.email} — Финансы`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="sectionData"
            :paginate="false"
            :display-pagination="false"
        >
            <template #header>
                <TraderCardHeader :trader="trader" current="finances" />
            </template>

            <template #body>
                <div class="max-w-5xl space-y-5">
                    <div class="grid sm:grid-cols-2 grid-cols-1 gap-4">
                        <BalanceCard
                            title="Траст баланс"
                            accent="primary"
                            :amount="walletStats.base.trustAmount"
                            :currency="walletStats.currency.primary.toUpperCase()"
                        >
                            <template #icon>
                                <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
                                </svg>
                            </template>
                            <template #badge>
                                <span class="badge badge-neutral badge-sm gap-1">
                                    <svg class="size-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z" />
                                    </svg>
                                    {{ walletStats.maxReserveBalance }} {{ walletStats.currency.primary.toUpperCase() }}
                                </span>
                            </template>
                            <template #meta>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="text-base-content/50">Резерв</span>
                                        <span class="font-medium">{{ walletStats.base.trustReserveAmount }} {{ walletStats.currency.primary.toUpperCase() }}</span>
                                    </div>
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="text-base-content/50">Вывод</span>
                                        <span class="font-medium">{{ walletStats.lockedForWithdrawalBalances.trust.primary }} {{ walletStats.currency.primary.toUpperCase() }}</span>
                                    </div>
                                </div>
                            </template>
                        </BalanceCard>

                        <BalanceCard
                            title="Холд по сделкам"
                            accent="neutral"
                            :amount="walletStats.escrowBalances.orders.balance.primary"
                            :currency="walletStats.currency.primary.toUpperCase()"
                        >
                            <template #icon>
                                <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </template>
                            <template #badge>
                                <span class="badge badge-ghost badge-sm">{{ walletStats.escrowBalances.orders.count }} сделок</span>
                            </template>
                            <template #meta>
                                <div class="inline-flex items-center gap-1.5 text-sm">
                                    <span class="text-base-content/50">≈</span>
                                    <span class="font-medium">{{ walletStats.escrowBalances.orders.balance.secondary }} {{ walletStats.currency.secondary.toUpperCase() }}</span>
                                </div>
                            </template>
                        </BalanceCard>

                        <BalanceCard
                            title="Спорные сделки"
                            accent="info"
                            :amount="walletStats.escrowBalances.disputes.balance.primary"
                            :currency="walletStats.currency.primary.toUpperCase()"
                        >
                            <template #icon>
                                <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </template>
                            <template #badge>
                                <span class="badge badge-ghost badge-sm">{{ walletStats.escrowBalances.disputes.count }} споров</span>
                            </template>
                            <template #meta>
                                <div class="inline-flex items-center gap-1.5 text-sm">
                                    <span class="text-base-content/50">≈</span>
                                    <span class="font-medium">{{ walletStats.escrowBalances.disputes.balance.secondary }} {{ walletStats.currency.secondary.toUpperCase() }}</span>
                                </div>
                            </template>
                        </BalanceCard>
                    </div>

                    <OperationsHistory />
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
