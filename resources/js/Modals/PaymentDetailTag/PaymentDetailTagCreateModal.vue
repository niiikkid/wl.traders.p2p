<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import { useModalStore } from "@/store/modal.js";
import { storeToRefs } from "pinia";
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

const modalStore = useModalStore();
const { paymentDetailTagCreateModal } = storeToRefs(modalStore);

const processing = ref(false);
const errors = ref({});
const form = ref({
    name: '',
    color: '#6366f1',
});

const resetState = () => {
    processing.value = false;
    errors.value = {};
    form.value = {
        name: '',
        color: '#6366f1',
    };
};

const close = () => {
    modalStore.closeModal('paymentDetailTagCreate');
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.post(route('payment-detail-tags.store'), form.value, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            processing.value = false;
            if (res.data?.success || res.status === 200 || res.status === 201) {
                close();
                router.reload({ only: ['paymentDetails', 'paymentDetailTags'] });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
            }
        });
};

watch(
    () => paymentDetailTagCreateModal.value.showed,
    (state) => {
        if (state) {
            resetState();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="paymentDetailTagCreateModal.showed" @close="close" maxWidth="md">
        <ModalHeader @close="close" title="Новый тег" />
        <ModalBody>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel
                        for="tag_name"
                        value="Название (до 10 символов)"
                        :error="!!errors.name?.[0]"
                        class="mb-1"
                    />
                    <TextInput
                        id="tag_name"
                        v-model="form.name"
                        type="text"
                        class="w-full"
                        maxlength="10"
                        :error="!!errors.name?.[0]"
                        @input="errors.name = null"
                        :disabled="processing"
                    />
                    <InputError :message="errors.name?.[0]" class="mt-2" />
                </div>
                <div>
                    <InputLabel
                        for="tag_color"
                        value="Цвет"
                        :error="!!errors.color?.[0]"
                        class="mb-1"
                    />
                    <div class="flex items-center gap-3">
                        <input
                            id="tag_color"
                            v-model="form.color"
                            type="color"
                            class="input input-bordered w-16 h-10 p-1"
                            :disabled="processing"
                        />
                        <div class="text-xs text-base-content/70">
                            Выберите цвет для тега
                        </div>
                    </div>
                    <InputError :message="errors.color?.[0]" class="mt-2" />
                </div>
            </form>
        </ModalBody>
        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">Отмена</button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Создать
            </button>
        </ModalFooter>
    </Modal>
</template>
