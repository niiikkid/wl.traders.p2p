<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useAttrs, watch } from 'vue';
import { useClipboard } from '@vueuse/core';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    uuid: {
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

const uuid_short = computed(() => {
    const raw = (props.uuid ?? '').trim();
    if (!raw) {
        return '—';
    }
    const segment = raw.split('-')[0] ?? '';
    if (segment.length >= 8) {
        return segment.slice(0, 8);
    }
    if (segment.length > 0) {
        return segment;
    }
    return raw.slice(0, 8);
});

const full_uuid = computed(() => (props.uuid ?? '').trim());

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
    const r = el.getBoundingClientRect();
    tip_style.value = {
        left: `${r.left + r.width / 2}px`,
        top: `${r.top - 6}px`,
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

const on_pointer_enter = () => {
    open_tip();
};

const on_pointer_leave = () => {
    close_tip();
};

const on_click = () => {
    if (!props.copyable || !full_uuid.value) {
        return;
    }
    copy(full_uuid.value);
    nextTick(() => update_tip_position());
};
</script>

<template>
    <span
        v-if="!copyable"
        v-bind="attrs"
        class="inline font-mono tabular-nums tracking-tight text-nowrap"
    >
        {{ uuid_short }}
    </span>
    <button
        v-else
        ref="trigger_ref"
        type="button"
        class="inline m-0 max-w-full min-h-0 min-w-0 cursor-pointer border-0 bg-transparent p-0 text-inherit font-mono tabular-nums tracking-tight text-nowrap transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-0"
        v-bind="attrs"
        :aria-label="tipCopy + ': ' + uuid_short"
        @mouseenter="on_pointer_enter"
        @mouseleave="on_pointer_leave"
        @focus="on_pointer_enter"
        @blur="on_pointer_leave"
        @click.prevent="on_click"
    >
        {{ uuid_short }}
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
