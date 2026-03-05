<script setup>
import {Head, router} from '@inertiajs/vue3';
import {computed} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import AntiFraudSettingModal from '@/Modals/Admin/AntiFraudSettingModal.vue';
import {useModalStore} from '@/store/modal.js';

defineOptions({ layout: AuthenticatedLayout });

const modalStore = useModalStore();

const props = defineProps({
    merchants: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Array,
        default: () => [],
    },
});

const confirmDelete = (setting) => {
    modalStore.openConfirmModal({
        title: `Удалить антифрод-настройки для "${setting?.merchant?.name ?? 'мерчанта'}"?`,
        body: 'Настройки будут удалены, антифрод перестанет применяться.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            router.delete(route('admin.anti-fraud-settings.destroy', setting.id), {
                preserveScroll: true,
            });
        },
    });
};

const createSetting = () => {
    modalStore.openAntiFraudSettingModal({});
};

const editSetting = (setting) => {
    modalStore.openAntiFraudSettingModal({ setting });
};

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

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Антифрод</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.history.index'), { preserveScroll: true })"
                    >
                        История
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="router.visit(route('admin.anti-fraud.clients.index'), { preserveScroll: true })"
                    >
                        Клиенты
                    </button>
                    <button type="button" class="btn btn-primary" @click="createSetting">
                        Создать настройки
                    </button>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Список настроек</h3>
                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                <tr>
                                    <th>Мерчант</th>
                                    <th>Статус</th>
                                    <th>Primary</th>
                                    <th>Secondary</th>
                                    <th class="text-right">Действия</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="setting in settings" :key="setting.id">
                                    <td>
                                        <div class="font-medium">{{ setting.merchant?.name || setting.merchant?.uuid || `#${setting.merchant_id}` }}</div>
                                    </td>
                                    <td>
                                        <span v-if="setting.enabled" class="badge badge-success badge-sm">Включен</span>
                                        <span v-else class="badge badge-ghost badge-sm">Выключен</span>
                                    </td>
                                    <td class="text-sm">
                                        <div>Pending: {{ setting.primary_max_pending ?? '—' }}</div>
                                        <div>Лимиты: {{ formatRateLimits(setting.primary_rate_limits) }}</div>
                                        <div>Fail подряд: {{ setting.primary_failed_limit ?? '—' }}</div>
                                        <div>Блок: {{ setting.primary_block_days ?? '—' }} дн.</div>
                                    </td>
                                    <td class="text-sm">
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
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn btn-xs btn-outline" @click="editSetting(setting)">
                                                Редактировать
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!settings.length">
                                    <td colspan="5" class="text-center text-sm text-base-content/60 py-6">
                                        Настройки антифрода еще не созданы.
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal />
        <AntiFraudSettingModal :merchants="merchants" :settings="settings" />
    </div>
</template>
