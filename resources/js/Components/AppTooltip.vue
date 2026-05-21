<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useFloatingTip } from '@/composables/useFloatingTip.js';

const props = defineProps({
    tip: {
        type: String,
        default: '',
    },
    placement: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value),
    },
    open: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    wrapperClass: {
        type: String,
        default: 'inline-block',
    },
    onlyXl: {
        type: Boolean,
        default: false,
    },
});

const trigger_ref = ref(null);
const hovered = ref(false);
const focused = ref(false);
const xl_enabled = ref(true);

const { visible: tip_visible, style: tip_style, show, hide, updatePosition } = useFloatingTip(
    trigger_ref,
    () => props.placement,
);

const can_show_tip = computed(() => {
    if (props.disabled || !props.tip?.trim()) {
        return false;
    }

    if (props.onlyXl && !xl_enabled.value) {
        return false;
    }

    return true;
});

const is_shown = computed(() => can_show_tip.value && (props.open || hovered.value || focused.value));

watch(is_shown, (shown) => {
    if (shown) {
        show();
        return;
    }

    hide();
});

watch(
    () => props.tip,
    () => {
        if (is_shown.value) {
            updatePosition();
        }
    },
);

let xl_media_query = null;

const sync_xl_enabled = () => {
    xl_enabled.value = !props.onlyXl || (xl_media_query?.matches ?? true);
};

onMounted(() => {
    if (!props.onlyXl) {
        return;
    }

    xl_media_query = window.matchMedia('(min-width: 1280px)');
    sync_xl_enabled();
    xl_media_query.addEventListener('change', sync_xl_enabled);
});

onBeforeUnmount(() => {
    xl_media_query?.removeEventListener('change', sync_xl_enabled);
});

const on_pointer_enter = () => {
    hovered.value = true;
};

const on_pointer_leave = () => {
    hovered.value = false;
};

const on_focus_in = () => {
    focused.value = true;
};

const on_focus_out = () => {
    focused.value = false;
};
</script>

<template>
    <span
        ref="trigger_ref"
        :class="wrapperClass"
        @mouseenter="on_pointer_enter"
        @mouseleave="on_pointer_leave"
        @focusin="on_focus_in"
        @focusout="on_focus_out"
    >
        <slot />
    </span>

    <Teleport to="body">
        <div
            v-if="tip_visible"
            role="tooltip"
            class="pointer-events-none fixed z-[10050] max-w-80 rounded-field bg-neutral px-2 py-1 text-center text-sm leading-snug font-normal whitespace-normal text-neutral-content shadow-sm"
            :style="tip_style"
        >
            {{ tip }}
        </div>
    </Teleport>
</template>
