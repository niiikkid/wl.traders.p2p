<script setup>
import { trimTime } from '@/composables/usePaymentDetailScheduleEditor.js';
import { computed } from 'vue';

const model = defineModel({
    type: String,
    default: '',
});

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: Boolean,
        default: false,
    },
    joinItem: {
        type: Boolean,
        default: false,
    },
    ariaLabel: {
        type: String,
        default: null,
    },
});

const TIME_VALUE_PATTERN = /^\d{2}:\d{2}$/;

const displayValue = computed(() => {
    const normalized = trimTime(model.value);

    return TIME_VALUE_PATTERN.test(normalized) ? normalized : '';
});

const inputClass = computed(() => [
    'input input-bordered input-sm h-8 min-h-8 min-w-0',
    props.joinItem ? 'join-item w-[5.75rem]' : 'w-full',
    props.error ? 'input-error' : '',
]);

const onInput = (event) => {
    const raw = event.target.value;

    if (!raw) {
        model.value = '';

        return;
    }

    model.value = raw.length >= 5 ? raw.slice(0, 5) : trimTime(raw);
};
</script>

<template>
    <input
        :id="id"
        type="time"
        step="60"
        :value="displayValue"
        :class="inputClass"
        :disabled="disabled"
        :aria-label="ariaLabel"
        @input="onInput"
    />
</template>
