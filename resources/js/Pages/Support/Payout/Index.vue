<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Pertials/DropdownFilter.vue';
import RefreshTableData from '@/Components/Table/RefreshTableData.vue';
import DisplayUUID from '@/Components/DisplayUUID.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';

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
            <template #header>
                <div class="space-y-4">
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

                    <div class="flex items-center justify-between">
                        <div
                            v-if="reloadingTableData"
                            class="px-2 text-sm text-base-content/80 flex items-center gap-2 xl:hidden"
                            aria-live="polite"
                        >
                            <span class="loading loading-spinner loading-sm text-primary" />
                            <span>Обновляем данные...</span>
                        </div>
                        <div
                            v-if="reloadingTableData"
                            class="hidden xl:flex px-2 text-sm text-base-content/80 items-center gap-2"
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
                                    <th class="w-24 text-center">Подробнее</th>
                                </tr>
                                </thead>
                                <tbody>
                                <template v-for="payout in payoutItems" :key="payout.id">
                                    <tr class="bg-base-100 border-base-200 border-b last:border-none align-top">
                                        <td>
                                            <DisplayUUID :uuid="payout.uuid" class="text-sm font-semibold" />
                                        </td>
                                        <td>
                                            <div>
                                                <div class="font-semibold text-base-content">{{ payout.requisites }}</div>
                                                <div class="text-xs text-base-content/60">
                                                    {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
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

                                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
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
                                                                    <span class="text-xs text-base-content/60">Тело (USDT)</span>
                                                                    <span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card bg-base-100 shadow-sm">
                                                        <div class="card-body text-sm">
                                                            <div class="text-xs uppercase text-base-content/50">Тайминг</div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Создано</div>
                                                                <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-base-content/60">Истекает</div>
                                                                <DateTime :data="payout.timings.expires_at" simple class="justify-start font-semibold" />
                                                            </div>
                                                            <div v-if="payout.timings.completed_at">
                                                                <div class="text-xs text-base-content/60">Завершено</div>
                                                                <DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" />
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

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden rounded-table relative">
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

                        <div class="space-y-3" :class="{'pointer-events-none': reloadingTableData}">
                            <div class="space-y-2">
                                <div
                                    v-for="payout in payoutItems"
                                    :key="`mobile-${payout.id}`"
                                    class="card bg-base-100 shadow-sm"
                                >
                                    <div class="card-body p-4 pt-2 pb-3 space-y-2">
                                        <div class="flex justify-between items-center gap-2 border-b border-base-content/10 pb-1 min-w-0">
                                            <div class="min-w-0 flex-1 text-[11px]">
                                                <div class="inline-flex items-center gap-1 pl-1 min-w-0">
                                                    <span class="text-base-content/70">UUID:</span>
                                                    <DisplayUUID :uuid="payout.uuid" class="text-sm font-semibold" />
                                                </div>
                                            </div>
                                            <div class="shrink-0">
                                                <div class="badge badge-sm" :class="statusBadge(payout.status)">
                                                    {{ payout.status_label }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 min-w-0 pt-1">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-medium text-base-content leading-snug break-words">
                                                    {{ payout.requisites }}
                                                </div>
                                                <div class="text-[11px] text-base-content/60 leading-snug">
                                                    {{ resolveBankName(payout) }} · {{ payout.payout_method_type.label }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border-b border-base-content/10 my-1"></div>

                                        <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Сумма</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ formatMoney(payout.amount) }}
                                                </div>
                                                <div class="text-[10px] text-base-content/60 text-nowrap">
                                                    {{ formatMoney(payout.usdt_body) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ payout.rate?.price ?? '—' }} {{ payout.rate?.currency ?? '' }}
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

                                        <button
                                            class="btn btn-ghost btn-xs w-full text-xs"
                                            type="button"
                                            @click="toggleRow(payout.id)"
                                        >
                                            <span>{{ isExpanded(payout.id) ? 'Скрыть' : 'Подробнее' }}</span>
                                            <svg class="size-4 transition-transform" :class="{'rotate-180': isExpanded(payout.id)}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div v-if="isExpanded(payout.id)" class="bg-base-200/40 border border-base-300 rounded-box p-3 space-y-3">
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

                                            <div class="space-y-2">
                                                <div class="card bg-base-100 shadow-sm">
                                                    <div class="card-body text-sm p-3">
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
                                                    </div>
                                                </div>
                                                <div class="card bg-base-100 shadow-sm">
                                                    <div class="card-body text-sm p-3">
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
                                                                <span class="text-xs text-base-content/60">Тело (USDT)</span>
                                                                <span class="font-semibold">{{ formatMoney(payout.usdt_body) }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card bg-base-100 shadow-sm">
                                                    <div class="card-body text-sm p-3">
                                                        <div class="text-xs uppercase text-base-content/50">Тайминг</div>
                                                        <div>
                                                            <div class="text-xs text-base-content/60">Создано</div>
                                                            <DateTime :data="payout.timings.created_at" simple class="justify-start font-semibold" />
                                                        </div>
                                                        <div>
                                                            <div class="text-xs text-base-content/60">Истекает</div>
                                                            <DateTime :data="payout.timings.expires_at" simple class="justify-start font-semibold" />
                                                        </div>
                                                        <div v-if="payout.timings.completed_at">
                                                            <div class="text-xs text-base-content/60">Завершено</div>
                                                            <DateTime :data="payout.timings.completed_at" simple class="justify-start font-semibold" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="space-y-2">
                                                <h4 class="text-sm font-semibold text-base-content">Операции</h4>
                                                <div v-if="(payout.operations ?? []).length" class="space-y-2">
                                                    <div
                                                        v-for="operation in payout.operations"
                                                        :key="`mobile-op-${operation.id}`"
                                                        class="border border-base-300 rounded-box p-3 space-y-1 text-xs bg-base-100"
                                                    >
                                                        <div class="font-semibold text-sm">{{ operation.type_label }}</div>
                                                        <div>Сумма: {{ formatMoney(operation.amount) }}</div>
                                                        <div>
                                                            Дата:
                                                            <DateTime :data="operation.created_at" simple class="justify-start font-semibold" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-sm text-base-content/60">
                                                    Операции не найдены.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="payoutItems.length === 0" class="py-6 text-center text-sm text-base-content/60">
                                Выплаты не найдены.
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
