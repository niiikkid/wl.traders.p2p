<script setup>
defineProps({
    isOnline: {
        type: Boolean,
        required: true,
    },
});
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium"
        :class="isOnline
            ? 'border-success/40 bg-success/15 text-success'
            : 'border-warning/40 bg-warning/15 text-warning'"
    >
        <span
            class="size-2 shrink-0 rounded-full"
            :class="isOnline ? 'bg-success animate-pulse' : 'bg-warning'"
            aria-hidden="true"
        />
        {{ isOnline ? 'Онлайн' : 'Оффлайн' }}
    </span>
</template>
