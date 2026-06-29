<script setup>
import { computed } from 'vue';

const props = defineProps({
    isConnected: {
        type: Boolean,
        default: false,
    },
    isOnline: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

const label = computed(() => {
    if (! props.isConnected) {
        return 'Ожидает подключения';
    }

    return props.isOnline ? 'Онлайн' : 'Оффлайн';
});

const statusClass = computed(() => {
    if (! props.isConnected) {
        return 'border-base-content/20 bg-base-200/60 text-base-content/70';
    }

    if (props.isOnline) {
        return 'border-success/40 bg-success/15 text-success';
    }

    return 'border-warning/40 bg-warning/15 text-warning';
});

const dotClass = computed(() => {
    if (! props.isConnected) {
        return 'bg-base-content/40';
    }

    if (props.isOnline) {
        return 'bg-success animate-pulse';
    }

    return 'bg-warning';
});

const sizeClass = computed(() => (props.size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'));
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-full border font-medium"
        :class="[statusClass, sizeClass]"
    >
        <span
            class="size-2 shrink-0 rounded-full"
            :class="dotClass"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
