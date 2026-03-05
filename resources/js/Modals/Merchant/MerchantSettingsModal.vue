<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import {storeToRefs} from "pinia";
import {useModalStore} from "@/store/modal.js";
import {computed, ref, watch} from "vue";
import {useViewStore} from "@/store/view.js";
import Settings from "@/Pages/Merchant/Tabs/Settings.vue";

const modalStore = useModalStore();
const viewStore = useViewStore();
const {merchantSettingsModal} = storeToRefs(modalStore);

const merchant = ref(null);
const markets = ref([]);
const categories = ref([]);
const currencies = ref([]);
const gatewaySettings = ref({});
const paymentGateways = ref({ data: [] });
const loading = ref(false);
const error = ref(null);

const title = computed(() => {
    if (!merchant.value) {
        return "Мерчант";
    }

    return `${merchant.value.name ?? `#${merchant.value.id}`}`;
});

const close = () => {
    modalStore.closeModal('merchantSettings');
};

const resetState = () => {
    merchant.value = null;
    markets.value = [];
    categories.value = [];
    currencies.value = [];
    gatewaySettings.value = {};
    paymentGateways.value = { data: [] };
    error.value = null;
};

const fetchSettings = async () => {
    const merchantId = merchantSettingsModal.value.params?.merchantId;

    if (!merchantId) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const prefix = viewStore.isAdminViewMode ? 'admin.' : '';
        const {data} = await axios.get(route(`${prefix}merchants.settings`, merchantId), {
            headers: {Accept: 'application/json'},
        });

        merchant.value = data.merchant ?? null;
        markets.value = data.markets ?? [];
        categories.value = data.categories ?? [];
        currencies.value = data.currencies ?? [];
        gatewaySettings.value = data.gateway_settings ?? {};
        paymentGateways.value = data.payment_gateways ?? { data: [] };
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить настройки мерчанта.';
    } finally {
        loading.value = false;
    }
};

const notifyUpdated = (updatedMerchant) => {
    if (updatedMerchant) {
        merchant.value = updatedMerchant;
    }

    const callback = merchantSettingsModal.value.params?.onUpdated;
    if (typeof callback === 'function' && updatedMerchant) {
        callback(updatedMerchant);
    }
};

watch(
    () => merchantSettingsModal.value.showed,
    (showed) => {
        if (showed) {
            fetchSettings();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="merchantSettingsModal.showed" maxWidth="7xl" @close="close">
        <ModalHeader :title="title" @close="close" />
        <ModalBody>
            <div v-if="loading" class="py-8 text-center text-sm text-base-content/60">
                Загрузка настроек...
            </div>
            <div v-else-if="error" class="alert alert-error shadow">
                {{ error }}
            </div>
            <Settings
                v-else-if="merchant"
                :merchant="merchant"
                :markets="markets"
                :categories="categories"
                :currencies="currencies"
                :gateway-settings="gatewaySettings"
                :payment-gateways="paymentGateways"
                @updated="notifyUpdated"
            />
            <div v-else class="py-8 text-center text-sm text-base-content/60">
                Данные отсутствуют.
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">
                Закрыть
            </button>
        </ModalFooter>
    </Modal>
</template>

