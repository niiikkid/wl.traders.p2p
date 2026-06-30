<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Partials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import SearchableDropdownFilter from '@/Components/Filters/Partials/SearchableDropdownFilter.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import Modal from '@/Components/Modals/Modal.vue';
import {useModalStore} from '@/store/modal.js';
import {useTableFiltersStore} from '@/store/tableFilters.js';
import PayoutSettingsModal from '@/Modals/Payout/PayoutSettingsModal.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import PageToolbar from '@/Components/Table/PageToolbar.vue';
import PageToolbarAction from '@/Components/Table/PageToolbarAction.vue';

const tableFiltersStore = useTableFiltersStore();

const payouts = computed(() => usePage().props.payouts ?? { data: [] });
const payoutItems = computed(() => payouts.value?.data ?? []);
const traders = computed(() => usePage().props.traders ?? []);
const expandedRows = ref({});
const statusUpdatingId = ref(null);
const modalStore = useModalStore();
const selectedTraders = ref({});
const traderModal = ref({
    open: false,
    payout: null,
    option: null,
    traderId: null,
    error: null,
});

const toggleRow = (id) => {
    expandedRows.value[id] = !expandedRows.value[id];
};

const isExpanded = (id) => !!expandedRows.value[id];

const statusClasses = {
    open: 'badge-warning',
    taken: 'badge-info',
    sent: 'badge-accent',
    completed: 'badge-success',
    canceled: 'badge-error',
};

const statusBadge = (status) => statusClasses[status] ?? 'badge-ghost';

const resolveBankName = (payout) => payout?.bank_name ?? payout?.payment_gateway?.name ?? '—';

const statusOptions = [
    {
        value: 'open',
        label: 'Открыта',
        hint: 'Вернём в стакан: сброс трейдера, холда и пересоздание истечения.',
        requiresTrader: false,
    },
    {
        value: 'taken',
        label: 'В работе',
        hint: 'Закрепим за трейдером и остановим авто-отмену по истечению.',
        requiresTrader: true,
    },
    {
        value: 'sent',
        label: 'Отправлено',
        hint: 'Запустит холд (если включён) или сразу завершит выплату.',
        requiresTrader: true,
    },
    {
        value: 'completed',
        label: 'Завершена',
        hint: 'Начислим трейдеру и закроем холд/резервы.',
        requiresTrader: true,
    },
    {
        value: 'canceled',
        label: 'Отменена',
        hint: 'Вернём резерв мерчанту, очистим трейдера и холд.',
        requiresTrader: false,
    },
];

const getAvailableOptions = (payout) => {
    // Разрешаем "open" только из open или canceled, чтобы не нарушать деньги
    const allowed = statusOptions.filter((option) => {
        if (option.value === payout.status) {
            return false;
        }

        if (option.value === 'open') {
            return payout.status === 'open' || payout.status === 'canceled';
        }

        if (payout.status === 'canceled') {
            // из canceled разрешаем только open (открытый стакан)
            return false;
        }

        return true;
    });

    return allowed;
};

const getSelectedTrader = (payoutId) => selectedTraders.value[payoutId] ?? null;

const setSelectedTrader = (payoutId, traderId) => {
    selectedTraders.value[payoutId] = traderId;
};

const getTraderLabel = (id) => {
    const trader = traders.value.find((item) => item.id === id);
    return trader ? `${trader.name ?? trader.email} (${trader.email})` : 'не выбран';
};

const openTraderModal = (payout, option) => {
    const preset = payout.trader?.id ?? getSelectedTrader(payout.id) ?? traders.value[0]?.id ?? null;
    traderModal.value = {
        open: true,
        payout,
        option,
        traderId: preset,
        error: null,
    };
};

const closeTraderModal = () => {
    traderModal.value.open = false;
    traderModal.value.error = null;
};

const buildStatusBody = (payout, option) => {
    const selectedTraderId = traderModal.value.open
        ? traderModal.value.traderId
        : getSelectedTrader(payout.id);
    const traderText = option.requiresTrader
        ? `Трейдер: ${payout.trader?.email ?? getTraderLabel(selectedTraderId)}.`
        : null;

    const lines = [
        `Текущий статус: ${payout.status_label}.`,
        `Новый статус: ${option.label}.`,
        option.hint,
        traderText,
        'Будут обновлены связанные резервы и отложенные джобы.',
    ].filter(Boolean);

    return lines.join(' ');
};

const sendStatusChange = (payout, option, forcedTraderId = null) => {
    statusUpdatingId.value = payout.id;

    const traderId = forcedTraderId ?? payout.trader?.id ?? getSelectedTrader(payout.id) ?? null;
    const payload = { status: option.value };

    if (traderId) {
        payload.trader_id = traderId;
    }

    if (option.requiresTrader && !payload.trader_id) {
        statusUpdatingId.value = null;
        modalStore.openConfirmModal({
            title: 'Выберите трейдера',
            body: 'Для перевода в этот статус нужно выбрать активного трейдера (выплаты включены, онлайн).',
            confirm_button_name: 'Понятно',
            cancel_button_name: 'Закрыть',
            confirm: () => {},
        });
        return;
    }

    router.patch(route('admin.payouts.status.update', payout.id), payload, {
        preserveScroll: true,
        onFinish: () => {
            statusUpdatingId.value = null;
        },
        onError: () => {
            statusUpdatingId.value = null;
        },
    });
};

const confirmTraderModal = () => {
    const { payout, option, traderId } = traderModal.value;

    if (! traderId) {
        traderModal.value.error = 'Выберите трейдера';
        return;
    }

    setSelectedTrader(payout.id, traderId);
    closeTraderModal();
    sendStatusChange(payout, option, traderId);
};

const openStatusConfirm = (payout, option) => {
    if (statusUpdatingId.value === payout.id) {
        return;
    }

    if (option.requiresTrader) {
        if (payout.trader?.id) {
            modalStore.openConfirmModal({
                title: `Сменить статус выплаты ${payout.uuid}?`,
                body: buildStatusBody(payout, option),
                confirm_button_name: 'Сменить',
                cancel_button_name: 'Отмена',
                confirm: () => sendStatusChange(payout, option, payout.trader.id),
            });
            return;
        }

        openTraderModal(payout, option);
        return;
    }

    modalStore.openConfirmModal({
        title: `Сменить статус выплаты ${payout.uuid}?`,
        body: buildStatusBody(payout, option),
        confirm_button_name: 'Сменить',
        cancel_button_name: 'Отмена',
        confirm: () => sendStatusChange(payout, option),
    });
};

const payoutReceiptLinks = (payout) => {
    if (Array.isArray(payout?.receipt_urls)) {
        return payout.receipt_urls;
    }

    if (payout?.receipt_url) {
        return [{ id: null, filename: 'Чек 1', url: payout.receipt_url }];
    }

    return [];
};

const openAdminPayoutsExport = () => {
    const url = route('admin.payouts.export', tableFiltersStore.getQueryData);
    window.open(url, '_blank');
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Выплаты" />

        <MainTableSection
            title="Выплаты"
            :data="payouts"
        >
            <template #button>
                <PageToolbar>
                    <PageToolbarAction
                        icon="export"
                        title="Выгрузить выплаты"
                        label="Выгрузить"
                        @click="openAdminPayoutsExport"
                    />

                    <PageToolbarAction
                        icon="bulk-settings"
                        title="Настройки выплат"
                        label="Настройки"
                        @click="modalStore.openPayoutSettingsModal()"
                    />
                </PageToolbar>
            </template>
            <template #header>
                <div class="space-y-4">
                    <FiltersPanel name="admin-payouts">
                        <DateFilter name="startDate" title="Создано с" />
                        <DateFilter name="endDate" title="Создано по" />
                        <InputFilter name="uuid" placeholder="UUID" />
                        <InputFilter name="externalID" placeholder="External ID" />
                        <InputFilter name="paymentDetail" placeholder="Реквизит" />
                        <SearchableDropdownFilter
                            name="merchantIds"
                            title="Мерчанты"
                            placeholder="Поиск магазина мерчанта..."
                            route-name="admin.main.filter-options"
                            request-type="merchant"
                            request-mode="payouts"
                        />
                        <InputFilter name="user" placeholder="Трейдер" />
                        <DropdownFilter name="payoutStatuses" title="Статусы" />
                        <DropdownFilter name="payoutMethodTypes" title="Типы реквизитов" />
                        <InputFilter name="paymentGateway" placeholder="Банк / метод" />
                        <InputFilter name="amount" placeholder="Сумма (точная)" />
                        <InputFilter name="minAmount" placeholder="Мин. сумма" />
                        <InputFilter name="maxAmount" placeholder="Макс. сумма" />
                        <InputFilter name="currency" placeholder="Валюта (например, RUB)" />
                    </FiltersPanel>
                </div>
            </template>
            <template #body>
                <div class="relative">
                    <DataTable>
                        <template #head>
                            <th scope="col">
                                <span class="ml-2">UUID</span>
                            </th>
                            <th>Реквизиты</th>
                            <th>Сумма</th>
                            <th>Курс</th>
                            <th>Статус</th>
                            <th>Стороны сделки</th>
                            <th class="w-24 text-center">Подробнее</th>
                            <th class="w-16 text-right">
                                <span class="sr-only">Действия</span>
                            </th>
                        </template>
                        <template v-for="payout in payoutItems" :key="payout.id">
                            <tr class="bg-base-100 border-base-200 border-b last:border-none align-top">
                                <th scope="row" class="font-medium whitespace-nowrap text-base-content">
                                    <div class="flex max-w-full flex-nowrap items-center gap-3 ml-2">
                                        <div class="w-[4rem] min-w-[4rem] shrink-0 overflow-hidden">
                                            <CopyableOrderUid
                                                :uuid="payout.uuid ?? ''"
                                                class="block max-w-full truncate text-left text-base-content"
                                            />
                                        </div>
                                    </div>
                                </th>
                                <td>
                                    <div class="min-w-0">
                                        <div class="text-nowrap text-base-content">{{ payout.requisites }}</div>
                                        <div class="text-xs text-base-content/60">
                                            {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="text-nowrap text-base-content">
                                            <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                        </div>
                                        <div class="text-nowrap text-xs text-base-content/60">
                                            <MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" compact secondary />
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">
                                        <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                    </div>
                                </td>
                                <td>
                                    <div
                                        :class="['badge badge-outline badge-sm font-normal', statusBadge(payout.status)]"
                                    >
                                        {{ payout.status_label }}
                                    </div>
                                </td>
                                <td>
                                    <div class="space-y-1 text-xs text-base-content">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="shrink-0 uppercase text-base-content/50">М:</span>
                                            <span class="truncate">{{ payout.merchant?.owner?.email ?? '—' }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="shrink-0 uppercase text-base-content/50">Т:</span>
                                            <span class="truncate">{{ payout.trader?.email ?? '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center align-center">
                                    <button
                                        class="btn btn-ghost btn-xs text-xs"
                                        type="button"
                                        @click="toggleRow(payout.id)"
                                    >
                                        <span>{{ isExpanded(payout.id) ? 'Скрыть' : 'Подробнее' }}</span>
                                        <svg class="size-4 transition-transform" :class="{'rotate-180': isExpanded(payout.id)}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="text-right align-top">
                                    <TableActionsDropdown>
                                        <TableAction
                                            v-for="option in getAvailableOptions(payout)"
                                            :key="`${payout.id}-${option.value}`"
                                            @click="openStatusConfirm(payout, option)"
                                        >
                                            <div class="flex flex-col text-left">
                                                <span class="text-xs font-medium">{{ option.label }}</span>
                                                <span class="text-[10px] text-base-content/60">{{ option.hint }}</span>
                                            </div>
                                        </TableAction>
                                    </TableActionsDropdown>
                                </td>
                            </tr>
                            <tr v-if="isExpanded(payout.id)" class="bg-base-100 border-base-200 border-b last:border-none">
                                <td colspan="8">
                                    <div class="bg-base-200/40 border border-base-300 rounded-box p-3 space-y-3 text-xs">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-[10px] uppercase text-base-content/50">External ID</span>
                                            <DisplayID v-if="payout.external_id" :id="payout.external_id" />
                                            <span v-else class="text-base-content/40">—</span>
                                        </div>

                                        <div class="grid grid-cols-2 xl:grid-cols-4 gap-2">
                                            <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                <div class="text-[10px] uppercase text-base-content/50">Клиент</div>
                                                <div class="font-medium">
                                                    <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                                </div>
                                            </div>
                                            <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                <div class="text-[10px] uppercase text-base-content/50">Тело / Списано</div>
                                                <div class="font-medium">
                                                    <MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" compact />
                                                </div>
                                                <div class="font-medium">
                                                    <MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact />
                                                </div>
                                            </div>
                                            <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                <div class="text-[10px] uppercase text-base-content/50">Курс / Ставка</div>
                                                <div class="font-medium">
                                                    <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                                </div>
                                                <div class="font-medium">{{ payout.commissions?.total ?? '—' }}%</div>
                                            </div>
                                            <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                <div class="text-[10px] uppercase text-base-content/50">Тайминг</div>
                                                <DateTime :data="payout.timings.created_at" simple class="justify-start font-medium" />
                                                <DateTime v-if="payout.timings.completed_at" :data="payout.timings.completed_at" simple class="justify-start font-medium" />
                                                <DateTime v-else :data="payout.timings.expires_at" simple class="justify-start font-medium" />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 border-t border-base-content/10 pt-2">
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Банковские данные</div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Метод</span>
                                                    <span class="font-medium text-right">{{ payout.payout_method_type.label }}</span>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Платёжный метод</span>
                                                    <span class="font-medium text-right">
                                                        <template v-if="payout.bank_name">{{ payout.bank_name }}</template>
                                                        <template v-else>{{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})</template>
                                                    </span>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Реквизит</span>
                                                    <span class="font-medium text-right break-all">{{ payout.requisites }}</span>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Получатель</span>
                                                    <span class="font-medium text-right">{{ payout.initials ?? '—' }}</span>
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Участники</div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Мерчант</span>
                                                    <span class="font-medium text-right">{{ payout.merchant?.name ?? '—' }}</span>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Владелец</span>
                                                    <span class="font-medium text-right">{{ payout.merchant?.owner?.email ?? '—' }}</span>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-base-content/60">Трейдер</span>
                                                    <span class="font-medium text-right">{{ payout.trader?.email ?? '—' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 border-t border-base-content/10 pt-2">
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Комиссии</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Всего</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.fees.total" :currency="payout.fees.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Трейдер</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.fees.trader" :currency="payout.fees.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Тимлид</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.fees.teamlead" :currency="payout.fees.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Сервис</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.fees.service" :currency="payout.fees.currency" compact /></span>
                                                </div>
                                                <div class="grid grid-cols-3 gap-2 pt-1 text-[11px]">
                                                    <div>Итого: <span class="font-medium">{{ payout.commissions.total ?? '—' }}%</span></div>
                                                    <div>Трейдер: <span class="font-medium">{{ payout.commissions.trader ?? '—' }}%</span></div>
                                                    <div>Тимлид: <span class="font-medium">{{ payout.commissions.teamlead ?? '—' }}%</span></div>
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Суммы</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Клиенту</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Списано у мерчанта</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Получит трейдер</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.trader_credit?.value" :currency="payout.trader_credit?.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Получит Team Leader</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.fees.teamlead ?? '0'" :currency="payout.fees.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Тело (USDT)</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" compact /></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 border-t border-base-content/10 pt-2">
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Курс</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Маркет</span>
                                                    <span class="font-medium">{{ payout.rate.market ?? '—' }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Цена</span>
                                                    <span class="font-medium"><MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact /></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Зафиксирован</span>
                                                    <DateTime :data="payout.rate.fixed_at" simple class="justify-end font-medium" />
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Тайминг</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Создано</span>
                                                    <DateTime :data="payout.timings.created_at" simple class="justify-end font-medium" />
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Истекает</span>
                                                    <DateTime :data="payout.timings.expires_at" simple class="justify-end font-medium" />
                                                </div>
                                                <div v-if="payout.timings.completed_at" class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Завершено</span>
                                                    <DateTime :data="payout.timings.completed_at" simple class="justify-end font-medium" />
                                                </div>
                                                <div v-if="payout.timings.hold_until" class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Холд до</span>
                                                    <DateTime :data="payout.timings.hold_until" simple class="justify-end font-medium" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border-t border-base-content/10 pt-2 space-y-1">
                                            <div class="text-[10px] uppercase text-base-content/50">Чеки</div>
                                            <div v-if="payoutReceiptLinks(payout).length" class="flex flex-wrap gap-1.5">
                                                <a
                                                    v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                                    :key="`receipt-desktop-${payout.id}-${receipt.id ?? index}`"
                                                    :href="receipt.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-xs btn-secondary btn-outline min-h-0 h-6 px-2.5"
                                                >
                                                    Чек {{ index + 1 }}
                                                </a>
                                            </div>
                                            <div v-else class="text-base-content/60">Чек ещё не загружен.</div>
                                            <div v-if="payoutReceiptLinks(payout).length" class="text-[10px] text-base-content/50">
                                                Доступ только авторизованным пользователям.
                                            </div>
                                        </div>

                                        <div class="border-t border-base-content/10 pt-2 space-y-2">
                                            <div class="text-[10px] uppercase text-base-content/50">Операции</div>
                                            <div v-if="(payout.operations ?? []).length" class="overflow-x-auto border border-base-300 rounded-box">
                                                <table class="table table-xs">
                                                    <thead class="bg-base-200 text-[11px] uppercase">
                                                        <tr>
                                                            <th>Тип</th>
                                                            <th>Сумма</th>
                                                            <th>Дата</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="operation in payout.operations" :key="operation.id" class="align-top">
                                                            <td class="font-medium">{{ operation.type_label }}</td>
                                                            <td><MoneyValue :value="operation.amount?.value" :currency="operation.amount?.currency" compact /></td>
                                                            <td>
                                                                <DateTime :data="operation.created_at" simple class="justify-start font-medium" />
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div v-else class="text-base-content/60">Операции не найдены.</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>

                    <DataCardList>
                        <DataCard
                            v-for="payout in payoutItems"
                            :key="`mobile-${payout.id}`"
                        >
                            <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                <div class="min-w-0 flex-1 text-[11px]">
                                    <div class="flex min-w-0 max-w-full flex-nowrap items-start gap-3">
                                        <span class="text-base-content/70 shrink-0 pt-0.5">UUID:</span>
                                        <div class="w-[10rem] min-w-[10rem] shrink-0 overflow-hidden">
                                            <CopyableOrderUid
                                                :uuid="payout.uuid ?? ''"
                                                class="block max-w-full truncate text-left text-base-content"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right leading-tight">
                                    <div class="text-[11px] text-base-content/50 uppercase">Создано</div>
                                    <DateTime
                                        :data="payout.timings.created_at"
                                        simple
                                        class="justify-end text-[11px]"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2 min-w-0 pt-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-base-content leading-snug break-words">
                                        {{ payout.requisites }}
                                    </div>
                                    <div class="text-[11px] text-base-content/60 leading-snug">
                                        {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                    </div>
                                </div>
                                <div class="shrink-0 self-center">
                                    <div
                                        :class="['badge badge-outline badge-sm font-normal', statusBadge(payout.status)]"
                                    >
                                        {{ payout.status_label }}
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2 mb-1"></div>

                            <div class="hidden sm:flex items-end justify-between gap-2">
                                <div
                                    class="grid gap-y-1.5 gap-x-5 sm:gap-x-6 text-[11px] leading-tight flex-1 min-w-0 grid-cols-[minmax(0,1.2fr)_minmax(0,0.95fr)_minmax(0,0.95fr)_minmax(0,0.95fr)]"
                                >
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Клиент</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Списано</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Комиссия</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.fees?.total" :currency="payout.fees?.currency" compact />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sm:hidden space-y-2">
                                <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Клиент</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Списано</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Комиссия</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.fees?.total" :currency="payout.fees?.currency" compact />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight border-t border-base-content/10 pt-2 mt-2">
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Мерчант</div>
                                    <div class="font-medium text-xs text-base-content truncate">{{ payout.merchant?.name ?? '—' }}</div>
                                    <div class="text-[10px] text-base-content/60 truncate">{{ payout.merchant?.owner?.email ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Трейдер</div>
                                    <div class="font-medium text-xs text-base-content truncate">{{ payout.trader?.email ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2 border-t border-base-content/10 pt-2 mt-2">
                                <TableActionsDropdown>
                                    <TableAction
                                        v-for="option in getAvailableOptions(payout)"
                                        :key="`mobile-${payout.id}-${option.value}`"
                                        @click="openStatusConfirm(payout, option)"
                                    >
                                        <div class="flex flex-col text-left">
                                            <span class="text-xs font-medium">{{ option.label }}</span>
                                            <span class="text-[10px] text-base-content/60">{{ option.hint }}</span>
                                        </div>
                                    </TableAction>
                                </TableActionsDropdown>
                                <button
                                    class="btn btn-primary btn-xs"
                                    type="button"
                                    @click="toggleRow(payout.id)"
                                    :aria-expanded="isExpanded(payout.id)"
                                    :aria-label="isExpanded(payout.id) ? 'Скрыть детали' : 'Показать детали'"
                                >
                                    <svg class="size-4 transition-transform" :class="{'rotate-180': isExpanded(payout.id)}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </div>

                            <div v-show="isExpanded(payout.id)" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2 text-[11px] leading-tight">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/50 uppercase">External ID</span>
                                    <DisplayID v-if="payout.external_id" :id="payout.external_id" />
                                    <span v-else class="text-base-content/50">—</span>
                                </div>

                                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Тело</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Получит трейдер</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.trader_credit?.value" :currency="payout.trader_credit?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Ставка</div>
                                        <div class="font-medium text-xs text-base-content">{{ payout.commissions?.total ?? '—' }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Метод</div>
                                        <div class="font-medium text-xs text-base-content">{{ payout.payout_method_type.label }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Получатель</div>
                                        <div class="font-medium text-xs text-base-content truncate">{{ payout.initials ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Маркет</div>
                                        <div class="font-medium text-xs text-base-content">{{ payout.rate.market ?? '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Зафиксирован</div>
                                        <DateTime :data="payout.rate.fixed_at" simple class="justify-start font-medium text-xs" />
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Истекает</div>
                                        <DateTime :data="payout.timings.expires_at" simple class="justify-start font-medium text-xs" />
                                    </div>
                                </div>

                                <div class="grid gap-1.5 border-t border-base-content/10 pt-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-base-content/50 uppercase">Платёжный метод</span>
                                        <span class="font-medium text-base-content text-right">
                                            <template v-if="payout.bank_name">{{ payout.bank_name }}</template>
                                            <template v-else>{{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})</template>
                                        </span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-base-content/50 uppercase">Реквизит</span>
                                        <span class="font-medium text-base-content text-right break-all">{{ payout.requisites }}</span>
                                    </div>
                                    <div v-if="payout.timings.completed_at" class="flex items-center justify-between gap-3">
                                        <span class="text-base-content/50 uppercase">Завершено</span>
                                        <DateTime :data="payout.timings.completed_at" simple class="justify-end font-medium text-xs" />
                                    </div>
                                    <div v-if="payout.timings.hold_until" class="flex items-center justify-between gap-3">
                                        <span class="text-base-content/50 uppercase">Холд до</span>
                                        <DateTime :data="payout.timings.hold_until" simple class="justify-end font-medium text-xs" />
                                    </div>
                                </div>

                                <div class="grid gap-1 border-t border-base-content/10 pt-2">
                                    <div class="text-[10px] text-base-content/50 uppercase">Комиссии</div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Всего</span>
                                        <span class="font-medium"><MoneyValue :value="payout.fees.total" :currency="payout.fees.currency" compact /></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Трейдер</span>
                                        <span class="font-medium"><MoneyValue :value="payout.fees.trader" :currency="payout.fees.currency" compact /></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Тимлид</span>
                                        <span class="font-medium"><MoneyValue :value="payout.fees.teamlead" :currency="payout.fees.currency" compact /></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Сервис</span>
                                        <span class="font-medium"><MoneyValue :value="payout.fees.service" :currency="payout.fees.currency" compact /></span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1 text-[10px] pt-0.5">
                                        <div>Итого: <span class="font-medium">{{ payout.commissions.total ?? '—' }}%</span></div>
                                        <div>Трейдер: <span class="font-medium">{{ payout.commissions.trader ?? '—' }}%</span></div>
                                        <div>Тимлид: <span class="font-medium">{{ payout.commissions.teamlead ?? '—' }}%</span></div>
                                    </div>
                                </div>

                                <div
                                    v-if="payoutReceiptLinks(payout).length"
                                    class="flex items-center justify-start gap-2 bg-base-200/40 rounded-lg p-1.5 px-2"
                                >
                                    <div class="text-[10px] text-base-content/50 uppercase shrink-0">Чеки:</div>
                                    <div class="flex flex-wrap gap-1">
                                        <a
                                            v-for="(receipt, index) in payoutReceiptLinks(payout)"
                                            :key="`receipt-mobile-${payout.id}-${receipt.id ?? index}`"
                                            :href="receipt.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-xs btn-secondary btn-outline min-h-0 h-5 px-2 text-[10px] leading-none"
                                        >
                                            Чек {{ index + 1 }}
                                        </a>
                                    </div>
                                </div>
                                <div v-else class="text-[10px] text-base-content/50">Чек ещё не загружен.</div>

                                <div class="border-t border-base-content/10 pt-2 space-y-1.5">
                                    <div class="text-[10px] text-base-content/50 uppercase">Операции</div>
                                    <div v-if="(payout.operations ?? []).length" class="space-y-1.5">
                                        <div
                                            v-for="operation in payout.operations"
                                            :key="`mobile-op-${operation.id}`"
                                            class="rounded-lg bg-base-200/40 p-2 space-y-0.5"
                                        >
                                            <div class="font-medium text-xs">{{ operation.type_label }}</div>
                                            <div class="text-[10px]">
                                                Сумма:
                                                <MoneyValue :value="operation.amount?.value" :currency="operation.amount?.currency" compact />
                                            </div>
                                            <div class="text-[10px]">
                                                Когда:
                                                <DateTime :data="operation.created_at" simple class="justify-start font-medium" />
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-[10px] text-base-content/50">Операции не найдены.</div>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal />
        <PayoutSettingsModal />

        <Modal :show="traderModal.open" max-width="md" @close="closeTraderModal">
            <div class="space-y-3">
                <h3 class="text-lg font-semibold text-base-content">Выбор трейдера</h3>
                <p class="text-sm text-base-content/70">
                    Выплата {{ traderModal.payout?.uuid }} → {{ traderModal.option?.label }}
                </p>
                <div class="space-y-2">
                    <div class="text-xs uppercase text-base-content/50">Активные трейдеры (онлайн, выплаты включены)</div>
                    <select
                        v-model.number="traderModal.traderId"
                        class="select select-bordered w-full"
                    >
                        <option :value="null">Выберите трейдера</option>
                        <option
                            v-for="trader in traders"
                            :key="`modal-tr-${trader.id}`"
                            :value="trader.id"
                        >
                            {{ trader.name ?? trader.email }} ({{ trader.email }})
                        </option>
                    </select>
                    <div v-if="traderModal.error" class="text-error text-sm">{{ traderModal.error }}</div>
                </div>
                <div class="modal-action">
                    <button class="btn btn-sm btn-ghost" type="button" @click="closeTraderModal">Отмена</button>
                    <button class="btn btn-sm btn-primary" type="button" @click="confirmTraderModal">Подтвердить</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

