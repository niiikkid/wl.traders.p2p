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
import UserFormSection from "@/Modals/User/Partials/UserFormSection.vue";
import UserFormToggle from "@/Modals/User/Partials/UserFormToggle.vue";
import BulkApplyField from "@/Modals/PaymentGateway/Partials/BulkApplyField.vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { isRemovedDetailType, stripRemovedDetailTypes } from "@/utils/paymentDetail.js";
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';

const filterDetailTypeOptions = (items) => (items || []).filter((item) => !isRemovedDetailType(item.code));

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
            detail_types.value = filterDetailTypeOptions(data.detailTypes || []);
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
            payload[field] = field === 'detail_types'
                ? stripRemovedDetailTypes(form.value[field])
                : form.value[field];
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

const currencyLabel = computed(() => (form.value.currency || 'RUB').toString().toUpperCase());

const appliedFieldsCount = computed(() => Object.values(form.value.apply).filter(Boolean).length);

const showFlexibleTiers = computed(() => (
    form.value.apply.use_flexible_trader_commission_for_orders
    && form.value.use_flexible_trader_commission_for_orders
    && (form.value.apply.trader_commission_tiers_for_orders || form.value.apply.total_service_commission_tiers_for_orders)
));

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
            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md" />
            </div>
            <form v-else class="space-y-4" @submit.prevent="submit">
                <div v-if="errors.message?.[0]" class="alert alert-error text-sm">
                    {{ errors.message[0] }}
                </div>

                <div class="grid grid-cols-1 gap-2 rounded-box border border-info/30 bg-info/10 px-3 py-2.5 text-xs sm:grid-cols-3 sm:text-sm">
                    <div class="flex items-center gap-2">
                        <span class="badge badge-info badge-sm shrink-0">1</span>
                        <span>Выберите валюту</span>
                    </div>
                    <div class="flex items-center gap-2" :class="{ 'opacity-50': !isCurrencySelected }">
                        <span class="badge badge-sm shrink-0" :class="isCurrencySelected ? 'badge-info' : 'badge-ghost'">2</span>
                        <span>Отметьте поля для изменения</span>
                    </div>
                    <div class="flex items-center gap-2" :class="{ 'opacity-50': !isCurrencySelected || !appliedFieldsCount }">
                        <span class="badge badge-sm shrink-0" :class="appliedFieldsCount ? 'badge-info' : 'badge-ghost'">3</span>
                        <span>Сохраните изменения</span>
                    </div>
                </div>

                <UserFormSection title="Валюта" description="Настройки применятся ко всем методам выбранной валюты." compact>
                    <DropDownWithRadio
                        v-model="form.currency"
                        :items="currencies"
                        value="code"
                        name="code"
                        label="Валюта"
                        currency-icons
                    />
                    <InputError :message="errors.currency?.[0]" class="mt-1" />
                    <p v-if="!isCurrencySelected && !errors.currency" class="text-xs text-warning">
                        Сначала выберите валюту — без неё остальные настройки недоступны.
                    </p>
                    <div v-else-if="isCurrencySelected" class="flex flex-wrap items-center gap-2">
                        <CurrencyDisplay
                            :currency="form.currency"
                            :show-label="true"
                            size="sm"
                            :icon-size="18"
                        />
                        <span v-if="appliedFieldsCount" class="text-xs text-base-content/60">
                            К применению: {{ appliedFieldsCount }} {{ appliedFieldsCount === 1 ? 'поле' : appliedFieldsCount < 5 ? 'поля' : 'полей' }}
                        </span>
                    </div>
                </UserFormSection>

                <div class="space-y-4" :class="{ 'pointer-events-none opacity-60': !isCurrencySelected }">
                    <UserFormSection title="Реквизиты" compact>
                        <BulkApplyField
                            v-model="form.apply.detail_types"
                            label="Тип реквизитов"
                            :disabled="!isCurrencySelected"
                        >
                            <DropDownWithCheckbox
                                v-model="form.detail_types"
                                :items="detail_types"
                                value="code"
                                name="name"
                                label="Тип реквизитов"
                            />
                            <InputError :message="errors.detail_types?.[0]" class="mt-1" />
                        </BulkApplyField>
                    </UserFormSection>

                    <UserFormSection
                        title="Лимиты и время"
                        description="Лимит на сумму одной сделки. Время резервирования — в минутах."
                        compact
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <BulkApplyField
                                v-model="form.apply.min_limit"
                                label="Мин. сумма"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="min_limit"
                                    :value="'Мин. сумма, ' + currencyLabel"
                                    :error="!!errors.min_limit?.[0]"
                                />
                                <NumberInput
                                    id="min_limit"
                                    v-model="form.min_limit"
                                    class="mt-1 block w-full"
                                    placeholder="0"
                                    :error="!!errors.min_limit?.[0]"
                                />
                                <InputError :message="errors.min_limit?.[0]" class="mt-1" />
                            </BulkApplyField>

                            <BulkApplyField
                                v-model="form.apply.max_limit"
                                label="Макс. сумма"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="max_limit"
                                    :value="'Макс. сумма, ' + currencyLabel"
                                    :error="!!errors.max_limit?.[0]"
                                />
                                <NumberInput
                                    id="max_limit"
                                    v-model="form.max_limit"
                                    class="mt-1 block w-full"
                                    placeholder="0"
                                    :error="!!errors.max_limit?.[0]"
                                />
                                <InputError :message="errors.max_limit?.[0]" class="mt-1" />
                            </BulkApplyField>

                            <BulkApplyField
                                v-model="form.apply.reservation_time_for_orders"
                                label="Время на сделку"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="reservation_time_for_orders"
                                    value="Время на сделку, мин"
                                    :error="!!errors.reservation_time_for_orders?.[0]"
                                />
                                <NumberInput
                                    id="reservation_time_for_orders"
                                    v-model="form.reservation_time_for_orders"
                                    class="mt-1 block w-full"
                                    placeholder="0"
                                    :error="!!errors.reservation_time_for_orders?.[0]"
                                />
                                <InputError :message="errors.reservation_time_for_orders?.[0]" class="mt-1" />
                            </BulkApplyField>

                            <BulkApplyField
                                v-model="form.apply.reservation_time_for_payouts"
                                label="Время на выплату"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="reservation_time_for_payouts"
                                    value="Время на выплату, мин"
                                    :error="!!errors.reservation_time_for_payouts?.[0]"
                                />
                                <NumberInput
                                    id="reservation_time_for_payouts"
                                    v-model="form.reservation_time_for_payouts"
                                    class="mt-1 block w-full"
                                    placeholder="0"
                                    :error="!!errors.reservation_time_for_payouts?.[0]"
                                />
                                <InputError :message="errors.reservation_time_for_payouts?.[0]" class="mt-1" />
                            </BulkApplyField>
                        </div>
                    </UserFormSection>

                    <UserFormSection title="Комиссии по сделкам" compact>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <BulkApplyField
                                v-model="form.apply.trader_commission_rate_for_orders"
                                label="Трейдер % (сделки)"
                                :disabled="!isCurrencySelected"
                            >
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
                                <InputError :message="errors.trader_commission_rate_for_orders?.[0]" class="mt-1" />
                                <InputHelper
                                    v-if="!errors.trader_commission_rate_for_orders"
                                    model-value="Не больше комиссии сервиса. Учитывайте прайм-тайм."
                                />
                            </BulkApplyField>

                            <BulkApplyField
                                v-model="form.apply.total_service_commission_rate_for_orders"
                                label="Тотал % (сделки)"
                                :disabled="!isCurrencySelected"
                            >
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
                                <InputError :message="errors.total_service_commission_rate_for_orders?.[0]" class="mt-1" />
                                <InputHelper
                                    v-if="!errors.total_service_commission_rate_for_orders"
                                    model-value="Доход сервиса = тотал − трейдер."
                                />
                            </BulkApplyField>
                        </div>

                        <div class="mt-3">
                            <BulkApplyField
                                v-model="form.apply.use_flexible_trader_commission_for_orders"
                                label="Режим комиссии (статическая / гибкая)"
                                :disabled="!isCurrencySelected"
                            >
                                <UserFormToggle
                                    v-model="form.use_flexible_trader_commission_for_orders"
                                    label="Гибкая комиссия по сумме сделки"
                                />
                                <InputError :message="errors.use_flexible_trader_commission_for_orders?.[0]" class="mt-1" />
                            </BulkApplyField>
                        </div>

                        <div
                            v-if="form.apply.use_flexible_trader_commission_for_orders && form.use_flexible_trader_commission_for_orders"
                            class="mt-3 space-y-3 rounded-box border border-base-300 bg-base-200/30 p-3"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium">Уровни гибкой комиссии</span>
                                <span class="badge badge-primary badge-sm">обязательно</span>
                            </div>
                            <p class="text-xs text-base-content/60">
                                При гибкой комиссии уровни трейдера и тотал сервиса применяются вместе с лимитами min/max.
                            </p>

                            <div v-if="showFlexibleTiers" class="space-y-3">
                                <div class="alert alert-warning py-2 text-xs">
                                    Для гибкой комиссии одновременно примените min_limit и max_limit.
                                </div>

                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-0 flex-1 sm:max-w-xs">
                                        <InputLabel value="Граница диапазона" />
                                        <NumberInput v-model="splitPoint" placeholder="2000" />
                                    </div>
                                    <div class="join">
                                        <button type="button" class="btn btn-xs join-item btn-outline" @click="splitTierAtPoint">
                                            Разделить
                                        </button>
                                        <button type="button" class="btn btn-xs join-item" @click="fillSingleTierByLimits">
                                            1 уровень
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-for="(tier, index) in form.trader_commission_tiers_for_orders"
                                    :key="`bulk-tier-${index}`"
                                    class="rounded-lg border border-base-300/60 bg-base-100 p-2"
                                >
                                    <div class="mb-1.5 text-xs font-medium text-base-content/60">
                                        Уровень #{{ index + 1 }}
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-5">
                                        <div>
                                            <InputLabel value="От" />
                                            <div class="input input-bordered w-full">{{ tier.from }}</div>
                                            <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.from`]?.[0]" class="mt-0.5" />
                                        </div>
                                        <div>
                                            <InputLabel value="До" />
                                            <div class="input input-bordered w-full">{{ tier.to }}</div>
                                            <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.to`]?.[0]" class="mt-0.5" />
                                        </div>
                                        <div>
                                            <InputLabel value="Трейдер %" />
                                            <NumberInput v-model="tier.rate" step="0.1" placeholder="7" />
                                            <InputError :message="errors[`trader_commission_tiers_for_orders.${index}.rate`]?.[0]" class="mt-0.5" />
                                        </div>
                                        <div>
                                            <InputLabel value="Тотал %" />
                                            <NumberInput
                                                v-model="form.total_service_commission_tiers_for_orders[index].rate"
                                                step="0.1"
                                                placeholder="10"
                                            />
                                            <InputError :message="errors[`total_service_commission_tiers_for_orders.${index}.rate`]?.[0]" class="mt-0.5" />
                                        </div>
                                        <div class="col-span-2 flex items-end justify-end sm:col-span-4 xl:col-span-1">
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-error btn-outline"
                                                :disabled="form.trader_commission_tiers_for_orders.length <= 1"
                                                @click="removeCommissionTier(index)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <InputError :message="errors.trader_commission_tiers_for_orders?.[0]" />
                                <InputError :message="errors.total_service_commission_tiers_for_orders?.[0]" />
                                <InputHelper model-value="Диапазоны трейдера и сервиса должны идти подряд от min_limit до max_limit без разрывов." />
                            </div>
                        </div>
                    </UserFormSection>

                    <UserFormSection title="Комиссии по выплатам" compact>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <BulkApplyField
                                v-model="form.apply.trader_commission_rate_for_payouts"
                                label="Трейдер % (выплаты)"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="trader_commission_rate_for_payouts"
                                    value="Комиссия трейдера в %"
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
                                <InputError :message="errors.trader_commission_rate_for_payouts?.[0]" class="mt-1" />
                            </BulkApplyField>

                            <BulkApplyField
                                v-model="form.apply.total_service_commission_rate_for_payouts"
                                label="Тотал % (выплаты)"
                                :disabled="!isCurrencySelected"
                            >
                                <InputLabel
                                    for="total_service_commission_rate_for_payouts"
                                    value="Тотал комиссия сервиса в %"
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
                                <InputError :message="errors.total_service_commission_rate_for_payouts?.[0]" class="mt-1" />
                            </BulkApplyField>
                        </div>
                    </UserFormSection>

                    <UserFormSection title="Доступность" compact>
                        <BulkApplyField
                            v-model="form.apply.is_active"
                            label="Статус метода"
                            :disabled="!isCurrencySelected"
                        >
                            <UserFormToggle
                                v-model="form.is_active"
                                label="Метод активен"
                                :disabled="!isCurrencySelected || !form.apply.is_active"
                            />
                            <InputError :message="errors.is_active?.[0]" class="mt-1" />
                        </BulkApplyField>
                    </UserFormSection>
                </div>
            </form>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing || !isCurrencySelected || !appliedFieldsCount }"
                :disabled="processing || !isCurrencySelected || !appliedFieldsCount"
                @click="submit"
            >
                <span v-if="processing" class="loading loading-spinner loading-xs" />
                <span v-else>Сохранить</span>
                <span v-if="appliedFieldsCount && isCurrencySelected && !processing" class="badge badge-sm badge-neutral ml-1">
                    {{ appliedFieldsCount }}
                </span>
            </button>
        </ModalFooter>
    </Modal>
</template>
