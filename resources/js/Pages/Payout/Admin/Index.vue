<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Pertials/DropdownFilter.vue';
import RefreshTableData from '@/Components/Table/RefreshTableData.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import BankManualIcon from '@/Components/BankManualIcon.vue';
import DisplayUUID from '@/Components/DisplayUUID.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import Modal from '@/Components/Modals/Modal.vue';
import {useModalStore} from '@/store/modal.js';
import PayoutSettingsModal from '@/Modals/Payout/PayoutSettingsModal.vue';

const payouts = computed(() => usePage().props.payouts ?? { data: [] });
const payoutItems = computed(() => payouts.value?.data ?? []);
const traders = computed(() => usePage().props.traders ?? []);
const reloadingTableData = ref(false);
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

const hasCustomBank = (payout) => !!payout?.bank_name;
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

const formatMoney = (money, empty = '—') => {
    if (!money) {
        return empty;
    }

    return `${money.value} ${money.currency ?? ''}`.trim();
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
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="modalStore.openPayoutSettingsModal()"
                >
                    Настройки выплат
                </button>
            </template>
            <template #header>
                <div class="space-y-4">
                    <FiltersPanel name="admin-payouts">
                        <DateFilter name="startDate" title="Создано с" />
                        <DateFilter name="endDate" title="Создано по" />
                        <InputFilter name="uuid" placeholder="UUID" />
                        <InputFilter name="externalID" placeholder="External ID" />
                        <InputFilter name="paymentDetail" placeholder="Реквизит" />
                        <InputFilter name="merchant" placeholder="Мерчант" />
                        <InputFilter name="user" placeholder="Трейдер" />
                        <DropdownFilter name="payoutStatuses" title="Статусы" />
                        <DropdownFilter name="payoutMethodTypes" title="Типы реквизитов" />
                        <InputFilter name="paymentGateway" placeholder="Банк / метод" />
                        <InputFilter name="amount" placeholder="Сумма (точная)" />
                        <InputFilter name="minAmount" placeholder="Мин. сумма" />
                        <InputFilter name="maxAmount" placeholder="Макс. сумма" />
                        <InputFilter name="currency" placeholder="Валюта (например, RUB)" />
                    </FiltersPanel>

                    <div class="flex items-center justify-between">
                        <div
                            v-if="reloadingTableData"
                            class="px-2 text-sm text-base-content/80 flex items-center gap-2"
                            aria-live="polite"
                        >
                            <span class="loading loading-spinner loading-sm text-primary" />
                            <span>Обновляем данные...</span>
                        </div>

                        <RefreshTableData
                            @refresh-started="reloadingTableData = true"
                            @refresh-finished="reloadingTableData = false"
                        />
                    </div>
                </div>
            </template>
            <template #body>
                <div class="relative">
                    <div class="hidden xl:block rounded-table relative">
                        <div
                            class="card sticky top-0 left-0 bg-base-100/40 z-10 flex items-center justify-center backdrop-blur-sm transition-all duration-300 ease-in-out opacity-0 pointer-events-none"
                            :class="{'opacity-100 pointer-events-auto': reloadingTableData}"
                            style="position: absolute; inset: 0; width: 100%; height: 100%;"
                        >
                            <div class="flex flex-col items-center transition-transform duration-300" :class="{'scale-90 opacity-0': !reloadingTableData, 'scale-100 opacity-100': reloadingTableData}">
                                <span class="loading loading-spinner loading-lg text-primary" />
                                <span class="mt-3 text-sm font-medium text-base-content">Загрузка данных...</span>
                            </div>
                        </div>

                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm" :class="{'pointer-events-none': reloadingTableData}">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th>UUID</th>
                                    <th>Реквизиты</th>
                                    <th>Сумма</th>
                                    <th>Курс</th>
                                    <th>Статус</th>
                                    <th>Стороны сделки</th>
                                    <th></th>
                                    <th class="w-24">Подробнее</th>
                                </tr>
                                </thead>
                                <tbody>
                                <template v-for="payout in payoutItems" :key="payout.id">
                                    <tr class="bg-base-100 border-base-200 border-b last:border-none align-top">
                                        <td>
                                            <DisplayUUID :uuid="payout.uuid" class="text-sm font-semibold" />
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div v-if="hasCustomBank(payout)" class="text-base-content/70">
                                                    <BankManualIcon class="w-10 h-10" />
                                                </div>
                                                <div v-else-if="payout.payout_method_type.value === 'sbp'" class="relative">
                                                    <img src="/images/sbp.svg" class="w-10 h-10" alt="СБП">
                                                    <GatewayLogo
                                                        v-if="payout.payment_gateway?.logo"
                                                        :img_path="payout.payment_gateway?.logo"
                                                        :name="payout.payment_gateway?.name"
                                                        class="absolute right-[-4px] bottom-[-4px] w-5 h-5 bg-base-100 border border-base-300 rounded-full"
                                                    />
                                                </div>
                                                <div v-else>
                                                    <GatewayLogo
                                                        :img_path="payout.payment_gateway?.logo"
                                                        :name="payout.payment_gateway?.name"
                                                        class="w-10 h-10"
                                                    />
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-base-content">{{ payout.requisites }}</div>
                                                    <div class="text-xs text-base-content/60">
                                                        {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="text-nowrap text-base-content">
                                                    {{ formatMoney(payout.amount) }}
                                                </div>
                                                <div class="text-nowrap text-xs text-base-content/60">
                                                    {{ formatMoney(payout.usdt_body) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge badge-sm" :class="statusBadge(payout.status)">
                                                {{ payout.status_label }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="text-xs uppercase text-base-content/50">М:</div>
                                                    <div class="text-xs text-base-content">
                                                        {{ payout.merchant?.owner?.email }}
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="text-xs uppercase text-base-content/50">Т:</div>
                                                    <div class="text-xs text-base-content">
                                                        {{ payout.trader?.email ?? '-' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right align-center">
                                            <TableActionsDropdown>
                                                <TableAction
                                                    v-for="option in getAvailableOptions(payout)"
                                                    :key="`${payout.id}-${option.value}`"
                                                    @click="openStatusConfirm(payout, option)"
                                                >
                                                    <div class="flex flex-col text-left">
                                                        <span class="text-xs font-semibold">{{ option.label }}</span>
                                                        <span class="text-[10px] text-base-content/60">{{ option.hint }}</span>
                                                    </div>
                                                </TableAction>
                                            </TableActionsDropdown>
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
                                    </tr>
                                    <tr v-if="isExpanded(payout.id)" class="bg-base-100 border-base-200 border-b last:border-none">
                                        <td colspan="8">
                                            <div class="bg-base-200/40 border border-base-300 rounded-box p-4 space-y-4">
                                                <div class="flex flex-wrap gap-6 text-xs">
                                                    <div>
                                                        <div class="text-[10px] uppercase text-base-content/50">Доп. информация</div>
                                                        <div class="mt-1 flex items-center gap-2">
                                                            <span class="text-[10px] uppercase text-base-content/50">External ID</span>
                                                            <DisplayID v-if="payout.external_id" :id="payout.external_id" />
                                                            <div v-else class="text-xs text-base-content/40">—</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 lg:grid-cols-4 gap-2">
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Комиссии</div>
                                                            <div class="flex items-center justify-between">
                                                                <span>Всего</span>
                                                                <span class="font-semibold">{{ payout.fees.total ?? '—' }} {{ payout.fees.currency }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span>Трейдер</span>
                                                                <span class="font-semibold">{{ payout.fees.trader ?? '—' }} {{ payout.fees.currency }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span>Тимлид</span>
                                                                <span class="font-semibold">{{ payout.fees.teamlead ?? '—' }} {{ payout.fees.currency }}</span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span>Сервис</span>
                                                                <span class="font-semibold">{{ payout.fees.service ?? '—' }} {{ payout.fees.currency }}</span>
                                                            </div>
                                                            <div class="divider my-0"></div>
                                                            <div class="text-xs uppercase text-base-content/50">Ставки</div>
                                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                                <div>Итого: <span class="font-semibold">{{ payout.commissions.total ?? '—' }}%</span></div>
                                                                <div>Трейдер: <span class="font-semibold">{{ payout.commissions.trader ?? '—' }}%</span></div>
                                                                <div>Тимлид: <span class="font-semibold">{{ payout.commissions.teamlead ?? '—' }}%</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Суммы</div>
                                                            <div class="space-y-2">
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs text-base-content/60">Клиенту (₽)</span>
                                                                    <span class="font-semibold">{{ formatMoney(payout.amount) }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs text-base-content/60">Списано у мерчанта</span>
                                                                    <span class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs text-base-content/60">Получит трейдер</span>
                                                                    <span class="font-semibold">{{ formatMoney(payout.trader_credit) }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs text-base-content/60">Получит Team Leader</span>
                                                                    <span class="font-semibold">
                                                                        {{ (payout.fees.teamlead ?? '0') }} {{ payout.fees.currency }}
                                                                    </span>
                                                                </div>
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs text-base-content/60">Тело (USDT)</span>
                                                                    <span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Банковские данные</div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Метод</div>
                                                                <div class="font-semibold">{{ payout.payout_method_type.label }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Платёжный метод</div>
                                                                <div class="font-semibold">
                                                                    <template v-if="payout.bank_name">
                                                                        {{ payout.bank_name }}
                                                                    </template>
                                                                    <template v-else>
                                                                        {{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})
                                                                    </template>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Реквизит</div>
                                                                <div class="font-semibold">{{ payout.requisites }})</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Получатель</div>
                                                                <div class="font-semibold">{{ payout.initials ?? '—' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Курс</div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Маркет</div>
                                                                <div class="font-semibold">{{ payout.rate.market ?? '—' }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Цена</div>
                                                                <div class="font-semibold">{{ payout.rate.price ?? '—' }} {{ payout.rate.currency }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Зафиксирован</div>
                                                                <DateTime :data="payout.rate.fixed_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div>
                                                                <div class="text-xs uppercase text-base-content/50">Создано</div>
                                                                <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div>
                                                                <div class="text-xs uppercase text-base-content/50">Истекает</div>
                                                                <DateTime :data="payout.timings.expires_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div v-if="payout.timings.completed_at">
                                                                <div class="text-xs uppercase text-base-content/50">Завершено</div>
                                                                <DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div v-if="payout.timings.hold_until">
                                                                <div class="text-xs uppercase text-base-content/50">Холд до</div>
                                                                <DateTime :data="payout.timings.hold_until" simple class="justify-start font-semibold" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Участники</div>
                                                            <div class="space-y-1">
                                                                <div class="text-xs text-base-content/60">Мерчант</div>
                                                                <div class="font-semibold">{{ payout.merchant?.name ?? '—' }}</div>
                                                                <div class="text-xs text-base-content/60">Владелец</div>
                                                                <div class="font-semibold">
                                                                    {{ payout.merchant?.owner?.email ?? '—' }}
                                                                </div>
                                                            </div>
                                                            <div class="divider my-0"></div>
                                                            <div class="space-y-1">
                                                                <div class="text-xs text-base-content/60">Трейдер</div>
                                                                <div class="font-semibold">
                                                                    {{ payout.trader?.email ?? '—' }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Чек выплаты</div>
                                                            <div v-if="payout.receipt_url" class="space-y-2">
                                                                <a
                                                                    :href="payout.receipt_url"
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                    class="btn btn-sm btn-outline btn-primary w-full"
                                                                >
                                                                    Скачать чек
                                                                </a>
                                                                <div class="text-xs text-base-content/60">
                                                                    Доступ только авторизованным пользователям.
                                                                </div>
                                                            </div>
                                                            <div v-else class="text-sm text-base-content/60">
                                                                Чек ещё не загружен.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="space-y-3">
                                                    <h4 class="text-sm font-semibold text-base-content">Операции</h4>
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
                                                                <td class="font-semibold text-xs">{{ operation.type_label }}</td>
                                                                <td class="text-xs">{{ formatMoney(operation.amount) }}</td>
                                                                <td class="text-xs">
                                                                    <DateTime :data="operation.created_at" simple class="justify-start font-semibold" />
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div v-else class="text-sm text-base-content/60">
                                                        Операции не найдены.
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-4">
                        <div
                            v-for="payout in payoutItems"
                            :key="`mobile-${payout.id}`"
                            class="card bg-base-100 shadow-sm border border-base-200"
                        >
                            <div class="card-body space-y-5 p-4">
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="text-[10px] uppercase text-base-content/50">UUID</div>
                                            <DisplayUUID :uuid="payout.uuid" />
                                            <div class="text-[10px] uppercase text-base-content/50 mt-2">Создано</div>
                                            <DateTime :data="payout.timings.created_at" simple class="justify-start text-sm font-semibold" />
                                        </div>
                                    <div class="flex flex-col items-end gap-2">
                                            <div class="badge badge-sm" :class="statusBadge(payout.status)">
                                                {{ payout.status_label }}
                                            </div>
                                            <TableActionsDropdown>
                                                <TableAction
                                                    v-for="option in getAvailableOptions(payout)"
                                                    :key="`mobile-${payout.id}-${option.value}`"
                                                    @click="openStatusConfirm(payout, option)"
                                                >
                                                    <div class="flex flex-col text-left">
                                                        <span class="text-xs font-semibold">{{ option.label }}</span>
                                                        <span class="text-[10px] text-base-content/60">{{ option.hint }}</span>
                                                    </div>
                                                </TableAction>
                                            </TableActionsDropdown>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-start gap-3 border border-dashed border-base-200 rounded-box p-3">
                                        <div class="flex items-center gap-3 flex-1 min-w-[180px]">
                                            <div v-if="hasCustomBank(payout)" class="text-base-content/70">
                                                <BankManualIcon class="w-12 h-12" />
                                            </div>
                                            <GatewayLogo
                                                v-else
                                                :img_path="payout.payment_gateway?.logo"
                                                :name="payout.payment_gateway?.name"
                                                class="w-12 h-12"
                                            />
                                            <div class="text-sm">
                                                <div class="text-[10px] uppercase text-base-content/50">Реквизит</div>
                                                <div class="font-semibold break-all">{{ payout.requisites }}</div>
                                                <div class="text-xs text-base-content/60 mt-1">
                                                    {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-1 text-sm sm:text-right min-w-[120px]">
                                            <div>
                                                <div class="text-[10px] uppercase text-base-content/50">Сумма клиенту</div>
                                                <div class="font-semibold">{{ formatMoney(payout.amount) }}</div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] uppercase text-base-content/50">Тело (USDT)</div>
                                                <div class="font-semibold">{{ formatMoney(payout.usdt_body) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid sm:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <div class="text-[10px] uppercase text-base-content/50">Курс</div>
                                        <div class="font-semibold">
                                            {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                        </div>
                                    </div>
                                        <div>
                                            <div class="text-[10px] uppercase text-base-content/50">Мерчант</div>
                                            <div class="font-semibold">{{ payout.merchant?.name ?? '—' }}</div>
                                            <div class="text-[10px] text-base-content/60">{{ payout.merchant?.owner?.email ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] uppercase text-base-content/50">Трейдер</div>
                                            <div class="font-semibold">{{ payout.trader?.email ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] uppercase text-base-content/50">Списано у мерчанта</div>
                                            <div class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] uppercase text-base-content/50">Получит трейдер</div>
                                            <div class="font-semibold">{{ formatMoney(payout.trader_credit) }}</div>
                                        </div>
                                    </div>

                                </div>

                                <button
                                    class="btn btn-outline btn-sm w-full"
                                    type="button"
                                    @click="toggleRow(payout.id)"
                                >
                                    <span>{{ isExpanded(payout.id) ? 'Скрыть подробности' : 'Все детали' }}</span>
                                    <svg class="size-4 transition-transform" :class="{'rotate-180': isExpanded(payout.id)}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>

                                <transition name="fade">
                                    <div v-if="isExpanded(payout.id)" class="space-y-4">
                                        <div class="flex flex-wrap gap-6 text-xs">
                                            <div>
                                                <div class="text-[10px] uppercase text-base-content/50">Доп. информация</div>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <span class="text-[10px] uppercase text-base-content/50">External ID</span>
                                                    <DisplayID v-if="payout.external_id" :id="payout.external_id" />
                                                    <div v-else class="text-xs text-base-content/40">—</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Комиссии и ставки
                                            </div>
                                            <div class="collapse-content text-sm space-y-3">
                                                <div class="flex items-center justify-between">
                                                    <span>Всего</span>
                                                    <span class="font-semibold">{{ payout.fees.total ?? '—' }} {{ payout.fees.currency }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span>Трейдер</span>
                                                    <span class="font-semibold">{{ payout.fees.trader ?? '—' }} {{ payout.fees.currency }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span>Тимлид</span>
                                                    <span class="font-semibold">{{ payout.fees.teamlead ?? '—' }} {{ payout.fees.currency }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span>Сервис</span>
                                                    <span class="font-semibold">{{ payout.fees.service ?? '—' }} {{ payout.fees.currency }}</span>
                                                </div>
                                                <div class="divider my-0"></div>
                                                <div class="grid grid-cols-2 gap-2 text-xs">
                                                    <div>Итого: <span class="font-semibold">{{ payout.commissions.total ?? '—' }}%</span></div>
                                                    <div>Трейдер: <span class="font-semibold">{{ payout.commissions.trader ?? '—' }}%</span></div>
                                                    <div>Тимлид: <span class="font-semibold">{{ payout.commissions.teamlead ?? '—' }}%</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Суммы и тело
                                            </div>
                                            <div class="collapse-content text-sm space-y-2">
                                                <div class="flex items-center justify-between"><span>Клиенту</span><span class="font-semibold">{{ formatMoney(payout.amount) }}</span></div>
                                                <div class="flex items-center justify-between"><span>Списано у мерчанта</span><span class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</span></div>
                                                <div class="flex items-center justify-between"><span>Получит трейдер</span><span class="font-semibold">{{ formatMoney(payout.trader_credit) }}</span></div>
                                                <div class="flex items-center justify-between"><span>Получит Team Leader</span><span class="font-semibold">{{ (payout.fees.teamlead ?? '0') }} {{ payout.fees.currency }}</span></div>
                                                <div class="flex items-center justify-between"><span>Тело (USDT)</span><span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span></div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Банковские данные
                                            </div>
                                            <div class="collapse-content text-sm space-y-2">
                                                <div><span class="text-xs text-base-content/60">Метод</span><div class="font-semibold">{{ payout.payout_method_type.label }}</div></div>
                                                <div>
                                                    <span class="text-xs text-base-content/60">Платёжный метод</span>
                                                    <div class="font-semibold">
                                                        <template v-if="payout.bank_name">
                                                            {{ payout.bank_name }}
                                                        </template>
                                                        <template v-else>
                                                            {{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})
                                                        </template>
                                                    </div>
                                                </div>
                                                <div><span class="text-xs text-base-content/60">Реквизит</span><div class="font-semibold break-all">{{ payout.requisites }}</div></div>
                                                <div><span class="text-xs text-base-content/60">Получатель</span><div class="font-semibold">{{ payout.initials ?? '—' }}</div></div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Курс
                                            </div>
                                            <div class="collapse-content text-sm space-y-2">
                                                <div><span class="text-xs text-base-content/60">Маркет</span><div class="font-semibold">{{ payout.rate.market ?? '—' }}</div></div>
                                                <div><span class="text-xs text-base-content/60">Цена</span><div class="font-semibold">{{ payout.rate.price ?? '—' }} {{ payout.rate.currency }}</div></div>
                                                <div><span class="text-xs text-base-content/60">Зафиксирован</span><DateTime :data="payout.rate.fixed_at" simple class="justify-start font-semibold" /></div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Тайминг
                                            </div>
                                            <div class="collapse-content text-sm space-y-2">
                                                <div><span class="text-xs text-base-content/60">Создано</span><DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" /></div>
                                                <div><span class="text-xs text-base-content/60">Истекает</span><DateTime :data="payout.timings.expires_at" simple class="justify-start font-semibold" /></div>
                                                <div v-if="payout.timings.completed_at"><span class="text-xs text-base-content/60">Завершено</span><DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" /></div>
                                                <div v-if="payout.timings.hold_until"><span class="text-xs text-base-content/60">Холд до</span><DateTime :data="payout.timings.hold_until" simple class="justify-start font-semibold" /></div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Участники сделки
                                            </div>
                                            <div class="collapse-content text-sm space-y-3">
                                                <div>
                                                    <div class="text-xs text-base-content/60">Мерчант</div>
                                                    <div class="font-semibold">{{ payout.merchant?.name ?? '—' }}</div>
                                                    <div class="text-xs text-base-content/60">Владелец</div>
                                                    <div class="font-semibold">{{ payout.merchant?.owner?.email ?? '—' }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-base-content/60">Трейдер</div>
                                                    <div class="font-semibold">{{ payout.trader?.email ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Чек выплаты
                                            </div>
                                            <div class="collapse-content text-sm">
                                                <div v-if="payout.receipt_url" class="space-y-2">
                                                    <a
                                                        :href="payout.receipt_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="btn btn-sm btn-outline w-full"
                                                    >
                                                        Скачать чек
                                                    </a>
                                                    <div class="text-xs text-base-content/60">
                                                        Доступ только авторизованным пользователям.
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-base-content/60">
                                                    Чек ещё не загружен.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="collapse collapse-arrow border border-base-200 bg-base-200/40 rounded-box">
                                            <input type="checkbox" />
                                            <div class="collapse-title text-sm font-semibold">
                                                Операции
                                            </div>
                                            <div class="collapse-content text-sm space-y-2">
                                                <div v-if="(payout.operations ?? []).length" class="space-y-2">
                                                    <div
                                                        v-for="operation in payout.operations"
                                                        :key="`mobile-op-${operation.id}`"
                                                        class="border border-base-200 rounded-box p-3 space-y-1 text-xs"
                                                    >
                                                        <div class="font-semibold text-sm">{{ operation.type_label }}</div>
                                                        <div>Сумма: {{ formatMoney(operation.amount) }}</div>
                                                        <div>Когда: <DateTime :data="operation.created_at" simple class="justify-start font-semibold" /></div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-base-content/60">Операции не найдены.</div>
                                            </div>
                                        </div>

                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
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

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>

