<script setup>
import PageToolbar from '@/Components/Table/PageToolbar.vue';
import PageToolbarAction from '@/Components/Table/PageToolbarAction.vue';

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
        <PageToolbar :loading="loading">
            <slot name="actions" />
            <PageToolbarAction
                icon="refresh"
                title="Обновить"
                :loading="loading"
                @click="emit('refresh')"
            />
        </PageToolbar>
    </div>
</template>
