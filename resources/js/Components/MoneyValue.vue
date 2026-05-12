<script setup>
defineProps({
    value: {
        type: [String, Number],
        default: null,
    },
    currency: {
        type: String,
        default: null,
    },
    empty: {
        type: String,
        default: '—',
    },
    secondary: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
    uppercaseCurrency: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <span
        class="text-nowrap"
        :class="[secondary || compact ? 'text-xs' : 'text-base-content', block ? 'block' : '']"
    >
        <span :class="secondary ? 'text-base-content/50' : 'text-base-content'">{{ value ?? empty }}</span>
        <span
            v-if="currency"
            class="ml-1"
            :class="secondary ? 'text-base-content/30' : 'text-primary/70'"
        >{{ uppercaseCurrency ? currency.toUpperCase() : currency }}</span>
    </span>
</template>
