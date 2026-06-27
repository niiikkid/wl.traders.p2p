<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <fieldset class="fieldset min-w-0 rounded-box border border-base-300" :class="compact ? 'p-2.5' : 'p-4'">
        <legend class="fieldset-legend px-1 text-sm font-semibold">{{ title }}</legend>
        <p v-if="description" class="mb-1.5 min-w-0 px-1 text-xs leading-snug break-words text-base-content/60">
            {{ description }}
        </p>
        <div :class="compact ? 'space-y-2' : 'space-y-4'">
            <slot />
        </div>
    </fieldset>
</template>
