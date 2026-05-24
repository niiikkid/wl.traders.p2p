<script setup>
import {
    PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY,
    PAYMENT_DETAIL_SCHEDULE_TICK_KEY,
} from '@/composables/usePaymentDetailScheduleTableTick.js';
import {
    resolvePaymentDetailScheduleDisplay,
    scheduleStatusBadgeClass,
    SCHEDULE_STATUS,
} from '@/utils/paymentDetailScheduleStatus.js';
import { computed, inject, ref, unref } from 'vue';

const props = defineProps({
    schedule: {
        type: Object,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const tick = inject(PAYMENT_DETAIL_SCHEDULE_TICK_KEY, null);
const offsetMs = inject(PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY, ref(0));

const display = computed(() => {
    unref(tick);

    return resolvePaymentDetailScheduleDisplay(props.schedule, unref(offsetMs));
});

const badgeClass = computed(() => scheduleStatusBadgeClass(display.value.status));

const showScheduleName = computed(
    () => display.value.status !== SCHEDULE_STATUS.NOT_CONFIGURED && display.value.scheduleName,
);
</script>

<template>
    <div class="min-w-0 space-y-1" :class="compact ? 'text-xs' : 'text-sm'">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="badge badge-sm whitespace-nowrap" :class="badgeClass">
                {{ display.statusLabel }}
            </span>
        </div>
        <template v-if="display.status !== SCHEDULE_STATUS.NOT_CONFIGURED">
            <div v-if="showScheduleName" class="truncate font-medium text-base-content">
                {{ display.scheduleName }}
            </div>
            <div
                v-if="display.intervalItems.length"
                class="text-base-content/70 flex flex-wrap items-center gap-x-1.5 text-nowrap"
            >
                <template v-for="(interval, index) in display.intervalItems" :key="index">
                    <span v-if="index > 0" class="text-base-content/35 select-none" aria-hidden="true">·</span>
                    <span>{{ interval }}</span>
                </template>
            </div>
        </template>
    </div>
</template>
