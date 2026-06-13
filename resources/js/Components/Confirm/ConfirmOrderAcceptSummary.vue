<script setup>
import DisplayUUID from '@/Components/DisplayUUID.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import PaymentDetail from '@/Components/PaymentDetail.vue';

defineProps({
    uuid: {
        type: String,
        required: true,
    },
    amount: {
        type: [String, Number],
        required: true,
    },
    currency: {
        type: String,
        required: true,
    },
    payment_detail: {
        type: String,
        default: null,
    },
    payment_detail_type: {
        type: String,
        default: null,
    },
    payment_detail_name: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="card bg-base-200/40 border border-base-300">
        <div class="card-body gap-0 divide-y divide-base-300/60 p-0">
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <span class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                    Сделка
                </span>
                <DisplayUUID :uuid="uuid" :copyable="false" />
            </div>

            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <span class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                    Сумма
                </span>
                <MoneyValue :value="amount" :currency="currency" />
            </div>

            <div class="flex flex-col gap-1.5 px-4 py-3">
                <span class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                    Реквизит
                </span>
                <PaymentDetail
                    v-if="payment_detail"
                    :detail="payment_detail"
                    :type="payment_detail_type"
                    :name="payment_detail_name"
                    :copyable="false"
                />
                <span v-else class="text-sm text-base-content/50">—</span>
            </div>
        </div>
    </div>
</template>
