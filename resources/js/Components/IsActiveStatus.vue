<script setup>
const props = defineProps({
    is_active: {
        type: [Boolean, Number],
        default: false,
    }
});
</script>

<template>
    <div class="flex items-center text-nowrap">
        <template v-if="props.is_active">
            <div aria-label="success" class="status status-success me-2"></div> В работе
        </template>
        <template v-else>
            <div aria-label="success" class="status status-error me-2"></div> Не в работе
        </template>
    </div>
</template>
