<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AntiFraudNav from '@/Components/Admin/AntiFraudNav.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import AntiFraudSettingModal from '@/Modals/Admin/AntiFraudSettingModal.vue';
import PageToolbar from '@/Components/Table/PageToolbar.vue';
import PageToolbarAction from '@/Components/Table/PageToolbarAction.vue';
import { useModalStore } from '@/store/modal.js';

defineOptions({ layout: AuthenticatedLayout });

const modalStore = useModalStore();

defineProps({
    merchants: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Array,
        default: () => [],
    },
});

const createSetting = () => {
    modalStore.openAntiFraudSettingModal({});
};

const editSetting = (setting) => {
    modalStore.openAntiFraudSettingModal({ setting });
};

const merchantLabel = (setting) => setting.merchant?.name || setting.merchant?.uuid || `#${setting.merchant_id}`;

const formatRateLimits = (limits) => {
    if (!limits || !limits.length) {
        return '—';
    }

    return limits.map((limit) => `${limit.count} / ${limit.minutes}м`).join(', ');
};
</script>

<template>
    <div>
        <Head title="Антифрод" />

        <MainTableSection
            title="Антифрод"
            :data="settings"
            :paginate="false"
            :display-pagination="false"
        >
            <template #button>
                <PageToolbar>
                    <PageToolbarAction
                        icon="plus"
                        title="Создать настройки"
                        label="Создать настройки"
                        @click="createSetting"
                    />
                </PageToolbar>
            </template>

            <template #header>
                <AntiFraudNav current="settings" />
            </template>

            <template #body>
                <div class="relative">
                    <DataTable>
                        <template #head>
                            <th>Мерчант</th>
                            <th>Статус</th>
                            <th>Primary</th>
                            <th>Secondary</th>
                            <th class="text-right">
                                <span class="sr-only">Действия</span>
                            </th>
                        </template>

                        <tr v-for="setting in settings" :key="setting.id">
                            <td class="align-top">
                                <div class="font-medium">{{ merchantLabel(setting) }}</div>
                            </td>
                            <td class="align-top whitespace-nowrap">
                                <span v-if="setting.enabled" class="badge badge-success badge-sm">Включен</span>
                                <span v-else class="badge badge-ghost badge-sm">Выключен</span>
                            </td>
                            <td class="align-top text-sm">
                                <div>Pending: {{ setting.primary_max_pending ?? '—' }}</div>
                                <div>Лимиты: {{ formatRateLimits(setting.primary_rate_limits) }}</div>
                                <div>Fail подряд: {{ setting.primary_failed_limit ?? '—' }}</div>
                                <div>Блок: {{ setting.primary_block_days ?? '—' }} дн.</div>
                            </td>
                            <td class="align-top text-sm">
                                <div v-if="setting.secondary_enabled === false" class="text-base-content/60">
                                    Фильтры отключены
                                </div>
                                <template v-else>
                                    <div>Pending: {{ setting.secondary_max_pending ?? '—' }}</div>
                                    <div>Лимиты: {{ formatRateLimits(setting.secondary_rate_limits) }}</div>
                                    <div>Fail подряд: {{ setting.secondary_failed_limit ?? '—' }}</div>
                                    <div>Блок: {{ setting.secondary_block_days ?? '—' }} дн.</div>
                                </template>
                            </td>
                            <td class="align-top text-right whitespace-nowrap">
                                <button type="button" class="btn btn-xs btn-outline" @click="editSetting(setting)">
                                    Редактировать
                                </button>
                            </td>
                        </tr>
                    </DataTable>

                    <DataCardList>
                        <DataCard
                            v-for="setting in settings"
                            :key="`mobile-${setting.id}`"
                        >
                            <div class="flex items-center justify-between gap-2 border-b border-base-content/10 pb-2">
                                <div class="min-w-0 font-medium text-sm break-words">
                                    {{ merchantLabel(setting) }}
                                </div>
                                <span v-if="setting.enabled" class="badge badge-success badge-sm shrink-0">Включен</span>
                                <span v-else class="badge badge-ghost badge-sm shrink-0">Выключен</span>
                            </div>

                            <div class="pt-2 space-y-3 text-sm">
                                <div>
                                    <div class="text-xs text-base-content/50 mb-1">Primary</div>
                                    <div>Pending: {{ setting.primary_max_pending ?? '—' }}</div>
                                    <div>Лимиты: {{ formatRateLimits(setting.primary_rate_limits) }}</div>
                                    <div>Fail подряд: {{ setting.primary_failed_limit ?? '—' }}</div>
                                    <div>Блок: {{ setting.primary_block_days ?? '—' }} дн.</div>
                                </div>

                                <div>
                                    <div class="text-xs text-base-content/50 mb-1">Secondary</div>
                                    <div v-if="setting.secondary_enabled === false" class="text-base-content/60">
                                        Фильтры отключены
                                    </div>
                                    <template v-else>
                                        <div>Pending: {{ setting.secondary_max_pending ?? '—' }}</div>
                                        <div>Лимиты: {{ formatRateLimits(setting.secondary_rate_limits) }}</div>
                                        <div>Fail подряд: {{ setting.secondary_failed_limit ?? '—' }}</div>
                                        <div>Блок: {{ setting.secondary_block_days ?? '—' }} дн.</div>
                                    </template>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-base-content/10 mt-3">
                                <button type="button" class="btn btn-xs btn-outline w-full" @click="editSetting(setting)">
                                    Редактировать
                                </button>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <AntiFraudSettingModal :merchants="merchants" :settings="settings" />
    </div>
</template>
