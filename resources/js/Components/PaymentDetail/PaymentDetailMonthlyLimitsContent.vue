<script setup>
import PaymentDetailLimit from '@/Components/PaymentDetailLimit.vue';
import PaymentDetailOrdersLimit from '@/Components/PaymentDetailOrdersLimit.vue';

defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-2 text-sm">
        <div class="grid gap-1">
            <PaymentDetailLimit
                label="Объём сделок"
                :current_daily_limit="paymentDetail.current_monthly_limit"
                :daily_limit="paymentDetail.monthly_limit"
            />
        </div>
        <div class="grid gap-1">
            <PaymentDetailOrdersLimit
                label="Количество сделок"
                :current_daily_successful_orders_count="paymentDetail.current_monthly_successful_orders_count"
                :daily_successful_orders_limit="paymentDetail.monthly_successful_orders_limit"
            />
        </div>
    </div>
</template>
