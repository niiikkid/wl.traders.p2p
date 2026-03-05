<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { ref, watch } from "vue";
import DateTime from "@/Components/DateTime.vue";

const modalStore = useModalStore();
const { userTempVipHistoryModal } = storeToRefs(modalStore);

const loading = ref(false);
const history = ref([]);

const resetState = () => {
    history.value = [];
    loading.value = false;
};

const close = () => {
    modalStore.closeModal('userTempVipHistory');
};

const loadHistory = async () => {
    const userId = userTempVipHistoryModal.value.params?.user?.id || userTempVipHistoryModal.value.params?.user_id;
    if (!userId) return;

    loading.value = true;
    try {
        const response = await axios.get(route('admin.users.temp-vip-history', userId), {
            headers: { Accept: 'application/json' },
        });
        history.value = response.data?.data || [];
    } finally {
        loading.value = false;
    }
};

watch(
    () => userTempVipHistoryModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            loadHistory();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="userTempVipHistoryModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="История временного VIP" />
        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <div v-else>
                <div v-if="history.length === 0" class="text-sm text-base-content/70">
                    История активаций отсутствует.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th>Активирован</th>
                            <th>Истекает</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(item, index) in history" :key="index">
                            <td><DateTime :data="item.activated_at" /></td>
                            <td><DateTime :data="item.expires_at" /></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </ModalBody>
    </Modal>
</template>

