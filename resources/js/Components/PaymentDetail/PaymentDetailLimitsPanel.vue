<script setup>
import { computed } from 'vue';
import PaymentDetailLimit from '@/Components/PaymentDetailLimit.vue';
import PaymentDetailOrdersLimit from '@/Components/PaymentDetailOrdersLimit.vue';
import { hasLimit, percentFrom, usageProgressClass, usageTextClass } from '@/utils/paymentDetailLimits.js';

const props = defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
});

const activePercent = computed(() =>
    percentFrom(props.paymentDetail.pending_orders_count, props.paymentDetail.max_pending_orders_quantity),
);

const activeHasLimit = computed(() => hasLimit(props.paymentDetail.max_pending_orders_quantity));

const monthlyResetDay = computed(() => props.paymentDetail.monthly_limit_reset_day ?? '—');
</script>

<template>
    <div class="grid gap-2 text-sm">
        <div class="rounded-box border border-base-200 bg-base-200/30 p-2.5">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-base-content/60">
                    Сегодня
                </span>
                <span class="badge badge-xs badge-ghost">суточные</span>
            </div>
            <div class="grid gap-2.5">
                <div class="grid gap-1">
                    <div class="flex min-w-0 flex-nowrap items-center justify-between gap-2">
                        <div class="min-w-0 truncate text-xs text-base-content/70">Активные сделки</div>
                        <div class="shrink-0 text-nowrap text-xs">
                            <span class="font-semibold" :class="usageTextClass(activePercent, activeHasLimit)">
                                {{ paymentDetail.pending_orders_count }}
                            </span>
                            <span class="mx-1 opacity-60">из</span>
                            <span class="font-semibold">{{ paymentDetail.max_pending_orders_quantity }}</span>
                        </div>
                    </div>
                    <progress
                        class="progress w-full"
                        :class="usageProgressClass(activePercent, activeHasLimit)"
                        :value="activePercent"
                        max="100"
                    ></progress>
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

        <div class="rounded-box border border-base-200 bg-base-200/30 p-2.5">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-base-content/60">
                    В этом месяце
                </span>
                <span class="badge badge-xs badge-ghost">сброс {{ monthlyResetDay }}</span>
            </div>
            <div class="grid gap-2.5">
                <PaymentDetailLimit
                    v-if="hasLimit(paymentDetail.monthly_limit)"
                    label="Объём сделок"
                    :current_daily_limit="paymentDetail.current_monthly_limit"
                    :daily_limit="paymentDetail.monthly_limit"
                />
                <PaymentDetailOrdersLimit
                    label="Количество сделок"
                    :current_daily_successful_orders_count="paymentDetail.current_monthly_successful_orders_count"
                    :daily_successful_orders_limit="paymentDetail.monthly_successful_orders_limit"
                />
            </div>
        </div>
    </div>
</template>
