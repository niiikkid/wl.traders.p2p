<script setup>
defineProps({
    status: {
        type: String,
    },
});
</script>

<template>
    <div class="flex items-center text-nowrap text-base-content">
        <template v-if="status === 'success'">
            <div aria-label="success" class="status status-success me-2"></div>Завершена
        </template>
        <template v-if="status === 'fail'">
            <div aria-label="success" class="status status-error me-2"></div>Отклонена
        </template>
        <template v-if="status === 'pending'">
            <div aria-label="success" class="status status-warning me-2"></div>Ожидает
        </template>
    </div>
</template>
