<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';

const paymentDetailBankStats = ref(usePage().props.paymentDetailBankStats);

router.on('success', () => {
    paymentDetailBankStats.value = usePage().props.paymentDetailBankStats;
});

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
                                    <span class="badge badge-xs badge-primary badge-outline">
                                        {{ bankStat.payment_details_count }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-right text-xs font-medium text-nowrap">
                                    {{ bankStat.successful_orders_total_turnover_usdt }} USDT
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
