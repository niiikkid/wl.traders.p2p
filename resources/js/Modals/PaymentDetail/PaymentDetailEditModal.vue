<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import Select from "@/Components/Select.vue";
import FieldHint from "@/Components/Form/FieldHint.vue";
import TextInputBlock from "@/Components/Form/TextInputBlock.vue";
import NumberInputBlock from "@/Components/Form/NumberInputBlock.vue";
import TraderCommissionRangePreview from "@/Components/PaymentGateway/TraderCommissionRangePreview.vue";
import { useModalStore } from "@/store/modal.js";
import {useViewStore} from "@/store/view.js";
import {
    paymentDetailFieldHints,
    paymentDetailSectionHints,
    paymentDetailTypeHints,
} from "@/utils/paymentDetailHints.js";
import { storeToRefs } from "pinia";
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const modalStore = useModalStore();
const { paymentDetailEditModal } = storeToRefs(modalStore);
const viewStore = useViewStore();

const processing = ref(false);
const loading = ref(false);
const errors = ref({});

const payment_detail = ref(null);
const devices = ref([]);
const payment_gateways = ref([]);
const canWorkWithoutDevice = ref(usePage().props.auth?.user?.can_work_without_device ?? false);

const currentUser = usePage().props.auth?.user;
const isAdminUser = computed(() => usePage().props.auth?.is_admin === true || usePage().props.auth?.role?.name === 'Super Admin');
const isVipUser = computed(() => {
    // В админ-режиме админ всегда должен видеть все поля (включая VIP-лимиты)
    if (isAdminUser.value && viewStore.isAdminViewMode) {
        return true;
    }

    // В режиме "как трейдер" (или для обычного трейдера) ориентируемся на владельца реквизита,
    // если бэкенд его отдал, иначе — на текущего пользователя.
    if (payment_detail.value?.owner_is_vip === true || payment_detail.value?.owner_is_temp_vip_active === true) {
        return true;
    }

    return currentUser?.is_vip === true || currentUser?.is_vip === 1 || currentUser?.is_temp_vip_active;
});

const form = ref({
    name: '',
    initials: '',
    additional_info: '',
    is_active: false,
    daily_limit: '',
    monthly_limit: '',
    monthly_limit_reset_day: '',
    daily_successful_orders_limit: '',
    max_pending_orders_quantity: null,
    min_order_amount: null,
    max_order_amount: null,
    order_interval_minutes: null,
    user_device_id: 0,
    payment_gateway_ids: [],
});


const formattedDevices = computed(() => {
    return (devices.value || []).map(device => ({
        ...device,
        name: `${device.name}`
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
    return paymentDetailTypeHints[payment_detail.value?.detail_type] ?? null;
});

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
        form.value.min_order_amount = Math.min(gatewayMax, Math.max(gatewayMin, currentMin));
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

const isMultipleGatewaysAllowed = computed(() => {
    // по логике сейчас запрещено
    return false;
});

const resetState = () => {
    errors.value = {};
    processing.value = false;
    loading.value = false;
    payment_detail.value = null;
    devices.value = [];
    payment_gateways.value = [];
    form.value = {
        name: '',
        initials: '',
        additional_info: '',
        is_active: false,
        daily_limit: '',
        monthly_limit: '',
        monthly_limit_reset_day: '',
        daily_successful_orders_limit: '',
        max_pending_orders_quantity: null,
        min_order_amount: null,
        max_order_amount: null,
        order_interval_minutes: null,
        user_device_id: 0,
        payment_gateway_ids: [],
    };
    canWorkWithoutDevice.value = usePage().props.auth?.user?.can_work_without_device ?? false;
};

const close = () => {
    modalStore.closeModal('paymentDetailEdit');
};

const loadCreateData = (userId = null) => {
    // те же данные, что и при создании (список активных ГП и устройства)
    const params = {};
    if (userId) {
        params.user_id = userId;
    }

    return axios.get(route('payment-details.create-data'), { params })
        .then((res) => {
            const data = res.data?.data || res.data || {};
            payment_gateways.value = data.paymentGateways || [];
            devices.value = (data.devices || []).map(device => ({
                ...device,
                name: `${device.name}`
            }));
            if (typeof data.canWorkWithoutDevice !== 'undefined') {
                canWorkWithoutDevice.value = !!data.canWorkWithoutDevice;
            }
        });
};

const loadPaymentDetail = (id) => {
    return axios.get(route('payment-details.show', id), {
        headers: { 'Accept': 'application/json' }
    }).then((res) => {
        const detail = res.data?.data || res.data;
        payment_detail.value = detail;
        // подготовка формы
        form.value = {
            name: detail.name,
            initials: detail.initials,
            additional_info: detail.additional_info ?? '',
            is_active: !!detail.is_active,
            daily_limit: detail.daily_limit,
            monthly_limit: detail.monthly_limit,
            monthly_limit_reset_day: detail.monthly_limit_reset_day,
            daily_successful_orders_limit: detail.daily_successful_orders_limit,
            max_pending_orders_quantity: detail.max_pending_orders_quantity,
            min_order_amount: detail.min_order_amount,
            max_order_amount: detail.max_order_amount,
            order_interval_minutes: detail.order_interval_minutes,
            user_device_id: detail.user_device_id ?? 0,
            payment_gateway_ids: detail.payment_gateway_ids ?? [],
        };

        if (typeof detail.owner_can_work_without_device !== 'undefined') {
            canWorkWithoutDevice.value = !!detail.owner_can_work_without_device;
        }

        clampVipOrderRangeToGatewayLimits();
    });
};

const loadData = async () => {
    loading.value = true;
    errors.value = {};
    try {
        const id = paymentDetailEditModal.value.params?.paymentDetail?.id ?? paymentDetailEditModal.value.params?.id;
        const ownerIdFromParams = paymentDetailEditModal.value.params?.paymentDetail?.owner_id
            ?? paymentDetailEditModal.value.params?.paymentDetail?.user_id
            ?? null;

        if (ownerIdFromParams) {
            await Promise.all([
                loadCreateData(ownerIdFromParams),
                loadPaymentDetail(id),
            ]);
        } else {
            await loadPaymentDetail(id);
            const ownerIdFromApi = payment_detail.value?.owner_id ?? payment_detail.value?.user_id ?? null;
            await loadCreateData(ownerIdFromApi);
        }
    } finally {
        loading.value = false;
    }
};

const submit = () => {
    if (!payment_detail.value) return;
    processing.value = true;
    errors.value = {};

    const payload = { ...form.value };
    if (!payload.user_device_id) {
        payload.user_device_id = null;
    }
    payload.additional_info = payload.additional_info || null;

    axios.patch(route('payment-details.update', payment_detail.value.id), payload, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            processing.value = false;
            if (res.data?.success || res.status === 200) {
                close();
                router.reload({ only: ['paymentDetails'] });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response && error.response.data) {
                // валидация
                if (error.response.data.errors) {
                    errors.value = error.response.data.errors;
                } else if (error.response.data.message) {
                    // серверная бизнес-ошибка по payment_gateway_ids, если будет
                    errors.value = { _error: [error.response.data.message] };
                }
            }
        });
};

watch(
    () => paymentDetailEditModal.value.showed,
    async (state) => {
        if (state) {
            resetState();
            await loadData();
        } else {
            resetState();
        }
    }
);

watch(
    () => form.value.payment_gateway_ids,
    () => {
        clampVipOrderRangeToGatewayLimits();
    },
    { deep: true }
);
</script>

<template>
    <Modal :show="paymentDetailEditModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" :title="'Реквизит — ' + (form.name || '')" />
        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="space-y-6">
                <div class="rounded-box border border-base-300 p-4 space-y-4">
                    <div class="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Платежные данные</span>
                        <FieldHint :text="paymentDetailSectionHints.paymentData" />
                    </div>
                    <div>
                        <InputLabel
                            for="user_device_id"
                            :value="canWorkWithoutDevice ? 'Способ обработки' : 'Устройство'"
                            :error="!!errors.user_device_id?.[0]"
                            :hint="paymentDetailFieldHints.user_device_id"
                            class="mb-1"
                        />
                        <Select
                            id="user_device_id"
                            v-model="form.user_device_id"
                            :error="!!errors.user_device_id?.[0]"
                            :items="formattedDevices"
                            value="id"
                            name="name"
                            :default_title="canWorkWithoutDevice ? 'Ручной режим' : 'Выберите устройство'"
                            :default_value="0"
                            @change="errors.user_device_id = null"
                            :disabled="processing"
                        />
                        <InputError :message="errors.user_device_id?.[0]" class="mt-2"/>
                        <div v-if="canWorkWithoutDevice" class="mt-3">
                            <div
                                role="alert"
                                class="alert text-sm"
                                :class="isManualProcessing ? 'alert-warning alert-outline' : 'alert-success alert-outline'"
                            >
                                <span class="badge badge-sm" :class="isManualProcessing ? 'badge-warning badge-outline' : 'badge-success badge-outline'">
                                    {{ isManualProcessing ? 'Ручной' : 'Автоматика' }}
                                </span>
                                <span>
                                    {{ isManualProcessing
                                        ? 'Устройство не назначено: необходимо обрабатывать платежи вручную.'
                                        : 'Назначено устройство: для реквизита будет доступна автоматическая обработка.' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2">
                            <span class="inline-flex max-w-full flex-wrap items-center gap-1.5">
                                <span class="label-text break-words text-inherit">Реквизит</span>
                                <FieldHint v-if="currentDetailHint" :text="currentDetailHint" />
                            </span>
                        </div>
                        <div class="flex items-center gap-3 rounded-box border border-base-200 bg-base-100 p-3">
                            <div class="w-10 h-10 rounded-full bg-base-200 flex items-center justify-center overflow-hidden">
                                <img
                                    v-if="payment_detail?.payment_gateway?.logo_path"
                                    :src="payment_detail?.payment_gateway?.logo_path"
                                    :alt="payment_detail?.payment_gateway?.name || 'Платежный метод'"
                                    class="w-10 h-10 object-contain"
                                />
                                <span v-else class="text-xs text-base-content/60">
                                    PG
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="font-medium truncate">
                                    {{ payment_detail?.payment_gateway?.name || 'Платежный метод' }}
                                </div>
                                <div class="text-sm text-base-content/70 break-all">
                                    {{ payment_detail?.detail || '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-box border border-base-300 p-4">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Данные получателя</span>
                        <FieldHint :text="paymentDetailSectionHints.recipientData" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <TextInputBlock
                            v-model="form.name"
                            :form="{}"
                            :errors="errors"
                            field="name"
                            label="Никнейм реквизитов"
                            :label-tooltip="paymentDetailFieldHints.name"
                        />
                        <TextInputBlock
                            v-model="form.initials"
                            :form="{}"
                            :errors="errors"
                            field="initials"
                            label="Инициалы"
                            :label-tooltip="paymentDetailFieldHints.initials"
                        />
                        <TextInputBlock
                            v-if="payment_detail?.detail_type === 'iban_uah'"
                            v-model="form.additional_info"
                            :form="{}"
                            :errors="errors"
                            field="additional_info"
                            label="ИПН (ИНН)"
                            :label-tooltip="paymentDetailFieldHints.additional_info"
                        />
                    </div>
                </div>

                <div v-if="isVipUser" class="rounded-box border border-base-300 p-4">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Лимит на сумму сделки ({{ payment_detail?.currency?.toUpperCase() || '' }})</span>
                        <FieldHint :text="paymentDetailSectionHints.vipOrderAmountLimits" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.min_order_amount"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="min_order_amount"
                            label="Минимум"
                            :label-tooltip="paymentDetailFieldHints.min_order_amount"
                        />
                        <NumberInputBlock
                            v-model="form.max_order_amount"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="max_order_amount"
                            label="Максимум"
                            :label-tooltip="paymentDetailFieldHints.max_order_amount"
                        />
                    </div>
                    <div class="text-xs text-base-content/70 mt-2">
                        Оставьте пустым для отключения лимита
                    </div>

                    <TraderCommissionRangePreview
                        v-if="selectedGatewaySupportsFlexibleCommission"
                        :gateway="selectedPaymentGateway"
                        :currency="payment_detail?.currency"
                        :min-amount="form.min_order_amount"
                        :max-amount="form.max_order_amount"
                        :disabled="processing"
                        @update:min-amount="(value) => { form.min_order_amount = value; errors.min_order_amount = null; }"
                        @update:max-amount="(value) => { form.max_order_amount = value; errors.max_order_amount = null; }"
                    />
                </div>

                <div class="rounded-box border border-base-300 p-4">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Дневные лимиты ({{ payment_detail?.currency?.toUpperCase() || '' }})</span>
                        <FieldHint :text="paymentDetailSectionHints.dailyLimits" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.daily_limit"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="daily_limit"
                            label="Объем сделок"
                            :label-tooltip="paymentDetailFieldHints.daily_limit"
                        />
                        <NumberInputBlock
                            v-model="form.daily_successful_orders_limit"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="daily_successful_orders_limit"
                            label="Количество сделок"
                            :label-tooltip="paymentDetailFieldHints.daily_successful_orders_limit"
                        />
                    </div>
                    <div class="text-xs text-base-content/70 mt-2">
                        Оставьте пустым для отключения лимита
                    </div>
                </div>

                <div class="rounded-box border border-base-300 p-4">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Ежемесячные лимиты ({{ payment_detail?.currency?.toUpperCase() || '' }})</span>
                        <FieldHint :text="paymentDetailSectionHints.monthlyLimits" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.monthly_limit"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="monthly_limit"
                            label="Объем сделок"
                            :label-tooltip="paymentDetailFieldHints.monthly_limit"
                        />
                        <NumberInputBlock
                            v-model="form.monthly_limit_reset_day"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="monthly_limit_reset_day"
                            label="День сброса (1-28)"
                            :label-tooltip="paymentDetailFieldHints.monthly_limit_reset_day"
                        />
                    </div>
                    <div class="text-xs text-base-content/70 mt-2">
                        Заполните оба поля или оставьте оба пустыми для отключения лимита
                    </div>
                </div>

                <div class="rounded-box border border-base-300 p-4">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span>Ограничения активности</span>
                        <FieldHint :text="paymentDetailSectionHints.activityLimits" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.max_pending_orders_quantity"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="max_pending_orders_quantity"
                            label="Макс. активных"
                            :label-tooltip="paymentDetailFieldHints.max_pending_orders_quantity"
                        />
                        <NumberInputBlock
                            v-model="form.order_interval_minutes"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="order_interval_minutes"
                            label="Интервал (мин)"
                            :label-tooltip="paymentDetailFieldHints.order_interval_minutes"
                        />
                    </div>
                    <div class="text-xs text-base-content/70 mt-2">
                        Оставьте пустым для отключения лимита
                    </div>
                </div>

                <div>
                    <label class="label cursor-pointer mb-3 mt-3 justify-start gap-3">
                        <span class="inline-flex max-w-full flex-wrap items-center gap-1.5">
                            <span class="label-text">Реквизит включен</span>
                            <FieldHint :text="paymentDetailFieldHints.is_active" />
                        </span>
                        <input type="checkbox" class="toggle toggle-primary" v-model="form.is_active" :disabled="processing" />
                    </label>
                </div>
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


