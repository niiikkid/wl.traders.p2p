<script setup>
import {Head, router} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    filterOptions: Object,
});

const expandedRows = ref({});
const expandedCards = ref({});
const form = reactive({
    type: props.filters?.type ?? '',
    operation: props.filters?.operation ?? '',
    is_successful: props.filters?.is_successful ?? '',
    search: props.filters?.search ?? '',
});

const summary = computed(() => {
    const rows = props.logs?.data ?? [];

    return {
        total: props.logs?.meta?.total ?? rows.length,
        api: rows.filter((log) => log.type === 'api').length,
        callback: rows.filter((log) => log.type === 'callback').length,
        failed: rows.filter((log) => ! log.is_successful).length,
    };
});

const applyFilters = () => {
    router.get(route('provider-liquidity.logs.index'), form, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    Object.assign(form, {
        type: '',
        operation: '',
        is_successful: '',
        search: '',
    });

    applyFilters();
};

const setType = (type) => {
    form.type = type;
    applyFilters();
};

const toggleRow = (logId) => {
    expandedRows.value[logId] = ! expandedRows.value[logId];
};

const toggleCard = (logId) => {
    expandedCards.value[logId] = ! expandedCards.value[logId];
};

const formatExecutionTime = (value) => {
    if (value === null || value === undefined) {
        return '-';
    }

    return `${Number(value).toLocaleString('ru-RU', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    })} сек`;
};

const prettyJson = (value) => {
    if (value === null || value === undefined) {
        return 'Пусто';
    }

    return JSON.stringify(value, null, 2);
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Логи каскада" />

        <MainTableSection
            title="Логи каскада"
            :data="logs"
        >
            <template #table-filters>
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body p-4 gap-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" :class="['btn btn-sm', form.type === '' ? 'btn-primary' : 'btn-outline']" @click="setType('')">
                                Все
                            </button>
                            <button type="button" :class="['btn btn-sm', form.type === 'api' ? 'btn-primary' : 'btn-outline']" @click="setType('api')">
                                API-запросы
                            </button>
                            <button type="button" :class="['btn btn-sm', form.type === 'callback' ? 'btn-primary' : 'btn-outline']" @click="setType('callback')">
                                Callback
                            </button>
                        </div>

                        <form class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                            <input v-model="form.search" type="search" class="input input-bordered input-sm w-full" placeholder="UUID, external ID, provider deal ID, URL, ошибка" />

                            <select v-model="form.operation" class="select select-bordered select-sm w-full">
                                <option value="">Все операции</option>
                                <option v-for="operation in filterOptions?.operations ?? []" :key="operation" :value="operation">
                                    {{ operation }}
                                </option>
                            </select>

                            <select v-model="form.is_successful" class="select select-bordered select-sm w-full">
                                <option value="">Любой результат</option>
                                <option value="1">Успешно</option>
                                <option value="0">Ошибка</option>
                            </select>

                            <div class="flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm grow">
                                    Фильтровать
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="resetFilters">
                                    Сброс
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <template #body>
                <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Всего</div>
                            <div class="stat-value text-2xl">{{ summary.total }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">API на странице</div>
                            <div class="stat-value text-2xl">{{ summary.api }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Callback на странице</div>
                            <div class="stat-value text-2xl">{{ summary.callback }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Ошибок на странице</div>
                            <div class="stat-value text-2xl text-error">{{ summary.failed }}</div>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="bg-base-300 text-xs uppercase">
                            <tr>
                                <th>Тип</th>
                                <th>Сделка</th>
                                <th>Метод</th>
                                <th>Результат</th>
                                <th>Время</th>
                                <th>Создан</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="log in logs?.data ?? []" :key="log.id">
                                <tr class="hover cursor-pointer border-b border-base-200 last:border-none" @click="toggleRow(log.id)">
                                    <td>
                                        <span :class="['badge badge-sm', log.type === 'callback' ? 'badge-info' : 'badge-primary']">
                                            {{ log.type === 'callback' ? 'Callback' : 'API' }}
                                        </span>
                                        <div class="mt-1 text-xs opacity-70">{{ log.operation }}</div>
                                    </td>
                                    <td>
                                        <CopyableOrderUid v-if="log.cascade_deal?.uuid" :uuid="log.cascade_deal.uuid" />
                                        <div v-else class="text-base-content/60">Пусто</div>
                                        <div v-if="log.cascade_transaction?.provider_deal_id" class="mt-1 text-xs opacity-70">
                                            Provider: {{ log.cascade_transaction.provider_deal_id }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ log.method }}</span>
                                    </td>
                                    <td>
                                        <span :class="['badge badge-sm', log.is_successful ? 'badge-success' : 'badge-error']">
                                            {{ log.is_successful ? 'Успешно' : 'Ошибка' }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">{{ formatExecutionTime(log.execution_time) }}</td>
                                    <td><DateTime class="justify-start" :data="log.created_at" show-time /></td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-ghost btn-xs">
                                            {{ expandedRows[log.id] ? 'Скрыть' : 'Детали' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expandedRows[log.id]" class="bg-base-200">
                                    <td colspan="7" class="p-4">
                                        <div class="mb-4 rounded border border-base-300 bg-base-100 p-3 text-sm">
                                            <div class="mb-2 font-semibold">HTTP</div>
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-4">
                                                <div>
                                                    <div class="text-xs opacity-60">Метод</div>
                                                    <div class="font-mono text-xs">{{ log.method }}</div>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <div class="text-xs opacity-60">Эндпоинт</div>
                                                    <div class="break-all font-mono text-xs">{{ log.url }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs opacity-60">Статус ответа</div>
                                                    <div class="font-mono text-xs">{{ log.status_code ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="log.error_message || log.error_code"
                                            class="mb-4 rounded border border-error/20 bg-base-100 p-3"
                                        >
                                            <div class="mb-1 text-sm font-semibold text-error">Ошибка (полный текст)</div>
                                            <div v-if="log.error_code" class="mb-2 break-all text-xs opacity-70">
                                                {{ log.error_code }}
                                            </div>
                                            <pre
                                                v-if="log.error_message"
                                                class="max-h-48 overflow-auto text-xs whitespace-pre-wrap break-all text-error"
                                            >{{ log.error_message }}</pre>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <div class="mb-1 text-sm font-semibold">Запрос</div>
                                                <pre class="max-h-80 overflow-auto rounded bg-base-100 p-3 text-xs whitespace-pre-wrap break-all">{{ prettyJson(log.request_payload) }}</pre>
                                            </div>
                                            <div>
                                                <div class="mb-1 text-sm font-semibold">Ответ (payload)</div>
                                                <pre class="max-h-80 overflow-auto rounded bg-base-100 p-3 text-xs whitespace-pre-wrap break-all">{{ prettyJson(log.response_payload) }}</pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="xl:hidden space-y-3">
                    <div v-for="log in logs?.data ?? []" :key="log.id" class="card bg-base-100 shadow-sm">
                        <div class="card-body p-4 gap-3">
                            <div class="flex items-start justify-between gap-3 border-b border-base-content/10 pb-2">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span :class="['badge badge-sm', log.type === 'callback' ? 'badge-info' : 'badge-primary']">
                                            {{ log.type === 'callback' ? 'Callback' : 'API' }}
                                        </span>
                                        <span class="font-medium">{{ log.operation }}</span>
                                    </div>
                                </div>
                                <span :class="['badge badge-sm', log.is_successful ? 'badge-success' : 'badge-error']">
                                    {{ log.is_successful ? 'Успешно' : 'Ошибка' }}
                                </span>
                            </div>

                            <div v-if="log.cascade_deal?.uuid" class="text-sm">
                                <div class="text-base-content/60">Сделка</div>
                                <CopyableOrderUid :uuid="log.cascade_deal.uuid" />
                            </div>

                            <div v-if="expandedCards[log.id]" class="grid grid-cols-1 gap-3">
                                <div class="rounded border border-base-300 bg-base-200 p-3 text-sm">
                                    <div class="mb-2 font-semibold">HTTP</div>
                                    <div class="space-y-2">
                                        <div>
                                            <div class="text-xs opacity-60">Метод</div>
                                            <div class="font-mono text-xs">{{ log.method }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs opacity-60">Эндпоинт</div>
                                            <div class="break-all font-mono text-xs">{{ log.url }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs opacity-60">Статус ответа</div>
                                            <div class="font-mono text-xs">{{ log.status_code ?? '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="log.error_message || log.error_code"
                                    class="rounded border border-error/20 bg-base-200 p-3"
                                >
                                    <div class="mb-1 text-sm font-semibold text-error">Ошибка (полный текст)</div>
                                    <div v-if="log.error_code" class="mb-2 break-all text-xs opacity-70">
                                        {{ log.error_code }}
                                    </div>
                                    <pre
                                        v-if="log.error_message"
                                        class="max-h-48 overflow-auto text-xs whitespace-pre-wrap break-all text-error"
                                    >{{ log.error_message }}</pre>
                                </div>
                                <div>
                                    <div class="mb-1 text-sm font-semibold">Запрос</div>
                                    <pre class="max-h-64 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap break-all">{{ prettyJson(log.request_payload) }}</pre>
                                </div>
                                <div>
                                    <div class="mb-1 text-sm font-semibold">Ответ (payload)</div>
                                    <pre class="max-h-64 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap break-all">{{ prettyJson(log.response_payload) }}</pre>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <DateTime class="justify-start text-xs" :data="log.created_at" show-time />
                                <button type="button" class="btn btn-primary btn-outline btn-xs" @click="toggleCard(log.id)">
                                    {{ expandedCards[log.id] ? 'Скрыть' : 'Подробнее' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
