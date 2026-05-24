<script setup>
import {
    buildScheduleSummaryDisplay,
} from '@/utils/paymentDetailScheduleStatus.js';
import { computed } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
});

const display = computed(() => buildScheduleSummaryDisplay(props.summary));
</script>

<template>
    <div
        class="mb-2 rounded-box border border-base-300 bg-base-100 px-3 py-2 text-xs shadow-sm"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-medium text-base-content">
                Сводка по расписанию
            </span>
            <span class="text-base-content/50">
                всего · {{ display.total }}
            </span>
            <span v-if="display.withSchedule > 0" class="text-base-content/60">
                с расписанием · {{ display.withSchedule }}
            </span>
        </div>

        <div v-if="!display.total" class="mt-1 text-base-content/60">
            Нет реквизитов.
        </div>

        <div v-else class="mt-1.5 flex flex-wrap items-center gap-1.5">
            <span
                v-for="item in display.items"
                :key="item.status"
                class="badge badge-sm gap-1 font-normal normal-case"
                :class="item.badgeClass"
                :title="item.countLabel"
            >
                <span class="font-semibold tabular-nums">{{ item.count }}</span>
                <span>{{ item.shortLabel }}</span>
            </span>
        </div>
    </div>
</template>
