<script setup>
import { computed } from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputHelper from "@/Components/InputHelper.vue";

const props = defineProps({
    form: {
        type: Object,
    },
    // Доп. режим: принимаем внешнюю карту ошибок, если не используется useForm
    errors: {
        type: Object,
        default: () => ({}),
    },
    field: {
        type: String,
    },
    label: {
        type: String
    },
    helper: {
        type: String,
        default: null,
    },
});

// Универсальная карта ошибок: сперва берем из form.errors, иначе из пропса errors
const errorsMap = computed(() => {
    if (props?.form && props.form?.errors) {
        return props.form.errors;
    }
    return props.errors ?? {};
});
</script>

<template>
    <div>
        <InputLabel
            :for="field"
            :value="label"
            :error="!!errorsMap[field]"
        />

        <div class="mt-1">
            <slot/>
        </div>

        <InputError :message="errorsMap[field]?.[0]" class="mt-2" />
        <InputHelper v-if="!errorsMap[field] && helper" :model-value="helper"></InputHelper>
    </div>
</template>

<style scoped>

</style>
