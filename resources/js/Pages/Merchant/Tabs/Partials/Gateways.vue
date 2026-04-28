<script setup>
import InputError from "@/Components/InputError.vue";
import InputHelper from "@/Components/InputHelper.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import {computed, ref, watch} from "vue";

const emit = defineEmits(['updated']);

const props = defineProps({
    merchantId: {
        type: Number,
        required: true,
    },
    paymentGateways: {
        type: Object,
        default: () => ({ data: [] }),
    },
    detailTypes: {
        type: Array,
        default: () => [],
    },
    commissionSettings: {
        type: Array,
        default: () => [],
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

const isEditMode = ref(false);
const processing = ref(false);
const errors = ref({});
const localCommissionSettings = ref([]);
const selectedPairKey = ref('');
const splitPoints = ref({});
const splitErrors = ref({});
const expandedSets = ref({});
const toNumberOrNull = (value) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
};

const detailTypeMap = computed(() => {
    const map = {};

    (props.detailTypes ?? []).forEach((detailType) => {
        if (!detailType?.code) {
            return;
        }

        map[detailType.code] = detailType.name ?? detailType.code;
    });

    return map;
});

const pairCombinations = computed(() => {
    const pairs = [];
    const unique = new Set();

    (props.paymentGateways?.data ?? []).forEach((gateway) => {
        const currency = (gateway.currency ?? '').toLowerCase();
        const detailTypes = Array.isArray(gateway.detail_types) ? gateway.detail_types : [];

        detailTypes.forEach((detailType) => {
            const detailCode = typeof detailType === 'string'
                ? detailType
                : (detailType?.value ?? detailType?.code ?? '');

            if (!currency || !detailCode) {
                return;
            }

            const key = `${currency}|${detailCode}`;

            if (unique.has(key)) {
                return;
            }

            unique.add(key);
            pairs.push({
                key,
                currency,
                detail_type: detailCode,
            });
        });
    });

    return pairs.sort((left, right) => {
        if (left.currency === right.currency) {
            return left.detail_type.localeCompare(right.detail_type);
        }

        return left.currency.localeCompare(right.currency);
    });
});

const makeTier = (from = null, to = null, rate = null) => ({ from, to, rate });

const normalizeTierList = (tiers) => {
    if (!Array.isArray(tiers)) {
        return [];
    }

    return tiers.map((tier) => makeTier(
        tier?.from ?? null,
        tier?.to ?? null,
        tier?.rate ?? null
    ));
};

const hasDetailType = (gateway, detailType) => {
    const gatewayDetailTypes = Array.isArray(gateway?.detail_types) ? gateway.detail_types : [];

    return gatewayDetailTypes.some((item) => {
        const code = typeof item === "string" ? item : (item?.value ?? item?.code ?? "");
        return code === detailType;
    });
};

const getPairLimitsByValues = (currency, detailType) => {
    let min = null;
    let max = null;

    (props.paymentGateways?.data ?? []).forEach((gateway) => {
        if ((gateway?.currency ?? "").toLowerCase() !== (currency ?? "").toLowerCase()) {
            return;
        }

        if (!hasDetailType(gateway, detailType)) {
            return;
        }

        const gatewayMin = toNumberOrNull(gateway?.min_limit);
        const gatewayMax = toNumberOrNull(gateway?.max_limit);

        if (gatewayMin !== null) {
            min = min === null ? gatewayMin : Math.min(min, gatewayMin);
        }

        if (gatewayMax !== null) {
            max = max === null ? gatewayMax : Math.max(max, gatewayMax);
        }
    });

    if (min === null || max === null || min >= max) {
        return null;
    }

    return { min, max };
};

const getPairLimits = (setting) => {
    return getPairLimitsByValues(setting?.currency, setting?.detail_type);
};

const getPairGateways = (setting) => {
    return (props.paymentGateways?.data ?? []).filter((gateway) => {
        if ((gateway?.currency ?? "").toLowerCase() !== (setting?.currency ?? "").toLowerCase()) {
            return false;
        }

        return hasDetailType(gateway, setting?.detail_type);
    });
};

const getPairLimitDetails = (setting) => {
    const gateways = getPairGateways(setting);

    if (!gateways.length) {
        return null;
    }

    let minGateway = null;
    let maxGateway = null;

    gateways.forEach((gateway) => {
        const minLimit = toNumberOrNull(gateway?.min_limit);
        const maxLimit = toNumberOrNull(gateway?.max_limit);

        if (minLimit !== null && (!minGateway || minLimit < minGateway.value)) {
            minGateway = {
                value: minLimit,
                name: gateway?.original_name ?? gateway?.name ?? `#${gateway?.id}`,
            };
        }

        if (maxLimit !== null && (!maxGateway || maxLimit > maxGateway.value)) {
            maxGateway = {
                value: maxLimit,
                name: gateway?.original_name ?? gateway?.name ?? `#${gateway?.id}`,
            };
        }
    });

    if (!minGateway || !maxGateway) {
        return null;
    }

    return {
        minGateway,
        maxGateway,
        gateways,
    };
};

const ensureTotalTiersLength = (setting) => {
    const traderTiers = setting.trader_commission_tiers_for_orders ?? [];
    const totalTiers = setting.total_service_commission_tiers_for_orders ?? [];

    if (totalTiers.length >= traderTiers.length) {
        return;
    }

    for (let i = totalTiers.length; i < traderTiers.length; i += 1) {
        totalTiers.push(makeTier(
            traderTiers[i]?.from ?? null,
            traderTiers[i]?.to ?? null,
            setting.total_service_commission_rate_for_orders ?? null,
        ));
    }
};

const applyFixedLimitsToTiers = (setting) => {
    if (!setting?.use_flexible_trader_commission_for_orders) {
        return;
    }

    const limits = getPairLimits(setting);

    if (!limits) {
        return;
    }

    ensureTotalTiersLength(setting);

    const traderTiers = setting.trader_commission_tiers_for_orders ?? [];
    const totalTiers = setting.total_service_commission_tiers_for_orders ?? [];

    if (!traderTiers.length) {
        traderTiers.push(makeTier(
            limits.min,
            limits.max,
            setting.trader_commission_rate_for_orders ?? 0
        ));
        totalTiers.push(makeTier(
            limits.min,
            limits.max,
            setting.total_service_commission_rate_for_orders ?? 0
        ));
        return;
    }

    traderTiers[0].from = limits.min;
    traderTiers[traderTiers.length - 1].to = limits.max;
    totalTiers[0].from = limits.min;
    totalTiers[traderTiers.length - 1].to = limits.max;
};

const getSettingKey = (setting) => `${setting?.currency}|${setting?.detail_type}`;

const isSetExpanded = (setting) => {
    const key = getSettingKey(setting);
    return !!expandedSets.value[key];
};

const toggleSetExpanded = (setting) => {
    const key = getSettingKey(setting);
    expandedSets.value = {
        ...expandedSets.value,
        [key]: !expandedSets.value[key],
    };
};

const setSplitError = (setting, message = '') => {
    const key = getSettingKey(setting);
    splitErrors.value = {
        ...splitErrors.value,
        [key]: message,
    };
};

const setSingleTierFromLimits = (setting) => {
    const limits = getPairLimits(setting);

    if (!limits) {
        return;
    }

    setting.trader_commission_tiers_for_orders = [makeTier(
        limits.min,
        limits.max,
        setting.trader_commission_rate_for_orders ?? 0
    )];
    setting.total_service_commission_tiers_for_orders = [makeTier(
        limits.min,
        limits.max,
        setting.total_service_commission_rate_for_orders ?? 0
    )];
    setSplitError(setting, '');
};

const getSetShortInfo = (setting) => {
    const hasFixed = setting.trader_commission_rate_for_orders !== null
        && setting.trader_commission_rate_for_orders !== ""
        && setting.total_service_commission_rate_for_orders !== null
        && setting.total_service_commission_rate_for_orders !== "";
    const hasFlexible = !!setting.use_flexible_trader_commission_for_orders
        && (setting.trader_commission_tiers_for_orders?.length ?? 0) > 0;

    if (!hasFixed && !hasFlexible) {
        return 'Не настроен';
    }

    if (hasFixed && !hasFlexible) {
        return `Фикс: трейдер ${setting.trader_commission_rate_for_orders}% / тотал ${setting.total_service_commission_rate_for_orders}%`;
    }

    if (!hasFixed && hasFlexible) {
        return `Гибкая: уровней ${setting.trader_commission_tiers_for_orders.length}`;
    }

    return `Фикс + гибкая: уровней ${setting.trader_commission_tiers_for_orders.length}`;
};

const initCommissionSettings = () => {
    const settings = [];

    (props.commissionSettings ?? []).forEach((item) => {
        const currency = (item?.currency ?? '').toLowerCase();
        const detailType = item?.detail_type ?? '';

        if (!currency || !detailType) {
            return;
        }

        const normalized = {
            currency,
            detail_type: detailType,
            trader_commission_rate_for_orders: item?.trader_commission_rate_for_orders ?? null,
            total_service_commission_rate_for_orders: item?.total_service_commission_rate_for_orders ?? null,
            use_flexible_trader_commission_for_orders: !!item?.use_flexible_trader_commission_for_orders,
            trader_commission_tiers_for_orders: normalizeTierList(item?.trader_commission_tiers_for_orders),
            total_service_commission_tiers_for_orders: normalizeTierList(item?.total_service_commission_tiers_for_orders),
        };
        ensureTotalTiersLength(normalized);
        applyFixedLimitsToTiers(normalized);

        settings.push(normalized);
    });

    localCommissionSettings.value = settings;
};

watch(
    () => [props.paymentGateways, props.commissionSettings, props.detailTypes],
    () => {
        initCommissionSettings();
    },
    { immediate: true, deep: true }
);

const getLabel = (setting) => {
    const currency = (setting?.currency ?? '').toUpperCase();
    const detailTypeLabel = detailTypeMap.value[setting?.detail_type] ?? setting?.detail_type ?? '-';
    return `${currency} / ${detailTypeLabel}`;
};

const configuredPairKeys = computed(() => {
    return new Set(
        localCommissionSettings.value.map((setting) => `${setting.currency}|${setting.detail_type}`)
    );
});

const availablePairs = computed(() => {
    return pairCombinations.value.filter((pair) => !configuredPairKeys.value.has(pair.key));
});

const addCommissionSet = () => {
    if (!selectedPairKey.value) {
        return;
    }

    const pair = pairCombinations.value.find((item) => item.key === selectedPairKey.value);

    if (!pair) {
        return;
    }

    localCommissionSettings.value.push({
        currency: pair.currency,
        detail_type: pair.detail_type,
        trader_commission_rate_for_orders: null,
        total_service_commission_rate_for_orders: null,
        use_flexible_trader_commission_for_orders: false,
        trader_commission_tiers_for_orders: [],
        total_service_commission_tiers_for_orders: [],
    });

    selectedPairKey.value = '';
};

const removeCommissionSet = (index) => {
    localCommissionSettings.value.splice(index, 1);
};

const splitTierAtPoint = (setting) => {
    const limits = getPairLimits(setting);

    if (!limits) {
        return;
    }

    const traderTiers = setting.trader_commission_tiers_for_orders ?? [];
    const totalTiers = setting.total_service_commission_tiers_for_orders ?? [];
    if (!traderTiers.length) {
        setSingleTierFromLimits(setting);
    }

    const key = getSettingKey(setting);
    const splitPoint = toNumberOrNull(splitPoints.value[key]);

    if (splitPoint === null || splitPoint <= limits.min || splitPoint >= limits.max) {
        setSplitError(setting, `Граница должна быть строго между ${limits.min} и ${limits.max}.`);
        return;
    }

    let targetIndex = -1;

    traderTiers.forEach((tier, index) => {
        const from = toNumberOrNull(tier?.from);
        const to = toNumberOrNull(tier?.to);

        if (from === null || to === null || to <= from) {
            return;
        }

        if (splitPoint > from && splitPoint < to) {
            targetIndex = index;
        }
    });

    if (targetIndex === -1) {
        setSplitError(setting, 'Эта граница уже существует или выходит за текущие уровни.');
        return;
    }

    const targetTraderTier = traderTiers[targetIndex];
    const targetTotalTier = totalTiers[targetIndex] ?? makeTier(
        targetTraderTier.from,
        targetTraderTier.to,
        setting.total_service_commission_rate_for_orders ?? 0
    );

    const from = Number(targetTraderTier.from);
    const to = Number(targetTraderTier.to);

    const leftTraderTier = makeTier(from, splitPoint, targetTraderTier.rate ?? setting.trader_commission_rate_for_orders ?? 0);
    const rightTraderTier = makeTier(splitPoint, to, targetTraderTier.rate ?? setting.trader_commission_rate_for_orders ?? 0);
    const leftTotalTier = makeTier(from, splitPoint, targetTotalTier.rate ?? setting.total_service_commission_rate_for_orders ?? 0);
    const rightTotalTier = makeTier(splitPoint, to, targetTotalTier.rate ?? setting.total_service_commission_rate_for_orders ?? 0);

    traderTiers.splice(targetIndex, 1, leftTraderTier, rightTraderTier);
    totalTiers.splice(targetIndex, 1, leftTotalTier, rightTotalTier);

    applyFixedLimitsToTiers(setting);
    splitPoints.value[key] = '';
    setSplitError(setting, '');
};

const removeTier = (setting, index) => {
    if ((setting.trader_commission_tiers_for_orders?.length ?? 0) <= 1) {
        return;
    }

    setting.trader_commission_tiers_for_orders.splice(index, 1);
    setting.total_service_commission_tiers_for_orders.splice(index, 1);
    applyFixedLimitsToTiers(setting);
};

const toggleFlexible = (setting, enabled) => {
    setting.use_flexible_trader_commission_for_orders = enabled;

    if (!enabled) {
        setSplitError(setting, '');
        return;
    }

    if ((setting.trader_commission_tiers_for_orders?.length ?? 0) === 0) {
        setSingleTierFromLimits(setting);
    } else {
        applyFixedLimitsToTiers(setting);
    }
};

const submit = () => {
    if (processing.value || !props.merchantId || !props.isAdmin) {
        return;
    }

    processing.value = true;
    errors.value = {};

    localCommissionSettings.value.forEach((setting) => {
        applyFixedLimitsToTiers(setting);
    });

    const payload = localCommissionSettings.value
        .filter((setting) => {
            const hasFixed = setting.trader_commission_rate_for_orders !== null
                && setting.trader_commission_rate_for_orders !== ""
                && setting.total_service_commission_rate_for_orders !== null
                && setting.total_service_commission_rate_for_orders !== "";
            const hasFlexible = setting.use_flexible_trader_commission_for_orders
                && (setting.trader_commission_tiers_for_orders?.length ?? 0) > 0
                && (setting.total_service_commission_tiers_for_orders?.length ?? 0) > 0;

            return hasFixed || hasFlexible;
        })
        .map((setting) => ({
            currency: setting.currency,
            detail_type: setting.detail_type,
            trader_commission_rate_for_orders: setting.trader_commission_rate_for_orders === "" ? null : setting.trader_commission_rate_for_orders,
            total_service_commission_rate_for_orders: setting.total_service_commission_rate_for_orders === "" ? null : setting.total_service_commission_rate_for_orders,
            use_flexible_trader_commission_for_orders: !!setting.use_flexible_trader_commission_for_orders,
            trader_commission_tiers_for_orders: setting.use_flexible_trader_commission_for_orders ? setting.trader_commission_tiers_for_orders : [],
            total_service_commission_tiers_for_orders: setting.use_flexible_trader_commission_for_orders ? setting.total_service_commission_tiers_for_orders : [],
        }));

    const prefix = props.isAdmin ? 'admin.' : '';

    axios.patch(route(`${prefix}merchants.commission-settings.update`, props.merchantId), {
        commission_settings: payload,
    }, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        emit('updated', data);
        isEditMode.value = false;
    }).catch((error) => {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    }).finally(() => {
        processing.value = false;
    });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <InputHelper model-value="Комиссия задается по уникальной паре: валюта + тип реквизита. Если пара не настроена, применяются комиссии платежного метода." />
            <div v-if="isAdmin">
                <button
                    v-if="!isEditMode"
                    type="button"
                    class="btn btn-xs btn-outline btn-primary"
                    @click="isEditMode = true"
                >
                    Изменить
                </button>
                <button
                    v-else
                    type="button"
                    class="btn btn-xs btn-success"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                    @click="submit"
                >
                    Сохранить
                </button>
            </div>
        </div>

        <div
            v-if="isAdmin && isEditMode && availablePairs.length"
            class="rounded-box border border-base-300 p-3 flex flex-col md:flex-row md:items-end gap-3"
        >
            <div class="flex-1">
                <InputLabel value="Добавить набор комиссии" />
                <select v-model="selectedPairKey" class="select select-bordered select-sm mt-1 w-full text-xs">
                    <option value="">Выберите валюту и тип реквизита</option>
                    <option
                        v-for="pair in availablePairs"
                        :key="pair.key"
                        :value="pair.key"
                    >
                        {{ pair.currency.toUpperCase() }} / {{ detailTypeMap[pair.detail_type] ?? pair.detail_type }}
                    </option>
                </select>
            </div>
            <button
                type="button"
                class="btn btn-sm btn-primary shrink-0"
                :disabled="!selectedPairKey"
                @click="addCommissionSet"
            >
                Добавить
            </button>
        </div>

        <div v-if="pairCombinations.length === 0" class="rounded-box border border-base-300 bg-base-200 px-3 py-2 text-sm text-base-content/70">
            Нет доступных сочетаний валюты и типа реквизита.
        </div>
        <div v-else-if="localCommissionSettings.length === 0" class="rounded-box border border-base-300 bg-base-200 px-3 py-2 text-sm text-base-content/70">
            Наборы комиссий не настроены. Будут применяться комиссии из платежных методов.
        </div>

        <div
            v-for="(setting, settingIndex) in localCommissionSettings"
            :key="`${setting.currency}|${setting.detail_type}`"
            class="rounded-box border border-base-300 p-4 space-y-3"
        >
            <button
                type="button"
                class="w-full text-left"
                @click="toggleSetExpanded(setting)"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">{{ getLabel(setting) }}</div>
                        <div class="text-xs text-base-content/70">
                            {{ getSetShortInfo(setting) }}
                        </div>
                    </div>
                    <div class="text-xs text-base-content/60">
                        {{ isSetExpanded(setting) ? 'Свернуть' : 'Развернуть' }}
                    </div>
                </div>
            </button>

            <div v-if="isSetExpanded(setting)" class="space-y-3">
                <div class="text-xs text-base-content/70">
                    Приоритетная комиссия для сделок по этой паре.
                </div>

                <button
                    v-if="isAdmin && isEditMode"
                    type="button"
                    class="btn btn-xs btn-outline btn-error"
                    @click="removeCommissionSet(settingIndex)"
                >
                    Удалить набор
                </button>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <InputLabel :value="'Фикс. комиссия трейдера %'" />
                        <NumberInput
                            class="input-sm text-xs"
                            :model-value="setting.trader_commission_rate_for_orders"
                            step="0.1"
                            placeholder="Например 7"
                            :disabled="!isAdmin || !isEditMode"
                            @update:model-value="(value) => setting.trader_commission_rate_for_orders = value"
                        />
                    </div>
                    <div>
                        <InputLabel :value="'Фикс. тотал комиссия сервиса %'" />
                        <NumberInput
                            class="input-sm text-xs"
                            :model-value="setting.total_service_commission_rate_for_orders"
                            step="0.1"
                            placeholder="Например 10"
                            :disabled="!isAdmin || !isEditMode"
                            @update:model-value="(value) => setting.total_service_commission_rate_for_orders = value"
                        />
                    </div>
                </div>

                <label class="label cursor-pointer justify-start gap-3">
                    <input
                        type="checkbox"
                        class="toggle toggle-primary"
                        :checked="!!setting.use_flexible_trader_commission_for_orders"
                        :disabled="!isAdmin || !isEditMode"
                        @change="toggleFlexible(setting, $event.target.checked)"
                    >
                    <span class="label-text text-sm">Гибкая комиссия по уровням суммы сделки</span>
                </label>

                <div
                    v-if="setting.use_flexible_trader_commission_for_orders"
                    class="space-y-2"
                >
                    <div v-if="!getPairLimits(setting)" class="rounded-box border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-base-content/80">
                        Для этой пары не удалось определить границы min/max по платежным методам.
                    </div>
                    <div v-else-if="getPairLimitDetails(setting)" class="rounded-box border border-base-300 bg-base-200 px-3 py-2 text-xs text-base-content/80">
                        <div>
                            Диапазон для гибкой комиссии: {{ getPairLimits(setting).min }} - {{ getPairLimits(setting).max }}.
                        </div>
                        <div class="mt-1">
                            Минимум взят из {{ getPairLimitDetails(setting).minGateway.name }},
                            максимум взят из {{ getPairLimitDetails(setting).maxGateway.name }}.
                        </div>
                        <div class="mt-1">
                            Расчет: по всем платежным методам этой пары (валюта + тип реквизита) берется самый маленький min_limit и самый большой max_limit.
                        </div>
                    </div>
                    <div v-if="getPairLimits(setting)" class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
                        <div>
                            <InputLabel value="Разделить диапазон по границе" />
                            <NumberInput
                                class="input-sm text-xs"
                                v-model="splitPoints[getSettingKey(setting)]"
                                step="0.01"
                                :min="getPairLimits(setting).min"
                                :max="getPairLimits(setting).max"
                                placeholder="Например 2000"
                                :disabled="!isAdmin || !isEditMode"
                            />
                        </div>
                        <button
                            type="button"
                            class="btn btn-xs btn-outline"
                            v-if="isAdmin && isEditMode"
                            @click="splitTierAtPoint(setting)"
                        >
                            Разделить
                        </button>
                        <button
                            type="button"
                            class="btn btn-xs"
                            v-if="isAdmin && isEditMode"
                            @click="setSingleTierFromLimits(setting)"
                        >
                            1 уровень из лимитов
                        </button>
                    </div>
                    <div v-if="splitErrors[getSettingKey(setting)]" class="text-xs text-error">
                        {{ splitErrors[getSettingKey(setting)] }}
                    </div>
                    <div
                        v-for="(tier, tierIndex) in setting.trader_commission_tiers_for_orders"
                        :key="`${setting.currency}|${setting.detail_type}-tier-${tierIndex}`"
                        class="grid grid-cols-1 lg:grid-cols-5 gap-2 items-end"
                    >
                        <div>
                            <InputLabel :value="'От'" />
                            <NumberInput
                                class="input-sm text-xs"
                                v-model="tier.from"
                                step="0.01"
                                :min="getPairLimits(setting)?.min ?? 0"
                                :max="getPairLimits(setting)?.max ?? 999999999"
                                :disabled="!isAdmin || !isEditMode || tierIndex === 0"
                            />
                        </div>
                        <div>
                            <InputLabel :value="'До'" />
                            <NumberInput
                                class="input-sm text-xs"
                                v-model="tier.to"
                                step="0.01"
                                :min="getPairLimits(setting)?.min ?? 0"
                                :max="getPairLimits(setting)?.max ?? 999999999"
                                :disabled="!isAdmin || !isEditMode || tierIndex === setting.trader_commission_tiers_for_orders.length - 1"
                            />
                        </div>
                        <div>
                            <InputLabel :value="'Трейдер %'" />
                            <NumberInput
                                class="input-sm text-xs"
                                v-model="tier.rate"
                                step="0.1"
                                :disabled="!isAdmin || !isEditMode"
                            />
                        </div>
                        <div>
                            <InputLabel :value="'Тотал %'" />
                            <NumberInput
                                class="input-sm text-xs"
                                v-model="setting.total_service_commission_tiers_for_orders[tierIndex].rate"
                                step="0.1"
                                :disabled="!isAdmin || !isEditMode"
                            />
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="btn btn-xs btn-outline btn-error"
                                :disabled="!isAdmin || !isEditMode || setting.trader_commission_tiers_for_orders.length <= 1"
                                @click="removeTier(setting, tierIndex)"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <InputError :message="errors.commission_settings?.[0]" />
    </div>
</template>
