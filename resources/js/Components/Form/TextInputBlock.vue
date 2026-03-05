<script setup>
import { computed } from "vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputBlock from "@/Components/Form/InputBlock.vue";

const props = defineProps({
    form: {
        type: Object,
    },
    // Доп. режим: внешняя карта ошибок и кастомная очистка
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
    helper: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
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
            :helper="helper"
        >
            <TextInput
                :id="field"
                v-model="model"
                type="text"
                class="block w-full"
                :placeholder="placeholder"
                :error="!!errorsMap[field]"
                :disabled="disabled"
                @input="clearErrors(field)"
            />
        </InputBlock>
    </div>
</template>

<style scoped>

</style>
