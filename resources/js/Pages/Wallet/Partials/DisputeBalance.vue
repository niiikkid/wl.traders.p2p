<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {ref} from "vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const user = usePage().props.user;
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

router.on('success', (event) => {
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
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex justify-between items-center">
                        <h3 class="card-title">Спорные сделки</h3>
                        <div>
                            <svg class="md:w-5 md:h-5 w-4 h-4 text-info" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="pt-1">
                        <span class="text-xl font-bold">
                            {{ disputeBalance.primary }} {{ currency.primary }}
                        </span>
                    </div>

                    <div class="mt-0">
                        <div class="inline-flex">
                            <div class="text-sm opacity-70">
                                {{ disputeBalance.secondary }} {{ currency.secondary }} — Споров — {{ disputeBalance.count }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
