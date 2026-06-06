<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import InputError from '@/Components/InputError.vue';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const modalStore = useModalStore();
const { paymentDetailResetLimitsModal } = storeToRefs(modalStore);

const processing = ref(false);
const errors = ref({});
const limitType = ref('daily');

const paymentDetail = computed(() => paymentDetailResetLimitsModal.value.params?.paymentDetail ?? null);

const modalTitle = computed(() => {
    if (!paymentDetail.value) {
        return 'Сбросить лимиты';
    }

    const name = paymentDetail.value.name ? ` — ${paymentDetail.value.name}` : '';

    return `Сбросить лимиты #${paymentDetail.value.id}${name}`;
});

const resetDescription = computed(() => {
    if (limitType.value === 'monthly') {
        return 'Обнулятся текущий месячный оборот и счётчик успешных сделок за месяц.';
    }

    return 'Обнулятся текущий дневной оборот и счётчик успешных сделок за день.';
});

const resetState = () => {
    processing.value = false;
    errors.value = {};
    limitType.value = 'daily';
};

const close = () => {
    modalStore.closeModal('paymentDetailResetLimits');
};

const submit = () => {
    if (!paymentDetail.value?.id) {
        return;
    }

    processing.value = true;
    errors.value = {};

    axios.post(
        route('payment-details.reset-limits', paymentDetail.value.id),
        { type: limitType.value },
        { headers: { Accept: 'application/json' } },
    )
        .then((response) => {
            if (response.data?.success || response.status === 200) {
                close();
                router.reload({ only: ['paymentDetails'], preserveScroll: true });
            }
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { _error: [error.response.data.message] };
            }
        })
        .finally(() => {
            processing.value = false;
        });
};

watch(
    () => paymentDetailResetLimitsModal.value.showed,
    (showed) => {
        if (showed) {
            resetState();
        }
    },
);
</script>

<template>
    <Modal :show="paymentDetailResetLimitsModal.showed" maxWidth="md" @close="close">
        <ModalHeader :title="modalTitle" @close="close" />
        <ModalBody>
            <form class="space-y-4" @submit.prevent="submit">
                <p class="text-sm text-base-content/70">
                    Выберите, какие счётчики нужно обнулить досрочно для этого реквизита.
                </p>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer rounded-box border border-base-content/10 p-3 hover:bg-base-200/40">
                        <input
                            v-model="limitType"
                            type="radio"
                            class="radio radio-primary mt-0.5"
                            name="limit_type"
                            value="daily"
                            :disabled="processing"
                        >
                        <span>
                            <span class="block font-medium">Дневные лимиты</span>
                            <span class="block text-sm text-base-content/60">Текущий дневной оборот и количество сделок за день</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer rounded-box border border-base-content/10 p-3 hover:bg-base-200/40">
                        <input
                            v-model="limitType"
                            type="radio"
                            class="radio radio-primary mt-0.5"
                            name="limit_type"
                            value="monthly"
                            :disabled="processing"
                        >
                        <span>
                            <span class="block font-medium">Месячные лимиты</span>
                            <span class="block text-sm text-base-content/60">Текущий месячный оборот и количество сделок за месяц</span>
                        </span>
                    </label>
                </div>

                <p class="text-sm text-base-content/60">
                    {{ resetDescription }}
                </p>

                <InputError :message="errors.type?.[0] || errors._error?.[0]" />
            </form>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-ghost" :disabled="processing" @click="close">
                Отмена
            </button>
            <button type="button" class="btn btn-primary" :disabled="processing" @click="submit">
                Сбросить
            </button>
        </ModalFooter>
    </Modal>
</template>
