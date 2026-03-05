<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import Select from "@/Components/Select.vue";
import TextInputBlock from "@/Components/Form/TextInputBlock.vue";
import NumberInputBlock from "@/Components/Form/NumberInputBlock.vue";
import { useModalStore } from "@/store/modal.js";
import {useViewStore} from "@/store/view.js";
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
    is_active: false,
    daily_limit: '',
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
    form.value = {
        name: '',
        initials: '',
        is_active: false,
        daily_limit: '',
        daily_successful_orders_limit: '',
        max_pending_orders_quantity: null,
        min_order_amount: null,
        max_order_amount: null,
        order_interval_minutes: null,
        user_device_id: null,
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
            is_active: !!detail.is_active,
            daily_limit: detail.daily_limit,
            daily_successful_orders_limit: detail.daily_successful_orders_limit,
            max_pending_orders_quantity: detail.max_pending_orders_quantity,
            min_order_amount: detail.min_order_amount,
            max_order_amount: detail.max_order_amount,
            order_interval_minutes: detail.order_interval_minutes,
            user_device_id: detail.user_device_id ?? null,
            payment_gateway_ids: detail.payment_gateway_ids ?? [],
        };

        if (typeof detail.owner_can_work_without_device !== 'undefined') {
            canWorkWithoutDevice.value = !!detail.owner_can_work_without_device;
        }
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
                    <div class="text-sm font-medium">
                        Платежные данные
                    </div>
                    <div v-if="!canWorkWithoutDevice || viewStore.isAdminViewMode">
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
                                :items="formattedDevices"
                                value="id"
                                name="name"
                                default_title="Выберите устройство"
                                @change="errors.user_device_id = null"
                                :disabled="processing"
                            />
                            <InputError :message="errors.user_device_id?.[0]" class="mt-2"/>
                        </div>
                        <div v-else-if="viewStore.isAdminViewMode" class="text-sm text-base-content/70">
                            Для этого трейдера работа без устройства включена. Привязка устройства не требуется.
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/70 mb-2">
                            Реквизит
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
                    <div class="text-sm font-medium mb-3">
                        Данные получателя
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <TextInputBlock
                            v-model="form.name"
                            :form="{}"
                            :errors="errors"
                            field="name"
                            label="Никнейм реквизитов"
                        />
                        <TextInputBlock
                            v-model="form.initials"
                            :form="{}"
                            :errors="errors"
                            field="initials"
                            label="Инициалы (имя получателя)"
                        />
                    </div>
                </div>

                <div v-if="isVipUser" class="rounded-box border border-base-300 p-4">
                    <div class="text-sm font-medium mb-3">
                        Лимит на сумму сделки ({{ payment_detail?.currency?.toUpperCase() || '' }})
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.min_order_amount"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="min_order_amount"
                            label="Минимум"
                        />
                        <NumberInputBlock
                            v-model="form.max_order_amount"
                            :form="{}"
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
                        Дневные лимиты ({{ payment_detail?.currency?.toUpperCase() || '' }})
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <NumberInputBlock
                            v-model="form.daily_limit"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="daily_limit"
                            label="Объем сделок"
                        />
                        <NumberInputBlock
                            v-model="form.daily_successful_orders_limit"
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="daily_successful_orders_limit"
                            label="Количество сделок"
                        />
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
                            :form="{}"
                            :errors="errors"
                            :on-clear="(field) => (errors[field] = null)"
                            field="max_pending_orders_quantity"
                            label="Макс. активных"
                        />
                        <NumberInputBlock
                            v-model="form.order_interval_minutes"
                            :form="{}"
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


