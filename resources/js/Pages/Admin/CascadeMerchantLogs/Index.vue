<script setup>
import {Head, router} from '@inertiajs/vue3';
import {computed, reactive, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import CopyableExternalId from '@/Components/CopyableExternalId.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';

const props = defineProps({
    logs: Object,
    summary: Object,
    filters: Object,
    filterOptions: Object,
    routeName: {
        type: String,
        default: 'admin.cascade-merchant-logs.index',
    },
    showAdminNav: {
        type: Boolean,
        default: true,
    },
});

const expandedRows = ref({});
const expandedCards = ref({});
const form = reactive({
    direction: props.filters.direction ?? '',
    payment_type: props.filters.payment_type ?? '',
    merchant_id: props.filters.merchant_id ?? '',
    operation: props.filters.operation ?? '',
    is_successful: props.filters.is_successful ?? '',
    search: props.filters.search ?? '',
});

const summary = computed(() => {
    const rows = props.logs?.data ?? [];

    return {
        total: props.summary?.total ?? props.logs?.meta?.total ?? rows.length,
        incoming: props.summary?.incoming ?? rows.filter((log) => log.direction === 'incoming').length,
        outgoing: props.summary?.outgoing ?? rows.filter((log) => log.direction === 'outgoing').length,
        payin: props.summary?.payin ?? rows.filter((log) => log.payment_type === 'payin').length,
        payout: props.summary?.payout ?? rows.filter((log) => log.payment_type === 'payout').length,
        failed: props.summary?.failed ?? rows.filter((log) => ! log.is_successful).length,
    };
});

const applyFilters = () => {
    router.get(route(props.routeName), form, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    Object.assign(form, {
        direction: '',
        payment_type: '',
        merchant_id: '',
        operation: '',
        is_successful: '',
        search: '',
    });

    applyFilters();
};

const setDirection = (direction) => {
    form.direction = direction;
    applyFilters();
};

const setPaymentType = (paymentType) => {
    form.payment_type = paymentType;
    applyFilters();
};

const paymentTypeBadgeClass = (paymentType) => {
    return paymentType === 'payout' ? 'badge-secondary' : 'badge-accent';
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
        <Head title="Логи мерчанта" />

        <MainTableSection
            title="Логи мерчанта"
            :data="logs"
        >
            <template v-if="showAdminNav" #button>
                <CascadeSectionNav active="merchant-logs" />
            </template>

            <template #table-filters>
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body p-4 gap-4">
                        <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" :class="['btn btn-sm', form.direction === '' ? 'btn-primary' : 'btn-outline']" @click="setDirection('')">
                                    Все
                                </button>
                                <button type="button" :class="['btn btn-sm', form.direction === 'incoming' ? 'btn-primary' : 'btn-outline']" @click="setDirection('incoming')">
                                    API-запросы
                                </button>
                                <button type="button" :class="['btn btn-sm', form.direction === 'outgoing' ? 'btn-primary' : 'btn-outline']" @click="setDirection('outgoing')">
                                    Callback
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2 sm:ml-auto">
                                <button type="button" :class="['btn btn-sm', form.payment_type === '' ? 'btn-primary' : 'btn-outline']" @click="setPaymentType('')">
                                    все
                                </button>
                                <button type="button" :class="['btn btn-sm', form.payment_type === 'payin' ? 'btn-primary' : 'btn-outline']" @click="setPaymentType('payin')">
                                    payin
                                </button>
                                <button type="button" :class="['btn btn-sm', form.payment_type === 'payout' ? 'btn-primary' : 'btn-outline']" @click="setPaymentType('payout')">
                                    payout
                                </button>
                            </div>
                        </div>

                        <form class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                            <input v-model="form.search" type="search" class="input input-bordered input-sm w-full" placeholder="UUID, external ID, merchant, URL, ошибка" />

                            <select v-model="form.merchant_id" class="select select-bordered select-sm w-full">
                                <option value="">Все мерчанты</option>
                                <option v-for="merchant in filterOptions?.merchants ?? []" :key="merchant.id" :value="merchant.id">
                                    {{ merchant.label }}
                                </option>
                            </select>

                            <select v-model="form.operation" class="select select-bordered select-sm w-full">
                                <option value="">Все операции</option>
                                <option v-for="op in filterOptions?.operations ?? []" :key="op.value" :value="op.value">
                                    {{ op.label }}
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
                <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Всего</div>
                            <div class="stat-value text-2xl">{{ summary.total }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">API</div>
                            <div class="stat-value text-2xl">{{ summary.incoming }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">callback</div>
                            <div class="stat-value text-2xl">{{ summary.outgoing }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Pay-in</div>
                            <div class="stat-value text-2xl">{{ summary.payin }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">Payout</div>
                            <div class="stat-value text-2xl">{{ summary.payout }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow-sm">
                        <div class="stat p-4">
                            <div class="stat-title">ошибок</div>
                            <div class="stat-value text-2xl text-error">{{ summary.failed }}</div>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="bg-base-300 text-xs uppercase">
                            <tr>
                                <th>Платёж</th>
                                <th>Тип</th>
                                <th>Мерчант</th>
                                <th>UUID</th>
                                <th>Внешний ID</th>
                                <th>Результат</th>
                                <th>Время</th>
                                <th>Создан</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="log in logs?.data ?? []" :key="log.id">
                                <tr class="hover border-b border-base-200 last:border-none">
                                    <td>
                                        <span :class="['badge badge-sm', paymentTypeBadgeClass(log.payment_type)]">
                                            {{ log.payment_type_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span :class="['badge badge-sm', log.direction === 'outgoing' ? 'badge-info' : 'badge-primary']">
                                            {{ log.direction_label }}
                                        </span>
                                        <div class="mt-1 text-xs text-base-content/70">{{ log.operation_label }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-nowrap">{{ log.merchant?.name ?? 'Пусто' }}</div>
                                    </td>
                                    <td>
                                        <CopyableOrderUid v-if="log.cascade_deal?.uuid || log.payout?.uuid" :uuid="log.cascade_deal?.uuid ?? log.payout?.uuid" />
                                        <div v-else class="text-base-content/60">Пусто</div>
                                    </td>
                                    <td>
                                        <CopyableExternalId v-if="log.cascade_deal?.external_id || log.payout?.external_id" :id="String(log.cascade_deal?.external_id ?? log.payout?.external_id)" />
                                        <div v-else class="text-base-content/60">Пусто</div>
                                    </td>
                                    <td>
                                        <span :class="['badge badge-sm', log.is_successful ? 'badge-success' : 'badge-error']">
                                            {{ log.is_successful ? 'Успешно' : 'Ошибка' }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">{{ formatExecutionTime(log.execution_time) }}</td>
                                    <td><DateTime class="justify-start" :data="log.created_at" show-time /></td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-ghost btn-xs" @click.stop="toggleRow(log.id)">
                                            {{ expandedRows[log.id] ? 'Скрыть' : 'Детали' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expandedRows[log.id]" class="bg-base-200">
                                    <td colspan="9" class="p-4">
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
                                        <span :class="['badge badge-sm', paymentTypeBadgeClass(log.payment_type)]">
                                            {{ log.payment_type_label }}
                                        </span>
                                        <span :class="['badge badge-sm', log.direction === 'outgoing' ? 'badge-info' : 'badge-primary']">
                                            {{ log.direction_label }}
                                        </span>
                                        <span class="text-xs text-base-content/70">{{ log.operation_label }}</span>
                                    </div>
                                    <div class="mt-1 text-xs opacity-70">{{ log.merchant?.name ?? 'Мерчант не найден' }}</div>
                                </div>
                                <span :class="['badge badge-sm', log.is_successful ? 'badge-success' : 'badge-error']">
                                    {{ log.is_successful ? 'Успешно' : 'Ошибка' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <div class="text-base-content/60">UUID</div>
                                    <CopyableOrderUid v-if="log.cascade_deal?.uuid || log.payout?.uuid" :uuid="log.cascade_deal?.uuid ?? log.payout?.uuid" />
                                    <div v-else class="text-base-content/60">Пусто</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Внешний ID</div>
                                    <CopyableExternalId v-if="log.cascade_deal?.external_id || log.payout?.external_id" :id="String(log.cascade_deal?.external_id ?? log.payout?.external_id)" />
                                    <div v-else class="text-base-content/60">Пусто</div>
                                </div>
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
