<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia';
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import { ref, watch } from "vue";
import { router } from '@inertiajs/vue3';

const modalStore = useModalStore();
const { merchantCreateModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});

const form = ref({
    name: '',
    description: '',
    project_link: '',
});

const resetForm = () => {
    form.value = {
        name: '',
        description: '',
        project_link: '',
    };
    errors.value = {};
};

const close = () => {
    modalStore.closeModal('merchantCreate');
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.post(route('merchants.store'), form.value, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200 || response.status === 201) {
                close();
                resetForm();
                const callback = merchantCreateModal.value.params?.onCreated;
                if (typeof callback === 'function') {
                    callback();
                } else {
                    router.reload({ only: ['merchants'] });
                }
            }
        })
        .catch(error => {
            processing.value = false;
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
            }
        });
};

watch(
    () => merchantCreateModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
        } else {
            resetForm();
        }
    }
);
</script>

<template>
    <Modal :show="merchantCreateModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Создание мерчанта" />

        <ModalBody>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel
                        for="name"
                        value="Название проекта"
                        :error="!!errors.name?.[0]"
                    />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autocomplete="off"
                        :error="!!errors.name?.[0]"
                        @input="errors.name = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.name?.[0]" />
                </div>

                <div>
                    <InputLabel
                        for="description"
                        value="Опишите деятельность проекта"
                        :error="!!errors.description?.[0]"
                    />
                    <TextInput
                        id="description"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.description"
                        autocomplete="off"
                        :error="!!errors.description?.[0]"
                        @input="errors.description = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.description?.[0]" />
                </div>

                <div>
                    <InputLabel
                        for="project_link"
                        value="Укажите ссылку на проект"
                        :error="!!errors.project_link?.[0]"
                    />
                    <TextInput
                        id="project_link"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.project_link"
                        autocomplete="off"
                        :error="!!errors.project_link?.[0]"
                        @input="errors.project_link = null"
                        :disabled="processing"
                    />
                    <div class="mt-1 text-sm text-base-content/60">
                        Указывайте ссылку в формате https://example.com/
                    </div>
                    <InputError class="mt-1" :message="errors.project_link?.[0]" />
                </div>
            </form>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">
                Отмена
            </button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
    </template>


