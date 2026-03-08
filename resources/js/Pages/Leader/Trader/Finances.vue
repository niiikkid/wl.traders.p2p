<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OperationsHistory from "@/Pages/Wallet/Partials/OperationsHistory.vue";

const trader = ref(usePage().props.trader);
const walletStats = ref(usePage().props.walletStats);

router.on("success", () => {
    trader.value = usePage().props.trader;
    walletStats.value = usePage().props.walletStats;
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`Трейдер #${trader.id} - Финансы`" />

        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="breadcrumbs text-sm">
                    <ul>
                        <li>
                            <button class="link link-hover" @click="router.visit(route('leader.traders.index'))">Трейдеры</button>
                        </li>
                        <li>{{ trader.email }}</li>
                    </ul>
                </div>

                <ul class="flex flex-wrap text-sm font-medium text-center">
                    <li class="me-2">
                        <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.payment-details.index', {trader: trader.id}))">Реквизиты</button>
                    </li>
                    <li class="me-2">
                        <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.orders.index', {trader: trader.id}))">Сделки</button>
                    </li>
                    <li class="me-2">
                        <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.disputes.index', {trader: trader.id}))">Споры</button>
                    </li>
                    <li class="me-2">
                        <button class="btn btn-sm btn-primary">Финансы</button>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Финансы трейдера</h2>
            </div>

            <div class="grid xl:grid-cols-2 grid-cols-1 gap-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Траст баланс</h3>
                        <div class="pt-1">
                            <span class="text-xl font-bold">
                                {{ walletStats.base.trustAmount }} {{ walletStats.currency.primary.toUpperCase() }}
                            </span>
                            <span class="sm:ml-3 mt-2 sm:mt-0 badge badge-neutral gap-1">
                                {{ walletStats.maxReserveBalance }} {{ walletStats.currency.primary.toUpperCase() }}
                            </span>
                        </div>
                        <div class="grid sm:block space-y-2 sm:space-y-0 mt-1">
                            <div class="inline-flex">
                                <div class="text-sm opacity-70">Резерв</div>
                                <div class="text-sm ml-1.5">
                                    {{ walletStats.base.trustReserveAmount }} {{ walletStats.currency.primary.toUpperCase() }}
                                </div>
                            </div>
                            <div class="inline-flex sm:ml-3">
                                <div class="text-sm opacity-70">Вывод</div>
                                <div class="text-sm ml-1.5">
                                    {{ walletStats.lockedForWithdrawalBalances.trust.primary }} {{ walletStats.currency.primary.toUpperCase() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Холд (проводится сделка)</h3>
                        <div class="pt-1">
                            <span class="text-xl font-bold">
                                {{ walletStats.escrowBalances.orders.balance.primary }} {{ walletStats.currency.primary.toUpperCase() }}
                            </span>
                        </div>
                        <div class="mt-0">
                            <div class="text-sm opacity-70">
                                {{ walletStats.escrowBalances.orders.balance.secondary }} {{ walletStats.currency.secondary.toUpperCase() }} —
                                Сделок — {{ walletStats.escrowBalances.orders.count }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Спорные сделки</h3>
                        <div class="pt-1">
                            <span class="text-xl font-bold">
                                {{ walletStats.escrowBalances.disputes.balance.primary }} {{ walletStats.currency.primary.toUpperCase() }}
                            </span>
                        </div>
                        <div class="mt-0">
                            <div class="text-sm opacity-70">
                                {{ walletStats.escrowBalances.disputes.balance.secondary }} {{ walletStats.currency.secondary.toUpperCase() }} —
                                Споров — {{ walletStats.escrowBalances.disputes.count }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <OperationsHistory />
        </div>
    </div>
</template>

