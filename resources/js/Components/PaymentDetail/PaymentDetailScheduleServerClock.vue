<script setup>
import {
    PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY,
    PAYMENT_DETAIL_SCHEDULE_TICK_KEY,
    PAYMENT_DETAIL_SCHEDULE_TIMEZONE_KEY,
} from '@/composables/usePaymentDetailScheduleTableTick.js';
import { formatServerScheduleClock } from '@/utils/paymentDetailScheduleStatus.js';
import { computed, inject, ref, unref } from 'vue';

const tick = inject(PAYMENT_DETAIL_SCHEDULE_TICK_KEY, null);
const offsetMs = inject(PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY, ref(0));
const serverTimezone = inject(PAYMENT_DETAIL_SCHEDULE_TIMEZONE_KEY, ref('UTC'));

const display = computed(() => {
    unref(tick);

    return formatServerScheduleClock(
        new Date(Date.now() + unref(offsetMs)),
        unref(serverTimezone),
    );
});

const title = computed(() => `Время сервера (${display.value.timezone})`);
</script>

<template>
    <span
        class="badge badge-sm badge-ghost border border-base-300 font-normal normal-case gap-1.5 px-2"
        :title="title"
    >
        <span class="text-base-content/60">{{ display.weekday }}</span>
        <span class="font-mono tabular-nums font-medium text-base-content">{{ display.time }}</span>
    </span>
</template>
