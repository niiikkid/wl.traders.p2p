<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTableFiltersStore } from '@/store/tableFilters.js';
import PageToolbarAction from '@/Components/Table/PageToolbarAction.vue';

const tableFiltersStore = useTableFiltersStore();

const emit = defineEmits(['refreshStarted', 'refreshFinished']);

const props = defineProps({
    /** Компактная кнопка только с иконкой (тулбар). */
    iconOnly: {
        type: Boolean,
        default: false,
    },
});

/** Раньше здесь был интервал из localStorage — автообновление отключено навсегда. */
const legacyPollStorageKey = 'refresh-storage-orders';

const isRefreshing = ref(false);

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.localStorage.removeItem(legacyPollStorageKey);
    }
});

const reloadTable = () => {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;
    emit('refreshStarted');

    router.visit(route(route().current()), {
        data: tableFiltersStore.getQueryData,
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
            emit('refreshFinished');
        },
    });
};
</script>

<template>
    <PageToolbarAction
        v-if="iconOnly"
        icon="refresh"
        title="Обновить список"
        :loading="isRefreshing"
        @click="reloadTable"
    />

    <button
        v-else
        type="button"
        class="btn btn-secondary btn-sm btn-outline"
        :disabled="isRefreshing"
        @click="reloadTable"
    >
        <span class="flex items-center gap-2">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="h-4 w-4 shrink-0"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                />
            </svg>
            <span>Обновить</span>
            <span v-if="isRefreshing" class="loading loading-spinner loading-xs" />
        </span>
    </button>
</template>
