<script setup>
import { computed } from 'vue';
import { formatInteger, formatMoneyAmount } from '@/utils/paymentDetailLimits.js';

const props = defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
});

const currencyLabel = computed(() => props.paymentDetail.currency?.toUpperCase?.() ?? '');
</script>

<template>
    <div class="grid gap-1.5 text-sm">
        <div class="flex items-center justify-between gap-3">
            <span class="text-base-content/60">Всего сделок</span>
            <span class="text-right font-semibold">
                {{ formatInteger(paymentDetail.successful_orders_total_count) }}
            </span>
        </div>
        <div class="flex items-center justify-between gap-3">
            <span class="text-base-content/60">Оборот</span>
            <span class="text-right font-semibold">
                {{ formatMoneyAmount(paymentDetail.successful_orders_total_turnover_fiat) }}
                <span class="text-primary">{{ currencyLabel }}</span>
            </span>
        </div>
        <div class="flex items-center justify-between gap-3">
            <span class="text-base-content/60">Оборот</span>
            <span class="text-right font-semibold">
                {{ formatMoneyAmount(paymentDetail.successful_orders_total_turnover_usdt) }}
                <span class="text-primary">USDT</span>
            </span>
        </div>
        <p class="pt-1 text-center text-[11px] text-base-content/50">
            Обновляется раз в 15 минут
        </p>
    </div>
</template>
