<script setup>
import CurrencySelect from '@/Components/Currency/CurrencySelect.vue';

const model = defineModel({
    required: true,
});

defineProps({
    error: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: '',
    },
    items: {
        type: [Array, Object],
    },
    value: {
        type: String,
    },
    name: {
        type: String,
    },
    default_title: {
        type: String,
    },
    default_value: {
        default: '0',
    },
    required: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    currencyIcons: {
        type: Boolean,
        default: false,
    },
    pairBase: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['change']);
</script>

<template>
    <CurrencySelect
        v-if="currencyIcons"
        v-model="model"
        :error="error"
        :size="size"
        :items="items"
        :value="value"
        :name="name"
        :default_title="default_title"
        :default_value="default_value"
        :required="required"
        :disabled="disabled"
        :pair-base="pairBase"
        @change="emit('change', $event)"
    />
    <select
        v-else
        :class="[
            !error
                ? 'select select-bordered appearance-none w-full'
                : 'select select-bordered appearance-none select-error w-full',
            size === 'sm' ? 'select-sm text-xs' : '',
        ]"
        :required="required"
        :disabled="disabled"
        v-model="model"
        @change="emit('change', $event.target.value)"
    >
        <option :value="default_value" selected>{{ default_title }}</option>
        <option v-for="item in items" :key="item[value]" :value="item[value]">{{ item[name] }}</option>
    </select>
</template>
