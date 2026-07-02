<script setup>
import PeriodNavigator from '@/Components/MainPage/PeriodNavigator.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
    canNavigate: {
        type: Boolean,
        default: false,
    },
    periodLabel: {
        type: String,
        default: '',
    },
    processing: {
        type: Boolean,
        default: false,
    },
    height: {
        type: String,
        default: '13rem',
    },
});

const emit = defineEmits(['prev', 'next']);
</script>

<template>
    <div class="mt-4 rounded-box border border-base-300/60 bg-base-100 px-4 pt-4 pb-6">
        <div class="mb-1 flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium text-base-content/60">{{ title }}</h2>
            <PeriodNavigator
                v-if="canNavigate"
                class="hidden sm:flex"
                :label="periodLabel"
                :disabled="processing"
                @prev="emit('prev')"
                @next="emit('next')"
            />
        </div>
        <div :style="{ height }">
            <slot />
        </div>
    </div>
</template>
