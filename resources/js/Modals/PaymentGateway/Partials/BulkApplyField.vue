<script setup>
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
});

const apply = defineModel({
    type: Boolean,
    required: true,
});
</script>

<template>
    <div
        class="rounded-lg border transition-colors"
        :class="[
            apply ? 'border-primary/40 bg-primary/5' : 'border-base-300 bg-base-200/20',
            disabled && 'pointer-events-none opacity-60',
        ]"
    >
        <label class="flex cursor-pointer items-start gap-3 px-3 py-2.5">
            <input
                v-model="apply"
                type="checkbox"
                class="checkbox checkbox-sm mt-0.5 shrink-0"
                :disabled="disabled"
            >
            <div class="min-w-0 flex-1">
                <span class="text-sm font-medium leading-tight">{{ label }}</span>
                <p v-if="hint && !apply" class="mt-0.5 text-xs text-base-content/50">
                    {{ hint }}
                </p>
            </div>
        </label>
        <div v-if="apply" class="border-t border-base-300/60 px-3 pb-3 pt-2">
            <slot />
        </div>
    </div>
</template>
