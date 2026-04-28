<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue';
import { useHasActiveTableFilters } from '@/composables/useHasActiveTableFilters.js';

const props = defineProps({
    deals: {
        type: [Object, null],
        default: () => ({ data: [] }),
    },
});

const hasActiveFilters = useHasActiveTableFilters();

/** Кнопка и панель фильтров — только когда есть сделки или уже заданы фильтры (чтобы можно было сбросить). */
const showDealFilters = computed(() => {
    const total = Number(props.deals?.meta?.total ?? 0);

    return total > 0 || hasActiveFilters.value;
});

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Сделки" />

        <MainTableSection
            title="Сделки"
            :data="deals ?? { data: [] }"
        >
            <template #table-filters>
                <FiltersPanel v-if="showDealFilters" name="provider-liquidity-deals">
                    <InputFilter
                        name="uuid"
                        placeholder="UUID сделки"
                    />
                    <InputFilter
                        name="externalID"
                        placeholder="External ID"
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
                                <th scope="col" class="pl-4">UUID</th>
                                <th scope="col">Внешний ID</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Статус</th>
                                <th scope="col" class="text-right pr-3 sm:pr-4">Создана</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="deal in (deals?.data ?? [])"
                                :key="deal.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                            >
                                <th scope="row" class="font-medium whitespace-nowrap pl-4">
                                    <CopyableOrderUid :uuid="deal.uuid ?? ''" />
                                </th>
                                <td class="text-nowrap text-base-content">
                                    <CopyableOrderUid :uuid="deal.external_id ?? ''" />
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">
                                        {{ deal.amount }} <span class="text-primary/70">{{ (deal.currency ?? '').toUpperCase() }}</span>
                                    </div>
                                    <div class="text-nowrap text-xs">
                                        <span class="text-base-content/50">{{ deal.usdt_amount ?? '—' }}</span>
                                        <span class="text-primary/50"> USDT</span>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <OrderStatus
                                        inline
                                        :status="deal.status"
                                        :status_name="deal.status_name"
                                        :sub_status_name="deal.sub_status_name"
                                    />
                                </td>
                                <td class="text-right align-middle pr-3 sm:pr-4">
                                    <div class="flex justify-end">
                                        <DateTime
                                            :data="deal.created_at"
                                        />
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
