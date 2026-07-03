<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, onMounted, ref} from "vue";
import {useViewStore} from "@/store/view.js";
import Pagination from "@/Components/Pagination/Pagination.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import DateTime from "@/Components/DateTime.vue";
import {walletBalanceTypeLabel} from "@/utils/walletBalanceTypeLabel.js";
import {useModalStore} from "@/store/modal.js";

const viewStore = useViewStore();
const modalStore = useModalStore();

const page = usePage();

/** Полный доступ к типам балансов в таблице — только при просмотре кошелька Super Admin в админке. */
const walletAdminFullView = computed(() => Boolean(page.props.walletAdminFullView));
const merchantWalletMode = computed(() => Boolean(page.props.merchantWalletMode));

const walletHistoryShowsBalanceType = computed(() => Boolean(page.props.walletHistoryShowsBalanceType));

const sharedReserveHistoryContext = computed(() => (
    walletHistoryShowsBalanceType.value
    && !walletAdminFullView.value
));

const showHistoryBalanceTypeColumn = computed(() => (
    walletAdminFullView.value
    || walletHistoryShowsBalanceType.value
));

const balanceTypeLabel = (balanceType) => walletBalanceTypeLabel(balanceType, {
    sharedReserveContext: sharedReserveHistoryContext.value || walletHistoryShowsBalanceType.value,
});

const user = page.props.user;
const invoices = ref(page.props.invoices);
const transactions = ref(page.props.transactions);
const walletDepositInvoices = computed(() => page.props.walletDepositInvoices ?? []);
const tabs = ref(page.props.tabs);
const filters = ref(page.props.filters);
const currentTab = ref(page.props.currentTab);
const currentFilters = ref(page.props.currentFilters);

router.on('success', (event) => {
    invoices.value = page.props.invoices;
    transactions.value = page.props.transactions;
    currentFilters.value = page.props.currentFilters;
});

const openPage = (page) => {
    if (viewStore.isAdminViewMode) {
        router.visit(route('admin.users.wallet.index', user.id), {
            data: {
                page,
                tab: currentTab.value,
                currentFilters: currentFilters.value,
            },
            preserveScroll: true
        })
    } else {
        router.visit(route(route().current(), route().params), {
            data: {
                page,
                tab: currentTab.value,
                currentFilters: currentFilters.value,
            },
            preserveScroll: true
        })
    }
}

const currentPage = ref(1);

onMounted(() => {
    let urlParams = new URLSearchParams(window.location.search);
    currentTab.value = urlParams.get('tab') ?? 'invoices'

    currentPage.value = urlParams.get('page') ?? 1;
})

/** Только при просмотре кошелька Super Admin: выгрузка после выбора типа кошелька, не «все». */
const isWalletExportBlocked = computed(() => {
    if (!walletAdminFullView.value) {
        return false;
    }
    const balanceTypes = currentFilters.value?.transactions?.balanceTypes;
    return !balanceTypes || balanceTypes === 'all';
});

const openTransactionsExport = () => {
    if (!user?.id || isWalletExportBlocked.value) {
        return;
    }

    let url = route('admin.users.wallet.transactions.export', user.id);
    if (walletAdminFullView.value) {
        const balanceType = currentFilters.value?.transactions?.balanceTypes;
        const joiner = url.includes('?') ? '&' : '?';
        url += joiner + 'balance_type=' + encodeURIComponent(balanceType);
    }
    window.open(url, '_blank');
};

const openWalletDepositInvoice = (invoice) => {
    modalStore.open(invoice.balance_type === 'reserve' ? 'leaderReserveDeposit' : 'traderDeposit', {
        invoice,
    });
};

const walletDepositStatus = (status) => {
    const statuses = {
        pending: { label: 'Ожидание оплаты', badge: 'badge-info' },
        processing: { label: 'Подтверждается', badge: 'badge-warning' },
        paid: { label: 'Зачислено', badge: 'badge-success' },
        expired: { label: 'Истёк', badge: 'badge-neutral' },
        cancelled: { label: 'Отменён', badge: 'badge-error' },
        amount_mismatch: { label: 'Проверка суммы', badge: 'badge-error' },
        failed: { label: 'Ошибка', badge: 'badge-error' },
    };

    return statuses[status] ?? { label: status, badge: 'badge-neutral' };
};
</script>

<template>
    <div class="card bg-base-100 border border-base-300/60 shadow-sm rounded-2xl">
        <div class="card-body gap-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-base-content">История операций</h2>

                <div class="flex items-center gap-2">
                    <div role="tablist" class="tabs tabs-box bg-base-200/60 p-1 gap-0.5">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            class="tab gap-2 px-3 sm:px-4"
                            :class="{ 'tab-active font-semibold': currentTab === tab.key }"
                            @click="currentTab = tab.key; openPage(1)"
                        >
                            {{ tab.name }}
                        </button>
                    </div>

                    <button
                        v-if="currentTab === 'transactions' && viewStore.isAdminViewMode && user?.id && !merchantWalletMode"
                        type="button"
                        class="btn btn-outline btn-sm gap-1.5 shrink-0"
                        :disabled="isWalletExportBlocked"
                        :title="isWalletExportBlocked ? 'Сначала выберите тип кошелька в фильтре' : 'Выгрузить в Excel'"
                        @click="openTransactionsExport"
                    >
                        <svg class="size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span class="hidden sm:inline">Excel</span>
                    </button>
                </div>
            </div>

            <div v-if="walletDepositInvoices.length" class="rounded-box border border-base-300 bg-base-200/40 p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <div class="font-medium">Крипто-инвойсы</div>
                        <div class="text-xs text-base-content/60">Последние локальные инвойсы USDT TRC20 можно открыть повторно.</div>
                    </div>
                    <span class="badge badge-outline">USDT TRC20</span>
                </div>

                <ul class="divide-y divide-base-300/60">
                    <li
                        v-for="depositInvoice in walletDepositInvoices"
                        :key="'wallet-deposit-' + depositInvoice.id"
                        class="flex flex-col gap-2 py-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ depositInvoice.amount }} USDT</span>
                                <span class="badge badge-sm badge-soft" :class="walletDepositStatus(depositInvoice.status).badge">
                                    {{ walletDepositStatus(depositInvoice.status).label }}
                                </span>
                                <span
                                    v-if="showHistoryBalanceTypeColumn && depositInvoice.balance_type"
                                    class="badge badge-ghost badge-xs"
                                >{{ balanceTypeLabel(depositInvoice.balance_type) }}</span>
                                <span
                                    v-if="depositInvoice.merchant"
                                    class="badge badge-outline badge-xs max-w-48 truncate"
                                >{{ depositInvoice.merchant.name }}</span>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-base-content/50">
                                <DateTime :data="depositInvoice.created_at" />
                                <span class="truncate">· {{ depositInvoice.address }}</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-outline btn-xs shrink-0"
                            @click="openWalletDepositInvoice(depositInvoice)"
                        >
                            Открыть оплату
                        </button>
                    </li>
                </ul>
            </div>

            <div
                v-if="filters[currentTab]"
                class="grid xl:grid-cols-4 sm:grid-cols-2 grid-cols-1 gap-2"
            >
                <select
                    v-for="(invoiceFilters, filterKey) in filters[currentTab]"
                    :key="filterKey"
                    class="select select-bordered select-sm w-full"
                    required
                    v-model="currentFilters[currentTab][filterKey]"
                    @change="openPage(1)"
                >
                    <option
                        v-for="filter in invoiceFilters"
                        :key="filter.key"
                        :value="filter.key"
                    >{{ filter.name }}</option>
                </select>
            </div>

            <div v-if="currentTab === 'invoices'">
                <TableEmptyState
                    v-if="!invoices?.data?.length"
                    title="Инвойсов пока нет"
                    description="По выбранным фильтрам записей о пополнениях и выводах пока нет — при появлении операций они появятся здесь."
                />
                <template v-else>
                    <ul class="divide-y divide-base-300/50">
                        <li
                            v-for="invoice in invoices.data"
                            :key="'inv-' + invoice.id"
                            class="flex items-center gap-3 py-3 first:pt-0"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="invoice.type === 'deposit' ? 'bg-success/10 text-success' : 'bg-error/10 text-error'"
                            >
                                <svg v-if="invoice.type === 'deposit'" class="size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0 6-6m-6 6-6-6" />
                                </svg>
                                <svg v-else class="size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6 6m6-6 6 6" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-base-content">
                                        {{ invoice.type === 'deposit' ? 'Пополнение' : 'Вывод' }}
                                    </span>
                                    <span class="text-xs text-base-content/40">#{{ invoice.id }}</span>
                                    <span
                                        v-if="showHistoryBalanceTypeColumn && invoice.balance_type"
                                        class="badge badge-ghost badge-xs"
                                    >{{ balanceTypeLabel(invoice.balance_type) }}</span>
                                <span
                                    v-if="invoice.merchant"
                                    class="badge badge-outline badge-xs max-w-48 truncate"
                                >{{ invoice.merchant.name }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-base-content/50 mt-0.5">
                                    <DateTime :data="invoice.created_at" />
                                    <span v-if="invoice.address" class="truncate hidden sm:inline">· {{ invoice.address }}</span>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <div
                                    class="font-semibold text-nowrap"
                                    :class="invoice.type === 'deposit' ? 'text-success' : 'text-error'"
                                >
                                    {{ invoice.type === 'deposit' ? '+' : '−' }}{{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                </div>
                                <div class="mt-0.5">
                                    <span v-if="invoice.status === 'success'" class="badge badge-sm badge-success badge-soft">Успешно</span>
                                    <span v-else-if="invoice.status === 'pending'" class="badge badge-sm badge-warning badge-soft">Ожидание</span>
                                    <span v-else-if="invoice.status === 'fail'" class="badge badge-sm badge-error badge-soft">Ошибка</span>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <Pagination
                        class="mt-3"
                        v-model="invoices.meta.current_page"
                        :total-items="invoices.meta.total"
                        previous-label="Назад" next-label="Вперед"
                        @page-changed="openPage"
                        :per-page="invoices.meta.per_page"
                    ></Pagination>
                </template>
            </div>

            <div v-if="currentTab === 'transactions'">
                <TableEmptyState
                    v-if="!transactions?.data?.length"
                    title="Операций пока нет"
                    description="По выбранным фильтрам движений по балансу пока нет — при появлении транзакций они отобразятся здесь."
                />
                <template v-else>
                    <ul class="divide-y divide-base-300/50">
                        <li
                            v-for="transaction in transactions.data"
                            :key="'tr-' + transaction.id"
                            class="flex items-center gap-3 py-3 first:pt-0"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="transaction.direction === 'in' ? 'bg-success/10 text-success' : 'bg-error/10 text-error'"
                            >
                                <svg v-if="transaction.direction === 'in'" class="size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0 6-6m-6 6-6-6" />
                                </svg>
                                <svg v-else class="size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6 6m6-6 6 6" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-base-content truncate">{{ transaction.type_name }}</span>
                                    <span class="text-xs text-base-content/40">#{{ transaction.id }}</span>
                                    <span
                                        v-if="showHistoryBalanceTypeColumn && transaction.balance_type"
                                        class="badge badge-ghost badge-xs"
                                    >{{ balanceTypeLabel(transaction.balance_type) }}</span>
                                <span
                                    v-if="transaction.merchant"
                                    class="badge badge-outline badge-xs max-w-48 truncate"
                                >{{ transaction.merchant.name }}</span>
                                </div>
                                <div class="text-xs text-base-content/50 mt-0.5">
                                    <DateTime :data="transaction.created_at" />
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <div
                                    class="font-semibold text-nowrap"
                                    :class="transaction.direction === 'in' ? 'text-success' : 'text-error'"
                                >
                                    {{ transaction.direction === 'in' ? '+' : '−' }}{{ transaction.amount }} {{ transaction.currency.toUpperCase() }}
                                </div>
                                <div class="mt-0.5">
                                    <span v-if="transaction.direction === 'in'" class="badge badge-sm badge-success badge-soft">Зачисление</span>
                                    <span v-else class="badge badge-sm badge-error badge-soft">Снятие</span>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <Pagination
                        class="mt-3"
                        v-model="transactions.meta.current_page"
                        :total-items="transactions.meta.total"
                        previous-label="Назад" next-label="Вперед"
                        @page-changed="openPage"
                        :per-page="transactions.meta.per_page"
                    ></Pagination>
                </template>
            </div>
        </div>
    </div>
</template>
