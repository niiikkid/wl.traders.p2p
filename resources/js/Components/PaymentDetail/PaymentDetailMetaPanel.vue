<script setup>
import { computed } from 'vue';

const props = defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    canSetOrderAmountLimits: {
        type: Boolean,
        default: false,
    },
    showProcessing: {
        type: Boolean,
        default: false,
    },
});

const usesManualProcessing = computed(() => !props.paymentDetail.user_device_id);

const processingBadgeClass = computed(() =>
    usesManualProcessing.value ? 'badge-warning badge-outline' : 'badge-success badge-outline',
);

const processingLabel = computed(() => (usesManualProcessing.value ? 'Ручная' : 'Автоматика'));

const intervalLabel = computed(() => {
    const minutes = props.paymentDetail.order_interval_minutes;

    return minutes !== null && minutes !== undefined ? `${minutes} мин` : '—';
});

const showAmountLimits = computed(() => props.isAdmin || props.canSetOrderAmountLimits);
</script>

<template>
    <dl class="grid gap-1.5 text-sm">
        <div v-if="isAdmin" class="flex items-center justify-between gap-3">
            <dt class="text-base-content/60">Профиль</dt>
            <dd class="min-w-0 truncate text-right font-medium">{{ paymentDetail.owner_email }}</dd>
        </div>
        <div v-if="showProcessing" class="flex items-center justify-between gap-3">
            <dt class="text-base-content/60">Обработка</dt>
            <dd>
                <span class="badge badge-sm" :class="processingBadgeClass">{{ processingLabel }}</span>
            </dd>
        </div>
        <div v-if="paymentDetail.user_device_id" class="flex items-center justify-between gap-3">
            <dt class="text-base-content/60">Устройство</dt>
            <dd class="min-w-0 truncate text-right font-medium">{{ paymentDetail.device_name }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-base-content/60">Интервал</dt>
            <dd class="text-right font-medium">{{ intervalLabel }}</dd>
        </div>
        <template v-if="showAmountLimits">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-base-content/60">Мин. сумма</dt>
                <dd class="text-right font-medium">
                    {{ paymentDetail.min_order_amount !== null ? paymentDetail.min_order_amount : '∞' }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-base-content/60">Макс. сумма</dt>
                <dd class="text-right font-medium">
                    {{ paymentDetail.max_order_amount !== null ? paymentDetail.max_order_amount : '∞' }}
                </dd>
            </div>
        </template>
    </dl>
</template>
