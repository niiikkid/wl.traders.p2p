<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {ref} from "vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const user = usePage().props.user;
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

router.on('success', (event) => {
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
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex justify-between">
                        <h3 class="card-title">Холд <span class="md:inline-block hidden">(проводится сделка)</span></h3>
                    </div>

                    <div class="pt-1 inline-block align-middle">
                        <span class="text-xl font-bold">
                           {{ escrowBalance.primary }} {{ currency.primary }}
                        </span>
                    </div>

                    <div class="mt-0">
                        <div class="inline-flex">
                            <div class="text-sm opacity-70">
                                {{ escrowBalance.secondary }} {{ currency.secondary }} — Сделок — {{ escrowBalance.count }}
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
