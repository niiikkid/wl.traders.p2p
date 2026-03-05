<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia';
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Select from "@/Components/Select.vue";
import NumberInputBlock from "@/Components/Form/NumberInputBlock.vue";
import AlertWarning from "@/Components/Alerts/AlertWarning.vue";
import AlertInfo from "@/Components/Alerts/AlertInfo.vue";
import { ref, watch } from "vue";
import { router } from '@inertiajs/vue3';
import AlertError from "@/Components/Alerts/AlertError.vue";

const modalStore = useModalStore();
const { paymentCreateModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});

const payment_gateways = ref([]);
const currencies = ref([]);
const merchants = ref([]);

const form = ref({
    amount: null,
    currency: 0,
    payment_gateway: 0,
    payment_detail_type: 'card',
    merchant_id: 0,
    manually: null,
});

const manually_mode = ref(false);
const gateway_mode = ref('payment_gateway');

const detailTypeOptions = [
    { id: 'card', name: 'Карта' },
    { id: 'phone', name: 'СБП' },
    { id: 'mobile_commerce', name: 'Моб. коммерция' },
    { id: 'account_number', name: 'Номер счета' },
    { id: 'nspk', name: 'NSPK (ссылка)' },
];

const resetForm = () => {
    form.value = {
        amount: null,
        currency: 0,
        payment_gateway: 0,
        payment_detail_type: 'card',
        merchant_id: 0,
        manually: null,
    };
    manually_mode.value = false;
    gateway_mode.value = 'payment_gateway';
    errors.value = {};
};

const close = () => {
    modalStore.closeModal('paymentCreate');
};

const loadData = () => {
    loading.value = true;
    axios.get(route('payments.create-data'))
        .then(response => {
            const data = response.data?.data || response.data || {};
            payment_gateways.value = data.paymentGateways || [];
            currencies.value = data.currencies || [];
            merchants.value = data.merchants || [];
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const transformedPayload = () => {
    const data = { ...form.value };
    if (data.payment_gateway === 0) {
        if (data.currency) {
            data.currency = String(data.currency).toLowerCase();
        }
        delete data.payment_gateway;
    }
    if (data.currency === 0) {
        delete data.currency;
    }
    if (data.merchant_id === 0) {
        delete data.merchant_id;
    }
    if (manually_mode.value === true) {
        data.manually = 1;
        delete data.payment_gateway;
        delete data.payment_detail_type;
    } else {
        delete data.manually;
    }
    return data;
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.post(route('payments.store'), transformedPayload(), {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200 || response.status === 201) {
                close();
                resetForm();
                router.reload({ only: ['orders'] });
            }
        })
        .catch(error => {
            processing.value = false;
            if (error.response && error.response.data) {
                if (error.response.data.errors) {
                    errors.value = error.response.data.errors;
                } else if (error.response.data.message) {
                    errors.value = { message: [error.response.data.message] };
                }
            }
        });
};

watch(
    () => paymentCreateModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
            loadData();
        } else {
            resetForm();
            payment_gateways.value = [];
            currencies.value = [];
            merchants.value = [];
        }
    }
);
</script>

<template>
    <Modal :show="paymentCreateModal.showed" @close="close" maxWidth="3xl">
        <ModalHeader @close="close" title="Создание платежа" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="mt-2 space-y-6">
                <AlertError v-if="errors.message?.[0]" :message="errors.message?.[0]"/>

                <NumberInputBlock
                    v-model="form.amount"
                    :form="{ errors }"
                    field="amount"
                    label="Сумма платежа"
                    placeholder="0"
                />

                <div>
                    <div class="mb-2">
                        <div v-show="manually_mode === false" class="mb-2">
                            <InputLabel
                                for="payment_detail_type"
                                value="Выберите направление"
                                class="mb-1"
                            />
                            <ul class="flex flex-wrap text-sm font-medium text-center">
                                <li class="me-2">
                                    <a @click.prevent="gateway_mode = 'payment_gateway'; form.currency = 0" href="#" :class="gateway_mode === 'payment_gateway' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" class="inline-flex items-center px-4 py-2 rounded-xl" aria-current="page">
                                        <span>Метод</span>
                                    </a>
                                </li>
                                <li class="me-2">
                                    <a @click.prevent="gateway_mode = 'currency'; form.payment_gateway = 0" href="#" :class="gateway_mode === 'currency' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" class="inline-flex items-center px-4 py-2 rounded-xl" aria-current="page">
                                        <span>Валюта</span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div v-show="gateway_mode === 'payment_gateway'">
                            <InputLabel
                                for="payment_gateway"
                                value="Платежный метод"
                                :error="!!errors.payment_gateway"
                                class="mb-1"
                            />
                            <Select
                                id="payment_gateway"
                                v-model="form.payment_gateway"
                                :error="!!errors.payment_gateway"
                                :items="payment_gateways"
                                value="code"
                                name="name"
                                default_title="Выберите метод"
                                @change="errors.payment_gateway = null"
                            ></Select>

                            <InputError :message="errors.payment_gateway" class="mt-1" />
                            <AlertInfo class="mt-3" v-if="! errors.payment_gateway" message="Платеж будет создан только в рамках выбранного платежного метода."/>
                        </div>

                        <div v-show="gateway_mode === 'currency'">
                            <InputLabel
                                for="currency"
                                value="Валюта"
                                :error="!!errors.currency"
                                class="mb-1"
                            />
                            <Select
                                id="currency"
                                v-model="form.currency"
                                :error="!!errors.currency"
                                :items="currencies"
                                value="code"
                                name="name"
                                default_title="Выберите валюту"
                                @change="errors.currency = null"
                            ></Select>

                            <InputError :message="errors.currency" class="mt-1" />
                            <AlertInfo class="mt-3" v-if="! errors.currency && manually_mode === false" message="Будет использован любой платежный метод в рамках выбранной валюты."/>
                        </div>
                    </div>

                    <div class="mb-2">
                        <InputLabel
                            for="payment_detail_type"
                            value="Выберите тип реквизитов"
                            :error="!!errors.payment_detail_type"
                            class="mb-1"
                        />
                    <Select
                        id="payment_detail_type"
                        v-model="form.payment_detail_type"
                        :items="detailTypeOptions"
                        value="id"
                        name="name"
                        default_title="Выберите тип реквизитов"
                        @change="errors.payment_detail_type = null"
                    ></Select>
                        <InputError :message="errors.payment_detail_type" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel
                            for="merchant_id"
                            value="Мерчант"
                            :error="!!errors.merchant_id"
                            class="mb-1"
                        />
                        <Select
                            id="merchant_id"
                            v-model="form.merchant_id"
                            :error="!!errors.merchant_id"
                            :items="merchants"
                            value="id"
                            name="name"
                            default_title="Выберите мерчант"
                            @change="errors.merchant_id = null"
                        ></Select>
                        <InputError :message="errors.merchant_id" class="mt-1" />
                    </div>
                </div>

                <AlertWarning message="Не для всех вариантов выбранных параметров могут не быть подходящие реквизиты."/>
            </form>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">
                Отмена
            </button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>


