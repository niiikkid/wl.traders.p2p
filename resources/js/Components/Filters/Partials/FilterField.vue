<script setup>
defineProps({
    label: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="form-control w-full gap-1">
        <span v-if="label" class="text-xs font-medium text-base-content/60">{{ label }}</span>
        <slot/>
    </div>
</template>
