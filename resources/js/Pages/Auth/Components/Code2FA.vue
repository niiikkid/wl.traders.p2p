<script setup>
import {ref, nextTick, watch} from 'vue';

const model = defineModel({
    required: true,
});

const code = ref(Array(6).fill(""));
const inputs = ref([]);

const setInputRef = (el, index) => {
    if (el) inputs.value[index] = el;
};

watch(
    () => model.value,
    () => {
        if (! model.value) {
            code.value = Array(6).fill("");
        }
    }
);

const handleInput = (index) => {
    if (code.value[index] && index < 5) {
        nextTick(() => inputs.value[index + 1]?.focus());
    }

    model.value = code.value.join('');
};

const handleBackspace = (index) => {
    if (!code.value[index] && index > 0) {
        nextTick(() => inputs.value[index - 1]?.focus());
    }

    model.value = code.value.join('');
};
</script>

<template>
    <div>
        <div class='flex space-x-2 justify-center'>
            <input v-for="(digit, index) in code"
                   :key="index"
                   v-model="code[index]"
                   :ref="(el) => setInputRef(el, index)"
                   type="text"
                   inputmode="numeric"
                   pattern="[0-9]*"
                   autocomplete="one-time-code"
                   maxlength="1"
                   class='input input-bordered w-12 h-12 md:w-14 md:h-14 text-center text-xl font-semibold'
                   @input="handleInput(index)"
                   @keydown.backspace="handleBackspace(index)">
        </div>
    </div>
</template>
