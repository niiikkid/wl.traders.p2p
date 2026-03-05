<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import {ref, watch} from "vue";
import DateTime from "@/Components/DateTime.vue";
import TextArea from "@/Components/TextArea.vue";

const modalStore = useModalStore();
const { userNotesModal } = storeToRefs(modalStore);

const userNotes = ref([]);
const loading = ref(false);
const processing = ref(false);
const form = ref({
    content: '',
});
const errors = ref({});

const close = () => {
    modalStore.closeModal('userNotes');
};

const loadUserNotes = () => {
    loading.value = true;
    axios.get(route('admin.users.notes.index', userNotesModal.value.params.user.id))
        .then(response => {
            if (response.data.success) {
                userNotes.value = response.data.data;
            }
            loading.value = false;
        })
        .catch(error => {
            loading.value = false;
        });
};

const addNote = () => {
    processing.value = true;
    errors.value = {};

    axios.post(route('admin.users.notes.store', userNotesModal.value.params.user.id), form.value)
        .then(response => {
            if (response.data.success) {
                userNotes.value.unshift(response.data.data);
                form.value.content = '';
            }
            processing.value = false;
        })
        .catch(error => {
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
            }
            processing.value = false;
        });
};

watch(
    () => modalStore.userNotesModal.showed,
    (state) => {
        if (state) {
            loadUserNotes();
        } else {
            userNotes.value = [];
            form.value.content = '';
            errors.value = {};
        }
    }
);
</script>

<template>
    <Modal :show="userNotesModal.showed" @close="close">
        <ModalHeader @close="close" :title="`Заметки о пользователе: ${userNotesModal.params.user?.name || ''}`" />

        <ModalBody>
            <div class="space-y-4">
                <form @submit.prevent="addNote" class="space-y-3">
                    <div>
                        <TextArea
                            v-model="form.content"
                            placeholder="Напишите заметку о пользователе..."
                            class="w-full"
                            rows="3"
                            :disabled="processing"
                        />
                        <InputError :message="errors.content?.[0]" class="mt-1" />
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                            Добавить заметку
                        </button>
                    </div>
                </form>

                <div v-if="loading" class="text-center py-4">
                    <span class="loading loading-spinner loading-md"></span>
                </div>

                <div v-else-if="userNotes.length === 0" class="text-center py-4 text-base-content">
                    Нет заметок о пользователе
                </div>

                <div v-else class="space-y-4 max-h-96 overflow-y-auto">
                    <div
                        v-for="note in userNotes"
                        :key="note.id"
                        class="p-4 card bg-base-200 shadow-sm"
                    >
                        <div class="text-sm whitespace-pre-line">{{ note.content }}</div>
                        <div class="mt-2 flex justify-between items-center text-xs text-base-content/70">
                            <div>Добавил: {{ note.creator.name }}</div>
                            <div>{{ note.created_at }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btm-sm">Закрыть</button>
        </ModalFooter>
    </Modal>
</template>
