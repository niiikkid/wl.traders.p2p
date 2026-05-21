<script setup>
import { computed, useAttrs } from 'vue';
import AppTooltip from '@/Components/AppTooltip.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

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

const { copy, copied } = useAppClipboard();

const tip_text = computed(() => (copied.value ? props.tipCopied : props.tipCopy));

const on_click = () => {
    if (!props.copyable || !full_id.value) {
        return;
    }
    copy(full_id.value);
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
    <AppTooltip
        v-else
        :tip="tip_text"
        placement="bottom"
        :open="copied"
        wrapper-class="inline-block max-w-full"
    >
        <button
            type="button"
            class="inline m-0 max-w-full min-h-0 min-w-0 cursor-pointer border-0 bg-transparent p-0 text-inherit font-mono tabular-nums tracking-tight text-nowrap transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-0"
            v-bind="attrs"
            :aria-label="tipCopy + ': ' + id_short"
            @click.prevent="on_click"
        >
            {{ id_short }}
        </button>
    </AppTooltip>
</template>
