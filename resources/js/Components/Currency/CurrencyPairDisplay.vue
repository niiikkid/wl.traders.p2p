<script setup>
import { computed } from 'vue';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';
import { parseCurrencyPair } from '@/utils/parse-currency-pair.js';

const props = defineProps({
    baseCurrency: {
        type: [String, null],
        default: null,
    },
    quoteCurrency: {
        type: [String, null],
        default: null,
    },
    pair: {
        type: [String, null],
        default: null,
    },
    showLabel: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: 'md',
    },
    iconSize: {
        type: [Number, String, null],
        default: null,
    },
});

const parsed = computed(() => parseCurrencyPair({
    base_currency: props.baseCurrency,
    quote_currency: props.quoteCurrency,
    pair: props.pair,
}));

const resolvedBase = computed(() => parsed.value.baseCurrency);
const resolvedQuote = computed(() => parsed.value.quoteCurrency);
const pairLabel = computed(() => parsed.value.label);

const hasIcons = computed(() => Boolean(resolvedBase.value && resolvedQuote.value));

const iconSizeValue = computed(() => props.iconSize ?? (props.size === 'lg' ? 32 : props.size === 'sm' ? 18 : 24));

const overlapMargin = computed(() => {
    const size = typeof iconSizeValue.value === 'number'
        ? iconSizeValue.value
        : parseInt(iconSizeValue.value, 10) || 24;

    return `-${Math.round(size / 3)}px`;
});

const labelClass = computed(() => {
    if (props.size === 'lg') {
        return 'text-xl font-semibold tracking-tight text-base-content';
    }

    if (props.size === 'sm') {
        return 'text-xs font-medium text-base-content';
    }

    return 'text-sm font-medium tabular-nums text-base-content';
});
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span v-if="hasIcons" class="inline-flex shrink-0 items-center">
            <span class="relative z-[2] rounded-full ring-2 ring-base-100" :style="{ marginRight: overlapMargin }">
                <CurrencyDisplay
                    :currency="resolvedBase"
                    :icon-size="iconSizeValue"
                    :show-label="false"
                    :size="size"
                />
            </span>
            <span class="relative z-[1] -ml-0">
                <CurrencyDisplay
                    :currency="resolvedQuote"
                    :icon-size="iconSizeValue"
                    :show-label="false"
                    :size="size"
                />
            </span>
        </span>

        <span v-if="showLabel && pairLabel" :class="labelClass">
            {{ pairLabel }}
        </span>
    </span>
</template>
