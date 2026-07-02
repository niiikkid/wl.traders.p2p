<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['refresh']);
</script>

<template>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-base-content">{{ title }}</h2>
        <button
            type="button"
            class="btn btn-ghost btn-sm btn-circle"
            aria-label="Обновить"
            :disabled="loading"
            @click="emit('refresh')"
        >
            <span v-if="loading" class="loading loading-spinner loading-sm"></span>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
        </button>
    </div>
</template>
