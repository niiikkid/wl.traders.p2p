<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <header
        class="shrink-0 border-b border-base-300/50 bg-gradient-to-b from-base-200/30 to-base-100 px-4 pb-2.5 pt-3 sm:px-5 sm:pb-3 sm:pt-4"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <slot name="title">
                    <h3 class="truncate text-base font-bold leading-tight tracking-tight text-base-content sm:text-lg">
                        {{ title }}
                    </h3>
                </slot>
                <p v-if="subtitle" class="mt-0.5 truncate text-xs text-base-content/60 sm:text-sm">
                    {{ subtitle }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <slot name="actions" />
                <button
                    v-if="closeable"
                    type="button"
                    class="btn btn-ghost btn-xs btn-circle shrink-0 touch-manipulation sm:btn-sm"
                    @click.prevent="emit('close')"
                >
                    <svg class="size-3.5 sm:size-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Закрыть</span>
                </button>
            </div>
        </div>
    </header>
</template>
