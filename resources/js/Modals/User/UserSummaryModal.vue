<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import DateTime from "@/Components/DateTime.vue";
import {useModalStore} from "@/store/modal.js";
import {storeToRefs} from "pinia";
import {computed} from "vue";

const modalStore = useModalStore();
const {userSummaryModal} = storeToRefs(modalStore);

const user = computed(() => userSummaryModal.value.params?.user || null);

const close = () => {
    modalStore.closeModal('userSummary');
};

const boolToText = (value) => value ? 'Да' : 'Нет';
</script>

<template>
    <Modal :show="userSummaryModal.showed" @close="close" maxWidth="3xl">
        <ModalHeader @close="close" title="Сводка по пользователю" />
        <ModalBody>
            <div v-if="!user" class="py-4 text-sm text-base-content/70">Пользователь не найден</div>
            <div v-else class="space-y-4">
                <div class="rounded-box border border-base-300 p-4">
                    <div class="flex items-center gap-3">
                        <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" class="w-12 h-12 rounded-full" alt="user photo">
                        <div>
                            <div class="font-semibold">{{ user.email }}</div>
                            <div class="text-sm text-base-content/70">
                                ID: {{ user.id }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-box border border-base-300 p-3">
                        <div class="text-base-content/70 mb-1">Основное</div>
                        <div>Email: <span class="font-medium">{{ user.email }}</span></div>
                    </div>

                    <div class="rounded-box border border-base-300 p-3">
                        <div class="text-base-content/70 mb-1">Статусы</div>
                        <div>Онлайн: <span class="font-medium">{{ boolToText(!!user.is_online) }}</span></div>
                        <div>VIP: <span class="font-medium">{{ boolToText(!!user.is_vip) }}</span></div>
                        <div>Трафик остановлен: <span class="font-medium">{{ boolToText(!!user.stop_traffic) }}</span></div>
                        <div>Без устройства: <span class="font-medium">{{ boolToText(!!user.can_work_without_device) }}</span></div>
                    </div>

                    <div class="rounded-box border border-base-300 p-3">
                        <div class="text-base-content/70 mb-1">Выплаты</div>
                        <div>Выплаты включены: <span class="font-medium">{{ boolToText(!!user.payouts_enabled) }}</span></div>
                        <div>Холд включен: <span class="font-medium">{{ boolToText(!!user.payout_hold_enabled) }}</span></div>
                        <div>Холд (мин): <span class="font-medium">{{ user.payout_hold_minutes ?? 0 }}</span></div>
                        <div>Лимит активных выплат: <span class="font-medium">{{ user.payout_active_payouts_limit ?? 1 }}</span></div>
                    </div>

                    <div class="rounded-box border border-base-300 p-3">
                        <div class="text-base-content/70 mb-1">Даты</div>
                        <div class="mb-1 inline-flex items-center gap-1">
                            Создан:
                            <span class="font-medium">
                                <DateTime :data="user.created_at" :simple="true" />
                            </span>
                        </div>
                        <div class="mb-1">
                            Блокировка:
                            <span class="font-medium">
                                <DateTime v-if="user.banned_at" :data="user.banned_at" />
                                <span v-else>—</span>
                            </span>
                        </div>
                        <div>
                            Архив:
                            <span class="font-medium">
                                <DateTime v-if="user.archived_at" :data="user.archived_at" />
                                <span v-else>—</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">Закрыть</button>
        </ModalFooter>
    </Modal>
</template>
