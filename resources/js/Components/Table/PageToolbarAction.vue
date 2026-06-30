<script setup>
import { computed } from 'vue';
import ToolbarIcon from '@/Components/Icons/ToolbarIcon.vue';

const props = defineProps({
    icon: {
        type: String,
        default: null,
    },
    title: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    badge: {
        type: [Number, String],
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    active: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    /** ghost | accent | primary */
    variant: {
        type: String,
        default: 'ghost',
    },
    /** Скрыть на мобильных (md+) */
    desktopOnly: {
        type: Boolean,
        default: false,
    },
    /** Показывать только на мобильных */
    mobileOnly: {
        type: Boolean,
        default: false,
    },
});

const variantClass = computed(() => ({
    ghost: 'btn-ghost hover:bg-base-200 hover:text-primary',
    accent: 'btn-soft btn-accent hover:brightness-110',
    primary: 'btn-soft btn-primary hover:brightness-110',
}[props.variant] || 'btn-ghost hover:bg-base-200 hover:text-primary'));

const visibilityClass = computed(() => {
    if (props.mobileOnly) {
        return 'md:hidden';
    }

    if (props.desktopOnly) {
        return 'hidden md:inline-flex';
    }

    return '';
});

const hasLabel = computed(() => Boolean(props.label));
const isIconOnly = computed(() => Boolean(props.icon) && !hasLabel.value);

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="btn join-item relative h-10 min-h-10 shrink-0 gap-2 rounded-none border-0 text-sm font-medium transition-colors"
        :class="[
            variantClass,
            visibilityClass,
            isIconOnly ? 'btn-square w-10 min-w-10' : 'px-3 sm:px-3.5',
            active ? 'bg-base-200 text-primary' : '',
        ]"
        :disabled="disabled || loading"
        :title="title || label || undefined"
        :aria-label="title || label || undefined"
        @click="$emit('click', $event)"
    >
        <span
            v-if="loading"
            class="loading loading-spinner loading-sm"
            role="status"
            aria-hidden="true"
        />
        <ToolbarIcon v-else-if="icon" :name="icon" />
        <slot v-else />

        <span v-if="label" class="hidden sm:inline">{{ label }}</span>

        <span
            v-if="badge !== null && badge !== undefined && Number(badge) > 0"
            class="badge badge-warning badge-sm absolute -top-1.5 -right-1.5 min-w-5 px-1"
        >
            {{ badge }}
        </span>
    </button>
</template>
