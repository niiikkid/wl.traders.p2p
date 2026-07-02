<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        default: '',
    },
    prefix: {
        type: String,
        default: '',
    },
    suffix: {
        type: String,
        default: '',
    },
    color: {
        type: String,
        default: 'primary',
    },
});

const CHIP_CLASSES = {
    primary: 'bg-primary/10 text-primary',
    secondary: 'bg-secondary/10 text-secondary',
    accent: 'bg-accent/10 text-accent',
    info: 'bg-info/10 text-info',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/10 text-warning',
    error: 'bg-error/10 text-error',
};

const chipClass = computed(() => CHIP_CLASSES[props.color] || CHIP_CLASSES.primary);
</script>

<template>
    <div class="rounded-box border border-base-300/60 bg-base-100 transition-colors hover:border-base-300">
        <div class="flex items-center justify-between gap-3 px-4 py-3.5">
            <div class="min-w-0">
                <div class="flex items-center gap-1">
                    <p class="truncate text-xs font-medium text-base-content/55">{{ label }}</p>
                    <slot name="label-suffix" />
                </div>
                <p class="mt-1.5 truncate text-xl font-semibold leading-none tabular-nums text-base-content">
                    <span v-if="prefix" class="text-base-content/45">{{ prefix }}</span>{{ value }}<span v-if="suffix" class="text-base-content/45">{{ suffix }}</span>
                </p>
            </div>
            <span
                v-if="$slots.icon"
                class="grid size-9 shrink-0 place-items-center rounded-xl [&>svg]:size-5"
                :class="chipClass"
            >
                <slot name="icon" />
            </span>
        </div>
    </div>
</template>
