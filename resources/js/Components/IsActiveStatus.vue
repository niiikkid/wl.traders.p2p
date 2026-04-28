<script setup>
const props = defineProps({
    is_active: {
        type: [Boolean, Number],
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="flex items-center text-nowrap"
        :class="compact ? 'text-xs gap-1' : 'gap-0'"
    >
        <template v-if="props.is_active">
            <div
                aria-label="success"
                class="status status-success shrink-0"
                :class="compact ? 'scale-90 me-1' : 'me-2'"
            ></div>
            {{ compact ? 'Активен' : 'В работе' }}
        </template>
        <template v-else>
            <div
                aria-label="error"
                class="status status-error shrink-0"
                :class="compact ? 'scale-90 me-1' : 'me-2'"
            ></div>
            {{ compact ? 'Неактивен' : 'Не в работе' }}
        </template>
    </div>
</template>
