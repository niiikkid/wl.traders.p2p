<script setup>
import { computed } from 'vue';
import {
    hasLimit,
    percentFrom,
    percentLabel,
    radialStyle,
    usageTextClass,
} from '@/utils/paymentDetailLimits.js';

const props = defineProps({
    paymentDetail: {
        type: Object,
        required: true,
    },
    /** Show a short caption under each ring. */
    withCaptions: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: '2.6rem',
    },
});

const rings = computed(() => {
    const detail = props.paymentDetail;

    return [
        {
            key: 'active',
            caption: 'Активные',
            current: detail.pending_orders_count,
            limit: detail.max_pending_orders_quantity,
        },
        {
            key: 'count',
            caption: 'Сделки',
            current: detail.current_daily_successful_orders_count,
            limit: detail.daily_successful_orders_limit,
        },
        {
            key: 'volume',
            caption: 'Объём',
            current: detail.current_daily_limit,
            limit: detail.daily_limit,
        },
    ].map((ring) => {
        const percent = percentFrom(ring.current, ring.limit);
        const active = hasLimit(ring.limit);

        return {
            ...ring,
            percent,
            active,
            label: active ? percentLabel(percent) : '∞',
            colorClass: usageTextClass(percent, active),
        };
    });
});
</script>

<template>
    <div class="flex items-start gap-3">
        <div
            v-for="ring in rings"
            :key="ring.key"
            class="flex flex-col items-center gap-1"
        >
            <div class="relative grid place-items-center">
                <div
                    class="radial-progress text-base-300/60"
                    :style="radialStyle(100, size)"
                ></div>
                <div
                    class="radial-progress absolute inset-0"
                    :class="ring.colorClass"
                    :style="radialStyle(ring.active ? ring.percent : 0, size)"
                    role="progressbar"
                    :aria-valuenow="ring.active ? Math.round(ring.percent) : 0"
                    :aria-label="ring.caption"
                >
                    <span class="text-[10px] font-semibold leading-none">
                        {{ ring.label }}
                    </span>
                </div>
            </div>
            <span
                v-if="withCaptions"
                class="text-[10px] font-medium uppercase tracking-wide text-base-content/50"
            >
                {{ ring.caption }}
            </span>
        </div>
    </div>
</template>
