<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import {useViewStore} from "@/store/view.js";
import DisplayUUID from "@/Components/DisplayUUID.vue";

const viewStore = useViewStore();
const modalStore = useModalStore();
const { disputeModal } = storeToRefs(modalStore);

const emit = defineEmits(['accept', 'cancel', 'rollback']);

const close = () => {
    modalStore.closeModal('dispute')
};

const accept = (dispute) => {
    emit('accept', dispute);
};

const cancel = (dispute) => {
    emit('cancel', dispute);
};

const rollback = (dispute) => {
    emit('rollback', dispute);
};

const showReceipt = () => {
    window.open(disputeModal.value.params.dispute.receipt_url, '_blank').focus();
};
</script>

<template>
    <Modal :show="disputeModal.showed" @close="close" maxWidth="sm">
        <ModalHeader
            :title="'Спор #' + disputeModal.params.dispute.id"
            @close="close"
        />
        <ModalBody>
            <form action="#" class="mx-auto max-w-screen-xl px-0 2xl:px-0">
                <div class="mx-auto max-w-3xl">
                    <div>
                        <div>
                            <div v-if="disputeModal.params.dispute.status === 'accepted'">
                                <div class="flex items-center justify-center mb-2">
                                    <svg class="w-16 h-16 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                </div>
                                <p class="mb-1 text-lg font-semibold text-base-content text-center">Спор принят</p>
                                <p class="text-sm font-semibold text-base-content/70 text-center">{{ disputeModal.params.dispute.created_at }}</p>
                            </div>
                            <div v-else-if="disputeModal.params.dispute.status === 'canceled'">
                                <div class="flex items-center justify-center mb-2">
                                    <svg class="w-16 h-16 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 9-6 6m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                </div>
                                <p class="mb-1 text-lg font-semibold text-base-content text-center">Спор отклонен</p>
                                <p class="text-sm font-semibold text-base-content/70 text-center">{{ disputeModal.params.dispute.created_at }}</p>
                            </div>
                            <div v-else-if="disputeModal.params.dispute.status === 'pending'">
                                <div class="flex items-center justify-center mb-2">
                                    <svg class="w-16 h-16 text-warning" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                </div>
                                <p class="mb-1 text-lg font-semibold text-base-content text-center">Спор ожидает проверки</p>
                                <p class="text-sm font-semibold text-base-content/70 text-center">{{ disputeModal.params.dispute.created_at }}</p>
                            </div>
                            <div class="space-y-3 mt-6">
                                <div class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex justify-between items-center">
                                        <div class="items-center">
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Сумма спора
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content text-sm">
                                                {{ disputeModal.params.dispute.order.amount }} {{disputeModal.params.dispute.order.currency.toUpperCase()}}
                                            </div>
                                            <div class="text-nowrap text-base-content/70 text-sm">
                                                {{ disputeModal.params.dispute.order.total_profit }} {{disputeModal.params.dispute.order.base_currency.toUpperCase()}}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex justify-between items-center">
                                        <div class="items-center">
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Сделка
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content text-sm">
                                                <DisplayUUID :uuid="disputeModal.params.dispute.order.uuid"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex justify-between items-center">
                                        <div class="hidden sm:block items-center">
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Реквизит #{{ disputeModal.params.dispute.payment_detail.id }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content text-sm">
                                                <PaymentDetail
                                                    :detail="disputeModal.params.dispute.payment_detail.detail"
                                                    :type="disputeModal.params.dispute.payment_detail.type"
                                                    :copyable="false"
                                                    class="text-base-content"
                                                ></PaymentDetail>
                                            </div>
                                            <div class="text-nowrap text-base-content/70 text-sm">
                                                {{ disputeModal.params.dispute.payment_detail.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode" class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex justify-between items-center">
                                        <div class="items-center">
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Трейдер #{{ disputeModal.params.dispute.user.id }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-nowrap text-base-content text-sm">
                                                {{ disputeModal.params.dispute.user.name }}
                                            </div>
                                            <div class="text-nowrap text-base-content/70 text-sm">
                                                {{ disputeModal.params.dispute.user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Квитанция
                                            </div>
                                        </div>
                                        <div v-if="disputeModal.params.dispute.receipt_url">
                                            <button
                                                @click.prevent="showReceipt"
                                                type="button"
                                                class="btn btn-xs btn-outline btn-info"
                                            >
                                                Посмотреть
                                                <svg class="w-3 h-3 ml-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else>
                                            <div class="text-sm text-base-content/70">
                                                Отсутствует
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="disputeModal.params.dispute.status === 'canceled'" class="py-3 px-5 bg-base-200/60 card">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="mr-3 text-sm text-nowrap text-base-content">
                                                Причина отклонения спора
                                            </div>
                                            <div class="mr-3 text-sm text-base-content/70">
                                                {{ disputeModal.params.dispute.reason }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </ModalBody>
        <ModalFooter v-show="(viewStore.isAdminViewMode || disputeModal.params.dispute.status === 'pending' || disputeModal.params.dispute.status === 'canceled') && !viewStore.isSupportViewMode">
            <div class="flex justify-center w-full">
                <template v-if="disputeModal.params.dispute.status === 'pending'">
                    <button
                        @click.prevent="cancel(disputeModal.params.dispute)"
                        type="button"
                        class="mr-2 btn btn-sm btn-error btn-outline"
                    >
                        <svg class="w-3.5 h-3.5 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/>
                        </svg>
                        Отклонить
                    </button>
                    <button
                        @click.prevent="accept(disputeModal.params.dispute)"
                        type="button"
                        class="btn btn-sm btn-primary btn-outline"
                    >
                        <svg class="w-3.5 h-3.5 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                        </svg>
                        Принять
                    </button>
                </template>
                <template v-if="disputeModal.params.dispute.status !== 'pending'">
                    <button
                        @click.prevent="rollback(disputeModal.params.dispute)"
                        type="button"
                        class="btn btn-sm btn-warning btn-outline"
                    >
                        <svg class="w-3.5 h-3.5 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3"/>
                        </svg>
                        Открыть спор
                    </button>
                </template>
            </div>
        </ModalFooter>
    </Modal>
</template>

<style scoped>

</style>
