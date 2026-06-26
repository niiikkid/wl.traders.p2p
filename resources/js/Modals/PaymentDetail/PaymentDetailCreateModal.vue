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
import FieldHint from "@/Components/Form/FieldHint.vue";
import TraderCommissionRangePreview from "@/Components/PaymentGateway/TraderCommissionRangePreview.vue";
import PaymentDetailScheduleField from "@/Components/PaymentDetail/PaymentDetailScheduleField.vue";
import { useModalStore } from "@/store/modal.js";
import { useViewStore } from "@/store/view.js";
import {
    paymentDetailFieldHints,
    paymentDetailSectionHints,
    paymentDetailTypeHints,
} from "@/utils/paymentDetailHints.js";
import { isRemovedDetailType } from "@/utils/paymentDetail.js";
import { storeToRefs } from "pinia";
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useTraderMaxMinOrderAmount } from "@/composables/useTraderMaxMinOrderAmount.js";

const modalStore = useModalStore();
const viewStore = useViewStore();
const { paymentDetailCreateModal } = storeToRefs(modalStore);

const isTraderView = computed(() => viewStore.isTraderViewMode);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const payment_gateways = ref([]);
const devices = ref([]);
const canWorkWithoutDevice = ref(usePage().props.auth?.user?.can_work_without_device ?? false);
const currentUser = usePage().props.auth?.user;
const isVipUser = computed(() => {
    return currentUser?.is_vip === true || currentUser?.is_vip === 1;
});

const { traderMaxMinOrderAmount, clampMinOrderAmount } = useTraderMaxMinOrderAmount({
    bypassForAdmin: false,
});

const form = ref({
    name: '',
    detail: '',
    initials: '',
    additional_info: '',
    is_active: true,
    daily_limit: '',
    monthly_limit: '',
    monthly_limit_reset_day: '',
    monthly_successful_orders_limit: '',
    daily_successful_orders_limit: '',
    max_pending_orders_quantity: 1,
    payment_gateway_ids: [],
    detail_type: null,
    user_device_id: 0,
    order_interval_minutes: '',
    currency: null,
    min_order_amount: '',
    max_order_amount: '',
    payment_detail_schedule_id: null,
});

const details = ref({
    'card': '',
    'phone': '',
    'mobile_commerce': '',
    'account_number': '',
    'iban_uah': '',
    'e-com': '',
});

const selectedDetailType = ref(null);
const activeHelpKey = ref('currency');
const desktopHintClass = 'xl:hidden';

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
    'iban_uah': 'IBAN UAH',
    'e-com': 'E-COM',
};

const availableDetailTypes = computed(() => {
    if (!form.value.currency) return [];
    const types = new Set();
    payment_gateways.value
        .filter(pg => pg.currency.toLowerCase() === form.value.currency.toLowerCase())
        .forEach(pg => {
            (pg.detail_types || []).forEach(type => types.add(type));
        });
    return Array.from(types)
        .filter(type => !isRemovedDetailType(type))
        .map(type => ({
        id: type,
        name: detail_type_names[type] ?? type
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

const normalizeGatewaySelection = (value) => {
    if (Array.isArray(value)) {
        return value.map((item) => Number(item)).filter((item) => Number.isFinite(item));
    }

    const single = Number(value);

    return Number.isFinite(single) ? [single] : [];
};

const selectedPaymentGateway = computed(() => {
    const selectedIds = normalizeGatewaySelection(form.value.payment_gateway_ids);
    if (!selectedIds.length) {
        return null;
    }

    return payment_gateways.value.find((gateway) => Number(gateway.id) === selectedIds[0]) ?? null;
});

const selectedGatewaySupportsFlexibleCommission = computed(() => {
    return !!selectedPaymentGateway.value?.use_flexible_trader_commission_for_orders;
});

const isManualProcessing = computed(() => {
    return canWorkWithoutDevice.value && !Number(form.value.user_device_id);
});

const currentDetailHint = computed(() => {
    return paymentDetailTypeHints[selectedDetailType.value] ?? null;
});

const helpTopics = computed(() => ({
    currency: {
        title: 'Валюта',
        text: paymentDetailFieldHints.currency,
    },
    detail_type: {
        title: 'Тип реквизита',
        text: paymentDetailFieldHints.detail_type,
    },
    payment_gateway_ids: {
        title: isMultipleGatewaysAllowed.value ? 'Платежные методы' : 'Платежный метод',
        text: paymentDetailFieldHints.payment_gateway_ids,
    },
    user_device_id: {
        title: canWorkWithoutDevice.value ? 'Способ обработки' : 'Устройство',
        text: paymentDetailFieldHints.user_device_id,
    },
    detail: {
        title: detail_type_names[selectedDetailType.value] ?? 'Реквизит',
        text: currentDetailHint.value ?? paymentDetailSectionHints.paymentData,
    },
    name: {
        title: 'Никнейм реквизитов',
        text: paymentDetailFieldHints.name,
    },
    initials: {
        title: 'Инициалы',
        text: paymentDetailFieldHints.initials,
    },
    additional_info: {
        title: 'ИПН (ИНН)',
        text: paymentDetailFieldHints.additional_info,
    },
    min_order_amount: {
        title: 'Минимум суммы сделки',
        text: paymentDetailFieldHints.min_order_amount,
    },
    max_order_amount: {
        title: 'Максимум суммы сделки',
        text: paymentDetailFieldHints.max_order_amount,
    },
    daily_limit: {
        title: 'Дневной объем сделок',
        text: paymentDetailFieldHints.daily_limit,
    },
    daily_successful_orders_limit: {
        title: 'Дневное количество сделок',
        text: paymentDetailFieldHints.daily_successful_orders_limit,
    },
    monthly_limit: {
        title: 'Месячный объем сделок',
        text: paymentDetailFieldHints.monthly_limit,
    },
    monthly_successful_orders_limit: {
        title: 'Месячное количество сделок',
        text: paymentDetailFieldHints.monthly_successful_orders_limit,
    },
    monthly_limit_reset_day: {
        title: 'День сброса',
        text: paymentDetailFieldHints.monthly_limit_reset_day,
    },
    max_pending_orders_quantity: {
        title: 'Макс. активных сделок',
        text: paymentDetailFieldHints.max_pending_orders_quantity,
    },
    order_interval_minutes: {
        title: 'Интервал выдачи',
        text: paymentDetailFieldHints.order_interval_minutes,
    },
    is_active: {
        title: 'Реквизит включен',
        text: paymentDetailFieldHints.is_active,
    },
}));

const activeHelp = computed(() => {
    return helpTopics.value[activeHelpKey.value] ?? helpTopics.value.currency;
});

const setActiveHelp = (key) => {
    activeHelpKey.value = key;
};

const clampVipOrderRangeToGatewayLimits = () => {
    const gateway = selectedPaymentGateway.value;
    if (!gateway) {
        return;
    }

    const gatewayMin = Number(gateway.min_limit);
    const gatewayMax = Number(gateway.max_limit);

    if (!Number.isFinite(gatewayMin) || !Number.isFinite(gatewayMax) || gatewayMin >= gatewayMax) {
        return;
    }

    const currentMin = form.value.min_order_amount === '' ? null : Number(form.value.min_order_amount);
    const currentMax = form.value.max_order_amount === '' ? null : Number(form.value.max_order_amount);

    if (Number.isFinite(currentMin)) {
        const clampedMin = Math.min(gatewayMax, Math.max(gatewayMin, currentMin));
        form.value.min_order_amount = clampMinOrderAmount(clampedMin);
    }

    if (Number.isFinite(currentMax)) {
        form.value.max_order_amount = Math.min(gatewayMax, Math.max(gatewayMin, currentMax));
    }

    if (
        Number.isFinite(Number(form.value.min_order_amount)) &&
        Number.isFinite(Number(form.value.max_order_amount)) &&
        Number(form.value.min_order_amount) > Number(form.value.max_order_amount)
    ) {
        form.value.max_order_amount = form.value.min_order_amount;
    }
};

watch(selectedDetailType, (newType) => {
    form.value.payment_gateway_ids = [];
    form.value.detail_type = newType;
    if (newType !== 'iban_uah') {
        form.value.additional_info = '';
    }
    if (newType) {
        Object.keys(details.value).forEach(key => {
            if (key !== newType) {
                details.value[key] = '';
            }
        });
    }
});

watch(
    () => form.value.payment_gateway_ids,
    () => {
        clampVipOrderRangeToGatewayLimits();
    },
    { deep: true }
);

const isMultipleGatewaysAllowed = computed(() => {
    return false;
});

const resetState = () => {
    form.value = {
        name: '',
        detail: '',
        initials: '',
        additional_info: '',
        is_active: true,
        daily_limit: '',
        monthly_limit: '',
        monthly_limit_reset_day: '',
        monthly_successful_orders_limit: '',
        daily_successful_orders_limit: '',
        max_pending_orders_quantity: 1,
        payment_gateway_ids: [],
        detail_type: null,
        user_device_id: 0,
        order_interval_minutes: '',
        currency: null,
        min_order_amount: '',
        max_order_amount: '',
        payment_detail_schedule_id: null,
    };
    details.value = {
        'card': '',
        'phone': '',
        'mobile_commerce': '',
        'account_number': '',
        'iban_uah': '',
        'e-com': '',
    };
    selectedDetailType.value = null;
    activeHelpKey.value = 'currency';
    errors.value = {};
    devices.value = [];
    payment_gateways.value = [];
};

const close = () => {
    modalStore.closeModal('paymentDetailCreate');
};

const loadCreateData = () => {
    loading.value = true;
    const requestedUserId = paymentDetailCreateModal.value.params?.user_id
        ?? paymentDetailCreateModal.value.params?.paymentDetail?.owner_id
        ?? paymentDetailCreateModal.value.params?.paymentDetail?.user_id
        ?? null;

    const params = requestedUserId ? { user_id: requestedUserId } : {};

    axios.get(route('payment-details.create-data'), { params })
        .then((res) => {
            const data = res.data?.data || res.data || {};
            payment_gateways.value = data.paymentGateways || [];
            devices.value = (data.devices || []).map(device => ({
                ...device,
                name: `${device.name}`
            }));
            canWorkWithoutDevice.value = !!data.canWorkWithoutDevice;
            form.value.user_device_id = 0;
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
    payload.additional_info = payload.additional_info || null;
    if (!payload.payment_detail_schedule_id) {
        payload.payment_detail_schedule_id = null;
    }

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
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Параметры реквизита</span>
                        <FieldHint :text="paymentDetailSectionHints.parameters" :class="desktopHintClass" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div @mouseenter="setActiveHelp('currency')" @focusin="setActiveHelp('currency')">
                            <InputLabel
                                for="currency"
                                value="Валюта"
                                :error="!!errors.currency?.[0]"
                                :hint="paymentDetailFieldHints.currency"
                                :hint-class="desktopHintClass"
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
                        <div @mouseenter="setActiveHelp('detail_type')" @focusin="setActiveHelp('detail_type')">
                            <InputLabel
                                for="detail_type"
                                value="Тип реквизита"
                                :error="!!errors.detail_type?.[0]"
                                :hint="paymentDetailFieldHints.detail_type"
                                :hint-class="desktopHintClass"
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
                        <div class="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Платежные данные</span>
                            <FieldHint :text="paymentDetailSectionHints.paymentData" :class="desktopHintClass" />
                        </div>
                        <div @mouseenter="setActiveHelp('payment_gateway_ids')" @focusin="setActiveHelp('payment_gateway_ids')">
                            <InputLabel
                                for="payment_gateway_ids"
                                :value="isMultipleGatewaysAllowed ? 'Платежные методы' : 'Платежный метод'"
                                :error="!!errors.payment_gateway_ids?.[0]"
                                :hint="paymentDetailFieldHints.payment_gateway_ids"
                                :hint-class="desktopHintClass"
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
                        <div @mouseenter="setActiveHelp('user_device_id')" @focusin="setActiveHelp('user_device_id')">
                            <InputLabel
                                for="user_device_id"
                                :value="canWorkWithoutDevice ? 'Способ обработки' : 'Устройство'"
                                :error="!!errors.user_device_id?.[0]"
                                :hint="paymentDetailFieldHints.user_device_id"
                                :hint-class="desktopHintClass"
                                class="mb-1"
                            />
                            <Select
                                id="user_device_id"
                                v-model="form.user_device_id"
                                :error="!!errors.user_device_id?.[0]"
                                :items="devices"
                                value="id"
                                name="name"
                                :default_title="canWorkWithoutDevice ? 'Ручной режим' : 'Выберите устройство'"
                                :default_value="0"
                                @change="errors.user_device_id = null"
                                :disabled="processing"
                            ></Select>
                            <InputError :message="errors.user_device_id?.[0]" class="mt-2"/>
                            <div v-if="canWorkWithoutDevice" class="mt-3">
                                <!-- For screens >= sm -->
                                <div
                                    role="alert"
                                    class="alert text-sm hidden sm:flex"
                                    :class="isManualProcessing ? 'alert-warning alert-outline' : 'alert-success alert-outline'"
                                >
                                    <span class="badge badge-xs" :class="isManualProcessing ? 'badge-warning badge-outline' : 'badge-success badge-outline'">
                                        {{ isManualProcessing ? 'Ручной' : 'Автоматика' }}
                                    </span>
                                    <span class="text-xs">
                                        {{ isManualProcessing
                                            ? 'Устройство не назначено: необходимо обрабатывать платежи вручную.'
                                            : 'Назначено устройство: для реквизита будет доступна автоматическая обработка.' }}
                                    </span>
                                </div>
                                <!-- For screens < sm -->
                                <div
                                    role="alert"
                                    class="flex flex-col gap-2 items-stretch alert text-xs sm:hidden"
                                    :class="isManualProcessing ? 'alert-warning alert-outline' : 'alert-success alert-outline'"
                                >
                                    <span class="badge badge-xs self-start" :class="isManualProcessing ? 'badge-warning badge-outline' : 'badge-success badge-outline'">
                                        {{ isManualProcessing ? 'Ручной' : 'Автоматика' }}
                                    </span>
                                    <span class="text-xs">
                                        {{ isManualProcessing
                                            ? 'Устройство не назначено: необходимо обрабатывать платежи вручную.'
                                            : 'Назначено устройство: для реквизита будет доступна автоматическая обработка.' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedDetailType === 'phone'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Номер телефона"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
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
                        <div v-if="selectedDetailType === 'mobile_commerce'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Номер телефона"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
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
                        <div v-if="selectedDetailType === 'card'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Карта"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
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

                        <div v-if="selectedDetailType === 'account_number'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Номер счета"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
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

                        <div v-if="selectedDetailType === 'iban_uah'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Номер счета IBAN"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
                            />
                            <TextInput
                                id="detail"
                                v-model="details['iban_uah']"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="UA543220010000026200353789635"
                                :error="!!errors.detail?.[0]"
                                @input="errors.detail = null"
                                :disabled="processing"
                            />
                            <InputError :message="errors.detail?.[0]" class="mt-2" />
                        </div>

                        <div v-if="selectedDetailType === 'e-com'" @mouseenter="setActiveHelp('detail')" @focusin="setActiveHelp('detail')">
                            <InputLabel
                                for="detail"
                                value="Ссылка E-COM"
                                :error="!!errors.detail?.[0]"
                                :hint="currentDetailHint"
                                :hint-class="desktopHintClass"
                            />
                            <TextInput
                                id="detail"
                                v-model="details['e-com']"
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
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Данные получателя</span>
                            <FieldHint :text="paymentDetailSectionHints.recipientData" :class="desktopHintClass" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div @mouseenter="setActiveHelp('name')" @focusin="setActiveHelp('name')">
                                <InputLabel
                                    for="name"
                                    value="Никнейм реквизитов"
                                    :error="!!errors.name?.[0]"
                                    :hint="paymentDetailFieldHints.name"
                                    :hint-class="desktopHintClass"
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
                            <div @mouseenter="setActiveHelp('initials')" @focusin="setActiveHelp('initials')">
                                <InputLabel
                                    for="initials"
                                    value="Инициалы"
                                    :error="!!errors.initials?.[0]"
                                    :hint="paymentDetailFieldHints.initials"
                                    :hint-class="desktopHintClass"
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
                            <div v-if="selectedDetailType === 'iban_uah'" @mouseenter="setActiveHelp('additional_info')" @focusin="setActiveHelp('additional_info')">
                                <InputLabel
                                    for="additional_info"
                                    value="ИПН (ИНН)"
                                    :error="!!errors.additional_info?.[0]"
                                    :hint="paymentDetailFieldHints.additional_info"
                                    :hint-class="desktopHintClass"
                                />
                                <TextInput
                                    id="additional_info"
                                    v-model="form.additional_info"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="3665906843"
                                    :error="!!errors.additional_info?.[0]"
                                    @input="errors.additional_info = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.additional_info?.[0]" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="isVipUser"
                        class="rounded-box border border-base-300 p-4"
                    >
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Лимит на сумму сделки ({{ form.currency?.toUpperCase() }})</span>
                            <FieldHint :text="paymentDetailSectionHints.vipOrderAmountLimits" :class="desktopHintClass" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <NumberInputBlock
                                v-model="form.min_order_amount"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="min_order_amount"
                                label="Минимум"
                                :label-tooltip="paymentDetailFieldHints.min_order_amount"
                                :label-tooltip-class="desktopHintClass"
                                :max="traderMaxMinOrderAmount"
                                @mouseenter="setActiveHelp('min_order_amount')"
                                @focusin="setActiveHelp('min_order_amount')"
                            />
                            <NumberInputBlock
                                v-model="form.max_order_amount"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="max_order_amount"
                                label="Максимум"
                                :label-tooltip="paymentDetailFieldHints.max_order_amount"
                                :label-tooltip-class="desktopHintClass"
                                @mouseenter="setActiveHelp('max_order_amount')"
                                @focusin="setActiveHelp('max_order_amount')"
                            />
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                        <div
                            v-if="traderMaxMinOrderAmount !== null"
                            class="alert alert-warning mt-3 py-2 text-sm"
                        >
                            <span>
                                Максимально допустимая минимальная сумма сделки: <strong>{{ traderMaxMinOrderAmount }}</strong>.
                                Чтобы указать больше, обратитесь в поддержку.
                            </span>
                        </div>

                        <TraderCommissionRangePreview
                            v-if="selectedGatewaySupportsFlexibleCommission"
                            :gateway="selectedPaymentGateway"
                            :currency="form.currency"
                            :min-amount="form.min_order_amount"
                            :max-amount="form.max_order_amount"
                            :disabled="processing"
                            @update:min-amount="(value) => { form.min_order_amount = value; errors.min_order_amount = null; }"
                            @update:max-amount="(value) => { form.max_order_amount = value; errors.max_order_amount = null; }"
                        />
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Дневные лимиты</span>
                            <FieldHint :text="paymentDetailSectionHints.dailyLimits" :class="desktopHintClass" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div @mouseenter="setActiveHelp('daily_limit')" @focusin="setActiveHelp('daily_limit')">
                                <InputLabel
                                    for="daily_limit"
                                    :value="form.currency ? `Объем сделок (${form.currency.toUpperCase()})` : 'Объем сделок'"
                                    :error="!!errors.daily_limit?.[0]"
                                    :hint="paymentDetailFieldHints.daily_limit"
                                    :hint-class="desktopHintClass"
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
                            <div @mouseenter="setActiveHelp('daily_successful_orders_limit')" @focusin="setActiveHelp('daily_successful_orders_limit')">
                                <InputLabel
                                    for="daily_successful_orders_limit"
                                    value="Количество сделок"
                                    :error="!!errors.daily_successful_orders_limit?.[0]"
                                    :hint="paymentDetailFieldHints.daily_successful_orders_limit"
                                    :hint-class="desktopHintClass"
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
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Ежемесячные лимиты</span>
                            <FieldHint :text="paymentDetailSectionHints.monthlyLimits" :class="desktopHintClass" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div @mouseenter="setActiveHelp('monthly_limit')" @focusin="setActiveHelp('monthly_limit')">
                                <InputLabel
                                    for="monthly_limit"
                                    :value="form.currency ? `Объем сделок (${form.currency.toUpperCase()})` : 'Объем сделок'"
                                    :error="!!errors.monthly_limit?.[0]"
                                    :hint="paymentDetailFieldHints.monthly_limit"
                                    :hint-class="desktopHintClass"
                                />
                                <NumberInput
                                    id="monthly_limit"
                                    v-model="form.monthly_limit"
                                    class="mt-1 block w-full"
                                    :error="!!errors.monthly_limit?.[0]"
                                    @input="errors.monthly_limit = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.monthly_limit?.[0]" class="mt-2" />
                            </div>
                            <div @mouseenter="setActiveHelp('monthly_successful_orders_limit')" @focusin="setActiveHelp('monthly_successful_orders_limit')">
                                <InputLabel
                                    for="monthly_successful_orders_limit"
                                    value="Количество сделок"
                                    :error="!!errors.monthly_successful_orders_limit?.[0]"
                                    :hint="paymentDetailFieldHints.monthly_successful_orders_limit"
                                    :hint-class="desktopHintClass"
                                />
                                <NumberInput
                                    id="monthly_successful_orders_limit"
                                    v-model="form.monthly_successful_orders_limit"
                                    class="mt-1 block w-full"
                                    :error="!!errors.monthly_successful_orders_limit?.[0]"
                                    @input="errors.monthly_successful_orders_limit = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.monthly_successful_orders_limit?.[0]" class="mt-2" />
                            </div>
                            <div class="md:col-span-2" @mouseenter="setActiveHelp('monthly_limit_reset_day')" @focusin="setActiveHelp('monthly_limit_reset_day')">
                                <InputLabel
                                    for="monthly_limit_reset_day"
                                    value="День сброса (1-31)"
                                    :error="!!errors.monthly_limit_reset_day?.[0]"
                                    :hint="paymentDetailFieldHints.monthly_limit_reset_day"
                                    :hint-class="desktopHintClass"
                                />
                                <NumberInput
                                    id="monthly_limit_reset_day"
                                    v-model="form.monthly_limit_reset_day"
                                    class="mt-1 block w-full"
                                    :error="!!errors.monthly_limit_reset_day?.[0]"
                                    @input="errors.monthly_limit_reset_day = null"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.monthly_limit_reset_day?.[0]" class="mt-2" />
                            </div>
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Лимиты можно оставить пустыми, чтобы отключить их. Количество сделок должно быть не меньше 1. День сброса общий: в этот день обнуляются и объем сделок, и количество сделок, после чего расчет начинается заново.
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Ограничения активности</span>
                            <FieldHint :text="paymentDetailSectionHints.activityLimits" :class="desktopHintClass" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <NumberInputBlock
                                v-model="form.max_pending_orders_quantity"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="max_pending_orders_quantity"
                                label="Макс. активных"
                                :label-tooltip="paymentDetailFieldHints.max_pending_orders_quantity"
                                :label-tooltip-class="desktopHintClass"
                                @mouseenter="setActiveHelp('max_pending_orders_quantity')"
                                @focusin="setActiveHelp('max_pending_orders_quantity')"
                            />
                            <NumberInputBlock
                                v-model="form.order_interval_minutes"
                                :form="form"
                                :errors="errors"
                                :on-clear="(field) => (errors[field] = null)"
                                field="order_interval_minutes"
                                label="Интервал (мин)"
                                :label-tooltip="paymentDetailFieldHints.order_interval_minutes"
                                :label-tooltip-class="desktopHintClass"
                                @mouseenter="setActiveHelp('order_interval_minutes')"
                                @focusin="setActiveHelp('order_interval_minutes')"
                            />
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <PaymentDetailScheduleField
                        v-if="isTraderView"
                        v-model="form.payment_detail_schedule_id"
                        :errors="errors"
                        :disabled="processing"
                        @clear-error="(field) => (errors[field] = null)"
                    />

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                            <span>Состояние реквизита</span>
                            <FieldHint :text="paymentDetailSectionHints.activeState" :class="desktopHintClass" />
                        </div>
                        <label
                            class="label cursor-pointer justify-start gap-3 rounded-box border border-base-200 bg-base-100 p-3"
                            @mouseenter="setActiveHelp('is_active')"
                            @focusin="setActiveHelp('is_active')"
                        >
                            <span class="inline-flex max-w-full flex-wrap items-center gap-1.5">
                                <span class="label-text">Реквизит включен</span>
                                <FieldHint :text="paymentDetailFieldHints.is_active" :class="desktopHintClass" />
                            </span>
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
        <template #aside>
            <aside class="hidden w-80 shrink-0 xl:block">
                <div class="card max-h-[calc(100dvh-4rem)] overflow-auto border border-base-300 bg-base-100 shadow-xl">
                    <div class="card-body gap-3 p-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                Подсказка
                            </div>
                            <h3 class="card-title mt-1 text-base">
                                {{ activeHelp.title }}
                            </h3>
                        </div>
                        <p class="text-sm leading-6 text-base-content/75 whitespace-pre-line">
                            {{ activeHelp.text }}
                        </p>
                        <div class="alert alert-info alert-outline py-2 text-xs">
                            <span>Наводите курсор на поля формы или переходите по ним с клавиатуры — описание появится здесь.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </template>
    </Modal>
</template>


