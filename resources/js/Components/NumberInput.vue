<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    required: true,
});
defineProps({
    error: {
        type: Boolean,
        default: false,
    },
    min: {
        type: [Number, String],
        default: null,
    },
    max: {
        type: [Number, String],
        default: null,
    },
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        :class="!error
        ? 'input input-bordered w-full'
        : 'input input-bordered input-error w-full'"
        v-model="model"
        ref="input"
        type="number"
        :min="min"
        :max="max"
    />
</template>
