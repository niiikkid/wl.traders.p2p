<script setup>
import FieldHint from "@/Components/Form/FieldHint.vue";

defineProps({
    value: {
        type: String,
    },
    error: {
        type: Boolean,
        default: false,
    },
    hint: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <label class="label" :class="error ? 'text-error' : 'text-base-content'">
        <span class="inline-flex max-w-full flex-wrap items-center gap-1.5">
            <span v-if="value" class="label-text break-words text-inherit">{{ value }}</span>
            <span v-else class="label-text break-words text-inherit"><slot /></span>
            <FieldHint v-if="hint" :text="hint" />
        </span>
    </label>
</template>
