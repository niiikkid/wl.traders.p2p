<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PaymentDetailScheduleForm from '@/Components/PaymentDetail/PaymentDetailScheduleForm.vue';
import { usePaymentDetailSchedules } from '@/composables/usePaymentDetailSchedules.js';
import {
    createEmptyEditorState,
    intervalsToEditorState,
    validateEditorStateLocally,
} from '@/composables/usePaymentDetailScheduleEditor.js';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';

const modalStore = useModalStore();
const { paymentDetailScheduleManagerModal } = storeToRefs(modalStore);
const {
    schedules,
    serverTimezone,
    serverNow,
    loading,
    fetchSchedules,
    invalidateSchedules,
} = usePaymentDetailSchedules();

const panelMode = ref('list');
const selectedScheduleId = ref(null);
const editorState = ref(createEmptyEditorState());
const processing = ref(false);
const errors = ref({});
const copyForm = ref({ name: '' });
const copySourceId = ref(null);

const selectedSchedule = computed(() => {
    if (!selectedScheduleId.value) {
        return null;
    }

    return schedules.value.find((schedule) => Number(schedule.id) === Number(selectedScheduleId.value)) ?? null;
});

const modalTitle = computed(() => {
    if (panelMode.value === 'copy') {
        return 'Копия расписания';
    }

    if (panelMode.value === 'create') {
        return 'Новое расписание';
    }

    if (panelMode.value === 'edit') {
        return 'Редактирование расписания';
    }

    return 'Расписания работы';
});

const showEditor = computed(() => ['create', 'edit', 'copy'].includes(panelMode.value));

const normalizeErrors = (rawErrors) => {
    const normalized = {};

    for (const [key, value] of Object.entries(rawErrors || {})) {
        normalized[key] = Array.isArray(value) ? value[0] : value;
    }

    return normalized;
};

const resetEditor = () => {
    panelMode.value = 'list';
    selectedScheduleId.value = null;
    editorState.value = createEmptyEditorState();
    processing.value = false;
    errors.value = {};
    copyForm.value = { name: '' };
    copySourceId.value = null;
};

const close = () => {
    modalStore.closeModal('paymentDetailScheduleManager');
};

const loadSchedules = async () => {
    await fetchSchedules(true);
};

const selectSchedule = (schedule) => {
    selectedScheduleId.value = schedule.id;
    panelMode.value = 'edit';
    editorState.value = intervalsToEditorState(schedule.intervals || [], schedule.name || '');
    errors.value = {};
    copyForm.value = { name: '' };
    copySourceId.value = null;
};

const startCreate = () => {
    selectedScheduleId.value = null;
    panelMode.value = 'create';
    editorState.value = createEmptyEditorState();
    errors.value = {};
    copyForm.value = { name: '' };
    copySourceId.value = null;
};

const startCopy = () => {
    if (!selectedSchedule.value) {
        return;
    }

    const baseName = selectedSchedule.value.name || 'Расписание';
    const suffix = ' (копия)';
    const maxLength = 255 - suffix.length;

    copySourceId.value = selectedSchedule.value.id;
    panelMode.value = 'copy';
    copyForm.value = {
        name: `${baseName.slice(0, maxLength)}${suffix}`,
    };
    errors.value = {};
};

const cancelEditor = () => {
    if (panelMode.value === 'create') {
        resetEditor();

        return;
    }

    if (panelMode.value === 'copy' && selectedSchedule.value) {
        selectSchedule(selectedSchedule.value);

        return;
    }

    panelMode.value = 'list';
    errors.value = {};
};

const saveSchedule = () => {
    const validation = validateEditorStateLocally(editorState.value);

    if (!validation.valid) {
        errors.value = validation.errors;

        return;
    }

    const payload = {
        name: editorState.value.name.trim(),
        intervals: validation.intervals,
    };

    const submitUpdate = () => {
        processing.value = true;
        errors.value = {};

        axios.patch(route('payment-detail-schedules.update', selectedScheduleId.value), payload, {
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                processing.value = false;

                if (!response.data?.success) {
                    return;
                }

                invalidateSchedules();
                await loadSchedules();

                const updated = response.data?.data;

                if (updated?.id) {
                    selectSchedule(updated);
                }
            })
            .catch((error) => {
                processing.value = false;

                if (error.response?.data?.errors) {
                    errors.value = normalizeErrors(error.response.data.errors);
                }
            });
    };

    if (panelMode.value === 'edit') {
        const attachedCount = Number(selectedSchedule.value?.payment_details_count || 0);

        if (attachedCount > 0) {
            modalStore.openConfirmModal({
                title: 'Сохранить изменения расписания?',
                body: `Изменения применятся ко всем реквизитам, где используется расписание «${selectedSchedule.value?.name}» (${attachedCount}).`,
                confirm_button_name: 'Сохранить',
                confirm: submitUpdate,
            });

            return;
        }

        submitUpdate();

        return;
    }

    processing.value = true;
    errors.value = {};

    axios.post(route('payment-detail-schedules.store'), payload, {
        headers: { Accept: 'application/json' },
    })
        .then(async (response) => {
            processing.value = false;

            if (!response.data?.success) {
                return;
            }

            invalidateSchedules();
            await loadSchedules();

            const created = response.data?.data;
            const onCreated = paymentDetailScheduleManagerModal.value.params?.onCreated;
            const closeOnCreate = paymentDetailScheduleManagerModal.value.params?.closeOnCreate === true;

            if (typeof onCreated === 'function' && created?.id) {
                onCreated(created);
            }

            if (created?.id) {
                if (closeOnCreate) {
                    close();
                } else {
                    selectSchedule(created);
                }
            }
        })
        .catch((error) => {
            processing.value = false;

            if (error.response?.data?.errors) {
                errors.value = normalizeErrors(error.response.data.errors);
            }
        });
};

const submitCopy = () => {
    if (!copySourceId.value) {
        return;
    }

    if (!copyForm.value.name?.trim()) {
        errors.value = { name: 'Укажите название расписания.' };

        return;
    }

    processing.value = true;
    errors.value = {};

    axios.post(route('payment-detail-schedules.copy', copySourceId.value), {
        name: copyForm.value.name.trim(),
    }, {
        headers: { Accept: 'application/json' },
    })
        .then(async (response) => {
            processing.value = false;

            if (!response.data?.success) {
                return;
            }

            invalidateSchedules();
            await loadSchedules();

            const copied = response.data?.data;

            if (copied?.id) {
                selectSchedule(copied);
            }
        })
        .catch((error) => {
            processing.value = false;

            if (error.response?.data?.errors) {
                errors.value = normalizeErrors(error.response.data.errors);
            }
        });
};

watch(
    () => paymentDetailScheduleManagerModal.value.showed,
    async (showed) => {
        if (showed) {
            resetEditor();
            await loadSchedules();

            const initialScheduleId = paymentDetailScheduleManagerModal.value.params?.scheduleId;

            if (initialScheduleId) {
                const schedule = schedules.value.find((item) => Number(item.id) === Number(initialScheduleId));

                if (schedule) {
                    selectSchedule(schedule);
                }
            } else if (paymentDetailScheduleManagerModal.value.params?.startInCreate) {
                startCreate();
            }
        } else {
            resetEditor();
        }
    },
);
</script>

<template>
    <Modal :show="paymentDetailScheduleManagerModal.showed" @close="close" maxWidth="4xl">
        <ModalHeader @close="close" :title="modalTitle" />
        <ModalBody>
            <div v-if="loading && !schedules.length" class="text-center text-sm text-base-content/70 py-8">
                Загрузка расписаний…
            </div>
            <div v-else class="grid gap-4 lg:grid-cols-[200px_minmax(0,1fr)]">
                <div class="space-y-2">
                    <button
                        type="button"
                        class="btn btn-xs btn-primary w-full min-h-8 h-8"
                        :disabled="processing"
                        @click="startCreate"
                    >
                        Создать расписание
                    </button>

                    <div v-if="!schedules.length" class="text-xs text-base-content/70 py-4">
                        Расписания ещё не созданы.
                    </div>

                    <div v-else class="space-y-1 max-h-[28rem] overflow-y-auto pr-1">
                        <button
                            v-for="schedule in schedules"
                            :key="schedule.id"
                            type="button"
                            class="w-full text-left rounded-box border px-2 py-1.5 transition-colors"
                            :class="Number(selectedScheduleId) === Number(schedule.id)
                                ? 'border-primary bg-primary/10'
                                : 'border-base-300 hover:border-base-content/30'"
                            :disabled="processing"
                            @click="selectSchedule(schedule)"
                        >
                            <div class="font-medium text-xs truncate">
                                {{ schedule.name }}
                            </div>
                            <div class="text-[11px] text-base-content/70 mt-0.5">
                                {{ schedule.status_label }}
                            </div>
                            <div
                                v-if="schedule.payment_details_count !== undefined"
                                class="text-[11px] text-base-content/60"
                            >
                                Реквизитов: {{ schedule.payment_details_count }}
                            </div>
                        </button>
                    </div>
                </div>

                <div class="min-w-0">
                    <div v-if="panelMode === 'list' && !showEditor" class="text-sm text-base-content/70 py-10 text-center">
                        Выберите расписание слева или создайте новое.
                    </div>

                    <div v-else-if="panelMode === 'copy'" class="space-y-2">
                        <p class="text-[11px] leading-snug text-base-content/70">
                            Будет создано независимое расписание с теми же интервалами, что у «{{ selectedSchedule?.name }}».
                        </p>
                        <div>
                            <InputLabel
                                for="schedule_copy_name"
                                value="Название копии"
                                :error="!!errors.name"
                                class="mb-0.5 [&_.label-text]:text-xs"
                            />
                            <TextInput
                                id="schedule_copy_name"
                                v-model="copyForm.name"
                                type="text"
                                class="w-full input-sm h-8 min-h-8 text-sm"
                                :class="{ 'input-error': !!errors.name }"
                                autocomplete="off"
                                :disabled="processing"
                            />
                            <InputError :message="errors.name" class="mt-1" />
                        </div>
                    </div>

                    <PaymentDetailScheduleForm
                        v-else
                        v-model="editorState"
                        :errors="errors"
                        :processing="processing"
                        :server-timezone="serverTimezone"
                        :server-now="serverNow"
                        :show-shared-edit-warning="panelMode === 'edit' && Number(selectedSchedule?.payment_details_count || 0) > 0"
                        :attached-count="Number(selectedSchedule?.payment_details_count || 0)"
                    />
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" :disabled="processing" @click="close">
                Закрыть
            </button>

            <template v-if="panelMode === 'copy'">
                <button type="button" class="btn btn-sm btn-ghost" :disabled="processing" @click="cancelEditor">
                    Назад
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                    @click="submitCopy"
                >
                    Создать копию
                </button>
            </template>

            <template v-else-if="showEditor">
                <button
                    v-if="panelMode === 'edit' && selectedSchedule"
                    type="button"
                    class="btn btn-sm btn-outline"
                    :disabled="processing"
                    @click="startCopy"
                >
                    Копировать
                </button>
                <button type="button" class="btn btn-sm btn-ghost" :disabled="processing" @click="cancelEditor">
                    Отмена
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                    @click="saveSchedule"
                >
                    Сохранить
                </button>
            </template>
        </ModalFooter>
    </Modal>
</template>
