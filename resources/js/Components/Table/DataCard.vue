<script setup>
defineProps({
    /** Классы для .card-body (отступы карточки в мобильной версии). */
    bodyClass: {
        type: [String, Array, Object],
        default: 'p-4 pt-2 pb-3',
    },
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body" :class="bodyClass">
            <slot />
        </div>
    </div>
</template>
