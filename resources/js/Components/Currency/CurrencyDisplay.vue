<script setup>
import { computed, ref, watch } from 'vue';
import {
    getCurrencyIconUrl,
    getGenericCurrencyIconUrl,
    normalizeIconKey,
} from '@/utils/currency-icons.js';

const props = defineProps({
    currency: {
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

const currencyCode = computed(() => String(props.currency ?? '').trim());

const currencyLabel = computed(() => {
    if (!currencyCode.value) {
        return '';
    }

    return currencyCode.value.toUpperCase();
});

const iconKey = computed(() => normalizeIconKey(currencyCode.value || currencyLabel.value));

const iconUrl = ref('');
const iconFailed = ref(false);

const iconSizeValue = computed(() => props.iconSize ?? (props.size === 'lg' ? 28 : props.size === 'sm' ? 18 : 24));

const iconStyle = computed(() => {
    const resolvedSize = typeof iconSizeValue.value === 'number'
        ? `${iconSizeValue.value}px`
        : iconSizeValue.value;

    return {
        width: resolvedSize,
        height: resolvedSize,
    };
});

const labelClass = computed(() => {
    if (props.size === 'lg') {
        return 'text-base font-medium';
    }

    if (props.size === 'sm') {
        return 'text-xs font-medium';
    }

    return 'text-sm font-medium';
});

const fallbackText = computed(() => {
    const value = currencyLabel.value;

    if (!value) {
        return '?';
    }

    return value.slice(0, 3);
});

function loadIcon() {
    iconFailed.value = false;

    if (!iconKey.value) {
        iconUrl.value = getGenericCurrencyIconUrl();
        return;
    }

    iconUrl.value = getCurrencyIconUrl(iconKey.value);
}

function onIconError() {
    if (iconUrl.value !== getGenericCurrencyIconUrl()) {
        iconUrl.value = getGenericCurrencyIconUrl();
        return;
    }

    iconFailed.value = true;
}

watch(iconKey, () => {
    loadIcon();
}, { immediate: true });
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span class="inline-flex shrink-0" :style="iconStyle">
            <img
                v-if="iconUrl && !iconFailed"
                :src="iconUrl"
                alt=""
                class="block size-full rounded-full object-contain"
                loading="lazy"
                decoding="async"
                @error="onIconError"
            />
            <span
                v-else
                class="inline-flex size-full items-center justify-center rounded-full bg-base-200 text-[10px] font-semibold text-base-content/50"
            >
                {{ fallbackText }}
            </span>
        </span>

        <span v-if="showLabel && currencyLabel" class="text-base-content" :class="labelClass">
            {{ currencyLabel }}
        </span>
    </span>
</template>
