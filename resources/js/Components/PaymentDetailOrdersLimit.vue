<script setup>
import { computed } from "vue";

const props = defineProps({
    current_daily_successful_orders_count: {
        type: [Number, String],
        default: 0,
    },
    daily_successful_orders_limit: {
        type: [Number, String, null],
        default: null,
    },
});

const hasLimit = computed(() => props.daily_successful_orders_limit !== null && props.daily_successful_orders_limit !== '');

const percent = computed(() => {
    if (!hasLimit.value) {
        return 0;
    }

    const current = Number(props.current_daily_successful_orders_count) || 0;
    const limit = Number(props.daily_successful_orders_limit) || 0;

    if (limit <= 0) {
        return 0;
    }

    return Math.min(100, (current / limit) * 100);
});
</script>

<template>
    <div class="flex justify-end mb-1">
        <div class="relative text-nowrap">
            <template v-if="hasLimit">
                <span
                    class="text-xs font-semibold"
                    :class="{
                        'text-success': percent < 40,
                        'text-warning': percent >= 40 && percent < 80,
                        'text-error': percent >= 80
                    }"
                >
                    {{ current_daily_successful_orders_count }}
                </span>
                <span class="mx-1 opacity-70">из</span>
                <span class="text-xs font-semibold">
                    {{ daily_successful_orders_limit }}
                </span>
            </template>
            <template v-else>
                <span class="text-xs text-base-content/70">Без лимита</span>
            </template>
        </div>
    </div>
    <progress
        class="progress w-full"
        :class="{
            'progress-success': percent < 40,
            'progress-warning': percent >= 40 && percent < 80,
            'progress-error': percent >= 80
        }"
        :value="percent"
        max="100"
        :aria-hidden="!hasLimit"
    ></progress>
</template>
