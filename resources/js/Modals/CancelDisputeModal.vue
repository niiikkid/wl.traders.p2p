<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import InputHelper from "@/Components/InputHelper.vue";
import {router, useForm} from "@inertiajs/vue3";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";

const modalStore = useModalStore();
const { disputeCancelModal } = storeToRefs(modalStore);

const close = () => {
    modalStore.closeModal('disputeCancel');
};

const form = useForm({
    reason: '',
});

const cancel = (dispute) => {
    form.patch(route('disputes.cancel', dispute.id), {
        preserveScroll: true,
        onSuccess: () => {
            modalStore.closeAll()
            router.visit(route(route().current()))
        },
    });
};
</script>

<template>
    <Modal :show="disputeCancelModal.showed" @close="close" maxWidth="sm">
        <ModalHeader
            :title="'Отклонение спора #' + disputeCancelModal.params.dispute.id"
            @close="close"
        />
        <ModalBody>
            <form action="#" class="py-3">
                <div>
                    <InputLabel
                        for="reason"
                        value="Причина"
                        :error="!!form.errors.reason"
                    />

                    <TextInput
                        id="reason"
                        v-model="form.reason"
                        class="mt-1 block w-full"
                        placeholder="Неверные реквизиты"
                        :error="!!form.errors.reason"
                        @input="form.clearErrors('reason')"
                    />

                    <InputError :message="form.errors.reason" class="mt-2" />
                    <InputHelper v-if="! form.errors.reason" model-value="Укажите причину отклонения спора"></InputHelper>
                </div>
            </form>
        </ModalBody>
        <ModalFooter>
            <div class="w-full flex justify-center space-x-2">
                <button
                    @click.prevent="close"
                    type="button"
                    class="btn btn-sm btn-error btn-outline"
                >
                    Отмена
                </button>
                <button
                    @click.prevent="cancel(disputeCancelModal.params.dispute)"
                    :disabled="form.processing"
                    type="button"
                    class="btn btn-primary btn-sm btn-outline"
                >
                    Подтвердить
                </button>
            </div>
        </ModalFooter>
    </Modal>
</template>

<style scoped>

</style>
