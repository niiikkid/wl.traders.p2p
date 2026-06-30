<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Partials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import RefreshTableData from '@/Components/Table/RefreshTableData.vue';
import PageToolbar from '@/Components/Table/PageToolbar.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';

const payouts = computed(() => usePage().props.payouts ?? { data: [] });
const payoutItems = computed(() => payouts.value?.data ?? []);
const reloadingTableData = ref(false);
const expandedRows = ref({});

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
                <PageToolbar :loading="reloadingTableData">
                    <RefreshTableData
                        icon-only
                        @refresh-started="reloadingTableData = true"
                        @refresh-finished="reloadingTableData = false"
                    />
                </PageToolbar>
            </template>
            <template #header>
                <FiltersPanel name="support-payouts">
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
            </template>
            <template #body>
                <div class="relative">
                    <DataTable :loading="reloadingTableData">
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
                        </template>
                        <template v-if="payoutItems.length === 0">
                            <tr>
                                <td colspan="7" class="py-6 text-center text-sm text-base-content/60">
                                    Выплаты не найдены.
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <template v-for="payout in payoutItems" :key="payout.id">
                            <tr class="bg-base-100 border-base-200 border-b last:border-none align-top">
                                <th scope="row" class="font-medium whitespace-nowrap text-base-content">
                                    <div class="flex max-w-full flex-nowrap items-center gap-3 ml-2">
                                        <CopyableOrderUid
                                            :uuid="payout.uuid ?? ''"
                                            class="text-left text-base-content"
                                        />
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
                                    <div :class="['badge badge-outline badge-sm font-normal', statusBadge(payout.status)]">
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
                            </tr>
                            <tr v-if="isExpanded(payout.id)" class="bg-base-100 border-base-200 border-b last:border-none">
                                <td colspan="7">
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
                                                <div class="text-[10px] uppercase text-base-content/50">Курс</div>
                                                <div class="font-medium">
                                                    <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                                </div>
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
                                                <div class="text-[10px] uppercase text-base-content/50">Комиссии</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Всего</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.fees.total" :currency="payout.fees.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Трейдер</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.fees.trader" :currency="payout.fees.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Тимлид</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.fees.teamlead" :currency="payout.fees.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Сервис</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.fees.service" :currency="payout.fees.currency" compact />
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-[10px] uppercase text-base-content/50">Суммы</div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Клиенту</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Списано у мерчанта</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Получит трейдер</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.trader_credit?.value" :currency="payout.trader_credit?.currency" compact />
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-base-content/60">Тело (USDT)</span>
                                                    <span class="font-medium">
                                                        <MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" compact />
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border-t border-base-content/10 pt-2 space-y-1">
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
                                                            <td>
                                                                <MoneyValue :value="operation.amount?.value" :currency="operation.amount?.currency" compact />
                                                            </td>
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
                                        <CopyableOrderUid
                                            :uuid="payout.uuid ?? ''"
                                            class="text-left text-base-content"
                                        />
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
                                    <div :class="['badge badge-outline badge-sm font-normal', statusBadge(payout.status)]">
                                        {{ payout.status_label }}
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2 mb-1"></div>

                            <div class="hidden sm:flex items-end justify-between gap-2">
                                <div
                                    class="grid gap-y-1.5 gap-x-5 sm:gap-x-6 text-[11px] leading-tight flex-1 min-w-0 grid-cols-[minmax(0,1.28fr)_minmax(0,0.91fr)_minmax(0,0.91fr)_minmax(0,0.91fr)]"
                                >
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                            <span class="text-base-content/50 font-normal">
                                                (<MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" secondary />)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Мерчант</div>
                                        <div class="font-medium text-xs text-base-content truncate">
                                            {{ payout.merchant?.owner?.email ?? '—' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Трейдер</div>
                                        <div class="font-medium text-xs text-base-content truncate">
                                            {{ payout.trader?.email ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sm:hidden space-y-2">
                                <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.amount?.value" :currency="payout.amount?.currency" compact />
                                        </div>
                                        <div class="text-[10px] text-base-content/60 text-nowrap">
                                            <MoneyValue :value="payout.usdt_body?.value" :currency="payout.usdt_body?.currency" secondary />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.rate?.price" :currency="payout.rate?.currency" compact />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Мерчант</div>
                                        <div class="font-medium text-xs text-base-content break-all">
                                            {{ payout.merchant?.owner?.email ?? '—' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-base-content/50 uppercase">Трейдер</div>
                                        <div class="font-medium text-xs text-base-content break-all">
                                            {{ payout.trader?.email ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-base-content/10 pt-2 mt-2">
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
                                        <div class="text-[10px] text-base-content/50 uppercase">Списано у мерчанта</div>
                                        <div class="font-medium text-xs text-base-content text-nowrap">
                                            <MoneyValue :value="payout.merchant_debit?.value" :currency="payout.merchant_debit?.currency" compact />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-base-content/50 uppercase">Истекает</div>
                                        <DateTime :data="payout.timings.expires_at" simple class="justify-start font-medium text-xs" />
                                    </div>
                                    <div v-if="payout.timings.completed_at">
                                        <div class="text-[10px] text-base-content/50 uppercase">Завершено</div>
                                        <DateTime :data="payout.timings.completed_at" simple class="justify-start font-medium text-xs" />
                                    </div>
                                </div>

                                <div class="grid gap-1 border-t border-base-content/10 pt-2">
                                    <div class="text-[10px] text-base-content/50 uppercase">Комиссии</div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Всего</span>
                                        <span class="font-medium">
                                            <MoneyValue :value="payout.fees.total" :currency="payout.fees.currency" compact />
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Трейдер</span>
                                        <span class="font-medium">
                                            <MoneyValue :value="payout.fees.trader" :currency="payout.fees.currency" compact />
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Тимлид</span>
                                        <span class="font-medium">
                                            <MoneyValue :value="payout.fees.teamlead" :currency="payout.fees.currency" compact />
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/60">Сервис</span>
                                        <span class="font-medium">
                                            <MoneyValue :value="payout.fees.service" :currency="payout.fees.currency" compact />
                                        </span>
                                    </div>
                                </div>

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
                        <div v-if="payoutItems.length === 0" class="py-6 text-center text-sm text-base-content/60">
                            Выплаты не найдены.
                        </div>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
