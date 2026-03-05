<script setup>
import { defineProps } from "vue";

const props = defineProps({
    message: String,
});
</script>

<template>
    <div v-if="message" role="alert" class="alert alert-info">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
        </svg>
        <span>{{ message }}</span>
    </div>
</template>
