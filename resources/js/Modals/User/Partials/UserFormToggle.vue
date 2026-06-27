<script setup>
import FieldHint from "@/Components/Form/FieldHint.vue";

defineProps({
    label: {
        type: String,
        required: true,
    },
    hint: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    toggleClass: {
        type: String,
        default: 'toggle-primary',
    },
});

const model = defineModel({
    type: Boolean,
    required: true,
});
</script>

<template>
    <label
        class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-200/30 px-3 py-2"
    >
        <div class="min-w-0 flex-1">
            <span class="inline-flex max-w-full items-center gap-1">
                <span class="label-text font-medium leading-tight">{{ label }}</span>
                <FieldHint v-if="hint" :text="hint" :title="label" />
            </span>
            <div v-if="$slots.default" class="mt-0.5 min-w-0">
                <slot />
            </div>
        </div>
        <input
            v-model="model"
            type="checkbox"
            class="toggle toggle-sm shrink-0"
            :class="toggleClass"
            :disabled="disabled"
        >
    </label>
</template>
