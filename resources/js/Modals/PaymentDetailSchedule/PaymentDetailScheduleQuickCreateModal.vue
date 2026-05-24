<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { usePaymentDetailSchedules } from '@/composables/usePaymentDetailSchedules.js';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';
import { ref, watch } from 'vue';

const modalStore = useModalStore();
const { paymentDetailScheduleQuickCreateModal } = storeToRefs(modalStore);
const { buildDefaultWeekdayIntervals, invalidateSchedules } = usePaymentDetailSchedules();

const processing = ref(false);
const errors = ref({});
const form = ref({
    name: '',
});

const resetState = () => {
    processing.value = false;
    errors.value = {};
    form.value = { name: '' };
};

const close = () => {
    modalStore.closeModal('paymentDetailScheduleQuickCreate');
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.post(route('payment-detail-schedules.store'), {
        name: form.value.name,
        intervals: buildDefaultWeekdayIntervals(),
    }, {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            processing.value = false;

            if (!response.data?.success) {
                return;
            }

            const schedule = response.data?.data;
            const onCreated = paymentDetailScheduleQuickCreateModal.value.params?.onCreated;

            invalidateSchedules();
            close();

            if (typeof onCreated === 'function') {
                onCreated(schedule);
            }
        })
        .catch((error) => {
            processing.value = false;

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }
        });
};

watch(
    () => paymentDetailScheduleQuickCreateModal.value.showed,
    (showed) => {
        if (showed) {
            resetState();
        } else {
            resetState();
        }
    },
);
</script>

<template>
    <Modal :show="paymentDetailScheduleQuickCreateModal.showed" @close="close" maxWidth="md">
        <ModalHeader @close="close" title="Новое расписание" />
        <ModalBody>
            <form class="space-y-4" @submit.prevent="submit">
                <p class="text-xs text-base-content/70">
                    Будет создано расписание пн–пт, 09:00–19:00 по времени сервера. Подробную настройку можно изменить позже.
                </p>
                <div>
                    <InputLabel
                        for="schedule_name"
                        value="Название"
                        :error="!!errors.name?.[0]"
                        class="mb-1"
                    />
                    <TextInput
                        id="schedule_name"
                        v-model="form.name"
                        type="text"
                        class="w-full"
                        :class="{ 'input-error': !!errors.name?.[0] }"
                        autocomplete="off"
                        :disabled="processing"
                    />
                    <InputError :message="errors.name?.[0]" class="mt-2" />
                </div>
                <InputError :message="errors.intervals?.[0]" />
            </form>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" :disabled="processing" @click="close">
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing }"
                :disabled="processing"
                @click="submit"
            >
                Создать
            </button>
        </ModalFooter>
    </Modal>
</template>
