<script setup>
import { Modal, ModalHeader, ModalBody, ModalFooter } from '@/Components/Modal';
import ConfirmOrderAcceptSummary from '@/Components/Confirm/ConfirmOrderAcceptSummary.vue';
import { useModalStore } from '@/store/modal.js';
import { computed, ref } from 'vue';

const modalStore = useModalStore();

const show = computed(() => modalStore.isOpen('confirm'));
const params = computed(() => modalStore.paramsOf('confirm'));

const processing = ref(false);

const close = () => {
    params.value.close?.();
    modalStore.close('confirm');
};

const confirm = () => {
    processing.value = true;

    try {
        params.value.confirm?.();
    } finally {
        processing.value = false;
        modalStore.close('confirm');
    }
};
</script>

<template>
    <Modal :show="show" size="md" :stack-level="2" @close="close">
        <ModalHeader :title="params.title" @close="close" />

        <ModalBody>
            <p v-if="params.body" class="text-sm text-base-content/70">
                {{ params.body }}
            </p>

            <ConfirmOrderAcceptSummary
                v-if="params.order_summary"
                class="mt-3"
                v-bind="params.order_summary"
            />
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-sm btn-ghost" @click="close">
                {{ params.cancel_button_name }}
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :disabled="processing"
                @click="confirm"
            >
                {{ params.confirm_button_name }}
            </button>
        </ModalFooter>
    </Modal>
</template>
