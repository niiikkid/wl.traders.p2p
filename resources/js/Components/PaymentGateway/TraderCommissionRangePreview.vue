<script setup>
import { computed, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    gateway: {
        type: Object,
        default: null,
    },
    currency: {
        type: String,
        default: "RUB",
    },
    minAmount: {
        type: [Number, String, null],
        default: null,
    },
    maxAmount: {
        type: [Number, String, null],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:min-amount", "update:max-amount"]);

const toNumberOrNull = (value) => {
    if (value === null || value === undefined || value === "") {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
};

const roundToStep = (value, step) => {
    const fixedStep = Number(step) > 0 ? Number(step) : 1;

    return Math.round(Number(value) / fixedStep) * fixedStep;
};

const globalMin = computed(() => toNumberOrNull(props.gateway?.min_limit));
const globalMax = computed(() => toNumberOrNull(props.gateway?.max_limit));

const tiers = computed(() => {
    const source = Array.isArray(props.gateway?.trader_commission_tiers_for_orders)
        ? props.gateway.trader_commission_tiers_for_orders
        : [];

    return source
        .map((tier) => ({
            from: toNumberOrNull(tier?.from),
            to: toNumberOrNull(tier?.to),
            rate: toNumberOrNull(tier?.rate),
        }))
        .filter((tier) => tier.from !== null && tier.to !== null && tier.rate !== null && tier.to > tier.from);
});

const hasFlexibleCommission = computed(() => {
    return !!props.gateway?.use_flexible_trader_commission_for_orders && tiers.value.length > 0;
});

const hasValidLimits = computed(() => {
    return globalMin.value !== null && globalMax.value !== null && globalMax.value > globalMin.value;
});

const sliderStep = computed(() => {
    if (!hasValidLimits.value) {
        return 1;
    }

    const distance = globalMax.value - globalMin.value;
    if (distance <= 100) {
        return 1;
    }
    if (distance <= 10000) {
        return 10;
    }

    return 100;
});

const sliderMin = ref(0);
const sliderMax = ref(0);
const sliderRailRef = ref(null);
const activeHandle = ref(null);

const syncSliderFromModel = () => {
    if (!hasValidLimits.value) {
        sliderMin.value = 0;
        sliderMax.value = 0;
        return;
    }

    const minValueFromModel = toNumberOrNull(props.minAmount);
    const maxValueFromModel = toNumberOrNull(props.maxAmount);

    let nextMin = minValueFromModel ?? globalMin.value;
    let nextMax = maxValueFromModel ?? globalMax.value;

    nextMin = Math.max(globalMin.value, Math.min(nextMin, globalMax.value));
    nextMax = Math.min(globalMax.value, Math.max(nextMax, globalMin.value));

    if (nextMin > nextMax) {
        nextMin = nextMax;
    }

    sliderMin.value = roundToStep(nextMin, sliderStep.value);
    sliderMax.value = roundToStep(nextMax, sliderStep.value);
};

watch(
    () => [props.minAmount, props.maxAmount, globalMin.value, globalMax.value, sliderStep.value],
    () => syncSliderFromModel(),
    { immediate: true }
);

const setMinValue = (value, shouldEmit = true) => {
    if (!hasValidLimits.value) {
        return;
    }

    const parsed = toNumberOrNull(value);
    if (parsed === null) {
        return;
    }

    const safeValue = Math.max(globalMin.value, Math.min(parsed, sliderMax.value));
    sliderMin.value = roundToStep(safeValue, sliderStep.value);
    if (shouldEmit) {
        emit("update:min-amount", sliderMin.value);
    }
};

const setMaxValue = (value, shouldEmit = true) => {
    if (!hasValidLimits.value) {
        return;
    }

    const parsed = toNumberOrNull(value);
    if (parsed === null) {
        return;
    }

    const safeValue = Math.min(globalMax.value, Math.max(parsed, sliderMin.value));
    sliderMax.value = roundToStep(safeValue, sliderStep.value);
    if (shouldEmit) {
        emit("update:max-amount", sliderMax.value);
    }
};

const resetToGatewayLimits = () => {
    if (!hasValidLimits.value) {
        return;
    }

    sliderMin.value = globalMin.value;
    sliderMax.value = globalMax.value;
    emit("update:min-amount", globalMin.value);
    emit("update:max-amount", globalMax.value);
};

const selectedRangeText = computed(() => {
    if (!hasValidLimits.value) {
        return null;
    }

    return `${sliderMin.value} - ${sliderMax.value} ${props.currency?.toUpperCase() || "RUB"}`;
});

const getRateByAmount = (amount) => {
    return tiers.value.find((tier, index) => {
        const isLast = index === tiers.value.length - 1;
        return isLast
            ? amount >= tier.from && amount <= tier.to
            : amount >= tier.from && amount < tier.to;
    })?.rate ?? toNumberOrNull(props.gateway?.trader_commission_rate_for_orders);
};

const selectedMinRate = computed(() => getRateByAmount(sliderMin.value));
const selectedMaxRate = computed(() => getRateByAmount(sliderMax.value));

const segmentViewModel = computed(() => {
    if (!hasValidLimits.value) {
        return [];
    }

    const distance = globalMax.value - globalMin.value;
    if (distance <= 0) {
        return [];
    }

    return tiers.value.map((tier, index) => {
        const widthPercent = ((tier.to - tier.from) / distance) * 100;

        return {
            key: `${tier.from}-${tier.to}-${index}`,
            widthPercent,
            from: tier.from,
            to: tier.to,
            rate: tier.rate,
        };
    });
});

const minPercent = computed(() => {
    if (!hasValidLimits.value) {
        return 0;
    }

    return ((sliderMin.value - globalMin.value) / (globalMax.value - globalMin.value)) * 100;
});

const maxPercent = computed(() => {
    if (!hasValidLimits.value) {
        return 0;
    }

    return ((sliderMax.value - globalMin.value) / (globalMax.value - globalMin.value)) * 100;
});

const selectedTrackWidthPercent = computed(() => Math.max(0, maxPercent.value - minPercent.value));

const clientXToSliderValue = (clientX) => {
    if (!hasValidLimits.value || !sliderRailRef.value) {
        return null;
    }

    const rect = sliderRailRef.value.getBoundingClientRect();
    if (!rect.width) {
        return null;
    }

    const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
    const rawValue = globalMin.value + ratio * (globalMax.value - globalMin.value);

    return roundToStep(rawValue, sliderStep.value);
};

const chooseClosestHandle = (value) => {
    const distanceToMin = Math.abs(value - sliderMin.value);
    const distanceToMax = Math.abs(value - sliderMax.value);

    return distanceToMin <= distanceToMax ? "min" : "max";
};

const moveHandleByValue = (value, handle = null) => {
    if (value === null) {
        return;
    }

    const targetHandle = handle ?? chooseClosestHandle(value);
    if (targetHandle === "min") {
        setMinValue(value);
        return;
    }

    setMaxValue(value);
};

const onWindowMouseMove = (event) => {
    if (!activeHandle.value || props.disabled) {
        return;
    }

    const value = clientXToSliderValue(event.clientX);
    moveHandleByValue(value, activeHandle.value);
};

const stopDragging = () => {
    activeHandle.value = null;
    window.removeEventListener("mousemove", onWindowMouseMove);
    window.removeEventListener("mouseup", stopDragging);
};

const startDragging = (handle, event) => {
    if (props.disabled) {
        return;
    }

    event.preventDefault();
    activeHandle.value = handle;
    window.addEventListener("mousemove", onWindowMouseMove);
    window.addEventListener("mouseup", stopDragging);
};

const onRailMouseDown = (event) => {
    if (props.disabled) {
        return;
    }

    const value = clientXToSliderValue(event.clientX);
    const handle = value === null ? null : chooseClosestHandle(value);
    moveHandleByValue(value, handle);

    if (handle) {
        startDragging(handle, event);
    }
};

onUnmounted(() => {
    stopDragging();
});
</script>

<template>
    <div
        v-if="hasFlexibleCommission && hasValidLimits"
        class="mt-4 rounded-box border border-primary/30 bg-primary/5 p-4 space-y-3"
    >
        <div class="flex items-center justify-between gap-2">
            <div class="text-sm font-medium">
                Гибкая комиссия по сумме чека
            </div>
            <button
                type="button"
                class="btn btn-xs btn-outline"
                :disabled="disabled"
                @click="resetToGatewayLimits"
            >
                Полный диапазон
            </button>
        </div>

        <div class="text-xs text-base-content/70">
            Укажите ваш Min-Max. Ниже видно, какой процент комиссии вы получите на каждом диапазоне суммы чека.
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="flex items-center justify-between text-xs mb-2">
                <span>Min: {{ globalMin }} {{ currency?.toUpperCase() }}</span>
                <span>Max: {{ globalMax }} {{ currency?.toUpperCase() }}</span>
            </div>

            <div class="w-full rounded-full bg-base-300 h-3 overflow-hidden flex">
                <div
                    v-for="segment in segmentViewModel"
                    :key="segment.key"
                    class="h-full border-r border-base-100 last:border-r-0"
                    :class="['bg-primary/60']"
                    :style="{ width: `${segment.widthPercent}%` }"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mt-3">
                <div
                    v-for="segment in segmentViewModel"
                    :key="`label-${segment.key}`"
                    class="text-xs rounded-box border border-base-300 p-2"
                >
                    <div class="font-medium">
                        {{ segment.from }} - {{ segment.to }} {{ currency?.toUpperCase() }}
                    </div>
                    <div class="text-primary">
                        Комиссия: {{ segment.rate }}%
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <div class="text-xs text-base-content/70">
                Ваш диапазон реквизита: <span class="font-medium text-base-content">{{ selectedRangeText }}</span>
            </div>

            <div class="text-xs text-base-content/70">
                % на старте диапазона: <span class="font-medium text-primary">{{ selectedMinRate ?? "-" }}%</span>,
                % на верхней границе: <span class="font-medium text-primary">{{ selectedMaxRate ?? "-" }}%</span>
            </div>

            <div
                ref="sliderRailRef"
                class="relative h-8 cursor-pointer select-none"
                :class="{ 'opacity-60 pointer-events-none': disabled }"
                @mousedown="onRailMouseDown"
            >
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-2 rounded-full bg-base-300" />
                <div
                    class="absolute top-1/2 -translate-y-1/2 h-2 rounded-full bg-primary"
                    :style="{ left: `${minPercent}%`, width: `${selectedTrackWidthPercent}%` }"
                />

                <button
                    type="button"
                    class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-5 h-5 rounded-full border-2 border-primary bg-base-100 shadow cursor-ew-resize"
                    :style="{ left: `${minPercent}%` }"
                    :disabled="disabled"
                    @mousedown.stop="startDragging('min', $event)"
                />
                <button
                    type="button"
                    class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-5 h-5 rounded-full border-2 border-secondary bg-base-100 shadow cursor-ew-resize"
                    :style="{ left: `${maxPercent}%` }"
                    :disabled="disabled"
                    @mousedown.stop="startDragging('max', $event)"
                />
            </div>
        </div>
    </div>
</template>
