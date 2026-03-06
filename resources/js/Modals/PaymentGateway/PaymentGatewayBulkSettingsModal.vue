<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputHelper from "@/Components/InputHelper.vue";
import DropDownWithCheckbox from "@/Components/Form/DropDownWithCheckbox.vue";
import DropDownWithRadio from "@/Components/Form/DropDownWithRadio.vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

const modalStore = useModalStore();
const { paymentGatewayBulkSettingsModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const currencies = ref([]);
const detail_types = ref([]);
const splitPoint = ref(null);

const makeTier = (from = null, to = null, rate = null) => ({
    from,
    to,
    rate,
});

const toNumberOrNull = (value) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
};

const getLimits = () => {
    const min = toNumberOrNull(form.value.min_limit);
    const max = toNumberOrNull(form.value.max_limit);

    if (min === null || max === null || min >= max) {
        return null;
    }

    return { min, max };
};

const clearTiersGeneralError = (field = "trader_commission_tiers_for_orders") => {
    if (!errors.value?.[field]) {
        return;
    }

    const next = { ...errors.value };
    delete next[field];
    errors.value = next;
};

const setTiersGeneralError = (message, field = "trader_commission_tiers_for_orders") => {
    errors.value = {
        ...errors.value,
        [field]: [message],
    };
};

const buildContiguousTiers = (innerPoints, sourceTiers = [], fallbackRate = 0) => {
    const limits = getLimits();
    if (!limits) {
        return [];
    }

    const points = Array.from(new Set(
        innerPoints
            .map((point) => toNumberOrNull(point))
            .filter((point) => point !== null && point > limits.min && point < limits.max)
    )).sort((left, right) => left - right);

    const boundaries = [limits.min, ...points, limits.max];
    const safeFallbackRate = toNumberOrNull(fallbackRate) ?? 0;

    return boundaries.slice(0, -1).map((from, index) => {
        const to = boundaries[index + 1];
        const midpoint = from + (to - from) / 2;
        const sourceTier = sourceTiers.find((tier, tierIndex) => {
            const tierFrom = toNumberOrNull(tier?.from);
            const tierTo = toNumberOrNull(tier?.to);
            if (tierFrom === null || tierTo === null) {
                return false;
            }

            const isLast = tierIndex === sourceTiers.length - 1;
            return isLast ? (midpoint >= tierFrom && midpoint <= tierTo) : (midpoint >= tierFrom && midpoint < tierTo);
        });

        return makeTier(
            from,
            to,
            toNumberOrNull(sourceTier?.rate) ?? safeFallbackRate
        );
    });
};

const alignTiersWithLimits = (tierField, fallbackRateField) => {
    const limits = getLimits();
    if (!limits) {
        form.value[tierField] = [];
        return;
    }

    const source = form.value[tierField] || [];
    const innerPoints = source
        .slice(0, -1)
        .map((tier) => toNumberOrNull(tier?.to))
        .filter((point) => point !== null);

    form.value[tierField] = buildContiguousTiers(innerPoints, source, form.value[fallbackRateField]);
};

const syncTotalServiceTiersWithTraderBoundaries = () => {
    const traderTiers = form.value.trader_commission_tiers_for_orders || [];
    const innerPoints = traderTiers
        .slice(0, -1)
        .map((tier) => toNumberOrNull(tier?.to))
        .filter((point) => point !== null);

    form.value.total_service_commission_tiers_for_orders = buildContiguousTiers(
        innerPoints,
        form.value.total_service_commission_tiers_for_orders || [],
        form.value.total_service_commission_rate_for_orders
    );
};

const alignAllFlexibleTiersWithLimits = () => {
    alignTiersWithLimits("trader_commission_tiers_for_orders", "trader_commission_rate_for_orders");
    syncTotalServiceTiersWithTraderBoundaries();
};

const form = ref({
    currency: null,
    detail_types: [],
    min_limit: null,
    max_limit: null,
    trader_commission_rate_for_orders: null,
    use_flexible_trader_commission_for_orders: false,
    trader_commission_tiers_for_orders: [],
    total_service_commission_tiers_for_orders: [],
    total_service_commission_rate_for_orders: null,
    trader_commission_rate_for_payouts: null,
    total_service_commission_rate_for_payouts: null,
    reservation_time_for_orders: null,
    reservation_time_for_payouts: null,
    is_active: true,
    is_payouts_enabled: true,
    apply: {
        detail_types: false,
        min_limit: false,
        max_limit: false,
        trader_commission_rate_for_orders: false,
        use_flexible_trader_commission_for_orders: false,
        trader_commission_tiers_for_orders: false,
        total_service_commission_tiers_for_orders: false,
        total_service_commission_rate_for_orders: false,
        trader_commission_rate_for_payouts: false,
        total_service_commission_rate_for_payouts: false,
        reservation_time_for_orders: false,
        reservation_time_for_payouts: false,
        is_active: false,
        is_payouts_enabled: false,
    },
});

const close = () => {
    modalStore.closeModal('paymentGatewayBulkSettings');
};

const resetForm = () => {
    splitPoint.value = null;
    form.value = {
        currency: null,
        detail_types: [],
        min_limit: null,
        max_limit: null,
        trader_commission_rate_for_orders: null,
        use_flexible_trader_commission_for_orders: false,
        trader_commission_tiers_for_orders: [],
        total_service_commission_tiers_for_orders: [],
        total_service_commission_rate_for_orders: null,
        trader_commission_rate_for_payouts: null,
        total_service_commission_rate_for_payouts: null,
        reservation_time_for_orders: null,
        reservation_time_for_payouts: null,
        is_active: true,
        is_payouts_enabled: true,
        apply: {
            detail_types: false,
            min_limit: false,
            max_limit: false,
            trader_commission_rate_for_orders: false,
            use_flexible_trader_commission_for_orders: false,
            trader_commission_tiers_for_orders: false,
            total_service_commission_tiers_for_orders: false,
            total_service_commission_rate_for_orders: false,
            trader_commission_rate_for_payouts: false,
            total_service_commission_rate_for_payouts: false,
            reservation_time_for_orders: false,
            reservation_time_for_payouts: false,
            is_active: false,
            is_payouts_enabled: false,
        },
    };
    errors.value = {};
};

const loadData = () => {
    loading.value = true;
    axios.get(route('admin.payment-gateways.bulk-settings-data'))
        .then((response) => {
            const data = response.data?.data || response.data || {};
            currencies.value = data.currencies || [];
            detail_types.value = data.detailTypes || [];
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const submit = () => {
    if (!form.value.currency || processing.value) {
        return;
    }

    if (
        form.value.apply.use_flexible_trader_commission_for_orders
        && form.value.use_flexible_trader_commission_for_orders
        && !form.value.apply.trader_commission_tiers_for_orders
    ) {
        errors.value = {
            ...errors.value,
            trader_commission_tiers_for_orders: ['При включении гибкой комиссии нужно применить уровни комиссии.'],
        };
        return;
    }

    if (
        form.value.apply.use_flexible_trader_commission_for_orders
        && form.value.use_flexible_trader_commission_for_orders
        && !form.value.apply.total_service_commission_tiers_for_orders
    ) {
        errors.value = {
            ...errors.value,
            total_service_commission_tiers_for_orders: ['При включении гибкой комиссии нужно применить уровни тотал комиссии сервиса.'],
        };
        return;
    }

    processing.value = true;
    errors.value = {};

    const payload = { currency: form.value.currency };
    if (form.value.use_flexible_trader_commission_for_orders) {
        alignAllFlexibleTiersWithLimits();
    }
    Object.entries(form.value.apply).forEach(([field, enabled]) => {
        if (enabled) {
            payload[field] = form.value[field];
        }
    });

    axios.patch(route('admin.payment-gateways.bulk-settings.update'), payload, {
        headers: { 'Accept': 'application/json' },
    })
        .then((response) => {
            processing.value = false;
            if (response.data?.success || response.status === 200) {
                close();
                router.reload({ only: ['paymentGateways'] });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { message: [error.response.data.message] };
            }
        });
};

const isCurrencySelected = computed(() => !!form.value.currency);

const removeCommissionTier = (index) => {
    const tiers = form.value.trader_commission_tiers_for_orders;
    if (tiers.length <= 1) {
        return;
    }

    if (index === 0) {
        tiers[1].from = tiers[0].from;
    } else {
        tiers[index - 1].to = tiers[index].to;
    }

    form.value.trader_commission_tiers_for_orders = tiers.filter((_, i) => i !== index);
    const nextInnerPoints = form.value.trader_commission_tiers_for_orders
        .slice(0, -1)
        .map((tier) => toNumberOrNull(tier?.to))
        .filter((point) => point !== null);
    form.value.total_service_commission_tiers_for_orders = buildContiguousTiers(
        nextInnerPoints,
        form.value.total_service_commission_tiers_for_orders || [],
        form.value.total_service_commission_rate_for_orders
    );
    clearTiersGeneralError("trader_commission_tiers_for_orders");
    clearTiersGeneralError("total_service_commission_tiers_for_orders");
};

const fillSingleTierByLimits = () => {
    const limits = getLimits();
    if (!limits) {
        setTiersGeneralError('Сначала укажите корректные min_limit и max_limit.');
        return;
    }

    clearTiersGeneralError("trader_commission_tiers_for_orders");
    clearTiersGeneralError("total_service_commission_tiers_for_orders");
    form.value.trader_commission_tiers_for_orders = [makeTier(
        limits.min,
        limits.max,
        toNumberOrNull(form.value.trader_commission_rate_for_orders) ?? 0
    )];
    form.value.total_service_commission_tiers_for_orders = [makeTier(
        limits.min,
        limits.max,
        toNumberOrNull(form.value.total_service_commission_rate_for_orders) ?? 0
    )];
};

const splitTierAtPoint = () => {
    const limits = getLimits();
    if (!limits) {
        setTiersGeneralError('Сначала укажите корректные min_limit и max_limit.');
        return;
    }

    alignAllFlexibleTiersWithLimits();
    const tiers = form.value.trader_commission_tiers_for_orders;
    if (!tiers.length) {
        fillSingleTierByLimits();
    }

    const point = toNumberOrNull(splitPoint.value);
    if (point === null || point <= limits.min || point >= limits.max) {
        setTiersGeneralError(`Граница должна быть строго между ${limits.min} и ${limits.max}.`);
        return;
    }

    const splitIndex = tiers.findIndex((tier) => point > tier.from && point < tier.to);
    if (splitIndex === -1) {
        setTiersGeneralError('Эта граница уже существует или выходит за текущие уровни.');
        return;
    }

    const targetTier = tiers[splitIndex];
    const leftTier = makeTier(targetTier.from, point, targetTier.rate);
    const rightTier = makeTier(point, targetTier.to, targetTier.rate);
    tiers.splice(splitIndex, 1, leftTier, rightTier);

    const innerPoints = tiers
        .slice(0, -1)
        .map((tier) => toNumberOrNull(tier?.to))
        .filter((value) => value !== null);
    form.value.total_service_commission_tiers_for_orders = buildContiguousTiers(
        innerPoints,
        form.value.total_service_commission_tiers_for_orders || [],
        form.value.total_service_commission_rate_for_orders
    );

    splitPoint.value = null;
    clearTiersGeneralError("trader_commission_tiers_for_orders");
    clearTiersGeneralError("total_service_commission_tiers_for_orders");
};

watch(
    () => form.value.apply.use_flexible_trader_commission_for_orders,
    (enabled) => {
        if (!enabled) {
            form.value.apply.trader_commission_tiers_for_orders = false;
            form.value.apply.total_service_commission_tiers_for_orders = false;
            return;
        }

        if (!form.value.trader_commission_tiers_for_orders.length) {
            fillSingleTierByLimits();
            return;
        }

        alignAllFlexibleTiersWithLimits();
    }
);

watch(
    () => form.value.use_flexible_trader_commission_for_orders,
    (enabled) => {
        if (enabled) {
            form.value.apply.trader_commission_tiers_for_orders = true;
            form.value.apply.total_service_commission_tiers_for_orders = true;
            if (!form.value.trader_commission_tiers_for_orders.length) {
                fillSingleTierByLimits();
            }
            return;
        }

        if (!enabled) {
            form.value.apply.trader_commission_tiers_for_orders = false;
            form.value.apply.total_service_commission_tiers_for_orders = false;
        }
    }
);

watch(
    () => [form.value.min_limit, form.value.max_limit],
    () => {
        if (!form.value.use_flexible_trader_commission_for_orders) {
            return;
        }

        alignAllFlexibleTiersWithLimits();
    }
);

watch(
    () => paymentGatewayBulkSettingsModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
            loadData();
        } else {
            resetForm();
            currencies.value = [];
            detail_types.value = [];
        }
    }
);
</script>

<template>
    <Modal :show="paymentGatewayBulkSettingsModal.showed" @close="close" maxWidth="4xl">
        <ModalHeader @close="close" title="Массовая настройка платежных методов" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <div v-else class="space-y-6">
                <div v-if="errors.message?.[0]" class="alert alert-error text-sm">
                    {{ errors.message?.[0] }}
                </div>

                <div class="alert alert-info text-sm">
                    1) Выберите валюту. 2) Отметьте нужные настройки. 3) Сохраните.
                </div>

                <div class="rounded-box border border-base-300 p-4">
                    <div class="text-sm font-medium mb-3">
                        Базовые параметры
                    </div>
                    <DropDownWithRadio
                        v-model="form.currency"
                        :items="currencies"
                        value="code"
                        name="code"
                        label="Валюта"
                    />
                    <InputError :message="errors.currency?.[0]" class="mt-2" />
                    <InputHelper v-if="!errors.currency" model-value="Настройки применятся ко всем методам выбранной валюты."></InputHelper>
                    <div v-if="!isCurrencySelected" class="mt-2 text-xs text-error">
                        Сначала выберите валюту — без неё настройки недоступны.
                    </div>
                </div>

                <div class="space-y-6" :class="{ 'opacity-60 pointer-events-none': !isCurrencySelected }">
                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Настройки реквизитов
                        </div>
                        <div class="space-y-2">
                            <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    v-model="form.apply.detail_types"
                                    :disabled="!isCurrencySelected"
                                >
                                <span class="label-text">Тип реквизитов</span>
                            </label>
                            <div v-if="form.apply.detail_types" class="grid gap-2">
                                <DropDownWithCheckbox
                                    v-model="form.detail_types"
                                    :items="detail_types"
                                    value="code"
                                    name="name"
                                    label="Тип реквизитов"
                                />
                                <InputError :message="errors.detail_types?.[0]" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Лимиты операции
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.min_limit"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Минимальная сумма</span>
                                </label>
                                <div v-if="form.apply.min_limit" class="grid gap-2">
                                    <InputLabel
                                        for="min_limit"
                                        :value="'Минимальная сумма в ' + (form.currency || 'RUB')?.toUpperCase()"
                                        :error="!!errors.min_limit?.[0]"
                                    />
                                    <NumberInput
                                        id="min_limit"
                                        v-model="form.min_limit"
                                        class="mt-1 block w-full"
                                        placeholder="0"
                                        :error="!!errors.min_limit?.[0]"
                                    />
                                    <InputError :message="errors.min_limit?.[0]" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.max_limit"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Максимальная сумма</span>
                                </label>
                                <div v-if="form.apply.max_limit" class="grid gap-2">
                                    <InputLabel
                                        for="max_limit"
                                        :value="'Максимальная сумма в ' + (form.currency || 'RUB')?.toUpperCase()"
                                        :error="!!errors.max_limit?.[0]"
                                    />
                                    <NumberInput
                                        id="max_limit"
                                        v-model="form.max_limit"
                                        class="mt-1 block w-full"
                                        placeholder="0"
                                        :error="!!errors.max_limit?.[0]"
                                    />
                                    <InputError :message="errors.max_limit?.[0]" />
                                </div>
                            </div>
                        </div>
                        <div v-if="!errors.min_limit && !errors.max_limit" class="text-xs text-base-content/70 mt-2">
                            Лимит на сумму одной сделки.
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Комиссии по сделкам
                        </div>
                        <div class="mb-4 p-3 rounded-box border border-base-300 space-y-2">
                            <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    v-model="form.apply.use_flexible_trader_commission_for_orders"
                                    :disabled="!isCurrencySelected"
                                >
                                <span class="label-text">Применить режим комиссии трейдера (статическая/гибкая)</span>
                            </label>
                            <div v-if="form.apply.use_flexible_trader_commission_for_orders" class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3">
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-primary"
                                        v-model="form.use_flexible_trader_commission_for_orders"
                                    >
                                    <span class="label-text text-sm">Включить гибкую комиссию трейдера и тотал комиссии сервиса</span>
                                </label>
                                <InputError :message="errors.use_flexible_trader_commission_for_orders?.[0]" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.trader_commission_rate_for_orders"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Комиссия трейдера</span>
                                </label>
                                <div v-if="form.apply.trader_commission_rate_for_orders" class="grid gap-2">
                                    <InputLabel
                                        for="trader_commission_rate_for_orders"
                                        value="Комиссия трейдера в %"
                                        :error="!!errors.trader_commission_rate_for_orders?.[0]"
                                    />
                                    <NumberInput
                                        id="trader_commission_rate_for_orders"
                                        v-model="form.trader_commission_rate_for_orders"
                                        class="mt-1 block w-full"
                                        step="0.1"
                                        placeholder="0.0"
                                        :error="!!errors.trader_commission_rate_for_orders?.[0]"
                                    />
                                    <InputError :message="errors.trader_commission_rate_for_orders?.[0]" />
                                    <InputHelper v-if="!errors.trader_commission_rate_for_orders" model-value="Не может быть больше чем комиссия сервиса. Учитывайте прайм-тайм."></InputHelper>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.total_service_commission_rate_for_orders"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Тотал комиссия сервиса</span>
                                </label>
                                <div v-if="form.apply.total_service_commission_rate_for_orders" class="grid gap-2">
                                    <InputLabel
                                        for="total_service_commission_rate_for_orders"
                                        value="Тотал комиссия сервиса в %"
                                        :error="!!errors.total_service_commission_rate_for_orders?.[0]"
                                    />
                                    <NumberInput
                                        id="total_service_commission_rate_for_orders"
                                        v-model="form.total_service_commission_rate_for_orders"
                                        class="mt-1 block w-full"
                                        step="0.1"
                                        placeholder="0.0"
                                        :error="!!errors.total_service_commission_rate_for_orders?.[0]"
                                    />
                                    <InputError :message="errors.total_service_commission_rate_for_orders?.[0]" />
                                    <InputHelper v-if="!errors.total_service_commission_rate_for_orders" model-value="Доход сервиса = тотал - трейдер."></InputHelper>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="form.apply.use_flexible_trader_commission_for_orders && form.use_flexible_trader_commission_for_orders"
                            class="mt-4 p-3 rounded-box border border-base-300 space-y-3"
                        >
                            <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    v-model="form.apply.trader_commission_tiers_for_orders"
                                    :disabled="!isCurrencySelected || form.use_flexible_trader_commission_for_orders"
                                >
                                <span class="label-text">Применить уровни гибкой комиссии трейдера</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    v-model="form.apply.total_service_commission_tiers_for_orders"
                                    :disabled="!isCurrencySelected || form.use_flexible_trader_commission_for_orders"
                                >
                                <span class="label-text">Применить уровни гибкой тотал комиссии сервиса</span>
                            </label>
                            <div v-if="form.use_flexible_trader_commission_for_orders" class="text-xs text-base-content/70">
                                При включенной гибкой комиссии уровни трейдера и тотал комиссии применяются обязательно.
                            </div>

                            <div v-if="form.apply.trader_commission_tiers_for_orders || form.apply.total_service_commission_tiers_for_orders" class="space-y-3">
                                <div class="alert alert-warning text-xs">
                                    Для массовой гибкой комиссии нужно одновременно применить min_limit и max_limit.
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
                                    <div>
                                        <InputLabel value="Разделить диапазон по границе" />
                                        <NumberInput v-model="splitPoint" placeholder="2000" />
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline" @click="splitTierAtPoint">
                                        Разделить
                                    </button>
                                    <button type="button" class="btn btn-xs" @click="fillSingleTierByLimits">
                                        1 уровень из лимитов
                                    </button>
                                </div>
                                <div
                                    v-for="(tier, index) in form.trader_commission_tiers_for_orders"
                                    :key="`bulk-tier-${index}`"
                                    class="grid grid-cols-1 lg:grid-cols-5 gap-2 items-end"
                                >
                                    <div>
                                        <InputLabel :value="`От (уровень #${index + 1})`" />
                                        <div class="input input-bordered w-full">{{ tier.from }}</div>
                                        <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.from`]?.[0]" class="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel value="До" />
                                        <div class="input input-bordered w-full">{{ tier.to }}</div>
                                        <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.to`]?.[0]" class="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel value="Трейдер %" />
                                        <NumberInput v-model="tier.rate" step="0.1" placeholder="7" />
                                        <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.rate`]?.[0]" class="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel value="Тотал %" />
                                        <NumberInput v-model="form.total_service_commission_tiers_for_orders[index].rate" step="0.1" placeholder="10" />
                                        <InputError :message="errors[`total_service_commission_tiers_for_orders.${index}.rate`]?.[0]" class="mt-1" />
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-error btn-outline"
                                            @click="removeCommissionTier(index)"
                                            :disabled="form.trader_commission_tiers_for_orders.length <= 1"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                                <InputError :message="errors.trader_commission_tiers_for_orders?.[0]" />
                                <InputError :message="errors.total_service_commission_tiers_for_orders?.[0]" />
                                <InputHelper model-value="Диапазоны для трейдера и тотал комиссии сервиса должны быть подряд и совпадать: от min_limit до max_limit без разрывов." />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Комиссии по выплатам
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.trader_commission_rate_for_payouts"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Комиссия трейдера</span>
                                </label>
                                <div v-if="form.apply.trader_commission_rate_for_payouts" class="grid gap-2">
                                    <InputLabel
                                        for="trader_commission_rate_for_payouts"
                                        value="Комиссия трейдера (выплаты) в %"
                                        :error="!!errors.trader_commission_rate_for_payouts?.[0]"
                                    />
                                    <NumberInput
                                        id="trader_commission_rate_for_payouts"
                                        v-model="form.trader_commission_rate_for_payouts"
                                        class="mt-1 block w-full"
                                        step="0.1"
                                        placeholder="0.0"
                                        :error="!!errors.trader_commission_rate_for_payouts?.[0]"
                                    />
                                    <InputError :message="errors.trader_commission_rate_for_payouts?.[0]" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.total_service_commission_rate_for_payouts"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Тотал комиссия сервиса</span>
                                </label>
                                <div v-if="form.apply.total_service_commission_rate_for_payouts" class="grid gap-2">
                                    <InputLabel
                                        for="total_service_commission_rate_for_payouts"
                                        value="Тотал комиссия сервиса (выплаты) в %"
                                        :error="!!errors.total_service_commission_rate_for_payouts?.[0]"
                                    />
                                    <NumberInput
                                        id="total_service_commission_rate_for_payouts"
                                        v-model="form.total_service_commission_rate_for_payouts"
                                        class="mt-1 block w-full"
                                        step="0.1"
                                        placeholder="0.0"
                                        :error="!!errors.total_service_commission_rate_for_payouts?.[0]"
                                    />
                                    <InputError :message="errors.total_service_commission_rate_for_payouts?.[0]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Временные ограничения
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.reservation_time_for_orders"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Время на сделку</span>
                                </label>
                                <div v-if="form.apply.reservation_time_for_orders" class="grid gap-2">
                                    <InputLabel
                                        for="reservation_time_for_orders"
                                        value="Время на сделку"
                                        :error="!!errors.reservation_time_for_orders?.[0]"
                                    />
                                    <NumberInput
                                        id="reservation_time_for_orders"
                                        v-model="form.reservation_time_for_orders"
                                        class="mt-1 block w-full"
                                        placeholder="0"
                                        :error="!!errors.reservation_time_for_orders?.[0]"
                                    />
                                    <InputError :message="errors.reservation_time_for_orders?.[0]" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.reservation_time_for_payouts"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Время на выплату</span>
                                </label>
                                <div v-if="form.apply.reservation_time_for_payouts" class="grid gap-2">
                                    <InputLabel
                                        for="reservation_time_for_payouts"
                                        value="Время на выплату"
                                        :error="!!errors.reservation_time_for_payouts?.[0]"
                                    />
                                    <NumberInput
                                        id="reservation_time_for_payouts"
                                        v-model="form.reservation_time_for_payouts"
                                        class="mt-1 block w-full"
                                        placeholder="0"
                                        :error="!!errors.reservation_time_for_payouts?.[0]"
                                    />
                                    <InputError :message="errors.reservation_time_for_payouts?.[0]" />
                                </div>
                            </div>
                        </div>
                        <div v-if="!errors.reservation_time_for_orders && !errors.reservation_time_for_payouts" class="text-xs text-base-content/70 mt-2">
                            Указывайте значения в минутах.
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Статусы и доступность
                        </div>
                        <div class="space-y-3">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.is_active"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Метод активен</span>
                                </label>
                                <label v-if="form.apply.is_active" class="label cursor-pointer justify-start gap-3">
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-primary"
                                        v-model="form.is_active"
                                        :disabled="!isCurrencySelected || !form.apply.is_active"
                                    >
                                    <span class="label-text text-sm">Метод активен</span>
                                </label>
                                <InputError :message="errors.is_active?.[0]" />
                            </div>

                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm"
                                        v-model="form.apply.is_payouts_enabled"
                                        :disabled="!isCurrencySelected"
                                    >
                                    <span class="label-text">Выплаты доступны по методу</span>
                                </label>
                                <label v-if="form.apply.is_payouts_enabled" class="label cursor-pointer justify-start gap-3">
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-primary"
                                        v-model="form.is_payouts_enabled"
                                        :disabled="!isCurrencySelected || !form.apply.is_payouts_enabled"
                                    >
                                    <span class="label-text text-sm">Выплаты доступны по методу</span>
                                </label>
                                <InputError :message="errors.is_payouts_enabled?.[0]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">
                Отмена
            </button>
            <button
                @click="submit"
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing || !isCurrencySelected }"
                :disabled="processing || !isCurrencySelected"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
