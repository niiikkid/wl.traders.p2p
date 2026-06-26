<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    hasOrderSms: {
        type: Boolean,
        default: false,
    },
    square: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        :class="[
            'btn btn-primary btn-outline btn-xs relative overflow-visible',
            square ? 'btn-square' : '',
        ]"
        :disabled="disabled"
        aria-label="Открыть сделку"
        @click.prevent="emit('click')"
    >
        <span
            v-if="hasOrderSms"
            class="badge badge-error badge-xs absolute z-[1] -top-1.5 -end-2 min-h-0 min-w-0 border-0 px-[2px] py-0 text-[0.5rem] font-bold leading-none normal-case pointer-events-none"
            title="По сделке есть привязанная SMS"
        >
            SMS
        </span>
        <svg
            :class="square ? 'h-3 w-3' : 'w-3.5 h-3.5'"
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            fill="none"
            viewBox="0 0 24 24"
        >
            <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
            <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
    </button>
</template>
