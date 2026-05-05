<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import AmountModifiedIndicator from '@/Components/AmountModifiedIndicator.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';
import OrderModal from '@/Modals/OrderModal.vue';
import EditOrderAmountModal from '@/Modals/Order/EditOrderAmountModal.vue';
import {useModalStore} from '@/store/modal.js';

const modalStore = useModalStore();
const cascadeDeals = ref(usePage().props.cascadeDeals);
const selectedDeal = ref(null);
const disputeDeal = ref(null);
const selectedDisputeDeal = ref(null);
const activeModalTab = ref('overview');
const receiptInput = ref(null);

const disputeForm = useForm({
    receipts: [],
});

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

const openDealAttemptsModal = (deal) => {
    selectedDeal.value = deal;
    activeModalTab.value = 'attempts';
};

const closeDealModal = () => {
    selectedDeal.value = null;
};

const openDisputeModal = (deal) => {
    disputeDeal.value = deal;
    disputeForm.reset();
    disputeForm.clearErrors();

    if (receiptInput.value) {
        receiptInput.value.value = '';
    }
};

const closeDisputeModal = () => {
    disputeDeal.value = null;
    disputeForm.reset();
    disputeForm.clearErrors();

    if (receiptInput.value) {
        receiptInput.value.value = '';
    }
};

const openCascadeDisputeModal = (deal) => {
    selectedDisputeDeal.value = deal;
};

const closeCascadeDisputeModal = () => {
    selectedDisputeDeal.value = null;
};

const openCascadeDisputeReceipt = (receipt) => {
    if (! receipt?.url) {
        return;
    }

    window.open(receipt.url, '_blank')?.focus();
};

const updateDisputeReceipts = (event) => {
    disputeForm.clearErrors('receipts');
    disputeForm.receipts = Array.from(event.target.files ?? []).slice(0, 3);
};

const submitDispute = () => {
    if (! disputeDeal.value) {
        return;
    }

    disputeForm.post(route('admin.cascade-deals.dispute.store', disputeDeal.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDisputeModal();
            router.reload({only: ['cascadeDeals']});
        },
    });
};

const openInternalOrderModal = (deal) => {
    if (! deal?.order_id) {
        return;
    }

    modalStore.openOrderModal({order_id: deal.order_id});
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

const selectedReceiptNames = computed(() => disputeForm.receipts.map((file) => file.name).join(', '));
const receiptErrors = computed(() => Object.entries(disputeForm.errors)
    .filter(([field]) => field === 'receipts' || field.startsWith('receipts.'))
    .map(([, message]) => message));
const selectedCascadeDisputeReceipts = computed(() => (selectedDisputeDeal.value?.dispute?.receipts ?? [])
    .flatMap((receiptBatch) => receiptBatch.files ?? []));
const selectedCascadeDisputeHistory = computed(() => {
    const items = selectedDisputeDeal.value?.dispute?.history ?? [];

    return [...items].reverse();
});
const selectedAmountHistory = computed(() => selectedDeal.value?.amount_history ?? []);
const selectedMerchantWalletTransactions = computed(() => selectedDeal.value?.wallet_transactions?.merchant ?? []);
const selectedProviderWalletTransactions = computed(() => selectedDeal.value?.wallet_transactions?.provider ?? []);

const formatFileSize = (size) => {
    if (! size) {
        return 'Размер неизвестен';
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toLocaleString('ru-RU', {maximumFractionDigits: 1})} КБ`;
    }

    return `${(size / 1024 / 1024).toLocaleString('ru-RU', {maximumFractionDigits: 1})} МБ`;
};

const formatCurrency = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return 'Пусто';
    }

    return `${amount} ${currency ?? ''}`.trim();
};

const getProviderName = (deal) => {
    return deal.selected_provider?.name ?? deal.selected_provider?.code ?? 'Не выбран';
};

const isExternalCascadeProvider = (deal) => deal?.selected_provider?.provider_type === 'external';

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

const amountHistorySourceLabel = (source) => ({
    provider_callback: 'Callback провайдера',
    internal_order: 'Внутренняя сделка',
}[source] ?? source ?? 'Источник не указан');

const walletTransactionTypeLabel = (type) => ({
    income_from_a_successful_cascade_deal: 'Зачисление мерчанту',
    rollback_income_from_a_successful_cascade_deal: 'Списание мерчанта (rollback)',
    cascade_provider_collateral_hold: 'Удержание залога провайдера',
    cascade_provider_collateral_release: 'Возврат залога провайдера',
}[type] ?? type ?? 'Операция');

const walletTransactionDirectionBadgeClass = (direction) => ({
    in: 'badge-success',
    out: 'badge-warning',
}[direction] ?? 'badge-ghost');

const transactionStatusBadgeClass = (status) => ({
    accepted: 'badge-success',
    opened: 'badge-info',
    failed_to_open: 'badge-error',
    cancelled: 'badge-warning',
}[status] ?? 'badge-ghost');

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
                                    <div class="flex flex-nowrap items-baseline gap-1.5">
                                        <div class="text-nowrap text-base-content">
                                            {{ deal.amount }}
                                            <span class="text-primary/70">{{ (deal.currency ?? '').toUpperCase() }}</span>
                                        </div>
                                        <AmountModifiedIndicator :modified="deal.amount_was_modified" />
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
                                    <div class="inline-flex items-center justify-end gap-1">
                                        <button
                                            v-if="deal.can_open_dispute"
                                            type="button"
                                            class="btn btn-warning btn-outline btn-xs"
                                            aria-label="Открыть спор по каскадной сделке"
                                            @click.prevent="openDisputeModal(deal)"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="deal.can_view_cascade_dispute"
                                            type="button"
                                            class="btn btn-warning btn-outline btn-xs"
                                            aria-label="Просмотр каскадного спора"
                                            @click.prevent="openCascadeDisputeModal(deal)"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="deal.order_id"
                                            type="button"
                                            class="btn btn-accent btn-outline btn-xs"
                                            aria-label="Открыть локальную сделку (внутренний контур)"
                                            @click.prevent="openInternalOrderModal(deal)"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-info btn-outline btn-xs"
                                            aria-label="Открыть попытки провайдеров"
                                            @click.prevent="openDealAttemptsModal(deal)"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16M8 4v16m8-16v16"/>
                                            </svg>
                                        </button>
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
                                    </div>
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
                                    <div class="flex flex-nowrap items-baseline gap-1.5">
                                        <div class="text-nowrap text-base-content">
                                            {{ deal.amount }}
                                            <span class="text-primary/70">{{ (deal.currency ?? '').toUpperCase() }}</span>
                                        </div>
                                        <AmountModifiedIndicator :modified="deal.amount_was_modified" />
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

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <DateTime class="justify-start text-xs" :data="deal.created_at"/>
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    <button
                                        v-if="deal.can_open_dispute"
                                        type="button"
                                        class="btn btn-warning btn-outline btn-xs"
                                        aria-label="Открыть спор по каскадной сделке"
                                        @click.prevent="openDisputeModal(deal)"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                    </button>
                                    <button
                                        v-if="deal.can_view_cascade_dispute"
                                        type="button"
                                        class="btn btn-warning btn-outline btn-xs"
                                        aria-label="Просмотр каскадного спора"
                                        @click.prevent="openCascadeDisputeModal(deal)"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                            <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                        </svg>
                                    </button>
                                    <button
                                        v-if="deal.order_id"
                                        type="button"
                                        class="btn btn-accent btn-outline btn-xs"
                                        aria-label="Открыть локальную сделку (внутренний контур)"
                                        @click.prevent="openInternalOrderModal(deal)"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                            <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-info btn-outline btn-xs"
                                        aria-label="Открыть попытки провайдеров"
                                        @click.prevent="openDealAttemptsModal(deal)"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16M8 4v16m8-16v16"/>
                                        </svg>
                                    </button>
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
                                </div>
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
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'attempts' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'attempts'">
                                Попытки
                            </button>
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'logs' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'logs'">
                                Логи
                            </button>
                            <button type="button" :class="['btn btn-sm join-item', activeModalTab === 'wallets' ? 'btn-primary' : 'btn-outline']" @click="activeModalTab = 'wallets'">
                                Кошельки
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
                                    <div class="flex flex-wrap items-baseline gap-1.5">
                                        <span>Сумма: {{ formatCurrency(selectedDeal.amount, selectedDeal.currency) }}</span>
                                        <AmountModifiedIndicator :modified="selectedDeal.amount_was_modified" />
                                    </div>
                                    <div>Initial: {{ formatCurrency(selectedDeal.initial_amount, selectedDeal.currency) }}</div>
                                    <div>USDT amount: {{ formatCurrency(selectedDeal.usdt_amount, selectedDeal.base_currency) }}</div>
                                    <div>Платим мерчанту: {{ formatCurrency(selectedDeal.credit, selectedDeal.base_currency) }}</div>
                                    <div v-if="isExternalCascadeProvider(selectedDeal)">
                                        Провайдер платит нам: {{ formatCurrency(selectedDeal.debit, selectedDeal.base_currency) }}
                                    </div>
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

                        <div class="card bg-base-200 md:col-span-2">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">История изменений суммы</h4>
                                    <span class="badge badge-ghost badge-sm">{{ selectedAmountHistory.length }} событие(й)</span>
                                </div>

                                <div v-if="! selectedAmountHistory.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    Изменений суммы по этой каскадной сделке пока нет.
                                </div>

                                <div v-else class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Дата</th>
                                                <th>Источник</th>
                                                <th>Было</th>
                                                <th>Стало</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in selectedAmountHistory" :key="item.id">
                                                <td>
                                                    <DateTime class="justify-start text-xs" :data="item.created_at" show-time/>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info badge-outline badge-sm">
                                                        {{ amountHistorySourceLabel(item.source) }}
                                                    </span>
                                                </td>
                                                <td>{{ formatCurrency(item.old_amount, item.currency) }}</td>
                                                <td class="font-medium">{{ formatCurrency(item.new_amount, item.currency) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeModalTab === 'attempts'" class="card bg-base-200">
                        <div class="card-body p-4">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-semibold">Попытки провайдеров</h4>
                                <span class="badge badge-info badge-outline badge-sm">{{ selectedDeal.transactions?.length ?? 0 }} показано</span>
                            </div>

                            <div v-if="! selectedDeal.transactions?.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                Для этой сделки пока нет попыток создания у провайдеров.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="transaction in selectedDeal.transactions"
                                    :key="transaction.id"
                                    class="collapse collapse-arrow rounded-box border border-base-300 bg-base-100"
                                >
                                    <input type="checkbox" />
                                    <div class="collapse-title">
                                        <div class="flex flex-col gap-2 pr-6 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span :class="['badge badge-sm', transactionStatusBadgeClass(transaction.status)]">
                                                        {{ transaction.status_name ?? transaction.status ?? 'Без статуса' }}
                                                    </span>
                                                    <span class="font-medium">{{ transaction.provider?.name ?? transaction.provider?.code ?? 'Провайдер не найден' }}</span>
                                                    <span v-if="transaction.provider?.provider_type" class="badge badge-ghost badge-sm">{{ transaction.provider.provider_type }}</span>
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                                    <span>Attempt #{{ transaction.id }}</span>
                                                    <span v-if="transaction.provider_deal_id">Provider deal ID: {{ transaction.provider_deal_id }}</span>
                                                </div>
                                            </div>
                                            <DateTime class="justify-start text-xs sm:justify-end" :data="transaction.created_at" show-time/>
                                        </div>
                                    </div>
                                    <div class="collapse-content">
                                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                            <div class="rounded-box bg-base-200 p-3 text-sm">
                                                <div class="mb-2 font-semibold">Экономика попытки</div>
                                                <div class="space-y-1">
                                                    <div>USDT amount: {{ formatCurrency(transaction.usdt_amount, selectedDeal.base_currency) }}</div>
                                                    <div>Credit: {{ formatCurrency(transaction.credit, selectedDeal.base_currency) }}</div>
                                                </div>
                                            </div>

                                            <div v-if="transaction.error_code || transaction.error_message" class="rounded-box border border-error/20 bg-error/5 p-3">
                                                <div class="mb-1 text-sm font-semibold text-error">Ошибка открытия</div>
                                                <div v-if="transaction.error_code" class="mb-2 wrap-anywhere font-mono text-xs text-error/80">{{ transaction.error_code }}</div>
                                                <pre v-if="transaction.error_message" class="max-h-36 overflow-auto whitespace-pre-wrap wrap-anywhere text-xs text-error">{{ transaction.error_message }}</pre>
                                            </div>

                                            <div>
                                                <div class="mb-1 text-xs font-semibold">Payload запроса</div>
                                                <pre class="max-h-80 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap wrap-anywhere">{{ prettyJson(transaction.request_payload) }}</pre>
                                            </div>
                                            <div>
                                                <div class="mb-1 text-xs font-semibold">Payload ответа</div>
                                                <pre class="max-h-80 overflow-auto rounded bg-base-200 p-3 text-xs whitespace-pre-wrap wrap-anywhere">{{ prettyJson(transaction.response_payload) }}</pre>
                                            </div>
                                        </div>
                                    </div>
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
                                                    <span class="text-xs text-base-content/70">{{ log.operation_label ?? log.operation }}</span>
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

                    <div v-else-if="activeModalTab === 'wallets'" class="grid grid-cols-1 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">Транзакции кошелька мерчанта</h4>
                                    <span class="badge badge-primary badge-outline badge-sm">{{ selectedMerchantWalletTransactions.length }} найдено</span>
                                </div>

                                <div v-if="! selectedMerchantWalletTransactions.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    По кошельку мерчанта не найдено операций каскада в окне этой сделки.
                                </div>

                                <div v-else class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Тип</th>
                                                <th>Направление</th>
                                                <th>Сумма</th>
                                                <th>Баланс</th>
                                                <th>Дата</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="transaction in selectedMerchantWalletTransactions" :key="`merchant-wallet-${transaction.id}`">
                                                <td>{{ transaction.id }}</td>
                                                <td>{{ walletTransactionTypeLabel(transaction.type) }}</td>
                                                <td>
                                                    <span :class="['badge badge-sm', walletTransactionDirectionBadgeClass(transaction.direction)]">
                                                        {{ transaction.direction ?? '—' }}
                                                    </span>
                                                </td>
                                                <td>{{ formatCurrency(transaction.amount, transaction.currency) }}</td>
                                                <td>{{ transaction.balance_type ?? '—' }}</td>
                                                <td>
                                                    <DateTime class="justify-start text-xs" :data="transaction.created_at" show-time/>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">Транзакции кошелька провайдера</h4>
                                    <span class="badge badge-info badge-outline badge-sm">{{ selectedProviderWalletTransactions.length }} найдено</span>
                                </div>

                                <div v-if="! selectedProviderWalletTransactions.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    По кошельку провайдера не найдено операций залога в окне этой сделки.
                                </div>

                                <div v-else class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Тип</th>
                                                <th>Направление</th>
                                                <th>Сумма</th>
                                                <th>Баланс</th>
                                                <th>Дата</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="transaction in selectedProviderWalletTransactions" :key="`provider-wallet-${transaction.id}`">
                                                <td>{{ transaction.id }}</td>
                                                <td>{{ walletTransactionTypeLabel(transaction.type) }}</td>
                                                <td>
                                                    <span :class="['badge badge-sm', walletTransactionDirectionBadgeClass(transaction.direction)]">
                                                        {{ transaction.direction ?? '—' }}
                                                    </span>
                                                </td>
                                                <td>{{ formatCurrency(transaction.amount, transaction.currency) }}</td>
                                                <td>{{ transaction.balance_type ?? '—' }}</td>
                                                <td>
                                                    <DateTime class="justify-start text-xs" :data="transaction.created_at" show-time/>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
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

        <dialog :open="Boolean(selectedDisputeDeal)" class="modal">
            <div class="modal-box max-w-4xl">
                <form method="dialog">
                    <button
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        @click="closeCascadeDisputeModal"
                    >
                        ✕
                    </button>
                </form>

                <template v-if="selectedDisputeDeal">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-bold text-lg">Каскадный спор</h3>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                <span>PayIn:</span>
                                <CopyableOrderUid :uuid="selectedDisputeDeal.uuid ?? ''"/>
                                <span class="badge badge-warning badge-outline badge-sm">
                                    {{ selectedDisputeDeal.dispute?.status_name ?? selectedDisputeDeal.dispute?.status ?? 'Открыт' }}
                                </span>
                            </div>
                        </div>
                        <DateTime class="justify-start text-xs sm:justify-end" :data="selectedDisputeDeal.updated_at" show-time/>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Сделка</h4>
                                <div class="text-sm space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">UUID:</span>
                                        <CopyableOrderUid :uuid="selectedDisputeDeal.uuid ?? ''"/>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">External ID:</span>
                                        <CopyableOrderUid :uuid="selectedDisputeDeal.external_id ?? ''"/>
                                    </div>
                                    <div>Мерчант: {{ selectedDisputeDeal.merchant?.name ?? 'Пусто' }}</div>
                                    <div>Интеграция: {{ getProviderName(selectedDisputeDeal) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Сумма</h4>
                                <div class="text-sm space-y-1">
                                    <div>Сумма: {{ formatCurrency(selectedDisputeDeal.amount, selectedDisputeDeal.currency) }}</div>
                                    <div>USDT amount: {{ formatCurrency(selectedDisputeDeal.usdt_amount, selectedDisputeDeal.base_currency) }}</div>
                                    <div>Provider deal ID: {{ selectedDisputeDeal.selected_transaction?.provider_deal_id ?? 'Пусто' }}</div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedDisputeDeal.dispute?.status === 'rejected'"
                            class="card bg-base-200 lg:col-span-2"
                        >
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Причина отклонения</h4>
                                <div class="rounded-box bg-base-100 p-3 text-sm whitespace-pre-wrap wrap-anywhere">
                                    {{ selectedDisputeDeal.dispute?.reason?.trim() ? selectedDisputeDeal.dispute.reason : 'Причина не указана' }}
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200 lg:col-span-2">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">Файлы спора</h4>
                                    <span class="badge badge-info badge-outline badge-sm">{{ selectedCascadeDisputeReceipts.length }} файл(ов)</span>
                                </div>

                                <div v-if="! selectedCascadeDisputeReceipts.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    К открытому каскадному спору файлы не приложены.
                                </div>

                                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="receipt in selectedCascadeDisputeReceipts"
                                        :key="receipt.url ?? receipt.hash_name ?? receipt.original_name"
                                        class="rounded-box border border-base-300 bg-base-100 p-3"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium">
                                                    {{ receipt.original_name ?? receipt.hash_name ?? 'Файл спора' }}
                                                </div>
                                                <div class="mt-1 text-xs text-base-content/60">
                                                    {{ receipt.mime_type ?? receipt.extension ?? 'Тип неизвестен' }}
                                                    <span class="px-1">·</span>
                                                    {{ formatFileSize(receipt.size) }}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-info btn-outline btn-xs"
                                                :disabled="! receipt.url"
                                                @click.prevent="openCascadeDisputeReceipt(receipt)"
                                            >
                                                Открыть
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200 lg:col-span-2">
                            <div class="card-body p-4">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold">История</h4>
                                    <span class="badge badge-ghost badge-sm">{{ selectedCascadeDisputeHistory.length }} событие(й)</span>
                                </div>

                                <div v-if="! selectedCascadeDisputeHistory.length" class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/60">
                                    История каскадного спора пока пустая.
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(historyItem, index) in selectedCascadeDisputeHistory"
                                        :key="`${historyItem.changed_at ?? index}-${historyItem.status ?? 'status'}`"
                                        class="rounded-box border border-base-300 bg-base-100 p-3"
                                    >
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <span class="badge badge-warning badge-outline badge-sm">
                                                    {{ historyItem.status ?? 'Без статуса' }}
                                                </span>
                                                <div v-if="historyItem.reason" class="mt-2 text-sm whitespace-pre-wrap wrap-anywhere">
                                                    {{ historyItem.reason }}
                                                </div>
                                                <div v-if="historyItem.dispute_id || historyItem.provider_deal_id" class="mt-2 text-xs text-base-content/60">
                                                    <span v-if="historyItem.dispute_id">Dispute ID: {{ historyItem.dispute_id }}</span>
                                                    <span v-if="historyItem.dispute_id && historyItem.provider_deal_id" class="px-1">·</span>
                                                    <span v-if="historyItem.provider_deal_id">Provider deal ID: {{ historyItem.provider_deal_id }}</span>
                                                </div>
                                            </div>
                                            <DateTime class="justify-start text-xs sm:justify-end" :data="historyItem.changed_at" show-time/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeCascadeDisputeModal">close</button>
            </form>
        </dialog>

        <dialog :open="Boolean(disputeDeal)" class="modal">
            <div class="modal-box max-w-xl">
                <form method="dialog">
                    <button
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        @click="closeDisputeModal"
                    >
                        ✕
                    </button>
                </form>

                <template v-if="disputeDeal">
                    <h3 class="font-bold text-lg">Открыть спор по каскадной сделке</h3>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                        <span>PayIn:</span>
                        <CopyableOrderUid :uuid="disputeDeal.uuid ?? ''"/>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="submitDispute">
                        <label class="form-control">
                            <div class="label">
                                <span class="label-text">Чеки</span>
                                <span class="label-text-alt">До 3 файлов, необязательно</span>
                            </div>
                            <input
                                ref="receiptInput"
                                type="file"
                                multiple
                                accept=".jpeg,.jpg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                class="file-input file-input-bordered w-full"
                                @change="updateDisputeReceipts"
                            />
                            <div v-if="selectedReceiptNames" class="label">
                                <span class="label-text-alt truncate">Выбрано: {{ selectedReceiptNames }}</span>
                            </div>
                            <div v-if="receiptErrors.length" class="mt-2 space-y-1">
                                <div v-for="error in receiptErrors" :key="error" class="text-xs text-error">
                                    {{ error }}
                                </div>
                            </div>
                        </label>

                        <div class="modal-action">
                            <button type="button" class="btn btn-ghost" :disabled="disputeForm.processing" @click="closeDisputeModal">
                                Отмена
                            </button>
                            <button type="submit" class="btn btn-warning" :disabled="disputeForm.processing">
                                {{ disputeForm.processing ? 'Открытие...' : 'Открыть спор' }}
                            </button>
                        </div>
                    </form>
                </template>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeDisputeModal">close</button>
            </form>
        </dialog>

        <OrderModal/>
        <EditOrderAmountModal/>
    </div>
</template>
