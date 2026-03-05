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
</script>

<template>
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex justify-between">
                        <h3 class="card-title">Баланс тимлидера</h3>
                        <template v-if="viewStore.isAdminViewMode">
                            <div class="join">
                                <button
                                    @click.prevent="modalStore.openWithdrawalModal({user}); setBalanceType('teamleader')"
                                    type="button"
                                    class="btn btn-outline btn-error join-item btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Вывести</span>
                                </button>
                                <button
                                    @click.prevent="modalStore.openDepositModal({user}); setBalanceType('teamleader')"
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
                        <template v-else-if="viewStore.isTeamLeaderViewMode">
                            <div>
                                <button
                                    @click.prevent="modalStore.openWithdrawalModal({user}); setBalanceType('teamleader')"
                                    type="button"
                                    class="btn btn-outline btn-error btn-sm"
                                >
                                    <svg class="w-4 h-4 md:mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="md:block hidden">Вывести</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="pt-1 inline-block align-middle">
                        <span class="text-xl font-bold">
                            {{ walletStats.totalAvailableBalances.teamleader.primary }} {{ primaryCurrency }}
                        </span>
                    </div>

                    <div class="mt-0">
                        <div class="inline-flex">
                            <div class="text-sm opacity-70">
                                Вывод
                            </div>
                            <div class="text-sm ml-1.5">
                                {{ walletStats.lockedForWithdrawalBalances.teamleader.primary }} {{ primaryCurrency }}
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
