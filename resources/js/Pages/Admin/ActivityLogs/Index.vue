<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import DateFilter from '@/Components/Filters/Partials/DateFilter.vue';
import DateTime from '@/Components/DateTime.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';

const page = usePage();
const logs = computed(() => page.props.logs);
const expandedRows = ref({});
const expandedCards = ref({});

const toggleRow = (id) => {
    expandedRows.value[id] = !expandedRows.value[id];
};

const toggleCard = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const actionBadgeClass = (action) => ({
    created: 'badge-success',
    updated: 'badge-info',
    deleted: 'badge-warning',
    restored: 'badge-success',
    force_deleted: 'badge-error',
    role_attached: 'badge-primary',
    role_detached: 'badge-warning',
}[action] || 'badge-neutral');

const actorLabel = (log) => {
    if (!log.actor) {
        return 'Пользователь удален';
    }

    return `${log.actor.email} #${log.actor.id}`;
};

const subjectLabel = (log) => {
    const id = log.subject_uuid || log.subject_id || '-';

    return `${log.subject_label} #${id}`;
};

const changeEntries = (changes) => Object.entries(changes || {});

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Аудит действий" />

        <MainTableSection
            title="Аудит действий"
            subtitle="Действия пользователей, выполненные через web-панель"
            :data="logs"
        >
            <template #header>
                <FiltersPanel name="activity-logs">
                    <DateFilter name="startDate" title="Дата от" />
                    <DateFilter name="endDate" title="Дата до" />
                    <InputFilter name="user" placeholder="Пользователь email или ID" />
                    <InputFilter name="subjectId" placeholder="ID объекта" />
                    <InputFilter name="uuid" placeholder="UUID объекта" />
                    <DropdownFilter name="activityActions" title="Действия" />
                    <DropdownFilter name="activitySubjectTypes" title="Сущности" />
                </FiltersPanel>
            </template>

            <template #body>
                <DataTable>
                    <template #head>
                        <th scope="col">ID</th>
                        <th scope="col">Пользователь</th>
                        <th scope="col">Действие</th>
                        <th scope="col">Сущность</th>
                        <th scope="col">Маршрут</th>
                        <th scope="col">IP</th>
                        <th scope="col">Дата</th>
                    </template>

                    <template v-for="log in logs.data" :key="log.id">
                        <tr class="hover cursor-pointer" @click.stop="toggleRow(log.id)">
                            <th scope="row" class="whitespace-nowrap font-medium">
                                {{ log.id }}
                            </th>
                            <td>
                                <div class="font-medium">{{ actorLabel(log) }}</div>
                                <div v-if="log.actor_role" class="text-xs text-base-content/60">
                                    {{ log.actor_role }}
                                </div>
                                <div v-if="log.impersonator" class="text-xs text-warning">
                                    Через impersonation: {{ log.impersonator.email }} #{{ log.impersonator.id }}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-sm" :class="actionBadgeClass(log.action)">
                                    {{ log.action_label }}
                                </span>
                            </td>
                            <td>
                                <div class="font-medium">{{ subjectLabel(log) }}</div>
                                <div class="text-xs text-base-content/60">{{ log.subject_type }}</div>
                            </td>
                            <td class="text-xs">
                                {{ log.route_name || '-' }}
                            </td>
                            <td class="text-xs">
                                {{ log.ip_address || '-' }}
                            </td>
                            <td class="whitespace-nowrap">
                                <DateTime :data="log.created_at" />
                            </td>
                        </tr>

                        <tr v-if="expandedRows[log.id]" class="bg-base-200">
                            <td colspan="7" class="px-6 py-4">
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <h4 class="mb-2 font-semibold">Изменения</h4>
                                        <div v-if="changeEntries(log.changes).length" class="space-y-2">
                                            <div
                                                v-for="[field, value] in changeEntries(log.changes)"
                                                :key="field"
                                                class="rounded-box border border-base-300 bg-base-100 p-3"
                                            >
                                                <div class="mb-2 text-sm font-medium">{{ field }}</div>
                                                <div v-if="value && typeof value === 'object' && ('old' in value || 'new' in value)" class="grid gap-2 md:grid-cols-2">
                                                    <div>
                                                        <div class="mb-1 text-xs text-base-content/60">Было</div>
                                                        <pre class="max-h-40 overflow-auto rounded bg-base-200 p-2 text-xs whitespace-pre-wrap">{{ formatValue(value.old) }}</pre>
                                                    </div>
                                                    <div>
                                                        <div class="mb-1 text-xs text-base-content/60">Стало</div>
                                                        <pre class="max-h-40 overflow-auto rounded bg-base-200 p-2 text-xs whitespace-pre-wrap">{{ formatValue(value.new) }}</pre>
                                                    </div>
                                                </div>
                                                <pre v-else class="max-h-40 overflow-auto rounded bg-base-200 p-2 text-xs whitespace-pre-wrap">{{ formatValue(value) }}</pre>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-base-content/60">Нет данных об изменениях</div>
                                    </div>

                                    <div class="space-y-3">
                                        <div>
                                            <h4 class="mb-2 font-semibold">Контекст</h4>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-3 text-sm">
                                                <div>Route: {{ log.route_name || '-' }}</div>
                                                <div>IP: {{ log.ip_address || '-' }}</div>
                                                <div>Path: {{ log.meta?.path || '-' }}</div>
                                                <div>Method: {{ log.meta?.method || '-' }}</div>
                                            </div>
                                        </div>
                                        <div v-if="log.user_agent">
                                            <h4 class="mb-2 font-semibold">User-Agent</h4>
                                            <div class="max-h-32 overflow-auto rounded-box border border-base-300 bg-base-100 p-3 text-xs break-words">
                                                {{ log.user_agent }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </DataTable>

                <DataCardList>
                    <DataCard v-for="log in logs.data" :key="log.id">
                        <div class="flex items-start justify-between gap-3 border-b border-base-content/10 pb-2">
                            <div class="min-w-0">
                                <div class="font-medium">{{ actorLabel(log) }}</div>
                                <div class="text-xs text-base-content/60">{{ subjectLabel(log) }}</div>
                            </div>
                            <span class="badge badge-sm shrink-0" :class="actionBadgeClass(log.action)">
                                {{ log.action_label }}
                            </span>
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                            <div>
                                <div class="text-xs text-base-content/60">Дата</div>
                                <DateTime :data="log.created_at" />
                            </div>
                            <button
                                type="button"
                                class="btn btn-primary btn-xs"
                                :aria-expanded="!!expandedCards[log.id]"
                                @click.stop="toggleCard(log.id)"
                            >
                                {{ expandedCards[log.id] ? 'Скрыть' : 'Детали' }}
                            </button>
                        </div>

                        <div v-if="expandedCards[log.id]" class="mt-3 space-y-2 rounded-box bg-base-300/50 p-2 text-sm">
                            <div>Route: {{ log.route_name || '-' }}</div>
                            <div>IP: {{ log.ip_address || '-' }}</div>
                            <div v-if="log.impersonator" class="text-warning">
                                Impersonation: {{ log.impersonator.email }} #{{ log.impersonator.id }}
                            </div>
                            <pre class="max-h-48 overflow-auto rounded bg-base-100 p-2 text-xs whitespace-pre-wrap">{{ formatValue(log.changes) }}</pre>
                        </div>
                    </DataCard>
                </DataCardList>
            </template>
        </MainTableSection>
    </div>
</template>
