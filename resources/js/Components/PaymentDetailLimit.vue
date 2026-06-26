<script setup>
import {computed} from "vue";

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    current_daily_limit: {
        type: [Number, String],
        default: 0,
    },
    daily_limit: {
        type: [Number, String, null],
        default: null,
    },
});

const hasLimit = computed(() => props.daily_limit !== null && props.daily_limit !== '');

const normalizeNumber = (value) => {
    return Number(String(value ?? 0).replace(/\s/g, '').replace(',', '.')) || 0;
};

const percent = computed(() => {
    if (!hasLimit.value) {
        return 0;
    }

    const limit = normalizeNumber(props.daily_limit);
    if (limit <= 0) {
        return 0;
    }

    return Math.min(100, (normalizeNumber(props.current_daily_limit) / limit) * 100);
});

</script>

<template>
    <div class="mb-1 flex min-w-0 flex-nowrap items-center justify-between gap-2">
        <div v-if="label" class="min-w-0 truncate text-xs text-base-content/70">
            {{ label }}
        </div>
        <div class="relative shrink-0 text-nowrap">
            <template v-if="hasLimit">
                <span
                    class="text-xs font-semibold"
                    :class="{
                        'text-success': percent < 40,
                        'text-warning': percent >= 40 && percent < 80,
                        'text-error': percent >= 80
                    }"
                >
                    {{current_daily_limit}}
                </span>
                <span class="mx-1 opacity-70">из</span>
                <span class="text-xs font-semibold">
                    {{daily_limit}}
                </span>
            </template>
            <template v-else>
                <span class="text-xs text-base-content/70">Без лимита</span>
            </template>
        </div>
    </div>
    <progress class="progress w-full" :class="{
        'progress-success': percent < 40,
        'progress-warning': percent >= 40 && percent < 80,
        'progress-error': percent >= 80
    }" :value="percent" max="100" :aria-hidden="!hasLimit"></progress>
</template>
