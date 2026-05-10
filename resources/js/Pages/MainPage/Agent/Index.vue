<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {computed} from 'vue';

const statistics = usePage().props.statistics;
const merchants = usePage().props.merchants ?? [];

const formatNumber = (value) => {
    const normalized = Number(String(value ?? 0).replace(/[,\s]/g, ''));

    return normalized.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formattedStatistics = computed(() => ({
    merchantsCount: statistics.merchantsCount ?? 0,
    totalTurnover: formatNumber(statistics.totalTurnover),
    totalProfit: formatNumber(statistics.totalProfit),
    balance: formatNumber(statistics.balance),
    agentRate: statistics.agentRate ?? 0,
}));

const openFinances = () => {
    router.visit(route('agent.finances.index'), {preserveScroll: true});
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Кабинет агента"/>

        <div class="mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Кабинет агента</h2>
                    <p class="mt-1 text-sm text-base-content/70">
                        Комиссия агента: {{ formattedStatistics.agentRate }}% с оборота привязанных мерчантов.
                    </p>
                </div>
                <button type="button" class="btn btn-primary btn-sm sm:btn-md" @click="openFinances">
                    Финансы
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="stats bg-base-100 shadow">
                    <div class="stat">
                        <div class="stat-title">Мерчанты</div>
                        <div class="stat-value text-primary">{{ formattedStatistics.merchantsCount }}</div>
                    </div>
                </div>
                <div class="stats bg-base-100 shadow">
                    <div class="stat">
                        <div class="stat-title">Оборот</div>
                        <div class="stat-value text-lg sm:text-2xl">${{ formattedStatistics.totalTurnover }}</div>
                    </div>
                </div>
                <div class="stats bg-base-100 shadow">
                    <div class="stat">
                        <div class="stat-title">Прибыль агента</div>
                        <div class="stat-value text-success text-lg sm:text-2xl">${{ formattedStatistics.totalProfit }}</div>
                    </div>
                </div>
                <div class="stats bg-base-100 shadow">
                    <div class="stat">
                        <div class="stat-title">Баланс</div>
                        <div class="stat-value text-info text-lg sm:text-2xl">${{ formattedStatistics.balance }}</div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="card-title">Мерчанты агента</h3>
                        <button type="button" class="btn btn-outline btn-primary btn-sm" @click="openFinances">
                            Вывести
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Название мерчанта</th>
                                    <th class="text-right">Оборот</th>
                                    <th class="text-right">Прибыль агента</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="merchant in merchants" :key="merchant.id">
                                    <td>{{ merchant.name || `#${merchant.id}` }}</td>
                                    <td class="text-right">{{ formatNumber(merchant.turnover) }} USDT</td>
                                    <td class="text-right">{{ formatNumber(merchant.agent_profit) }} USDT</td>
                                </tr>
                                <tr v-if="!merchants.length">
                                    <td colspan="3" class="py-8 text-center text-base-content/60">
                                        К агенту пока не привязаны мерчанты.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
