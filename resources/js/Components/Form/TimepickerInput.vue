<script setup>
import {ref, computed, watch, onMounted, onUnmounted} from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Выберите время',
    },
    error: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const inputRef = ref(null);
const popoverRef = ref(null);

const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));

const selectedHour = ref('00');
const selectedMinute = ref('00');

const displayValue = computed(() => {
    if (!props.modelValue) return '';
    const [h, m] = props.modelValue.split(':');
    if (!h || !m) return '';
    return `${h}:${m}`;
});

const open = () => {
    // Инициализируем выбор текущим значением
    if (props.modelValue && props.modelValue.includes(':')) {
        const [h, m] = props.modelValue.split(':');
        if (h) selectedHour.value = h.padStart(2, '0');
        if (m) selectedMinute.value = m.padStart(2, '0');
    }
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
};

const apply = () => {
    const value = `${selectedHour.value}:${selectedMinute.value}`;
    emit('update:modelValue', value);
    emit('change', value);
    close();
};

const clear = () => {
    emit('update:modelValue', '');
    emit('change', '');
    close();
};

const onClickOutside = (e) => {
    const inputEl = inputRef.value;
    const popEl = popoverRef.value;
    if (!inputEl || !popEl) return;
    if (!inputEl.contains(e.target) && !popEl.contains(e.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
});

watch(() => props.modelValue, (nv) => {
    // держим отображение в актуальном состоянии
    if (!nv) return;
    const [h, m] = nv.split(':');
    if (h) selectedHour.value = h.padStart(2, '0');
    if (m) selectedMinute.value = m.padStart(2, '0');
});
</script>

<template>
    <div class="relative w-full">
        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none z-2">
            <svg class="w-4 h-4 text-base-content opacity-70" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm11-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l3 3a1 1 0 0 0 1.414-1.414L13 11.586V8Z" clip-rule="evenodd"/>
            </svg>
        </div>
        <input
            ref="inputRef"
            type="text"
            class="input input-bordered w-full ps-10"
            :class="error ? 'input-error' : ''"
            :placeholder="placeholder"
            :value="displayValue"
            readonly
            @click="open"
        />

        <div
            v-show="isOpen"
            ref="popoverRef"
            class="absolute z-20 mt-1 w-full p-3 bg-base-100 rounded-box shadow border border-base-300"
        >
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="label p-0 mb-1"><span class="label-text opacity-70">Часы</span></div>
                    <select v-model="selectedHour" class="select select-bordered w-full">
                        <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>
                <div>
                    <div class="label p-0 mb-1"><span class="label-text opacity-70">Минуты</span></div>
                    <select v-model="selectedMinute" class="select select-bordered w-full">
                        <option v-for="m in minutes" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="clear">Очистить</button>
                <button type="button" class="btn btn-primary btn-sm" @click="apply">Готово</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>


