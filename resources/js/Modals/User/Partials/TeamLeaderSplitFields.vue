<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import { computed } from "vue";

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    percentField: {
        type: String,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const splitMode = computed({
    get() {
        const value = Number(props.form[props.percentField] ?? 0);
        if (value <= 0) {
            return 'trader';
        }
        if (value >= 100) {
            return 'admin';
        }

        return 'split';
    },
    set(mode) {
        if (mode === 'trader') {
            props.form[props.percentField] = 0;
            return;
        }

        if (mode === 'admin') {
            props.form[props.percentField] = 100;
            return;
        }

        if (mode === 'split') {
            const current = Number(props.form[props.percentField] ?? 0);
            if (current <= 0 || current >= 100) {
                props.form[props.percentField] = 50;
            }
        }
    },
});

const percent = computed(() => Number(props.form[props.percentField] ?? 0));
</script>

<template>
    <div class="space-y-2 rounded-lg border border-base-300/80 bg-base-200/20 p-2.5">
        <InputLabel
            :value="label"
            :error="!!errors[percentField]?.[0]"
        />

        <div class="flex flex-wrap gap-1.5">
            <label
                v-for="option in [
                    { value: 'trader', label: 'Трейдер' },
                    { value: 'admin', label: 'Админ' },
                    { value: 'split', label: 'Сплит' },
                ]"
                :key="option.value"
                class="label cursor-pointer gap-2 rounded-lg border border-base-300 px-2.5 py-1"
            >
                <input
                    v-model="splitMode"
                    type="radio"
                    class="radio radio-sm radio-primary"
                    :value="option.value"
                    :disabled="processing"
                >
                <span class="label-text text-sm">{{ option.label }}</span>
            </label>
        </div>

        <div v-if="splitMode === 'split'" class="space-y-2">
            <input
                v-model.number="form[percentField]"
                type="range"
                min="0"
                max="100"
                step="1"
                class="range range-primary range-sm"
                :disabled="processing"
            >
            <div class="flex justify-between text-xs text-base-content/60">
                <span>Админ: {{ percent }}%</span>
                <span>Трейдер: {{ 100 - percent }}%</span>
            </div>
        </div>
        <p v-else class="text-xs text-base-content/60">
            Админ: {{ percent }}%, Трейдер: {{ 100 - percent }}%
        </p>

        <InputError :message="errors[percentField]?.[0]" />
    </div>
</template>
