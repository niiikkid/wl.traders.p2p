<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue';

defineProps({
    deals: {
        type: [Object, null],
        default: () => ({ data: [] }),
    },
});

const formatCurrency = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return 'Пусто';
    }

    return `${amount} ${currency ?? ''}`.trim();
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Сделки провайдера" />

        <MainTableSection
            title="Сделки провайдера ликвидности"
            :data="deals ?? { data: [] }"
        >
            <template #table-filters>
                <FiltersPanel name="provider-liquidity-deals">
                    <InputFilter
                        name="uuid"
                        placeholder="UUID сделки"
                    />
                    <InputFilter
                        name="externalID"
                        placeholder="External ID"
                    />
                    <InputFilter
                        name="clientId"
                        placeholder="ID клиента мерчанта"
                    />
                    <InputFilter
                        name="amount"
                        placeholder="Сумма"
                    />
                    <DateFilter
                        name="startDate"
                        placeholder="Дата от"
                    />
                    <DateFilter
                        name="endDate"
                        placeholder="Дата до"
                    />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th scope="col">UUID</th>
                                <th scope="col">External ID</th>
                                <th scope="col">Клиент</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Залог</th>
                                <th scope="col">Создана</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="deal in (deals?.data ?? [])"
                                :key="deal.id"
                                class="hover"
                            >
                                <th scope="row" class="font-medium whitespace-nowrap">
                                    <CopyableOrderUid :uuid="deal.uuid ?? ''" />
                                </th>
                                <td class="text-nowrap">
                                    <CopyableOrderUid :uuid="deal.external_id ?? ''" />
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">
                                        {{ deal.merchant?.name ?? 'Пусто' }}
                                    </div>
                                    <div class="text-nowrap text-xs text-base-content/60">
                                        <span class="mr-1">Client:</span>
                                        <CopyableOrderUid :uuid="deal.merchant_client?.external_id ?? ''" />
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap">
                                        {{ formatCurrency(deal.amount, deal.currency) }}
                                    </div>
                                    <div class="text-nowrap text-xs text-base-content/60">
                                        Profit: {{ formatCurrency(deal.service_profit, deal.base_currency) }}
                                    </div>
                                </td>
                                <td>
                                    <OrderStatus
                                        :status="deal.status"
                                        :status_name="deal.status_name"
                                    />
                                    <div
                                        v-if="deal.sub_status"
                                        class="text-nowrap text-xs text-base-content/60"
                                    >
                                        {{ deal.sub_status_name ?? deal.sub_status }}
                                    </div>
                                </td>
                                <td>
                                    {{ formatCurrency(deal.collateral_holds?.[0]?.amount, deal.collateral_holds?.[0]?.currency ?? deal.base_currency) }}
                                </td>
                                <td>
                                    <DateTime
                                        class="justify-start"
                                        :data="deal.created_at"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
