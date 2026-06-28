<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    amount: {
        type: [String, Number],
        default: null,
    },
    currency: {
        type: String,
        default: null,
    },
    accent: {
        type: String,
        default: 'primary',
    },
});

const accentClasses = {
    primary: 'bg-primary/10 text-primary',
    success: 'bg-success/10 text-success',
    info: 'bg-info/10 text-info',
    warning: 'bg-warning/10 text-warning',
    error: 'bg-error/10 text-error',
    neutral: 'bg-base-content/10 text-base-content/70',
    accent: 'bg-accent/10 text-accent',
};

const iconClass = computed(() => accentClasses[props.accent] ?? accentClasses.primary);
</script>

<template>
    <div class="card bg-base-100 border border-base-300/60 shadow-sm rounded-2xl h-full">
        <div class="card-body gap-3 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                        :class="iconClass"
                    >
                        <slot name="icon" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-medium text-base-content/70 truncate">{{ title }}</h3>
                        <slot name="subtitle" />
                    </div>
                </div>
                <div class="shrink-0">
                    <slot name="actions" />
                </div>
            </div>

            <div class="flex items-baseline gap-x-2 gap-y-1 flex-wrap">
                <span class="text-2xl font-bold tracking-tight text-base-content">{{ amount }}</span>
                <span v-if="currency" class="text-sm font-medium text-primary/70">{{ currency }}</span>
                <slot name="badge" />
            </div>

            <slot name="meta" />
            <slot />
        </div>
    </div>
</template>
