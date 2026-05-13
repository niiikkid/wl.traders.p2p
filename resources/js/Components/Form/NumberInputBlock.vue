<script setup>
import { computed } from "vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputBlock from "@/Components/Form/InputBlock.vue";

const props = defineProps({
    form: {
        type: Object,
    },
    // Доп. режим: принимаем внешнюю карту ошибок и функцию очистки
    errors: {
        type: Object,
        default: () => ({}),
    },
    onClear: {
        type: Function,
        default: null,
    },
    field: {
        type: String,
    },
    placeholder: {
        type: String,
        default: null
    },
    label: {
        type: String
    },
    labelTooltip: {
        type: String,
        default: null,
    },
    labelTooltipClass: {
        type: String,
        default: '',
    },
    helper: {
        type: String,
        default: null,
    },
});

const model = defineModel({
    required: true,
});

const errorsMap = computed(() => {
    if (props?.form && props.form?.errors) {
        return props.form.errors;
    }
    return props.errors ?? {};
});

const clearErrors = (field) => {
    if (props?.form && typeof props.form.clearErrors === 'function') {
        props.form.clearErrors(field);
        return;
    }
    if (typeof props.onClear === 'function') {
        props.onClear(field);
    }
};
</script>

<template>
    <div>
        <InputBlock
            :form="form"
            :errors="errors"
            :field="field"
            :label="label"
            :label-tooltip="labelTooltip"
            :label-tooltip-class="labelTooltipClass"
            :helper="helper"
        >
            <NumberInput
                :id="field"
                v-model="model"
                class="block w-full"
                :placeholder="placeholder"
                :error="!!errorsMap[field]"
                @input="clearErrors(field)"
            />
        </InputBlock>
    </div>
</template>

<style scoped>

</style>
