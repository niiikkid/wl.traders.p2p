<script setup>
import {Head, useForm, usePage} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AutomationNavButtons from '@/Components/Automation/AutomationNavButtons.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import DateTime from '@/Components/DateTime.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import {useModalStore} from '@/store/modal.js';
import MainTableSection from '@/Wrappers/MainTableSection.vue';

defineOptions({layout: AuthenticatedLayout})

const page = usePage();
const modalStore = useModalStore();

const shadowSmsLogs = computed(() => page.props.shadowSmsLogs);
const shadowSmsLogsTotalCount = computed(() => page.props.shadowSmsLogsTotalCount);
const shadowSmsLogEnabled = computed(() => page.props.shadowSmsLogEnabled);
const enabled = ref(shadowSmsLogEnabled.value);

const toggleForm = useForm({
    enabled: enabled.value,
});

const deleteForm = useForm({});

const deleteByPatternForm = useForm({
    pattern: '',
});

watch(shadowSmsLogEnabled, (value) => {
    enabled.value = value;
    toggleForm.enabled = value;
});

const updateEnabled = () => {
    toggleForm.enabled = enabled.value;
    toggleForm.patch(route('admin.shadow-sms-logs.enabled.update'), {
        preserveScroll: true,
        onError: () => {
            enabled.value = shadowSmsLogEnabled.value;
            toggleForm.enabled = shadowSmsLogEnabled.value;
        },
    });
};

const confirmDeleteAll = () => {
    modalStore.openConfirmModal({
        title: 'Удалить все записи теневого лога?',
        body: 'Это действие нельзя отменить.',
        confirm_button_name: 'Удалить всё',
        confirm: () => {
            deleteForm.delete(route('admin.shadow-sms-logs.destroy-all'), {
                preserveScroll: true,
            });
        },
    });
};

const confirmDeleteByPattern = () => {
    const pattern = deleteByPatternForm.pattern.trim();

    if (!pattern) {
        return;
    }

    modalStore.openConfirmModal({
        title: 'Удалить записи по совпадению?',
        body: `Будут удалены все записи, где отправитель, сообщение или детали фильтра содержат «${pattern}». Это действие нельзя отменить.`,
        confirm_button_name: 'Удалить',
        confirm: () => {
            deleteByPatternForm
                .transform((data) => ({
                    ...data,
                    pattern,
                }))
                .delete(route('admin.shadow-sms-logs.destroy-by-pattern'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        deleteByPatternForm.reset();
                    },
                });
        },
    });
};

const messageTypeBadgeClass = (type) => {
    return type === 'push' ? 'badge-info' : 'badge-accent';
};

const filterReasonBadgeClass = (reason) => {
    if (reason === 'sender_stop_list') {
        return 'badge-error';
    }

    if (reason === 'stop_word') {
        return 'badge-warning';
    }

    return 'badge-neutral';
};
</script>

<template>
    <div>
        <Head title="Теневой лог" />

        <MainTableSection title="Теневой лог" :data="shadowSmsLogs">
            <template #button>
                <AutomationNavButtons current="shadow" />
            </template>

            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-base text-base-content/70">
                            Всего записей:
                            <span class="font-semibold text-base-content">{{ shadowSmsLogsTotalCount }}</span>
                        </div>
                        <p class="text-sm text-base-content/60">
                            Здесь сохраняются сообщения, отфильтрованные до основного лога.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-box bg-base-100 px-3 py-2 shadow-sm">
                            <span class="text-sm font-medium">Запись в теневой лог</span>
                            <input
                                v-model="enabled"
                                type="checkbox"
                                class="toggle toggle-primary"
                                :disabled="toggleForm.processing"
                                @change="updateEnabled"
                            >
                        </label>
                        <div class="flex flex-wrap items-center gap-2 rounded-box bg-base-100 px-3 py-2 shadow-sm">
                            <input
                                v-model="deleteByPatternForm.pattern"
                                type="text"
                                class="input input-bordered input-sm w-48"
                                placeholder="Удалить по совпадению"
                                :disabled="deleteByPatternForm.processing"
                                @keyup.enter="confirmDeleteByPattern"
                            >
                            <button
                                type="button"
                                class="btn btn-warning btn-sm"
                                :disabled="deleteByPatternForm.processing || !deleteByPatternForm.pattern.trim()"
                                @click="confirmDeleteByPattern"
                            >
                                Удалить по LIKE
                            </button>
                        </div>
                        <button
                            type="button"
                            class="btn btn-error btn-sm"
                            :disabled="deleteForm.processing || !shadowSmsLogsTotalCount"
                            @click="confirmDeleteAll"
                        >
                            Удалить всё
                        </button>
                    </div>
                </div>
            </template>

            <template #table-filters>
                <FiltersPanel name="shadow-sms-logs">
                    <InputFilter name="login" placeholder="Логин" class="w-48" />
                    <InputFilter name="deviceName" placeholder="Устройство" class="w-48" />
                    <InputFilter name="searchSender" placeholder="Отправитель" class="w-48" />
                    <InputFilter name="searchMessage" placeholder="Сообщение" class="w-64" />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Сообщение</th>
                                    <th scope="col">Причина</th>
                                    <th scope="col">Пользователь</th>
                                    <th scope="col">Время</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="log in shadowSmsLogs.data" :key="log.id" class="hover">
                                    <th scope="row" class="font-medium whitespace-nowrap">{{ log.id }}</th>
                                    <td>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-primary text-xs text-nowrap">{{ log.sender }}</span>
                                                <span class="badge badge-outline badge-xs" :class="messageTypeBadgeClass(log.type)">
                                                    {{ log.type.toUpperCase() }}
                                                </span>
                                            </div>
                                            <div class="max-w-md break-words text-base-content">
                                                {{ log.message }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1">
                                            <span class="badge badge-sm" :class="filterReasonBadgeClass(log.filter_reason)">
                                                {{ log.filter_reason_label }}
                                            </span>
                                            <div class="text-xs text-base-content/70">{{ log.filter_detail_text }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1 text-nowrap">
                                            <div>{{ log.user?.email ?? '—' }}</div>
                                            <div class="text-xs text-base-content/70">{{ log.device?.name ?? '—' }}</div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime :data="log.created_at" />
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-3">
                        <div
                            v-for="log in shadowSmsLogs.data"
                            :key="log.id"
                            class="card bg-base-100 shadow-sm"
                        >
                            <div class="card-body p-4 gap-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-base-content/60">ID: {{ log.id }}</div>
                                        <div class="font-medium text-primary truncate">{{ log.sender }}</div>
                                    </div>
                                    <DateTime class="justify-start" :data="log.created_at" />
                                </div>

                                <div class="rounded-box bg-base-300/40 p-2 break-words">
                                    {{ log.message }}
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge badge-outline badge-xs" :class="messageTypeBadgeClass(log.type)">
                                        {{ log.type.toUpperCase() }}
                                    </span>
                                    <span class="badge badge-sm" :class="filterReasonBadgeClass(log.filter_reason)">
                                        {{ log.filter_reason_label }}
                                    </span>
                                </div>

                                <div class="text-sm text-base-content/80">{{ log.filter_detail_text }}</div>

                                <div class="grid gap-2 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Логин</span>
                                        <span class="truncate">{{ log.user?.email ?? '—' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Устройство</span>
                                        <span class="truncate">{{ log.device?.name ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal />
    </div>
</template>
