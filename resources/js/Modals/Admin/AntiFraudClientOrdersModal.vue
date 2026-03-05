<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { ref, watch } from "vue";
import DateTime from "@/Components/DateTime.vue";
import OrderStatus from "@/Components/OrderStatus.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";

const modalStore = useModalStore();
const { antiFraudClientOrdersModal } = storeToRefs(modalStore);

const loading = ref(false);
const orders = ref([]);
const client = ref(null);

const resetState = () => {
    orders.value = [];
    loading.value = false;
    client.value = null;
};

const close = () => {
    modalStore.closeModal('antiFraudClientOrders');
};

const loadOrders = async () => {
    const clientId = antiFraudClientOrdersModal.value.params?.client?.id
        || antiFraudClientOrdersModal.value.params?.client_id;

    if (!clientId) {
        return;
    }

    client.value = antiFraudClientOrdersModal.value.params?.client ?? null;
    loading.value = true;

    try {
        const response = await axios.get(route('admin.anti-fraud.clients.orders', clientId), {
            headers: { Accept: 'application/json' },
        });
        orders.value = response.data?.data || [];
    } finally {
        loading.value = false;
    }
};

watch(
    () => antiFraudClientOrdersModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            loadOrders();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="antiFraudClientOrdersModal.showed" @close="close" maxWidth="4xl">
        <ModalHeader
            @close="close"
            :title="client?.client_id ? `Сделки клиента ${client.client_id}` : 'Сделки клиента'"
        />
        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <div v-else>
                <div v-if="!orders.length" class="text-sm text-base-content/70">
                    Сделки отсутствуют.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                        <tr>
                            <th>UUID</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th class="text-right">Создан</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="order in orders" :key="order.id">
                            <td class="whitespace-nowrap font-medium">
                                <DisplayUUID :uuid="order.uuid" />
                            </td>
                            <td>
                                <div class="text-nowrap text-base-content">
                                    {{ order.amount }} {{ order.currency?.toUpperCase() }}
                                </div>
                                <div class="text-nowrap text-xs">
                                    {{ order.total_profit }} {{ order.base_currency?.toUpperCase() }}
                                </div>
                            </td>
                            <td>
                                <OrderStatus :status="order.status" :status_name="order.status_name" />
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <DateTime class="justify-start" :data="order.created_at" />
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </ModalBody>
    </Modal>
</template>
