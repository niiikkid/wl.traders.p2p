<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateFilter from '@/Components/Filters/Partials/DateFilter.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import DisplayID from '@/Components/DisplayID.vue';
import DateTime from '@/Components/DateTime.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';

const payouts = computed(() => usePage().props.payouts ?? { data: [] });
const payoutItems = computed(() => payouts.value?.data ?? []);
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

const payoutReceiptLinks = (payout) => {
    if (Array.isArray(payout?.receipt_urls)) {
        return payout.receipt_urls;
    }

    if (payout?.receipt_url) {
        return [{ id: null, filename: 'Чек 1', url: payout.receipt_url }];
    }

    return [];
};

const shortReceiptLabel = (filename) => {
    if (!filename) {
        return '—';
    }

    return String(filename).slice(0, 8);
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
                                    <th scope="col">
                                        <span class="ml-2">UUID</span>
                                    </th>
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
                                    <tr class="bg-base-100 border-base-200 border-b last:border-none">
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
                                                    <template v-if="payout.amount">
                                                        {{ payout.amount.value }}
                                                        <span class="text-primary/70">{{ payout.amount.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                                <div class="text-nowrap text-xs text-base-content/60">
                                                    <template v-if="payout.usdt_body">
                                                        {{ payout.usdt_body.value }}
                                                        <span class="text-primary/50">{{ payout.usdt_body.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-nowrap text-base-content">
                                                {{ payout.rate?.price ?? '—' }}
                                                <span v-if="payout.rate?.currency" class="text-primary/70">{{ payout.rate.currency }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-nowrap text-base-content">
                                                {{ payout.fees?.total ?? '—' }}
                                                <span v-if="payout.fees?.currency" class="text-primary/70">{{ payout.fees.currency }}</span>
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
                                                            <template v-if="payout.amount">
                                                                {{ payout.amount.value }}
                                                                <span class="text-primary/70">{{ payout.amount.currency }}</span>
                                                            </template>
                                                            <template v-else>—</template>
                                                        </div>
                                                    </div>
                                                    <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                        <div class="text-[10px] uppercase text-base-content/50">Тело / Списано</div>
                                                        <div class="font-medium">
                                                            <template v-if="payout.usdt_body">
                                                                {{ payout.usdt_body.value }}
                                                                <span class="text-primary/70">{{ payout.usdt_body.currency }}</span>
                                                            </template>
                                                            <template v-else>—</template>
                                                        </div>
                                                        <div class="font-medium">
                                                            <template v-if="payout.merchant_debit">
                                                                {{ payout.merchant_debit.value }}
                                                                <span class="text-primary/70">{{ payout.merchant_debit.currency }}</span>
                                                            </template>
                                                            <template v-else>—</template>
                                                        </div>
                                                    </div>
                                                    <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                        <div class="text-[10px] uppercase text-base-content/50">Курс / Ставка</div>
                                                        <div class="font-medium">
                                                            {{ payout.rate?.price ?? '—' }}
                                                            <span v-if="payout.rate?.currency" class="text-primary/70">{{ payout.rate.currency }}</span>
                                                        </div>
                                                        <div class="font-medium">{{ payout.commissions?.total ?? '—' }}%</div>
                                                    </div>
                                                    <div class="bg-base-100 rounded-lg px-2.5 py-2">
                                                        <div class="text-[10px] uppercase text-base-content/50">Тайминг</div>
                                                        <DateTime :data="payout.timings.created_at" simple class="justify-start font-medium" />
                                                        <DateTime v-if="payout.timings.completed_at" :data="payout.timings.completed_at" simple class="justify-start font-medium" />
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
                                                        <div v-else class="text-base-content/60">Чек недоступен.</div>
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

                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <article
                                v-for="payout in payoutItems"
                                :key="`mobile-${payout.id}`"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
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
                                                    <template v-if="payout.amount">
                                                        {{ payout.amount.value }}
                                                        <span class="text-primary/70">{{ payout.amount.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Списано</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    <template v-if="payout.merchant_debit">
                                                        {{ payout.merchant_debit.value }}
                                                        <span class="text-primary/70">{{ payout.merchant_debit.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ payout.rate?.price ?? '—' }}
                                                    <span v-if="payout.rate?.currency" class="text-primary/70">{{ payout.rate.currency }}</span>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] text-base-content/50 uppercase">Комиссия</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ payout.fees?.total ?? '—' }}
                                                    <span v-if="payout.fees?.currency" class="text-primary/70">{{ payout.fees.currency }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sm:hidden space-y-2">
                                        <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Клиент</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    <template v-if="payout.amount">
                                                        {{ payout.amount.value }}
                                                        <span class="text-primary/70">{{ payout.amount.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Списано</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    <template v-if="payout.merchant_debit">
                                                        {{ payout.merchant_debit.value }}
                                                        <span class="text-primary/70">{{ payout.merchant_debit.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Курс</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ payout.rate?.price ?? '—' }}
                                                    <span v-if="payout.rate?.currency" class="text-primary/70">{{ payout.rate.currency }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-base-content/50 uppercase">Комиссия</div>
                                                <div class="font-medium text-xs text-base-content text-nowrap">
                                                    {{ payout.fees?.total ?? '—' }}
                                                    <span v-if="payout.fees?.currency" class="text-primary/70">{{ payout.fees.currency }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-2 border-t border-base-content/10 pt-2 mt-2">
                                        <button
                                            class="btn btn-secondary btn-outline btn-xs min-h-0 h-6 px-2"
                                            type="button"
                                            @click="resendPayoutCallback(payout.uuid)"
                                        >
                                            Отправить callback
                                        </button>
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
                                                    <template v-if="payout.usdt_body">
                                                        {{ payout.usdt_body.value }}
                                                        <span class="text-primary/70">{{ payout.usdt_body.currency }}</span>
                                                    </template>
                                                    <template v-else>—</template>
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
                                        </div>
                                        <div class="grid gap-1.5 border-t border-base-content/10 pt-2">
                                            <div class="flex items-start justify-between gap-3">
                                                <span class="text-base-content/50 uppercase">Платёжный метод</span>
                                                <span class="font-medium text-base-content text-right">
                                                    <template v-if="payout.bank_name">
                                                        {{ payout.bank_name }}
                                                    </template>
                                                    <template v-else>
                                                        {{ payout.payment_gateway?.name ?? '—' }} ({{ payout.payment_gateway?.code ?? '—' }})
                                                    </template>
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
                                        </div>
                                        <div
                                            v-if="payoutReceiptLinks(payout).length"
                                            class="flex items-center justify-start gap-2 bg-base-200/40 rounded-lg p-1.5 px-2"
                                        >
                                            <div class="text-[10px] text-base-content/50 uppercase shrink-0">
                                                Чеки:
                                            </div>
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
                                        <div v-else class="text-[10px] text-base-content/50">
                                            Чек недоступен.
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
