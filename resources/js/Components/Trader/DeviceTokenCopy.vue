<script setup>
import { computed } from 'vue';
import AppTooltip from '@/Components/AppTooltip.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    truncateClass: {
        type: String,
        default: 'w-36',
    },
});

const { copy, copied } = useAppClipboard();

const tip_text = computed(() => (copied.value ? 'Скопировано' : 'Скопировать токен'));

const on_click = () => {
    if (!props.token || copied.value) {
        return;
    }

    copy(props.token);
};
</script>

<template>
    <AppTooltip
        :tip="tip_text"
        placement="bottom"
        :open="copied"
        wrapper-class="inline-block max-w-full"
    >
        <button
            type="button"
            class="inline-flex max-w-full min-h-0 items-center gap-1.5 rounded-lg border px-2 py-1 font-mono text-xs normal-case transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="copied
                ? 'border-success/40 bg-success/10 text-success'
                : 'border-base-content/10 bg-base-200/50 text-base-content/80 hover:border-primary/30 hover:bg-primary/5 hover:text-primary'"
            :aria-label="tip_text"
            @click="on_click"
        >
            <span class="truncate text-left" :class="truncateClass">
                {{ token }}
            </span>
            <svg
                v-if="!copied"
                xmlns="http://www.w3.org/2000/svg"
                class="size-3.5 shrink-0 opacity-60"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 1.927-.184" />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="size-3.5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </button>
    </AppTooltip>
</template>
