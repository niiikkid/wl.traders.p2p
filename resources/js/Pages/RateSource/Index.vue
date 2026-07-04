<script setup>
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import RateSourceEditModal from '@/Modals/RateSource/RateSourceEditModal.vue';
import CurrencyPairDisplay from '@/Components/Currency/CurrencyPairDisplay.vue';
import { useModalStore } from '@/store/modal.js';
import { ref } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    sources: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
});

const modalStore = useModalStore();
const refreshingId = ref(null);

const TYPE_LABELS = { manual: 'Ручной', bybit: 'Bybit', binance: 'Binance' };
const STATUS_BADGE = {
    success: 'badge-success',
    empty: 'badge-warning',
    failed: 'badge-error',
};

const typeLabel = (value) => TYPE_LABELS[value] ?? value;

const openCreate = () => {
    modalStore.openRateSourceEditModal({ currencies: props.currencies });
};

const openEdit = (source) => {
    modalStore.openRateSourceEditModal({ source, currencies: props.currencies });
};

const refresh = (source) => {
    if (refreshingId.value) return;
    refreshingId.value = source.id;
    axios.post(route('admin.rate-sources.refresh', source.id), {}, { headers: { Accept: 'application/json' } })
        .finally(() => {
            refreshingId.value = null;
        });
};

const remove = (source) => {
    modalStore.openConfirmModal({
        title: 'Удалить источник курса?',
        body: 'Действие нельзя отменить. Мерчанты, привязанные к этому источнику, потеряют курс по нему.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            axios.delete(route('admin.rate-sources.destroy', source.id), { headers: { Accept: 'application/json' } })
                .then(() => router.reload({ only: ['sources'] }));
        },
    });
};
</script>

<template>
    <div>
        <Head title="Источники курсов" />

        <MainTableSection
            title="Источники курсов"
            :data="sources"
            :paginate="false"
        >
            <template #button>
                <button type="button" class="btn btn-sm btn-primary" @click="openCreate">
                    Создать источник
                </button>
            </template>

            <template #body>
                <div class="relative space-y-4">
                    <DataTable table-class="text-sm">
                        <template #head>
                            <th scope="col" class="px-4 py-3">Название</th>
                            <th scope="col" class="px-4 py-3">Пара</th>
                            <th scope="col" class="px-4 py-3">Тип</th>
                            <th scope="col" class="px-4 py-3">Курс</th>
                            <th scope="col" class="px-4 py-3">Статус</th>
                            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Действия</span></th>
                        </template>

                        <tr v-for="source in sources" :key="source.id">
                            <td class="px-4 py-3 font-medium">
                                {{ source.name || '—' }}
                                <span v-if="!source.is_active" class="badge badge-ghost badge-xs ml-1">выкл</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <CurrencyPairDisplay
                                    :base-currency="source.base_currency"
                                    :quote-currency="source.quote_currency"
                                    :pair="source.pair"
                                    size="sm"
                                />
                            </td>
                            <td class="px-4 py-3">{{ typeLabel(source.type) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span :class="!source.rate || source.rate === '0.00' ? 'text-error' : ''">
                                    {{ source.rate ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="source.last_parse_attempt?.status"
                                    class="badge badge-sm"
                                    :class="STATUS_BADGE[source.last_parse_attempt.status] ?? 'badge-ghost'"
                                >
                                    {{ source.last_parse_attempt.status }}
                                </span>
                                <span v-else class="text-base-content/50">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button
                                        v-if="source.is_automatic"
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        :disabled="refreshingId === source.id"
                                        title="Обновить курс"
                                        @click="refresh(source)"
                                    >
                                        <span v-if="refreshingId === source.id" class="loading loading-spinner loading-xs"></span>
                                        <span v-else>Обновить</span>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(source)">
                                        Изменить
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(source)">
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </DataTable>

                    <DataCardList>
                        <DataCard v-for="source in sources" :key="source.id">
                            <div class="flex items-center justify-between border-b border-base-content/10 mb-2 pb-2">
                                <div class="font-medium">
                                    {{ source.name || source.pair }}
                                    <span v-if="!source.is_active" class="badge badge-ghost badge-xs ml-1">выкл</span>
                                </div>
                                <span class="badge badge-sm badge-ghost">{{ typeLabel(source.type) }}</span>
                            </div>
                            <div class="flex flex-col gap-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-base-content/70">Пара</span>
                                    <CurrencyPairDisplay
                                        :base-currency="source.base_currency"
                                        :quote-currency="source.quote_currency"
                                        :pair="source.pair"
                                        size="sm"
                                    />
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-base-content/70">Тип</span>
                                    <span>{{ typeLabel(source.type) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-base-content/70">Курс</span>
                                    <span :class="!source.rate || source.rate === '0.00' ? 'text-error' : ''">{{ source.rate ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end gap-1">
                                <button
                                    v-if="source.is_automatic"
                                    type="button"
                                    class="btn btn-ghost btn-xs"
                                    :disabled="refreshingId === source.id"
                                    @click="refresh(source)"
                                >
                                    Обновить
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(source)">Изменить</button>
                                <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(source)">Удалить</button>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <RateSourceEditModal />
    </div>
</template>
