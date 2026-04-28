<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTableFiltersStore } from '@/store/tableFilters.js';

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
    <button
        type="button"
        :class="
            iconOnly
                ? 'btn btn-sm btn-square btn-secondary btn-outline shrink-0 rounded-lg'
                : 'btn btn-secondary btn-sm btn-outline'
        "
        :disabled="isRefreshing"
        :title="iconOnly ? 'Обновить' : undefined"
        :aria-label="iconOnly ? 'Обновить список' : undefined"
        @click="reloadTable"
    >
        <span
            :class="
                iconOnly
                    ? 'inline-flex h-5 w-5 items-center justify-center'
                    : 'flex items-center gap-2'
            "
        >
            <span
                v-if="iconOnly && isRefreshing"
                class="loading loading-spinner loading-sm text-secondary"
                role="status"
            />
            <template v-else>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    :class="iconOnly ? 'h-5 w-5 shrink-0' : 'h-4 w-4 shrink-0'"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                    />
                </svg>
            </template>
            <span v-if="!iconOnly">Обновить</span>
            <span v-if="!iconOnly && isRefreshing" class="loading loading-spinner loading-xs" />
        </span>
    </button>
</template>
