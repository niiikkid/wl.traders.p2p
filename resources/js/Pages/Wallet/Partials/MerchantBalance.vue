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
const merchantWalletMode = computed(() => Boolean(usePage().props.merchantWalletMode));

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
})
</script>

<template>
    <BalanceCard
        title="Баланс мерчанта"
        accent="success"
        :amount="walletStats.totalAvailableBalances.merchant.primary"
        :currency="primaryCurrency"
    >
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
            </svg>
        </template>

        <template v-if="!merchantWalletMode" #actions>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="btn btn-sm btn-square btn-ghost text-error"
                    title="Вывести"
                    @click.prevent="modalStore.open('withdrawal', { user, balanceType: 'merchant' })"
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
                    @click.prevent="modalStore.open('deposit', { user, balanceType: 'merchant' })"
                >
                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span class="sr-only">Пополнить</span>
                </button>
            </div>
        </template>

        <template #meta>
            <div class="inline-flex items-center gap-1.5 text-sm">
                <span class="text-base-content/50">Вывод</span>
                <span class="font-medium">{{ walletStats.lockedForWithdrawalBalances.merchant.primary }} {{ primaryCurrency }}</span>
            </div>
        </template>
    </BalanceCard>
</template>
