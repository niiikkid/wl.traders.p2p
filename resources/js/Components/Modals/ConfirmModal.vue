<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { storeToRefs } from 'pinia'
import {ref} from "vue";
import { useModalStore } from "@/store/modal.js";

const modalStore = useModalStore();
const { confirmModal } = storeToRefs(modalStore);

const processing = ref(false);

const close = () => {
    modalStore.closeModal('confirm')
};
const confirm = () => {
    processing.value = true;
    confirmModal.value.params.confirm()
    processing.value = false;
    modalStore.closeModal('confirm')
};
</script>

<template>
    <Modal :show="confirmModal.showed" max-width="md" @close="close">
        <div class="space-y-3">
            <h2 class="text-lg font-semibold text-base-content">
                {{ confirmModal.params.title }}
            </h2>

            <p class="mt-1 text-sm text-base-content/70">
                {{ confirmModal.params.body }}
            </p>

            <div class="modal-action">
                <button class="btn btn-sm btn-error btn-outline" @click="close">{{ confirmModal.params.cancel_button_name }}</button>
                <button
                    type="button"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                    @click="confirm"
                    class="btn btn-sm btn-primary btn-outline"
                >
                    {{ confirmModal.params.confirm_button_name }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>

</style>
