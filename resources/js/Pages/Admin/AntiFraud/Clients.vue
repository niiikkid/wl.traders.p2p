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
import { computed, ref, unref } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const clients = computed(() => usePage().props.clients ?? { data: [] });
const modalStore = useModalStore();

const clientsFiltersPanel = ref(null);

const toggleClientsFilters = () => {
    clientsFiltersPanel.value?.toggleFiltersDisplay();
};

const isClientsFiltersOpen = computed(() => {
    const panel = clientsFiltersPanel.value;

    return Boolean(panel && unref(panel.displayFilters));
});

const hasActiveClientsFilters = computed(() => {
    const panel = clientsFiltersPanel.value;

    return Boolean(panel && unref(panel.hasActiveFilters));
});

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
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline"
                        @click="router.visit(route('admin.anti-fraud.history.index'), { preserveScroll: true })"
                    >
                        История
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline"
                        @click="router.visit(route('admin.anti-fraud.settings.index'), { preserveScroll: true })"
                    >
                        Настройки
                    </button>
                </div>
            </template>
            <template v-slot:table-filters>
                <div class="flex justify-end mb-3">
                    <div class="inline-flex items-center justify-end gap-2 rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm">
                        <div class="relative inline-flex shrink-0">
                            <button
                                type="button"
                                class="btn btn-sm btn-square btn-primary btn-outline rounded-lg"
                                :class="{ 'btn-active': isClientsFiltersOpen }"
                                :title="isClientsFiltersOpen ? 'Скрыть фильтры' : 'Показать фильтры'"
                                aria-label="Показать или скрыть фильтры"
                                @click.prevent="toggleClientsFilters"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                </svg>
                            </button>
                            <span
                                v-if="hasActiveClientsFilters"
                                class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border border-base-100 bg-error"
                                aria-hidden="true"
                                title="Есть применённые фильтры"
                            />
                        </div>
                    </div>
                </div>

                <FiltersPanel ref="clientsFiltersPanel" name="anti-fraud-clients" :omit-default-toggle-button="true">
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
