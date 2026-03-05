<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Pertials/DropdownFilter.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import BankManualIcon from '@/Components/BankManualIcon.vue';
import DisplayUUID from '@/Components/DisplayUUID.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import PayoutCreateModal from "@/Modals/Payout/PayoutCreateModal.vue";
import { useModalStore } from "@/store/modal.js";

const payouts = computed(() => usePage().props.payouts ?? { data: [] });
const payoutItems = computed(() => payouts.value?.data ?? []);
const expandedRows = ref({});
const modalStore = useModalStore();

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

const formatMoney = (money, empty = '—') => {
    if (!money) {
        return empty;
    }

    return `${money.value} ${money.currency ?? ''}`.trim();
};

const formatMeta = (meta) => {
    if (!meta) {
        return 'Нет данных';
    }

    try {
        return JSON.stringify(meta, null, 2);
    } catch (error) {
        return String(meta);
    }
};

const resendPayoutCallback = (payoutUUID) => {
    if (!payoutUUID) {
        return;
    }

    router.post(route('merchant.payouts.callback.resend', payoutUUID));
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
                    @click="modalStore.openPayoutCreateModal()"
                    type="button"
                    class="hidden md:block btn btn-primary"
                >
                    Создать выплату
                </button>
                <AddMobileIcon
                    @click="modalStore.openPayoutCreateModal()"
                />
            </template>
            <template #header>
                <div class="space-y-4">
                    <FiltersPanel name="merchant-payouts">
                        <DateFilter name="startDate" title="Создано с" />
                        <DateFilter name="endDate" title="Создано по" />
                        <InputFilter name="uuid" placeholder="UUID" />
                        <InputFilter name="externalID" placeholder="External ID" />
                        <InputFilter name="paymentDetail" placeholder="Реквизит" />
                        <DropdownFilter name="merchantIds" title="Мерчант" />
                        <DropdownFilter name="payoutStatuses" title="Статусы" />
                        <DropdownFilter name="payoutMethodTypes" title="Тип реквизитов" />
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
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th>UUID</th>
                                    <th>Реквизиты</th>
                                    <th>Сумма</th>
                                    <th>Курс</th>
                                    <th>Комиссия</th>
                                    <th>Статус</th>
                                    <th>Мерчант</th>
                                    <th class="w-24">Подробнее</th>
                                    <th class="w-16 text-right"></th>
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
                                            <div class="text-nowrap">
                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{ payout.fees?.total ?? '—' }} {{ payout.fees?.currency ?? '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge badge-sm" :class="statusBadge(payout.status)">
                                                {{ payout.status_label }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-xs text-base-content max-w-35">
                                                {{ payout.merchant?.name ?? '—' }}
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
                                                <TableAction @click="resendPayoutCallback(payout.uuid)">
                                                    Отправить callback
                                                </TableAction>
                                            </TableActionsDropdown>
                                        </td>
                                    </tr>
                                    <tr v-if="isExpanded(payout.id)" class="bg-base-100 border-base-200 border-b last:border-none">
                                        <td colspan="9">
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
                                                        <div class="card-body text-sm space-y-3">
                                                            <div>
                                                                <div class="text-xs uppercase text-base-content/50">Сумма</div>
                                                                <div class="space-y-1">
                                                                    <div class="flex items-center justify-between">
                                                                        <span>Клиент</span>
                                                                        <span class="font-semibold">{{ formatMoney(payout.amount) }}</span>
                                                                    </div>
                                                                    <div class="flex items-center justify-between">
                                                                        <span>Тело</span>
                                                                        <span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span>
                                                                    </div>
                                                                    <div class="flex items-center justify-between">
                                                                        <span>Комиссия</span>
                                                                        <span class="font-semibold">{{ payout.fees?.total ?? '—' }} {{ payout.fees?.currency ?? '' }}</span>
                                                                    </div>
                                                                    <div class="flex items-center justify-between">
                                                                        <span>Списано</span>
                                                                        <span class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="divider my-0"></div>
                                                            <div>
                                                                <div class="text-xs uppercase text-base-content/50">Ставка</div>
                                                                <div class="text-sm">
                                                                    Итого: <span class="font-semibold">{{ payout.commissions?.total ?? '—' }}%</span>
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
                                                                <div class="font-semibold break-all">{{ payout.requisites }}</div>
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
                                                        <div class="card-body text-sm space-y-3">
                                                            <div>
                                                                <div class="text-xs uppercase text-base-content/50">Создано</div>
                                                                <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div v-if="payout.timings.completed_at">
                                                                <div class="text-xs uppercase text-base-content/50">Завершено</div>
                                                                <DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" />
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
                                                                    Ссылка доступна только авторизованным пользователям.
                                                                </div>
                                                            </div>
                                                            <div v-else class="text-sm text-base-content/60">
                                                                Чек недоступен.
                                                            </div>
                                                        </div>
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
                        <article
                            v-for="payout in payoutItems"
                            :key="`mobile-${payout.id}`"
                            class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden"
                        >
                            <div class="card-body space-y-2">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="text-xs uppercase text-base-content/60">UUID</div>
                                        <DisplayUUID :uuid="payout.uuid" />
                                    </div>
                                    <div class="flex items-center gap-2 sm:justify-end">
                                        <span class="badge badge-sm" :class="statusBadge(payout.status)">
                                            {{ payout.status_label }}
                                        </span>
                                        <button
                                            class="btn btn-ghost btn-xs"
                                            type="button"
                                            @click="resendPayoutCallback(payout.uuid)"
                                        >
                                            Отправить callback
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="relative shrink-0">
                                            <template v-if="hasCustomBank(payout)">
                                                <BankManualIcon class="w-12 h-12 text-base-content/70" />
                                            </template>
                                            <template v-else-if="payout.payout_method_type.value === 'sbp'">
                                                <img src="/images/sbp.svg" class="w-12 h-12" alt="СБП">
                                                <GatewayLogo
                                                    v-if="payout.payment_gateway?.logo"
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="absolute right-[-4px] bottom-[-4px] w-6 h-6 bg-base-100 border border-base-300 rounded-full"
                                                />
                                            </template>
                                            <template v-else>
                                                <GatewayLogo
                                                    :img_path="payout.payment_gateway?.logo"
                                                    :name="payout.payment_gateway?.name"
                                                    class="w-12 h-12"
                                                />
                                            </template>
                                        </div>
                                        <div class="flex-1 space-y-1 text-sm">
                                            <div class="font-semibold break-all">{{ payout.requisites }}</div>
                                            <div class="text-xs text-base-content/60">
                                                {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                            </div>
                                            <div class="text-xs text-base-content/60">
                                                Мерчант: <span class="font-semibold">{{ payout.merchant?.name ?? '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-3 text-sm">
                                        <div class="bg-base-200/40 rounded-box p-2">
                                            <div class="text-[10px] uppercase text-base-content/60">Сумма клиента</div>
                                            <div class="font-semibold">{{ formatMoney(payout.amount) }}</div>
                                        </div>
                                        <div class="bg-base-200/40 rounded-box p-2">
                                            <div class="text-[10px] uppercase text-base-content/60">USDT тело</div>
                                            <div class="font-semibold">{{ formatMoney(payout.usdt_body) }}</div>
                                        </div>
                                        <div class="bg-base-200/40 rounded-box p-2">
                                            <div class="text-[10px] uppercase text-base-content/60">Курс</div>
                                            <div class="font-semibold">
                                                {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
                                            </div>
                                        </div>
                                        <div class="bg-base-200/40 rounded-box p-2">
                                            <div class="text-[10px] uppercase text-base-content/60">Списано</div>
                                            <div class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</div>
                                        </div>
                                        <div class="bg-base-200/40 rounded-box p-2">
                                            <div class="text-[10px] uppercase text-base-content/60">Комиссия</div>
                                            <div class="font-semibold">
                                                {{ payout.fees?.total ?? '—' }} {{ payout.fees?.currency ?? '' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="sm:flex items-center justify-between space-y-2 sm:space-y-0">
                                    <div class="text-xs text-base-content/60">
                                        Создано: <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                    </div>
                                    <button
                                        class="btn btn-outline btn-sm"
                                        type="button"
                                        @click="toggleRow(payout.id)"
                                    >
                                        <span>{{ isExpanded(payout.id) ? 'Скрыть детали' : 'Показать детали' }}</span>
                                        <svg class="size-4 transition-transform" :class="{'rotate-180': isExpanded(payout.id)}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>

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
                                        <div class="grid gap-3">
                                            <div class="card bg-base-200/40 border border-base-300">
                                                <div class="card-body p-3 text-sm space-y-0">
                                                    <div class="text-xs uppercase text-base-content/60">Сумма</div>
                                                    <div class="flex items-center justify-between">
                                                        <span>Клиент</span>
                                                        <span class="font-semibold">{{ formatMoney(payout.amount) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span>Тело</span>
                                                        <span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span>Комиссия</span>
                                                        <span class="font-semibold">{{ payout.fees?.total ?? '—' }} {{ payout.fees?.currency ?? '' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span>Списано</span>
                                                        <span class="font-semibold">{{ formatMoney(payout.merchant_debit) }}</span>
                                                    </div>
                                                    <div class="pt-2 border-t border-base-300 flex items-center justify-between">
                                                        <span>Ставка</span>
                                                        <span class="font-semibold">{{ payout.commissions?.total ?? '—' }}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card bg-base-200/40 border border-base-300">
                                                <div class="card-body p-3 text-sm space-y-0">
                                                    <div class="text-xs uppercase text-base-content/60">Банковские данные</div>
                                                    <div>
                                                        <div class="text-xs text-base-content/60">Метод</div>
                                                        <div class="font-semibold">{{ payout.payout_method_type.label }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs text-base-content/60">Платёжный метод</div>
                                                        <div class="font-semibold">
                                                            {{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs text-base-content/60">Реквизит</div>
                                                        <div class="font-semibold break-all">{{ payout.requisites }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs text-base-content/60">Получатель</div>
                                                        <div class="font-semibold">{{ payout.initials ?? '—' }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card bg-base-200/40 border border-base-300">
                                                <div class="card-body p-3 text-sm space-y-0">
                                                    <div class="text-xs uppercase text-base-content/60">Курс</div>
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

                                            <div class="card bg-base-200/40 border border-base-300">
                                                <div class="card-body p-3 text-sm space-y-0">
                                                    <div class="text-xs uppercase text-base-content/60">Тайминги</div>
                                                    <div>
                                                        <div class="text-xs text-base-content/60">Создано</div>
                                                        <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                                    </div>
                                                    <div v-if="payout.timings.completed_at">
                                                        <div class="text-xs text-base-content/60">Завершено</div>
                                                        <DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card bg-base-200/40 border border-base-300">
                                                <div class="card-body p-3 text-sm space-y-0">
                                                    <div class="text-xs uppercase text-base-content/60">Чек выплаты</div>
                                                    <div v-if="payout.receipt_url" class="space-y-1">
                                                        <a
                                                            :href="payout.receipt_url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="btn btn-sm btn-outline btn-primary w-full"
                                                        >
                                                            Скачать чек
                                                        </a>
                                                        <div class="text-[10px] text-base-content/60">
                                                            Ссылка доступна только авторизованным пользователям.
                                                        </div>
                                                    </div>
                                                    <div v-else class="text-xs text-base-content/60">
                                                        Чек недоступен.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </article>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <PayoutCreateModal />
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

