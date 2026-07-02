<script setup>
import PaymentDetailLimit from '@/Components/PaymentDetailLimit.vue';
import PaymentDetailOrdersLimit from '@/Components/PaymentDetailOrdersLimit.vue';
import PaymentDetailMonthlyLimitsContent from '@/Components/PaymentDetail/PaymentDetailMonthlyLimitsContent.vue';
import PaymentDetailScheduleStatus from '@/Components/PaymentDetail/PaymentDetailScheduleStatus.vue';
import { formatMonthlyLimitResetLabel } from '@/utils/paymentDetailMonthlyLimits.js';
import { useViewStore } from '@/store/view.js';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
});

const viewStore = useViewStore();
const currentUser = usePage().props.auth?.user;

const canSetOrderAmountLimits = computed(() => (
    currentUser?.can_set_order_amount_limits === true
    || currentUser?.can_set_order_amount_limits === 1
));

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

const pendingProgressClass = computed(() => {
    const percent = percentFrom(
        props.paymentDetail.pending_orders_count,
        props.paymentDetail.max_pending_orders_quantity,
    );

    if (percent < 40) {
        return 'progress-success';
    }

    if (percent < 80) {
        return 'progress-warning';
    }

    return 'progress-error';
});

const pendingCountClass = computed(() => {
    const percent = percentFrom(
        props.paymentDetail.pending_orders_count,
        props.paymentDetail.max_pending_orders_quantity,
    );

    if (percent < 40) {
        return 'text-success';
    }

    if (percent < 80) {
        return 'text-warning';
    }

    return 'text-error';
});

const detailUsesManualProcessing = computed(() => !props.paymentDetail.user_device_id);

const processingModeBadgeClass = computed(() => (
    detailUsesManualProcessing.value
        ? 'badge-warning badge-outline'
        : 'badge-success badge-outline'
));

const processingModeLabel = computed(() => (
    detailUsesManualProcessing.value ? 'Ручной' : 'Автоматика'
));

const displayValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return value;
};

const isActiveLabel = computed(() => (
    props.paymentDetail.is_active ? 'Включен' : 'Выключен'
));

const isActiveBadgeClass = computed(() => (
    props.paymentDetail.is_active
        ? 'badge-success badge-outline'
        : 'badge-ghost badge-outline'
));

const showAdditionalInfo = computed(() => (
    props.paymentDetail.detail_type === 'iban_uah'
    || (props.paymentDetail.additional_info !== null
        && props.paymentDetail.additional_info !== undefined
        && props.paymentDetail.additional_info !== '')
));

const sectionClass = 'rounded-box border border-base-300 bg-base-200/40 p-3';
const sectionTitleClass = 'text-[10px] font-semibold uppercase tracking-wide text-base-content/60';
</script>

<template>
    <div class="grid gap-3">
        <div :class="sectionClass">
            <p class="mb-2.5" :class="sectionTitleClass">
                Информация по реквизиту
            </p>
            <div class="grid gap-1.5 text-sm">
                <div
                    v-if="paymentDetail.payment_gateway?.name"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="text-base-content/70">Банк:</span>
                    <span class="text-right font-medium">{{ paymentDetail.payment_gateway.name }}</span>
                </div>
                <div
                    v-if="viewStore.isAdminViewMode && paymentDetail.owner_email"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="text-base-content/70">Профиль:</span>
                    <span class="text-right">{{ paymentDetail.owner_email }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base-content/70">Инициалы:</span>
                    <span class="text-right">{{ displayValue(paymentDetail.initials) }}</span>
                </div>
                <div
                    v-if="showAdditionalInfo"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="text-base-content/70">ИПН (ИНН):</span>
                    <span class="text-right">{{ displayValue(paymentDetail.additional_info) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base-content/70">Статус:</span>
                    <span class="badge badge-sm" :class="isActiveBadgeClass">
                        {{ isActiveLabel }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base-content/70">Обработка:</span>
                    <span class="badge badge-sm" :class="processingModeBadgeClass">
                        {{ processingModeLabel }}
                    </span>
                </div>
                <div
                    v-if="paymentDetail.user_device_id"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="text-base-content/70">Устройство:</span>
                    <span class="text-right">{{ paymentDetail.device_name }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base-content/70">Макс. активных:</span>
                    <span class="text-right">
                        {{ displayValue(paymentDetail.max_pending_orders_quantity) }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-base-content/70">Интервал:</span>
                    <span class="text-right">
                        {{ paymentDetail.order_interval_minutes !== null && paymentDetail.order_interval_minutes !== ''
                            ? paymentDetail.order_interval_minutes + ' мин'
                            : '-' }}
                    </span>
                </div>
                <div
                    v-if="viewStore.isAdminViewMode || canSetOrderAmountLimits"
                    class="grid gap-1"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-base-content/70">Мин:</span>
                        <span class="text-right">
                            {{ paymentDetail.min_order_amount !== null ? paymentDetail.min_order_amount : '∞' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-base-content/70">Макс:</span>
                        <span class="text-right">
                            {{ paymentDetail.max_order_amount !== null ? paymentDetail.max_order_amount : '∞' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div :class="sectionClass">
            <p class="mb-2.5" :class="sectionTitleClass">
                Расписание
            </p>
            <PaymentDetailScheduleStatus
                :schedule="paymentDetail.schedule"
                compact
                :live="false"
            />
        </div>

        <div :class="sectionClass">
            <div class="mb-2.5 flex items-center justify-between gap-2">
                <p :class="sectionTitleClass">
                    Дневные лимиты
                </p>
                <span class="badge badge-xs badge-outline">суточные</span>
            </div>
            <div class="grid gap-2 text-sm">
                <div class="grid gap-1">
                    <div class="flex min-w-0 flex-nowrap items-center justify-between gap-2">
                        <div class="min-w-0 truncate text-xs text-base-content/70">
                            Активных сделок
                        </div>
                        <div class="relative shrink-0 text-nowrap">
                            <span
                                class="text-xs font-semibold"
                                :class="pendingCountClass"
                            >
                                {{ paymentDetail.pending_orders_count }}
                            </span>
                            <span class="mx-1 opacity-70">из</span>
                            <span class="text-xs font-semibold">
                                {{ paymentDetail.max_pending_orders_quantity }}
                            </span>
                        </div>
                    </div>
                    <progress
                        class="progress w-full"
                        :class="pendingProgressClass"
                        :value="percentFrom(paymentDetail.pending_orders_count, paymentDetail.max_pending_orders_quantity)"
                        max="100"
                    />
                </div>
                <PaymentDetailOrdersLimit
                    label="Количество сделок"
                    :current_daily_successful_orders_count="paymentDetail.current_daily_successful_orders_count"
                    :daily_successful_orders_limit="paymentDetail.daily_successful_orders_limit"
                />
                <PaymentDetailLimit
                    label="Объём сделок"
                    :current_daily_limit="paymentDetail.current_daily_limit"
                    :daily_limit="paymentDetail.daily_limit"
                />
            </div>
        </div>

        <div :class="sectionClass">
            <div class="mb-2.5 flex items-center justify-between gap-2">
                <p :class="sectionTitleClass">
                    Месячные лимиты
                </p>
                <span class="badge badge-xs badge-outline">
                    {{ formatMonthlyLimitResetLabel(paymentDetail.monthly_limit_reset_day) }}
                </span>
            </div>
            <PaymentDetailMonthlyLimitsContent :payment-detail="paymentDetail" />
        </div>
    </div>
</template>
