<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Pertials/DropdownFilter.vue';
import ShowAction from '@/Components/Table/ShowAction.vue';
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
            <template v-slot:button>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.settings.index'), { preserveScroll: true })"
                    >
                        К настройкам
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.history.index'), { preserveScroll: true })"
                    >
                        История
                    </button>
                </div>
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
                    <div class="overflow-x-auto card bg-base-100 shadow">
                        <table class="table table-sm">
                            <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>Client ID</th>
                                <th>Мерчант</th>
                                <th>Сделки</th>
                                <th class="text-right">Создан</th>
                                <th class="text-right">
                                    <span class="sr-only">Действия</span>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <AntiFraudClientOrdersModal />
    </div>
</template>
