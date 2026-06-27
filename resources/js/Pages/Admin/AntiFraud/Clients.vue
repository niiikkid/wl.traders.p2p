<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AntiFraudNav from '@/Components/Admin/AntiFraudNav.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import ShowAction from '@/Components/Table/ShowAction.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import AntiFraudClientOrdersModal from '@/Modals/Admin/AntiFraudClientOrdersModal.vue';
import { useModalStore } from '@/store/modal.js';
import { computed } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const clients = computed(() => usePage().props.clients ?? { data: [] });
const modalStore = useModalStore();

const formatOrdersCount = (client) => {
    const success = client.success_orders_count ?? 0;
    const total = client.total_orders_count ?? 0;

    return `${success}/${total}`;
};

const openOrdersModal = (client) => {
    modalStore.openAntiFraudClientOrdersModal({ client });
};
</script>

<template>
    <div>
        <Head title="Клиенты антифрода" />

        <MainTableSection
            title="Клиенты антифрода"
            :data="clients"
        >
            <template v-slot:header>
                <AntiFraudNav current="clients" />
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="anti-fraud-clients">
                    <InputFilter
                        name="clientId"
                        placeholder="Client ID"
                    />
                    <InputFilter
                        name="orderUuid"
                        placeholder="UUID сделки"
                    />
                    <DropdownFilter
                        name="merchantIds"
                        title="Мерчант"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                                <th>Client ID</th>
                                <th>Мерчант</th>
                                <th>Сделки</th>
                                <th class="text-right">Создан</th>
                                <th class="text-right">
                                    <span class="sr-only">Действия</span>
                                </th>
                        </template>
                            <tr v-for="client in clients.data" :key="client.id">
                                <td class="whitespace-nowrap">
                                    {{ client.client_id || '—' }}
                                </td>
                                <td>
                                    {{ client.merchant?.name || client.merchant?.uuid || `#${client.merchant_id}` }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ formatOrdersCount(client) }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <DateTime class="justify-start" :data="client.created_at" />
                                </td>
                                <td class="text-right">
                                    <ShowAction @click.prevent="openOrdersModal(client)" />
                                </td>
                            </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="client in clients.data"
                            :key="client.id"
                        >
                            <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] text-base-content/50 uppercase">Client ID</div>
                                    <div class="font-medium text-xs text-base-content break-words">{{ client.client_id || '—' }}</div>
                                </div>
                                <div class="shrink-0 text-right leading-tight">
                                    <div class="text-[10px] text-base-content/50 uppercase">Создан</div>
                                    <DateTime
                                        :data="client.created_at"
                                        class="justify-end text-[11px]"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2 min-w-0 pt-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-base-content leading-snug break-words">
                                        {{ client.merchant?.name || client.merchant?.uuid || `#${client.merchant_id}` }}
                                    </div>
                                </div>
                                <ShowAction class="shrink-0" @click.prevent="openOrdersModal(client)" />
                            </div>

                            <div class="border-b border-base-content/10 my-2 mb-1"></div>

                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Сделки</div>
                                    <div class="font-medium text-xs text-base-content">{{ formatOrdersCount(client) }}</div>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <AntiFraudClientOrdersModal />
    </div>
</template>
