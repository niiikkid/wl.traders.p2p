<script setup>
import { useModalStore } from '@/store/modal.js';
import { router, usePage } from '@inertiajs/vue3';
import { useViewStore } from '@/store/view.js';
import { computed, ref } from 'vue';
import BalanceCard from '@/Pages/Wallet/Partials/BalanceCard.vue';

const props = defineProps({
    teamLeaderInsurance: {
        type: Object,
        required: true,
    },
});

const viewStore = useViewStore();
const modalStore = useModalStore();

const walletStats = ref(usePage().props.walletStats);
const user = usePage().props.user;
const primaryCurrency = walletStats.value.currency.primary.toUpperCase();

router.on('success', () => {
    walletStats.value = usePage().props.walletStats;
});

const reserveAmount = computed(() => walletStats.value.base.trustReserveAmount);

const requiredReserve = computed(() => props.teamLeaderInsurance.reserve_balance_limit);

const stopThreshold = computed(() => props.teamLeaderInsurance.reserve_stop_threshold);

const openLeaderReserveDepositModal = () => {
    modalStore.open('leaderReserveDeposit');
};
</script>

<template>
    <BalanceCard
        title="Общий страховой резерв"
        accent="warning"
        :amount="reserveAmount"
        :currency="primaryCurrency"
    >
        <template #icon>
            <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
        </template>

        <template v-if="teamLeaderInsurance.reserve_at_stop_threshold" #subtitle>
            <span class="badge badge-warning badge-xs mt-0.5">Выдача сделок остановлена</span>
        </template>

        <template #actions>
            <button
                v-if="viewStore.isAdminViewMode"
                type="button"
                class="btn btn-sm btn-square btn-ghost text-error"
                title="Вывести"
                @click.prevent="modalStore.open('withdrawal', { user, balanceType: 'reserve' })"
            >
                <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <span class="sr-only">Вывести</span>
            </button>
            <button
                v-else-if="viewStore.isTeamLeaderViewMode"
                type="button"
                class="btn btn-sm btn-square btn-ghost text-primary"
                title="Пополнить резерв"
                @click.prevent="openLeaderReserveDepositModal"
            >
                <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span class="sr-only">Пополнить резерв</span>
            </button>
            <button
                v-if="viewStore.isAdminViewMode"
                type="button"
                class="btn btn-sm btn-square btn-ghost text-primary ml-1.5"
                title="Пополнить"
                @click.prevent="modalStore.open('deposit', { user, balanceType: 'reserve' })"
            >
                <svg class="size-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span class="sr-only">Пополнить</span>
            </button>
        </template>

        <template #meta>
            <div class="space-y-1 text-sm">
                <div v-if="requiredReserve !== null" class="inline-flex items-center gap-1.5 mr-4">
                    <span class="text-base-content/50">Требуемый резерв</span>
                    <span class="font-medium">{{ requiredReserve }} {{ primaryCurrency }}</span>
                </div>
                <div v-if="stopThreshold !== null" class="inline-flex items-center gap-1.5">
                    <span class="text-base-content/50">Порог остановки</span>
                    <span class="font-medium">{{ stopThreshold }} {{ primaryCurrency }}</span>
                </div>
                <div v-if="teamLeaderInsurance.trader_limit !== null" class="inline-flex items-center gap-1.5">
                    <span class="text-base-content/50">Трейдеров</span>
                    <span class="font-medium">
                        {{ teamLeaderInsurance.connected_trader_count }} / {{ teamLeaderInsurance.trader_limit }}
                        <template v-if="teamLeaderInsurance.remaining_trader_slots !== null">
                            (свободно: {{ teamLeaderInsurance.remaining_trader_slots }})
                        </template>
                    </span>
                </div>
            </div>
        </template>

        <div class="alert alert-info text-sm py-2">
            <span>
                Резервный баланс — общий страховой депозит подключённых трейдеров. Пополнить можно только резерв; вывод выполняет администратор по запросу.
            </span>
        </div>
    </BalanceCard>
</template>
