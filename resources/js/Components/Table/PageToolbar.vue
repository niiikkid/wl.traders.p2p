<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    loadingLabel: {
        type: String,
        default: 'Загрузка…',
    },
});
</script>

<template>
    <div class="flex max-w-full min-w-0 flex-wrap items-center justify-end gap-2">
        <div
            v-if="loading"
            class="flex w-full items-center justify-end gap-2 text-sm text-base-content/70 xl:hidden"
            aria-live="polite"
        >
            <span class="loading loading-spinner loading-sm text-primary" role="status" aria-label="Загрузка" />
            <span class="hidden sm:inline">Загрузка данных…</span>
            <span class="sm:hidden">{{ loadingLabel }}</span>
        </div>

        <slot name="prefix" />

        <div class="join overflow-visible rounded-xl border border-base-content/10 bg-base-100/90 p-0.5 shadow-sm">
            <slot />
        </div>

        <slot name="extra" />
    </div>
</template>
