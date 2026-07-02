<script setup>
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AntiFraudNav from '@/Components/Admin/AntiFraudNav.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';

defineOptions({ layout: AuthenticatedLayout });

const logs = usePage().props.logs;
</script>

<template>
    <div>
        <Head title="История антифрода" />

        <MainTableSection
            title="История антифрода"
            :data="logs"
        >
            <template v-slot:header>
                <AntiFraudNav current="history" />
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="anti-fraud-history">
                    <InputFilter
                        name="merchant"
                        placeholder="Мерчант (имя или uuid)"
                    />
                    <InputFilter
                        name="clientId"
                        placeholder="Client ID"
                    />
                </FiltersPanel>
            </template>

            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                                <th>Мерчант</th>
                                <th>Client ID</th>
                                <th>Решение</th>
                                <th>Сообщение</th>
                                <th class="text-right">Дата</th>
                        </template>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td>
                                    {{ log.merchant?.name || log.merchant?.uuid || `#${log.merchant_id}` }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ log.client_id || '—' }}
                                </td>
                                <td>
                                    <span v-if="log.decision === 'allow'" class="badge badge-success badge-sm">Разрешено</span>
                                    <span v-else class="badge badge-error badge-sm">Отклонено</span>
                                </td>
                                <td class="text-sm text-base-content/80">
                                    {{ log.message || '—' }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <DateTime :data="log.created_at" />
                                </td>
                            </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="log in logs.data"
                            :key="log.id"
                        >
                            <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] text-base-content/50 uppercase">Client ID</div>
                                    <div class="font-medium text-xs text-base-content break-words">{{ log.client_id || '—' }}</div>
                                </div>
                                <div class="shrink-0 text-right leading-tight">
                                    <div class="text-[10px] text-base-content/50 uppercase">Дата</div>
                                    <DateTime
                                        :data="log.created_at"
                                        class="justify-end text-[11px]"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2 min-w-0 pt-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-base-content leading-snug break-words">
                                        {{ log.merchant?.name || log.merchant?.uuid || `#${log.merchant_id}` }}
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <span v-if="log.decision === 'allow'" class="badge badge-success badge-sm">Разрешено</span>
                                    <span v-else class="badge badge-error badge-sm">Отклонено</span>
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2 mb-1"></div>

                            <div class="grid grid-cols-1 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Сообщение</div>
                                    <div class="font-medium text-xs text-base-content/80 break-words">{{ log.message || '—' }}</div>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
