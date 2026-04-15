<script setup>
/**
 * Маркетинговый лендинг: та же тема DIM, что и в кабинете (см. resources/css/app.css).
 */
</script>

<template>
    <div
        data-theme="dim"
        class="landing-surface min-h-screen bg-base-100 font-sans text-base-content antialiased selection:bg-primary/30 selection:text-primary-content"
    >
        <slot />
    </div>
</template>
