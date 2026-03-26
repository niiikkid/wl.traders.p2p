<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import {computed, ref, watch} from 'vue';

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
    <Modal :show="show" max-width="md" @close="closeModal">
        <div class="space-y-5">
            <div>
                <h3 class="text-lg font-semibold text-base-content">Выгрузка в Excel</h3>
                <p class="text-sm text-base-content/70 mt-1">
                    Выберите, как выгрузить {{ entityLabel }}.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <label class="flex items-center gap-3 cursor-pointer min-h-10">
                    <input
                        v-model="exportMode"
                        type="radio"
                        class="radio radio-primary radio-sm shrink-0"
                        value="all"
                    />
                    <span class="text-sm leading-none text-base-content">Выгрузить все</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer min-h-10">
                    <input
                        v-model="exportMode"
                        type="radio"
                        class="radio radio-primary radio-sm shrink-0"
                        value="range"
                    />
                    <span class="text-sm leading-none text-base-content">Выбрать диапазон дат</span>
                </label>
            </div>

            <div
                v-if="exportMode === 'range'"
                class="grid grid-cols-1 sm:grid-cols-2 gap-3"
            >
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Дата с</legend>
                    <input
                        v-model="startDate"
                        type="date"
                        class="input input-bordered w-full"
                        :max="endDate || null"
                    />
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Дата по</legend>
                    <input
                        v-model="endDate"
                        type="date"
                        class="input input-bordered w-full"
                        :min="startDate || null"
                    />
                </fieldset>
            </div>

            <div class="modal-action">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="closeModal"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="!canExport"
                    @click="exportData"
                >
                    Выгрузить
                </button>
            </div>
        </div>
    </Modal>
</template>
