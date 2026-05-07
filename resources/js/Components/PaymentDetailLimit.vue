<script setup>
import {computed} from "vue";

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    current_daily_limit: {
        type: String,
    },
    daily_limit: {
        type: String,
    },
});

const percent = computed(() => {
    return props.current_daily_limit / (props.daily_limit/100);
});

const color = computed(() => {
    if (percent.value < 40) {
        return 'bg-green-400';
    } else if (percent.value > 40 && percent.value < 80) {
        return 'bg-yellow-400';
    } else if (percent.value > 80) {
        return 'bg-red-600';
    }
})

</script>

<template>
    <div class="mb-1 flex min-w-0 flex-nowrap items-center justify-between gap-2">
        <div v-if="label" class="min-w-0 truncate text-xs text-base-content/70">
            {{ label }}
        </div>
        <div class="relative shrink-0 text-nowrap">
            <span
                class="text-xs font-semibold"
                :class="{
                    'text-success': current_daily_limit / daily_limit < 0.4,
                    'text-warning': current_daily_limit / daily_limit >= 0.4 && current_daily_limit / daily_limit < 0.8,
                    'text-error': current_daily_limit / daily_limit >= 0.8
                }"
            >
                {{current_daily_limit}}
            </span>
            <span class="mx-1 opacity-70">из</span>
            <span class="text-xs font-semibold">
                {{daily_limit}}
            </span>
        </div>
    </div>
    <progress class="progress w-full" :class="{
        'progress-success': percent < 40,
        'progress-warning': percent >= 40 && percent < 80,
        'progress-error': percent >= 80
    }" :value="percent" max="100"></progress>
</template>

<style scoped>

</style>
