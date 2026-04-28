<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';

const cascadeDeals = ref(usePage().props.cascadeDeals);
const selectedDeal = ref(null);
const activeModalTab = ref('overview');

router.on('success', () => {
    cascadeDeals.value = usePage().props.cascadeDeals;
});

const selectedDealJson = computed(() => {
    if (! selectedDeal.value) {
        return '';
    }

    return JSON.stringify(selectedDeal.value, null, 2);
});

const openDealModal = (deal) => {
    selectedDeal.value = deal;
    activeModalTab.value = 'overview';
};

const closeDealModal = () => {
    selectedDeal.value = null;
};

const logSummary = computed(() => {
    const providerLogs = selectedDeal.value?.provider_logs ?? [];
    const events = selectedDeal.value?.events ?? [];

    return {
        total: providerLogs.length + events.length,
        providerLogs: providerLogs.length,
        events: events.length,
        failed: providerLogs.filter((log) => ! log.is_successful).length,
    };
});

const formatCurrency = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return 'Пусто';
    }

    return `${amount} ${currency ?? ''}`.trim();
};

const getProviderName = (deal) => {
    return deal.selected_provider?.name ?? deal.selected_provider?.code ?? 'Не выбран';
};

const eventTypeLabel = (type) => ({
    status_changed: 'Статус',
    dispute_changed: 'Спор',
    amount_changed: 'Сумма',
    manual_control_changed: 'Ручное управление',
    callback_sent: 'Callback',
    provider_callback_received: 'Callback провайдера',
    provider_operation: 'Операция провайдера',
    collateral_changed: 'Залог',
    timeout: 'Таймаут',
    error: 'Ошибка',
}[type] ?? type ?? 'Событие');

const formatExecutionTime = (value) => {
    if (value === null || value === undefined) {
        return '—';
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

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Сделки каскада" />

        <MainTableSection
            title="Сделки каскада"
            :data="cascadeDeals"
        >
            <template #button>
                <CascadeSectionNav active="deals" />
            </template>

            <template v-slot:body>
                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th scope="col">UUID</th>
                                <th scope="col">Внешний ID</th>
                                <th scope="col">Мерчант</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Метод</th>
                                <th scope="col">Интеграция</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Создана</th>
                                <th scope="col">
                                    <span class="sr-only">Действия</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="deal in cascadeDeals.data"
                                :key="deal.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                            >
                                <th scope="row" class="font-medium whitespace-nowrap">
                                    <CopyableOrderUid :uuid="deal.uuid ?? ''"/>
                                </th>
                                <td class="text-nowrap text-base-content">
                                    <CopyableOrderUid :uuid="deal.external_id ?? ''"/>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ deal.merchant?.name ?? 'Пусто' }}</div>
                                    <div v-if="deal.merchant_client?.external_id" class="flex flex-wrap items-center gap-1.5 text-nowrap text-xs opacity-70">
                                        <span>Клиент:</span>
                                        <CopyableOrderUid :uuid="deal.merchant_client.external_id ?? ''"/>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">
                                        {{ deal.amount }}
                                        <span class="text-primary/70">{{ (deal.currency ?? '').toUpperCase() }}</span>
                                    </div>
                                    <div class="text-nowrap text-xs">
                                        <span class="text-base-content/50">{{ deal.usdt_amount ?? '—' }}</span>
                                        <span class="text-primary/50"> USDT</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ deal.payment_method_name ?? deal.payment_method ?? 'Пусто' }}</div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ getProviderName(deal) }}</div>
                                    <div class="text-nowrap text-xs opacity-70">
                                        Попыток: {{ deal.transactions_count ?? 0 }}
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <OrderStatus
                                        inline
                                        :status="deal.status"
                                        :status_name="deal.status_name"
                                        :sub_status_name="deal.sub_status_name"
                                    />
                                </td>
                                <td>
                                    <DateTime class="justify-start" :data="deal.created_at"/>
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-outline btn-xs"
                                        aria-label="Открыть каскадную сделку"
                                        @click.prevent="openDealModal(deal)"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                            <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="xl:hidden space-y-3">
                    <div
                        v-for="deal in cascadeDeals.data"
                        :key="deal.id"
                        class="card bg-base-100 shadow-sm"
                    >
                        <div class="card-body p-4 gap-3">
                            <div class="flex items-start justify-between gap-3 border-b border-base-content/10 pb-2">
                                <div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-sm text-base-content/70">UUID:</span>
                                        <CopyableOrderUid :uuid="deal.uuid ?? ''"/>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs text-base-content/70">
                                        <span>External ID:</span>
                                        <CopyableOrderUid :uuid="deal.external_id ?? ''"/>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-0.5 min-w-0 max-w-[min(100%,14rem)]">
                                    <OrderStatus
                                        inline
                                        :status="deal.status"
                                        :status_name="deal.status_name"
                                        :sub_status_name="deal.sub_status_name"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="col-span-2">
                                    <div class="text-base-content/60">Сумма</div>
                                    <div class="text-nowrap text-base-content">
                                        {{ deal.amount }}
                                        <span class="text-primary/70">{{ (deal.currency ?? '').toUpperCase() }}</span>
                                    </div>
                                    <div class="text-nowrap text-xs">
                                        <span class="text-base-content/50">{{ deal.usdt_amount ?? '—' }}</span>
                                        <span class="text-primary/50"> USDT</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Метод</div>
                                    <div class="font-medium">{{ deal.payment_method_name ?? deal.payment_method ?? 'Пусто' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Интеграция</div>
                                    <div class="font-medium">{{ getProviderName(deal) }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <DateTime class="justify-start text-xs" :data="deal.created_at"/>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-outline btn-xs"
                                    @click.prevent="openDealModal(deal)"
                                >
                                    Подробнее
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <dialog :open="Boolean(selectedDeal)" class="modal">
            <div class="modal-box max-w-6xl">
                <form method="dialog">
                    <button
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        @click="closeDealModal"
                    >
                        ✕
                    </button>
                </form>

                <template v-if="selectedDeal">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-bold text-lg">Каскадная сделка</h3>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                <span>Логов: {{ logSummary.total }}</span>
                                <span class="badge badge-primary badge-outline badge-sm">HTTP {{ logSummary.providerLogs }}</span>
                                <span class="badge badge-info badge-outline badge-sm">События {{ logSummary.events }}</span>
                                <span v-if="logSummary.failed" class="badge badge-error badge-outline badge-sm">Ошибки {{ logSummary.failed }}</span>
                            </div>
                        </div>

                        <div class="join self-start">
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'overview' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'overview'">
                                Обзор
                            </button>
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'logs' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'logs'">
                                Логи
                            </button>
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'raw' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'raw'">
                                Raw
                            </button>
                        </div>
                    </div>

                    <div v-if="activeModalTab === 'overview'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Основное</h4>
                                <div class="text-sm space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">UUID:</span>
                                        <CopyableOrderUid :uuid="selectedDeal.uuid ?? ''"/>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">External ID:</span>
                                        <CopyableOrderUid :uuid="selectedDeal.external_id ?? ''"/>
                                    </div>
                                    <div>Мерчант: {{ selectedDeal.merchant?.name ?? 'Пусто' }}</div>
                                    <div>Статус: {{ selectedDeal.status_name ?? selectedDeal.status }}</div>
                                    <div>Подстатус: {{ selectedDeal.sub_status_name ?? selectedDeal.sub_status ?? 'Пусто' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Экономика</h4>
                                <div class="text-sm space-y-1">
                                    <div>Сумма: {{ formatCurrency(selectedDeal.amount, selectedDeal.currency) }}</div>
                                    <div>Initial: {{ formatCurrency(selectedDeal.initial_amount, selectedDeal.currency) }}</div>
                                    <div>USDT amount: {{ formatCurrency(selectedDeal.usdt_amount, selectedDeal.base_currency) }}</div>
                                    <div>Fee: {{ formatCurrency(selectedDeal.fee, selectedDeal.base_currency) }}</div>
                                    <div>Profit: {{ formatCurrency(selectedDeal.service_profit, selectedDeal.base_currency) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Платёжные данные</h4>
                                <div class="text-sm space-y-1">
                                    <div>Метод: {{ selectedDeal.payment_method_name ?? selectedDeal.payment_method ?? 'Пусто' }}</div>
                                    <div>Callback: {{ selectedDeal.callback_url ?? 'Пусто' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Интеграция</h4>
                                <div class="text-sm space-y-1">
                                    <div>Выбран: {{ getProviderName(selectedDeal) }}</div>
                                    <div>Provider deal ID: {{ selectedDeal.selected_transaction?.provider_deal_id ?? 'Пусто' }}</div>
                                    <div>Статус транзакции: {{ selectedDeal.selected_transaction?.status_name ?? selectedDeal.selected_transaction?.status ?? 'Пусто' }}</div>
                                    <div>Попыток: {{ selectedDeal.transactions_count ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeModalTab === 'logs'" class="grid grid-cols-1 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">HTTP-логи интеграций</h4>
                                    <span class="badge badge-primary badge-outline badge-sm">{{ selectedDeal.provider_logs_count ?? 0 }} всего</span>
                                </div>

                                <div v-if="! selectedDeal.provider_logs?.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    Для этой сделки пока нет HTTP-логов провайдера.
                                </div>

                                <div v-else class="space-y-3">
                                    <div v-for="log in selectedDeal.provider_logs" :key="log.id" class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span :class="['badge badge-sm', log.is_successful ? 'badge-success' : 'badge-error']">
                                                        {{ log.is_successful ? 'Успешно' : 'Ошибка' }}
                                                    </span>
                                                    <span class="font-medium">{{ log.operation }}</span>
                                                    <span class="font-mono text-xs text-base-content/60">{{ log.method }}</span>
                                                    <span v-if="log.status_code" class="badge badge-ghost badge-sm">{{ log.status_code }}</span>
                                                </div>
                                                <div class="mt-1 text-xs text-base-content/70">
                                                    {{ log.provider?.name ?? log.provider?.code ?? 'Интеграция не найдена' }}
                                                    <span class="px-1">·</span>
                                                    {{ formatExecutionTime(log.execution_time) }}
                                                </div>
                                            </div>
                                            <DateTime class="justify-start text-xs sm:justify-end" :data="log.created_at" show-time/>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                            <div class="lg:col-span-2">
                                                <div class="mb-1 text-xs text-base-content/60">Эндпоинт</div>
                                                <div class="wrap-anywhere rounded bg-base-200 p-2 font-mono text-xs">{{ log.url ?? 'Пусто' }}</div>
                                            </div>

                                            <div v-if="log.cascade_transaction?.provider_deal_id" class="lg:col-span-2">
                                                <div class="mb-1 text-xs text-base-content/60">ID сделки у интеграции</div>
                                                <CopyableOrderUid :uuid="String(log.cascade_transaction.provider_deal_id)"/>
                                            </div>

                                            <div v-if="log.error_message || log.error_code" class="lg:col-span-2 rounded border border-error/20 bg-error/5 p-3">
                                                <div class="mb-1 text-sm font-semibold text-error">Ошибка</div>
                                                <div v-if="log.error_code" class="mb-2 wrap-anywhere font-mono text-xs text-error/80">{{ log.error_code }}</div>
                                                <pre v-if="log.error_message" class="max-h-36 overflow-auto whitespace-pre-wrap wrap-anywhere text-xs text-error">{{ log.error_message }}</pre>
                                            </div>

                                            <div>
                                                <div class="mb-1 text-xs font-semibold">Запрос</div>
                                                <pre class="max-h-56 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap wrap-anywhere">{{ prettyJson(log.request_payload) }}</pre>
                                            </div>
                                            <div>
                                                <div class="mb-1 text-xs font-semibold">Ответ</div>
                                                <pre class="max-h-56 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap wrap-anywhere">{{ prettyJson(log.response_payload) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">События сделки</h4>
                                    <span class="badge badge-info badge-outline badge-sm">{{ selectedDeal.events?.length ?? 0 }} показано</span>
                                </div>

                                <div v-if="! selectedDeal.events?.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    Для этой сделки пока нет событий.
                                </div>

                                <div v-else class="space-y-3">
                                    <div v-for="event in selectedDeal.events" :key="event.id" class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span :class="['badge badge-sm', event.type === 'error' ? 'badge-error' : event.type === 'timeout' ? 'badge-warning' : 'badge-info']">
                                                        {{ eventTypeLabel(event.type) }}
                                                    </span>
                                                    <span v-if="event.provider?.name" class="text-sm font-medium">{{ event.provider.name }}</span>
                                                </div>
                                                <div v-if="event.from_status || event.to_status" class="mt-1 text-xs text-base-content/70">
                                                    {{ event.from_status ?? '—' }} → {{ event.to_status ?? '—' }}
                                                    <span v-if="event.from_sub_status || event.to_sub_status">
                                                        / {{ event.from_sub_status ?? '—' }} → {{ event.to_sub_status ?? '—' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <DateTime class="justify-start text-xs sm:justify-end" :data="event.created_at" show-time/>
                                        </div>

                                        <pre class="mt-3 max-h-56 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap wrap-anywhere">{{ prettyJson(event.payload) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-box bg-base-200 p-4">
                        <pre class="max-h-[70vh] overflow-auto text-xs whitespace-pre-wrap wrap-anywhere">{{ selectedDealJson }}</pre>
                    </div>
                </template>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeDealModal">close</button>
            </form>
        </dialog>
    </div>
</template>
