<script setup>
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import ModalFooterNext from '@/Components/Modals/Next/ModalFooterNext.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    routeName: {
        type: String,
        required: true,
    },
    entityLabel: {
        type: String,
        default: 'данные',
    },
});

const emit = defineEmits(['close']);

const exportMode = ref('all');
const startDate = ref('');
const endDate = ref('');

const canExport = computed(() => {
    if (exportMode.value === 'all') {
        return true;
    }

    if (!startDate.value || !endDate.value) {
        return false;
    }

    return startDate.value <= endDate.value;
});

watch(
    () => props.show,
    (show) => {
        if (!show) {
            exportMode.value = 'all';
            startDate.value = '';
            endDate.value = '';
        }
    },
);

const closeModal = () => {
    emit('close');
};

const exportData = () => {
    if (!canExport.value) {
        return;
    }

    const url = new URL(route(props.routeName), window.location.origin);

    if (exportMode.value === 'range') {
        url.searchParams.set('start_date', startDate.value);
        url.searchParams.set('end_date', endDate.value);
    }

    window.open(url.toString(), '_blank');
    closeModal();
};
</script>

<template>
    <ModalNext :show="show" max-width="md" @close="closeModal">
        <header class="shrink-0 border-b border-base-300/50 px-4 py-3 sm:px-5 sm:py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="shrink-0 rounded-lg border border-base-300/60 bg-base-200/40 p-2 text-primary">
                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M9.29289 1.29289C9.48043 1.10536 9.73478 1 10 1H18C19.6569 1 21 2.34315 21 4V9C21 9.55228 20.5523 10 20 10C19.4477 10 19 9.55228 19 9V4C19 3.44772 18.5523 3 18 3H11V8C11 8.55228 10.5523 9 10 9H5V20C5 20.5523 5.44772 21 6 21H7C7.55228 21 8 21.4477 8 22C8 22.5523 7.55228 23 7 23H6C4.34315 23 3 21.6569 3 20V8C3 7.73478 3.10536 7.48043 3.29289 7.29289L9.29289 1.29289ZM6.41421 7H9V4.41421L6.41421 7ZM19 12C19.5523 12 20 12.4477 20 13V19H23C23.5523 19 24 19.4477 24 20C24 20.5523 23.5523 21 23 21H19C18.4477 21 18 20.5523 18 20V13C18 12.4477 18.4477 12 19 12ZM11.8137 12.4188C11.4927 11.9693 10.8682 11.8653 10.4188 12.1863C9.96935 12.5073 9.86526 13.1318 10.1863 13.5812L12.2711 16.5L10.1863 19.4188C9.86526 19.8682 9.96935 20.4927 10.4188 20.8137C10.8682 21.1347 11.4927 21.0307 11.8137 20.5812L13.5 18.2205L15.1863 20.5812C15.5073 21.0307 16.1318 21.1347 16.5812 20.8137C17.0307 20.4927 17.1347 19.8682 16.8137 19.4188L14.7289 16.5L16.8137 13.5812C17.1347 13.1318 17.0307 12.5073 16.5812 12.1863C16.1318 11.8653 15.5073 11.9693 15.1863 12.4188L13.5 14.7795L11.8137 12.4188Z"
                                fill="currentColor"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold leading-tight tracking-tight text-base-content sm:text-lg">
                            Выгрузка в Excel
                        </h3>
                        <p class="mt-1 text-xs leading-snug text-base-content/60 sm:text-sm">
                            Выберите, как выгрузить {{ entityLabel }}.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs btn-circle shrink-0 touch-manipulation sm:btn-sm"
                    @click.prevent="closeModal"
                >
                    <svg
                        class="size-3.5 sm:size-4"
                        aria-hidden="true"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 14 14"
                    >
                        <path
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"
                        />
                    </svg>
                    <span class="sr-only">Закрыть</span>
                </button>
            </div>
        </header>

        <ModalBodyNext>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label
                        :class="[
                            'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors',
                            exportMode === 'all'
                                ? 'border-primary bg-primary/5'
                                : 'border-base-300/60 hover:border-base-300',
                        ]"
                    >
                        <input
                            v-model="exportMode"
                            type="radio"
                            class="radio radio-primary radio-sm mt-0.5 shrink-0"
                            value="all"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-base-content">Все записи</span>
                            <span class="mt-0.5 block text-xs text-base-content/55">Полная выгрузка без фильтра</span>
                        </span>
                    </label>
                    <label
                        :class="[
                            'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors',
                            exportMode === 'range'
                                ? 'border-primary bg-primary/5'
                                : 'border-base-300/60 hover:border-base-300',
                        ]"
                    >
                        <input
                            v-model="exportMode"
                            type="radio"
                            class="radio radio-primary radio-sm mt-0.5 shrink-0"
                            value="range"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-base-content">По датам</span>
                            <span class="mt-0.5 block text-xs text-base-content/55">Указать период выгрузки</span>
                        </span>
                    </label>
                </div>

                <div
                    v-if="exportMode === 'range'"
                    class="rounded-xl border border-base-300/60 bg-base-200/20 p-3 sm:p-4"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend text-xs">Дата с</legend>
                            <input
                                v-model="startDate"
                                type="date"
                                class="input input-bordered input-sm w-full"
                                :max="endDate || null"
                            />
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend text-xs">Дата по</legend>
                            <input
                                v-model="endDate"
                                type="date"
                                class="input input-bordered input-sm w-full"
                                :min="startDate || null"
                            />
                        </fieldset>
                    </div>
                </div>
            </div>
        </ModalBodyNext>

        <ModalFooterNext>
            <button
                type="button"
                class="btn btn-ghost btn-sm min-w-24"
                @click="closeModal"
            >
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-primary btn-sm min-w-28"
                :disabled="!canExport"
                @click="exportData"
            >
                <svg
                    class="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 11.25 12 15.75m0 0 4.5-4.5M12 15.75V3"
                    />
                </svg>
                Выгрузить
            </button>
        </ModalFooterNext>
    </ModalNext>
</template>
