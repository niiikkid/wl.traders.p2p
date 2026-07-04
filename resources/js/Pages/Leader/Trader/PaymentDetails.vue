<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {onMounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Partials/FilterCheckbox.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import PaymentDetailGatewayWithCurrency from "@/Components/PaymentDetail/PaymentDetailGatewayWithCurrency.vue";
import PaymentDetailLimit from "@/Components/PaymentDetailLimit.vue";
import PaymentDetailOrdersLimit from "@/Components/PaymentDetailOrdersLimit.vue";
import TableCellPopover from "@/Components/Table/TableCellPopover.vue";
import TableInfoDropdown from "@/Components/Table/TableInfoDropdown.vue";
import PaymentDetailScheduleStatus from "@/Components/PaymentDetail/PaymentDetailScheduleStatus.vue";
import {usePaymentDetailScheduleTableTick} from "@/composables/usePaymentDetailScheduleTableTick.js";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import CopyableOrderUid from "@/Components/CopyableOrderUid.vue";
import TraderCardHeader from "@/Components/Leader/TraderCardHeader.vue";
import TraderPaymentDetailsSubNav from "@/Components/Leader/TraderPaymentDetailsSubNav.vue";

const page = usePage();
const tableFiltersStore = useTableFiltersStore();

const trader = ref(page.props.trader);
const paymentDetails = ref(page.props.paymentDetails);
usePaymentDetailScheduleTableTick(paymentDetails);
const currentTab = ref('active');

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
};

const percentFrom = (current, limit) => {
    const currentValue = normalizeNumber(current);
    const limitValue = normalizeNumber(limit);

    if (limitValue <= 0) {
        return 0;
    }

    return Math.min(100, (currentValue / limitValue) * 100);
};

const hasLimit = (limit) => {
    return normalizeNumber(limit) > 0;
};

const progressClass = (percent, has_limit = true) => {
    if (!has_limit) {
        return 'text-base-content/40';
    }

    if (percent < 40) {
        return 'text-success';
    }

    if (percent < 80) {
        return 'text-warning';
    }

    return 'text-error';
};

const percentLabel = (percent) => {
    if (!Number.isFinite(percent)) {
        return '0%';
    }

    return `${Math.round(percent)}%`;
};

const radialStyle = (value) => {
    return {
        '--value': value,
        '--size': '2.4rem',
        '--thickness': '3px',
    };
};

router.on('success', () => {
    trader.value = usePage().props.trader;
    paymentDetails.value = usePage().props.paymentDetails;
    currentTab.value = tableFiltersStore.getTab || 'active';
});

onMounted(() => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('active');
    }

    currentTab.value = tableFiltersStore.getTab || 'active';
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`${trader.email} — Реквизиты`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="paymentDetails"
        >
            <template #header>
                <TraderCardHeader :trader="trader" current="payment-details">
                    <TraderPaymentDetailsSubNav
                        :trader-id="trader.id"
                        :current="currentTab"
                    />
                </TraderCardHeader>
            </template>

            <template #table-filters>
                <FiltersPanel name="leader-trader-payment-details">
                    <InputFilter
                        name="id"
                        placeholder="UUID реквизита"
                    />
                    <InputFilter
                        name="name"
                        placeholder="Название"
                    />
                    <DropdownFilter
                        name="detailTypes"
                        title="Тип реквизита"
                    />
                    <InputFilter
                        name="paymentGateway"
                        placeholder="Платежный метод"
                    />
                    <InputFilter
                        name="paymentDetail"
                        placeholder="Реквизит"
                    />
                    <FilterCheckbox
                        name="active"
                        title="Включенные"
                    />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <DataTable>
                        <template #head>
                            <th>UUID</th>
                            <th>Реквизит</th>
                            <th>Лимиты</th>
                            <th class="text-nowrap">Расписание</th>
                            <th>Статус</th>
                            <th><span class="sr-only">Настройки</span></th>
                        </template>
                                    <tr v-for="detail in paymentDetails.data" :key="detail.uuid" class="hover">
                                        <th class="font-medium whitespace-nowrap">
                                            <CopyableOrderUid :uuid="detail.uuid ?? ''" />
                                        </th>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <PaymentDetailGatewayWithCurrency
                                                    :img_path="detail.payment_gateway.logo_path"
                                                    :name="detail.payment_gateway.name"
                                                    :currency="detail.currency"
                                                />
                                                <PaymentDetail
                                                    :detail="detail.detail"
                                                    :type="detail.detail_type"
                                                    :name="detail.name"
                                                />
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <TableCellPopover>
                                                <template #trigger>
                                                    <div class="flex items-center gap-2">
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity),
                                                                    hasLimit(detail.max_pending_orders_quantity)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit),
                                                                    hasLimit(detail.daily_successful_orders_limit)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.current_daily_limit, detail.daily_limit),
                                                                    hasLimit(detail.daily_limit)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.current_daily_limit, detail.daily_limit))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.current_daily_limit, detail.daily_limit)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.current_daily_limit, detail.daily_limit)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div class="grid gap-3 text-sm">
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Активных сделок</div>
                                                        <div class="flex justify-end mb-1">
                                                            <div class="relative text-nowrap">
                                                                <span
                                                                    class="text-xs font-semibold"
                                                                    :class="{
                                                                        'text-success': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 40,
                                                                        'text-warning': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 40 && percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 80,
                                                                        'text-error': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 80
                                                                    }"
                                                                >
                                                                    {{ detail.pending_orders_count }}
                                                                </span>
                                                                <span class="mx-1 opacity-70">из</span>
                                                                <span class="text-xs font-semibold">
                                                                    {{ detail.max_pending_orders_quantity }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <progress
                                                            class="progress w-full"
                                                            :class="{
                                                                'progress-success': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 40,
                                                                'progress-warning': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 40 && percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 80,
                                                                'progress-error': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 80
                                                            }"
                                                            :value="percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)"
                                                            max="100"
                                                        ></progress>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Количество сделок за день</div>
                                                        <PaymentDetailOrdersLimit
                                                            :current_daily_successful_orders_count="detail.current_daily_successful_orders_count"
                                                            :daily_successful_orders_limit="detail.daily_successful_orders_limit"
                                                        />
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Объём сделок за день</div>
                                                        <PaymentDetailLimit
                                                            :current_daily_limit="detail.current_daily_limit"
                                                            :daily_limit="detail.daily_limit"
                                                        />
                                                    </div>
                                                    <div v-if="hasLimit(detail.monthly_limit)" class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Объём сделок за месяц</div>
                                                        <PaymentDetailLimit
                                                            :current_daily_limit="detail.current_monthly_limit"
                                                            :daily_limit="detail.monthly_limit"
                                                        />
                                                    </div>
                                                </div>
                                            </TableCellPopover>
                                        </td>
                                        <td class="min-w-[9rem]">
                                            <PaymentDetailScheduleStatus
                                                :schedule="detail.schedule"
                                                compact
                                            />
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-success badge-sm" v-if="detail.is_active">Активен</span>
                                            <span class="badge badge-ghost badge-sm" v-else>Выключен</span>
                                        </td>
                                        <td class="text-right">
                                            <TableInfoDropdown>
                                                <div class="grid gap-2 text-sm">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/70">Интервал:</span>
                                                        <span class="text-right">{{ detail.order_interval_minutes !== null ? detail.order_interval_minutes + ' мин' : '-' }}</span>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-base-content/70">Мин:</span>
                                                            <span class="text-right">{{ detail.min_order_amount !== null ? detail.min_order_amount : '∞' }}</span>
                                                        </div>
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-base-content/70">Макс:</span>
                                                            <span class="text-right">{{ detail.max_order_amount !== null ? detail.max_order_amount : '∞' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableInfoDropdown>
                                        </td>
                                    </tr>
                    </DataTable>

                    <DataCardList>
                        <DataCard v-for="detail in paymentDetails.data" :key="detail.uuid">
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-base-content/70">UUID:</span>
                                        <CopyableOrderUid :uuid="detail.uuid ?? ''" />
                                    </div>
                                    <span class="badge badge-success badge-sm" v-if="detail.is_active">Активен</span>
                                    <span class="badge badge-ghost badge-sm" v-else>Выключен</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <PaymentDetailGatewayWithCurrency
                                        :img_path="detail.payment_gateway.logo_path"
                                        :name="detail.payment_gateway.name"
                                        :currency="detail.currency"
                                    />
                                    <div class="min-w-0">
                                        <PaymentDetail
                                            :detail="detail.detail"
                                            :type="detail.detail_type"
                                            :name="detail.name"
                                        />
                                    </div>
                                </div>
                                <div class="mt-2 grid gap-1 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Сумма:</span>
                                        <span class="text-right">
                                            {{ detail.min_order_amount !== null ? detail.min_order_amount : '∞' }}
                                            -
                                            {{ detail.max_order_amount !== null ? detail.max_order_amount : '∞' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Активных сделок:</span>
                                        <span class="text-right">{{ detail.pending_orders_count }} / {{ detail.max_pending_orders_quantity }}</span>
                                    </div>
                                    <div class="grid gap-1">
                                        <div class="text-base-content/70">Лимит сделок/день:</div>
                                        <PaymentDetailOrdersLimit
                                            :current_daily_successful_orders_count="detail.current_daily_successful_orders_count"
                                            :daily_successful_orders_limit="detail.daily_successful_orders_limit"
                                        />
                                    </div>
                                    <div class="grid gap-1">
                                        <div class="text-base-content/70">Объем/день:</div>
                                        <PaymentDetailLimit
                                            :current_daily_limit="detail.current_daily_limit"
                                            :daily_limit="detail.daily_limit"
                                        />
                                    </div>
                                    <div class="text-xs">
                                        <div class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60 mb-1">
                                            Расписание
                                        </div>
                                        <PaymentDetailScheduleStatus
                                            :schedule="detail.schedule"
                                            compact
                                        />
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Интервал:</span>
                                        <span class="text-right">{{ detail.order_interval_minutes !== null ? detail.order_interval_minutes + ' мин' : '-' }}</span>
                                    </div>
                                </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

