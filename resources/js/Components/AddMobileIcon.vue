<script setup>
import { computed } from 'vue';
import ToolbarIcon from '@/Components/Icons/ToolbarIcon.vue';

const props = defineProps({
    /** DaisyUI color: primary | accent | secondary */
    variant: {
        type: String,
        default: 'accent',
    },
    title: {
        type: String,
        default: 'Создать',
    },
});

const variantClass = computed(() => ({
    primary: 'btn-soft btn-primary hover:brightness-110',
    accent: 'btn-soft btn-accent hover:brightness-110',
    secondary: 'btn-soft btn-secondary hover:brightness-110',
}[props.variant] || 'btn-soft btn-accent hover:brightness-110'));
</script>

<template>
    <button
        type="button"
        class="btn btn-square h-10 min-h-10 w-10 min-w-10 rounded-xl border border-base-content/10 bg-base-100/90 shadow-sm md:hidden"
        :class="variantClass"
        :title="title"
        :aria-label="title"
    >
        <ToolbarIcon name="plus" />
    </button>
</template>
