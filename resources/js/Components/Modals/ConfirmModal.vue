<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ConfirmOrderAcceptSummary from '@/Components/Confirm/ConfirmOrderAcceptSummary.vue';
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
    try {
        confirmModal.value.params.confirm?.();
    } finally {
        processing.value = false;
        modalStore.closeModal('confirm');
    }
};
</script>

<template>
    <Modal :show="confirmModal.showed" max-width="md" :stack-level="1" @close="close">
        <div class="space-y-3">
            <h2 class="text-lg font-semibold text-base-content">
                {{ confirmModal.params.title }}
            </h2>

            <p
                v-if="confirmModal.params.body"
                class="text-sm text-base-content/70"
            >
                {{ confirmModal.params.body }}
            </p>

            <ConfirmOrderAcceptSummary
                v-if="confirmModal.params.order_summary"
                v-bind="confirmModal.params.order_summary"
            />

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
