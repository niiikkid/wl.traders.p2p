<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useAttrs, watch } from 'vue';
import { useClipboard } from '@vueuse/core';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    id: {
        type: String,
        default: '',
    },
    copyable: {
        type: Boolean,
        default: true,
    },
    tipCopy: {
        type: String,
        default: 'Скопировать',
    },
    tipCopied: {
        type: String,
        default: 'Скопировано!',
    },
});

const attrs = useAttrs();

const full_id = computed(() => (props.id ?? '').trim());

const id_short = computed(() => {
    if (!full_id.value) {
        return '—';
    }
    return full_id.value.slice(0, 8);
});

const { copy, copied } = useClipboard();

const trigger_ref = ref(null);
const tip_visible = ref(false);
const tip_style = ref({});

const tip_label = computed(() => {
    if (!tip_visible.value) {
        return props.tipCopy;
    }
    return copied.value ? props.tipCopied : props.tipCopy;
});

const update_tip_position = () => {
    const el = trigger_ref.value;
    if (!el || !tip_visible.value) {
        return;
    }
    const rect = el.getBoundingClientRect();
    tip_style.value = {
        left: `${rect.left + rect.width / 2}px`,
        top: `${rect.top - 6}px`,
        transform: 'translate(-50%, -100%)',
    };
};

const open_tip = () => {
    if (!props.copyable) {
        return;
    }
    tip_visible.value = true;
    nextTick(() => update_tip_position());
};

const close_tip = () => {
    tip_visible.value = false;
};

let remove_position_listeners = null;

const attach_position_listeners = () => {
    const handler = () => update_tip_position();
    window.addEventListener('scroll', handler, true);
    window.addEventListener('resize', handler);

    return () => {
        window.removeEventListener('scroll', handler, true);
        window.removeEventListener('resize', handler);
    };
};

watch(tip_visible, (visible) => {
    remove_position_listeners?.();
    remove_position_listeners = null;

    if (visible) {
        remove_position_listeners = attach_position_listeners();
    }
});

onBeforeUnmount(() => {
    remove_position_listeners?.();
});

const on_click = () => {
    if (!props.copyable || !full_id.value) {
        return;
    }
    copy(full_id.value);
    nextTick(() => update_tip_position());
};
</script>

<template>
    <span
        v-if="!copyable"
        v-bind="attrs"
        class="inline font-mono tabular-nums tracking-tight text-nowrap"
    >
        {{ id_short }}
    </span>
    <button
        v-else
        ref="trigger_ref"
        type="button"
        class="inline m-0 max-w-full min-h-0 min-w-0 cursor-pointer border-0 bg-transparent p-0 text-inherit font-mono tabular-nums tracking-tight text-nowrap transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-0"
        v-bind="attrs"
        :aria-label="tipCopy + ': ' + id_short"
        @mouseenter="open_tip"
        @mouseleave="close_tip"
        @focus="open_tip"
        @blur="close_tip"
        @click.prevent="on_click"
    >
        {{ id_short }}
    </button>

    <Teleport v-if="copyable" to="body">
        <div
            v-show="tip_visible"
            class="pointer-events-none fixed z-[2147483646] rounded-box bg-base-300 px-2 py-1 text-center text-xs font-medium text-base-content shadow-md ring-1 ring-base-content/10"
            :style="tip_style"
        >
            {{ tip_label }}
        </div>
    </Teleport>
</template>
