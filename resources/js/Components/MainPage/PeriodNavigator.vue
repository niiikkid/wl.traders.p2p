<script setup>
defineProps({
    label: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['prev', 'next']);
</script>

<template>
    <div class="join join-horizontal items-center" :class="block ? 'w-full' : ''">
        <button
            type="button"
            class="btn btn-sm btn-ghost join-item"
            :disabled="disabled"
            aria-label="Предыдущий период"
            @click="emit('prev')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
            </svg>
        </button>
        <span
            class="join-item px-3 text-center text-sm font-medium text-base-content"
            :class="block ? 'flex-1' : 'min-w-36'"
        >
            {{ label }}
        </span>
        <button
            type="button"
            class="btn btn-sm btn-ghost join-item"
            :disabled="disabled"
            aria-label="Следующий период"
            @click="emit('next')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
        </button>
    </div>
</template>
