<script setup>
import PrimaryButton from "@/Components/PrimaryButton.vue";

defineProps({
    disabled: {
        type: Boolean,
    },
    saved: {
        type: Boolean
    },
    /** '', 'sm', 'xs' — passed to PrimaryButton */
    size: {
        type: String,
        default: '',
    },
    /** Visible button text (default: Сохранить) */
    buttonText: {
        type: String,
        default: 'Сохранить',
    },
});
</script>

<template>
    <div :class="['flex items-center', size === 'xs' ? 'gap-2' : 'gap-4']">
        <PrimaryButton :disabled="disabled" :size="size">{{ buttonText }}</PrimaryButton>

        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <p v-if="saved" :class="size === 'xs' ? 'text-xs text-base-content/70' : 'text-sm text-base-content/70'">Сохранено.</p>
        </Transition>
    </div>
</template>

<style scoped>

</style>
