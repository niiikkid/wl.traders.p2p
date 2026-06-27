<script setup>
import { useModalStore } from '@/store/modal.js';
import { router, usePage } from '@inertiajs/vue3';
import { useViewStore } from '@/store/view.js';
import { computed, ref } from 'vue';

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
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="card-title">Общий страховой резерв</h3>
                            <span
                                v-if="teamLeaderInsurance.reserve_at_stop_threshold"
                                class="badge badge-warning badge-sm"
                            >
                                Выдача сделок остановлена
                            </span>
                        </div>
                        <template v-if="viewStore.isAdminViewMode">
                            <div class="join">
                                <button
                                    type="button"
                                    class="btn btn-outline btn-error join-item btn-sm"
                                    @click.prevent="modalStore.open('withdrawal', { user, balanceType: 'reserve' })"
                                >
                                    <span class="md:block hidden">Вывести</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-primary join-item btn-sm"
                                    @click.prevent="modalStore.open('deposit', { user, balanceType: 'reserve' })"
                                >
                                    <span class="md:block hidden">Пополнить</span>
                                </button>
                            </div>
                        </template>
                        <template v-else-if="viewStore.isTeamLeaderViewMode">
                            <button
                                type="button"
                                class="btn btn-outline btn-primary btn-sm"
                                @click.prevent="openLeaderReserveDepositModal"
                            >
                                Пополнить резерв
                            </button>
                        </template>
                    </div>

                    <div class="pt-1">
                        <span class="text-xl font-bold">
                            {{ reserveAmount }} {{ primaryCurrency }}
                        </span>
                    </div>

                    <div class="mt-2 space-y-1 text-sm opacity-80">
                        <div v-if="requiredReserve !== null">
                            Требуемая сумма резерва: {{ requiredReserve }} {{ primaryCurrency }}
                        </div>
                        <div v-if="stopThreshold !== null">
                            Порог остановки выдачи: {{ stopThreshold }} {{ primaryCurrency }}
                        </div>
                        <div v-if="teamLeaderInsurance.trader_limit !== null">
                            Подключено трейдеров: {{ teamLeaderInsurance.connected_trader_count }} / {{ teamLeaderInsurance.trader_limit }}
                            <template v-if="teamLeaderInsurance.remaining_trader_slots !== null">
                                (осталось слотов: {{ teamLeaderInsurance.remaining_trader_slots }})
                            </template>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 text-sm">
                        <span>
                            Резервный баланс используется как общий страховой депозит подключённых трейдеров.
                            Вы можете пополнить только резервный баланс. Вывод резервного баланса выполняется администратором по вашему запросу.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
