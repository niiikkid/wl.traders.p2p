<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {ref} from "vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const walletStats = ref(usePage().props.walletStats);
const user = usePage().props.user;
const primaryCurrency = walletStats.value.currency.primary.toUpperCase();

const emit = defineEmits(['setBalanceType']);

router.on('success', (event) => {
    walletStats.value = usePage().props.walletStats;
})

const setBalanceType = (type) => {
    emit('setBalanceType', type);
};

const custom = getRandomInt(9999999999999999);

const openTraderDepositModal = () => {
    modalStore.openTraderDepositModal({});
}

function getRandomInt(max) {
    return Math.floor(Math.random() * max);
}
</script>

<template>
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex justify-between">
                        <h3 class="card-title">Траст баланс</h3>
                        <template v-if="viewStore.isAdminViewMode">
                            <div class="join">
                                <button
                                    @click.prevent="modalStore.openWithdrawalModal({user}); setBalanceType('trust')"
                                    type="button"
                                    class="btn btn-outline btn-error join-item btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Вывести</span>
                                </button>
                                <button
                                    @click.prevent="modalStore.openDepositModal({user}); setBalanceType('trust')"
                                    type="button"
                                    class="btn btn-outline btn-primary join-item btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Пополнить</span>
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="join">
                                <button
                                    @click.prevent="modalStore.openWithdrawalModal({user}); setBalanceType('trust')"
                                    type="button"
                                    class="btn btn-outline btn-error join-item btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Вывести</span>
                                </button>
                                <button
                                    @click.prevent="openTraderDepositModal"
                                    type="button"
                                    class="btn btn-outline btn-primary join-item btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Пополнить</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="pt-1 block sm:flex items-center sm:space-y-0 align-middle">
                        <span class="text-xl font-bold">{{ walletStats.base.trustAmount }} {{ primaryCurrency }}</span>
                        <span class="sm:ml-3 mt-2 sm:mt-0 badge badge-neutral gap-1">
                            <svg class="md:w-4 md:h-4 w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                             </svg>
                            {{ walletStats.maxReserveBalance }} {{ primaryCurrency }}
                        </span>
                    </div>
                    <div class="grid sm:block space-y-2 sm:space-y-0 mt-1">
                        <div class="inline-flex">
                            <div class="text-sm opacity-70">
                                <span>Резерв</span>
                            </div>
                            <div class="text-sm ml-1.5">
                                {{ walletStats.base.trustReserveAmount }} {{ primaryCurrency }}
                            </div>
                        </div>
                        <div class="inline-flex sm:ml-3">
                            <div class="text-sm opacity-70">
                                <span>Вывод</span>
                            </div>
                            <div class="text-sm ml-1.5">
                                {{ walletStats.lockedForWithdrawalBalances.trust.primary }} {{ primaryCurrency }}
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
