<script setup>
import {useModalStore} from "@/store/modal.js";
import {router, usePage} from "@inertiajs/vue3";
import {useViewStore} from "@/store/view.js";
import {computed, ref} from "vue";

const props = defineProps({
    traderBalanceTransfer: {
        type: Object,
        default: null,
    },
    teamLeaderInsurance: {
        type: Object,
        default: null,
    },
});

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
};

const showTransferButton = computed(() => (
    !viewStore.isAdminViewMode
    && props.traderBalanceTransfer?.available === true
));

const usesTeamLeaderSharedReserve = computed(() => (
    props.teamLeaderInsurance?.uses_shared_reserve === true
    && props.teamLeaderInsurance?.role === 'trader'
));

const openTraderBalanceTransferModal = () => {
    modalStore.openTraderBalanceTransferModal({});
};

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
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-if="showTransferButton"
                                    type="button"
                                    class="btn btn-outline btn-info btn-sm btn-square shrink-0"
                                    title="Перевести средства"
                                    @click.prevent="openTraderBalanceTransferModal"
                                >
                                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path d="M13.3085 0.293087C13.699 -0.0976958 14.3322 -0.0976956 14.7227 0.293087L17.7186 3.29095C18.1091 3.68175 18.1091 4.31536 17.7185 4.70613L14.716 7.71034C14.3255 8.10113 13.6923 8.10113 13.3018 7.71034C12.9113 7.31956 12.9113 6.68598 13.3018 6.2952L14.6087 4.98743L7 4.98743C6.44771 4.98743 6 4.53942 6 3.98677C6 3.43412 6.44771 2.98611 7 2.98611L14.5855 2.9861L13.3085 1.70824C12.918 1.31745 12.918 0.683869 13.3085 0.293087Z" fill="currentColor"/>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 20.998C14.2091 20.998 16 19.206 16 16.9954C16 14.7848 14.2091 12.9927 12 12.9927C9.79086 12.9927 8 14.7848 8 16.9954C8 19.206 9.79086 20.998 12 20.998ZM12 19.0934C10.842 19.0934 9.90331 18.1541 9.90331 16.9954C9.90331 15.8366 10.842 14.8973 12 14.8973C13.158 14.8973 14.0967 15.8366 14.0967 16.9954C14.0967 18.1541 13.158 19.0934 12 19.0934Z" fill="currentColor"/>
                                        <path d="M7 16.9954C7 17.548 6.55229 17.996 6 17.996C5.44772 17.996 5 17.548 5 16.9954C5 16.4427 5.44772 15.9947 6 15.9947C6.55229 15.9947 7 16.4427 7 16.9954Z" fill="currentColor"/>
                                        <path d="M19 16.9954C19 17.548 18.5523 17.996 18 17.996C17.4477 17.996 17 17.548 17 16.9954C17 16.4427 17.4477 15.9947 18 15.9947C18.5523 15.9947 19 16.4427 19 16.9954Z" fill="currentColor"/>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M21 9.99074C22.6569 9.99074 24 11.3348 24 12.9927V20.998C24 22.656 22.6569 24 21 24H3C1.34315 24 0 22.656 0 20.998V12.9927C0 11.3348 1.34315 9.99074 3 9.99074H21ZM4 11.9921H20C20 12.2549 20.0517 12.5151 20.1522 12.7579C20.2528 13.0007 20.4001 13.2214 20.5858 13.4072C20.7715 13.593 20.992 13.7405 21.2346 13.841C21.4773 13.9416 21.7374 13.9934 22 13.9934V19.9974C21.7374 19.9974 21.4773 20.0491 21.2346 20.1497C20.992 20.2503 20.7715 20.3977 20.5858 20.5835C20.4001 20.7694 20.2528 20.99 20.1522 21.2328C20.0517 21.4756 20 21.7359 20 21.9987H4C4 21.7359 3.94827 21.4756 3.84776 21.2328C3.74725 20.99 3.59993 20.7694 3.41421 20.5835C3.2285 20.3977 3.00802 20.2503 2.76537 20.1497C2.52272 20.0491 2.26264 19.9974 2 19.9974V13.9934C2.26264 13.9934 2.52272 13.9416 2.76537 13.841C3.00802 13.7405 3.2285 13.593 3.41421 13.4072C3.59993 13.2214 3.74725 13.0007 3.84776 12.7579C3.94827 12.5151 4 12.2549 4 11.9921Z" fill="currentColor"/>
                                    </svg>
                                    <span class="sr-only">Перевести средства</span>
                                </button>
                                <button
                                    @click.prevent="modalStore.openWithdrawalModal({user}); setBalanceType('trust')"
                                    type="button"
                                    class="btn btn-outline btn-error btn-sm btn-square shrink-0"
                                    title="Вывести"
                                >
                                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="sr-only">Вывести</span>
                                </button>
                                <button
                                    @click.prevent="openTraderDepositModal"
                                    type="button"
                                    class="btn btn-outline btn-primary btn-sm btn-square shrink-0"
                                    title="Пополнить"
                                >
                                    <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                    </svg>
                                    <span class="sr-only">Пополнить</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div
                        v-if="usesTeamLeaderSharedReserve"
                        class="alert alert-info mt-2 text-sm"
                    >
                        <template v-if="viewStore.isAdminViewMode">
                            Трейдер работает через общий страховой резерв Team Leader
                            <template v-if="teamLeaderInsurance?.team_leader_email">
                                ({{ teamLeaderInsurance.team_leader_email }})
                            </template>.
                            Пополнения зачисляются на траст-баланс; личный резерв трейдера не используется.
                        </template>
                        <template v-else>
                            Вы работаете через общий страховой резерв Team Leader. Пополнения зачисляются на основной баланс, резервный баланс трейдера не используется.
                        </template>
                    </div>

                    <div class="pt-1 block sm:flex items-center sm:space-y-0 align-middle">
                        <span class="text-xl font-bold">{{ walletStats.base.trustAmount }} {{ primaryCurrency }}</span>
                        <span
                            v-if="!usesTeamLeaderSharedReserve"
                            class="sm:ml-3 mt-2 sm:mt-0 badge badge-neutral gap-1"
                        >
                            <svg class="md:w-4 md:h-4 w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                             </svg>
                            {{ walletStats.maxReserveBalance }} {{ primaryCurrency }}
                        </span>
                    </div>
                    <div class="grid sm:block space-y-2 sm:space-y-0 mt-1">
                        <div v-if="!usesTeamLeaderSharedReserve" class="inline-flex">
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
