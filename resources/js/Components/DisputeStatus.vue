<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';

defineProps({
    status: {
        type: String,
    },
    status_name: {
        type: String,
    },
});
</script>

<template>
    <div class="flex items-center text-nowrap text-base-content">
        <template v-if="status === 'accepted'">
            <AppTooltip :tip="status_name" wrapper-class="text-success inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'canceled'">
            <AppTooltip :tip="status_name" wrapper-class="text-error inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'pending'">
            <AppTooltip :tip="status_name" wrapper-class="text-warning inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
    </div>
</template>
