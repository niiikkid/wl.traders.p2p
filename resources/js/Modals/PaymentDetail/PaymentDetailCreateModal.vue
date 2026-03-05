<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import NumberInput from "@/Components/NumberInput.vue";
import Select from "@/Components/Select.vue";
import Multiselect from "@/Components/Form/Multiselect.vue";
import NumberInputBlock from "@/Components/Form/NumberInputBlock.vue";
import { useModalStore } from "@/store/modal.js";
import { storeToRefs } from "pinia";
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const modalStore = useModalStore();
const { paymentDetailCreateModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const payment_gateways = ref([]);
const devices = ref([]);
const canWorkWithoutDevice = ref(usePage().props.auth?.user?.can_work_without_device ?? false);
const currentUser = usePage().props.auth?.user;
const isVipUser = computed(() => {
    return currentUser?.is_vip === true || currentUser?.is_vip === 1 || currentUser?.is_temp_vip_active;
});

const form = ref({
    name: '',
    detail: '',
    initials: '',
    is_active: true,
    daily_limit: '',
    daily_successful_orders_limit: '',
    max_pending_orders_quantity: 1,
    payment_gateway_ids: [],
    detail_type: null,
    user_device_id: null,
    order_interval_minutes: '',
    currency: null,
    min_order_amount: '',
    max_order_amount: '',
});

const details = ref({
    'card': '',
    'phone': '',
    'mobile_commerce': '',
    'account_number': '',
    'nspk': '',
});

const selectedDetailType = ref(null);

const availableCurrencies = computed(() => {
    const currencies = [...new Set(payment_gateways.value.map(pg => pg.currency))];
    return currencies.map(currency => ({
        id: currency,
        name: currency?.toUpperCase()
    }));
});

const detail_type_names = {
    'card': 'Карта',
    'phone': 'СБП',
    'mobile_commerce': 'Моб. коммерция',
    'account_number': 'Номер счета',
    'nspk': 'NSPK (ссылка)',
};

const availableDetailTypes = computed(() => {
    if (!form.value.currency) return [];
    const types = new Set();
    payment_gateways.value
        .filter(pg => pg.currency.toLowerCase() === form.value.currency.toLowerCase())
        .forEach(pg => {
            (pg.detail_types || []).forEach(type => types.add(type));
        });
    return Array.from(types).map(type => ({
        id: type,
        name: detail_type_names[type]
    }));
});

const formattedPaymentGateways = computed(() => {
    if (!form.value.currency || !selectedDetailType.value) return [];
    return payment_gateways.value
        .filter(pg =>
            pg.currency.toLowerCase() === form.value.currency.toLowerCase() &&
            (pg.detail_types || []).includes(selectedDetailType.value)
        )
        .map(pg => ({
            value: pg.id,
            label: pg.name
        }));
});

watch(selectedDetailType, (newType) => {
    form.value.payment_gateway_ids = [];
    form.value.detail_type = newType;
    if (newType) {
        Object.keys(details.value).forEach(key => {
            if (key !== newType) {
                details.value[key] = '';
            }
        });
    }
});

const isMultipleGatewaysAllowed = computed(() => {
    return false;
});

const resetState = () => {
    form.value = {
        name: '',
        detail: '',
        initials: '',
        is_active: true,
        daily_limit: '',
        daily_successful_orders_limit: '',
        max_pending_orders_quantity: 1,
        payment_gateway_ids: [],
        detail_type: null,
        user_device_id: null,
        order_interval_minutes: '',
        currency: null,
        min_order_amount: '',
        max_order_amount: '',
    };
    details.value = {
        'card': '',
        'phone': '',
        'mobile_commerce': '',
        'account_number': '',
        'nspk': '',
    };
    selectedDetailType.value = null;
    errors.value = {};
    devices.value = [];
    payment_gateways.value = [];
};

const close = () => {
    modalStore.closeModal('paymentDetailCreate');
};

const loadCreateData = () => {
    loading.value = true;
    axios.get(route('payment-details.create-data'))
        .then((res) => {
            const data = res.data?.data || res.data || {};
            payment_gateways.value = data.paymentGateways || [];
            devices.value = (data.devices || []).map(device => ({
                ...device,
                name: `${device.name}`
            }));
            canWorkWithoutDevice.value = !!data.canWorkWithoutDevice;
            if (canWorkWithoutDevice.value) {
                form.value.user_device_id = null;
            }
        })
        .finally(() => {
            loading.value = false;
        });
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    const payload = { ...form.value };
    if (!payload.user_device_id) {
        payload.user_device_id = null;
    }
    payload.detail_type = selectedDetailType.value;
    payload.detail = details.value[payload.detail_type];

    axios.post(route('payment-details.store'), payload, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            processing.value = false;
            if (res.data?.success || res.status === 200 || res.status === 201) {
                close();
                router.reload({ only: ['paymentDetails'] });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
            }
        });
};

watch(
    () => paymentDetailCreateModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            loadCreateData();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="paymentDetailCreateModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Новый реквизит" />
        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="space-y-6">
                <div class="rounded-box border border-base-300 p-4">
                    <div class="text-sm font-medium mb-3">
                        Параметры реквизита
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="currency"
                                value="Валюта"
                                :error="!!errors.currency?.[0]"
                                class="mb-1"
                            />
                            <Select
                                id="currency"
                                v-model="form.currency"
                                :error="!!errors.currency?.[0]"
                                :items="availableCurrencies"
                                value="id"
                                name="name"
                                default_title="Выберите валюту"
                                :default_value="null"
                                @change="selectedDetailType = null; form.payment_gateway_ids = []; errors.currency = null"
                                :disabled="processing"
                            ></Select>
                            <InputError :message="errors.currency?.[0]" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel
                                for="detail_type"
                                value="Тип реквизита"
                                :error="!!errors.detail_type?.[0]"
                                class="mb-1"
                            />
                            <Select
                                id="detail_type"
                                v-model="selectedDetailType"
                                :error="!!errors.detail_type?.[0]"
                                :items="availableDetailTypes"
                                value="id"
                                name="name"
                                default_title="Выберите тип реквизита"
                                :default_value="null"
                                :disabled="processing || !form.currency"
                            ></Select>
                            <InputError :message="errors.detail_type?.[0]" class="mt-2" />
                        </div>
                    </div>
                </div>

                <template v-if="selectedDetailType">
                    <div class="rounded-box border border-base-300 p-4 space-y-4">
                        <div class="text-sm font-medium">
                            Платежные данные
                        </div>
                        <div>
                            <InputLabel
                                for="payment_gateway_ids"
                                :value="isMultipleGatewaysAllowed ? 'Платежные методы' : 'Платежный метод'"
                                :error="!!errors.payment_gateway_ids?.[0]"
                                class="mb-1"
                            />
                            <Multiselect
                                id="payment_gateway_ids"
                                v-model="form.payment_gateway_ids"
                                :options="formattedPaymentGateways"
                                :error="!!errors.payment_gateway_ids?.[0]"
                                @change="errors.payment_gateway_ids = null"
                                :enable-search="true"
                                :single-select="!isMultipleGatewaysAllowed"
                                :placeholder="isMultipleGatewaysAllowed ? 'Выберите платежные методы' : 'Выберите платежный метод'"
                                :disabled="processing"
                            />
                            <InputError :message="errors.payment_gateway_ids?.[0]" class="mt-2"/>
                        </div>
                        <div v-if="!canWorkWithoutDevice">
                            <InputLabel
                                for="user_device_id"
                                value="Устройство"
                                :error="!!errors.user_device_id?.[0]"
                                class="mb-1"
                            />
                            <Select
                                id="user_device_id"
                                v-model="form.user_device_id"
                                :error="!!errors.user_device_id?.[0]"
                                :items="devices"
                                value="id"
                                name="name"
                                default_title="Выберите устройство"
                                @change="errors.user_device_id = null"
                                :disabled="processing"
                            ></Select>
                            <InputError :message="errors.user_device_id?.[0]" class="mt-2"/>
                        </div>
                        <div v-if="selectedDetailType === 'phone'">
                            <InputLabel
                                for="detail"
                                value="Номер телефона"
                                :error="!!errors.detail?.[0]"
                            />
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400">+</span>
                                </div>
                                <TextInput
                                    id="detail"
                                    v-model="details['phone']"
                                    type="text"
                                    class="mt-1 block w-full ps-7"
                                    :error="!!errors.detail?.[0]"
                                    @input="errors.detail = null"
                                    :disabled="processing"
                                />
                            </div>
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>
                        <div v-if="selectedDetailType === 'mobile_commerce'">
                            <InputLabel
                                for="detail"
                                value="Номер телефона"
                                :error="!!errors.detail?.[0]"
                            />
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400">+</span>
                                </div>
                                <TextInput
                                    id="detail"
                                    v-model="details['mobile_commerce']"
                                    type="text"
                                    class="mt-1 block w-full ps-7"
                                    :error="!!errors.detail?.[0]"
                                    @input="errors.detail = null"
                                    :disabled="processing"
                                />
                            </div>
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>
                        <div v-if="selectedDetailType === 'card'">
                            <InputLabel
                                for="detail"
                                value="Карта"
                                :error="!!errors.detail?.[0]"
                            />
                            <TextInput
                                id="detail"
                                v-model="details['card']"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="0000 0000 0000 0000"
                                :error="!!errors.detail?.[0]"
                                @input="errors.detail = null"
                                :disabled="processing"
                            />
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>

                        <div v-if="selectedDetailType === 'account_number'">
                            <InputLabel
                                for="detail"
                                value="Номер счета"
                                :error="!!errors.detail?.[0]"
                            />
                            <TextInput
                                id="detail"
                                v-model="details['account_number']"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="00000000000000000000"
                                :error="!!errors.detail?.[0]"
                                @input="errors.detail = null"
                                :disabled="processing"
                            />
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>

                        <div v-if="selectedDetailType === 'nspk'">
                            <InputLabel
                                for="detail"
                                value="Ссылка NSPK/SBP"
                                :error="!!errors.detail?.[0]"
                            />
                            <TextInput
                                id="detail"
                                v-model="details['nspk']"
                                type="url"
                                class="mt-1 block w-full"
                                placeholder="https://example.com/pay"
                                :error="!!errors.detail?.[0]"
                                @input="errors.detail = null"
                                :disabled="processing"
                            />
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Данные получателя
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    for="name"
                                    value="Никнейм реквизитов"
                                    :error="!!errors.name?.[0]"
                                />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :error="!!errors.name?.[0]"
                                    @input="errors.name = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.name?.[0]" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel
                                    for="initials"
                                    value="Инициалы (имя получателя)"
                                    :error="!!errors.initials?.[0]"
                                />
                                <TextInput
                                    id="initials"
                                    v-model="form.initials"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :error="!!errors.initials?.[0]"
                                    @input="errors.initials = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.initials?.[0]" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div v-if="isVipUser" class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Лимит на сумму сделки ({{ form.currency?.toUpperCase() }})
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <NumberInputBlock
                                v-model="form.min_order_amount"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="min_order_amount"
                                label="Минимум"
                            />
                            <NumberInputBlock
                                v-model="form.max_order_amount"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="max_order_amount"
                                label="Максимум"
                            />
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Дневные лимиты ({{ form.currency?.toUpperCase() }})
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    for="daily_limit"
                                    value="Объем сделок"
                                    :error="!!errors.daily_limit?.[0]"
                                />
                                <NumberInput
                                    id="daily_limit"
                                    v-model="form.daily_limit"
                                    class="mt-1 block w-full"
                                    :error="!!errors.daily_limit?.[0]"
                                    @input="errors.daily_limit = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.daily_limit?.[0]" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel
                                    for="daily_successful_orders_limit"
                                    value="Количество сделок"
                                    :error="!!errors.daily_successful_orders_limit?.[0]"
                                />
                                <NumberInput
                                    id="daily_successful_orders_limit"
                                    v-model="form.daily_successful_orders_limit"
                                    class="mt-1 block w-full"
                                    :error="!!errors.daily_successful_orders_limit?.[0]"
                                    @input="errors.daily_successful_orders_limit = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.daily_successful_orders_limit?.[0]" class="mt-2" />
                            </div>
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Ограничения активности
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <NumberInputBlock
                                v-model="form.max_pending_orders_quantity"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="max_pending_orders_quantity"
                                label="Макс. активных"
                            />
                            <NumberInputBlock
                                v-model="form.order_interval_minutes"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="order_interval_minutes"
                                label="Интервал (мин)"
                            />
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <div>
                        <label class="label cursor-pointer mb-3 mt-3 justify-start gap-3">
                            <span class="label-text">Реквизит включен</span>
                            <input type="checkbox" class="toggle toggle-primary" v-model="form.is_active" :disabled="processing" />
                        </label>
                    </div>
                </template>
            </form>
        </ModalBody>
        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">Отмена</button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>


