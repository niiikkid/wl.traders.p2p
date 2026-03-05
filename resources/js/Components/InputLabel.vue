<script setup>
defineProps({
    value: {
        type: String,
    },
    error: {
        type: Boolean,
        default: false,
    }
});
</script>

<template>
    <label class="label" :class="error ? 'text-error' : 'text-base-content'">
        <span v-if="value" class="label-text">{{ value }}</span>
        <span v-else class="label-text"><slot /></span>
    </label>
</template>
