<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';

const paymentDetailBankStats = ref(usePage().props.paymentDetailBankStats);
const selectedPeriod = ref(usePage().props.period ?? 'all');
const periodOptions = ref(usePage().props.periodOptions ?? []);

router.on('success', () => {
    paymentDetailBankStats.value = usePage().props.paymentDetailBankStats;
    selectedPeriod.value = usePage().props.period ?? 'all';
    periodOptions.value = usePage().props.periodOptions ?? [];
});

const formatPercent = (value) => {
    return `${Number(value ?? 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
};

const applyPeriod = (period) => {
    if (selectedPeriod.value === period) {
        return;
    }

    router.get(route('admin.payment-details.statistics'), {
        period,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Статистика реквизитов" />

        <MainTableSection
            title="Статистика реквизитов"
            :data="paymentDetailBankStats"
        >
            <template v-slot:button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline btn-primary"
                    @click="router.visit(route('admin.payment-details.index'))"
                >
                    К реквизитам
                </button>
            </template>

            <template v-slot:body>
                <div class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="option in periodOptions"
                        :key="option.value"
                        type="button"
                        class="btn btn-xs"
                        :class="selectedPeriod === option.value ? 'btn-primary' : 'btn-outline btn-primary'"
                        @click="applyPeriod(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="overflow-x-auto card bg-base-100 shadow-sm">
                    <table class="table table-xs">
                        <thead class="text-[11px] uppercase bg-base-300">
                            <tr>
                                <th scope="col" class="px-3 py-2">
                                    Банк
                                </th>
                                <th scope="col" class="px-3 py-2 text-right">
                                    Реквизитов
                                </th>
                                <th scope="col" class="px-3 py-2 text-right">
                                    Объём USDT
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="bankStat in paymentDetailBankStats.data"
                                :key="bankStat.id"
                            >
                                <td class="px-3 py-1.5">
                                    <div class="flex gap-2 items-center">
                                        <GatewayLogo :img_path="bankStat.logo_path" class="w-7 h-7" />
                                        <div>
                                            <div class="text-nowrap text-xs">{{ bankStat.name }}</div>
                                            <div class="text-nowrap text-[11px] text-base-content/60">{{ bankStat.code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <div class="inline-flex flex-col items-end gap-0.5">
                                        <span class="badge badge-xs badge-primary badge-outline">
                                            {{ bankStat.payment_details_count }}
                                        </span>
                                        <span class="text-[11px] text-base-content/60">
                                            {{ formatPercent(bankStat.payment_details_percent) }} от общего
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-right text-xs font-medium text-nowrap">
                                    <div class="inline-flex flex-col items-end gap-0.5">
                                        <span>{{ bankStat.successful_orders_total_turnover_usdt }} USDT</span>
                                        <span class="text-[11px] text-base-content/60 font-normal">
                                            {{ formatPercent(bankStat.successful_orders_total_turnover_percent) }} от общего
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
