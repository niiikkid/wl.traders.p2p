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
    /** Меньшая высота поля и попапа (как input-sm) */
    compact: {
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
        <div
            class="pointer-events-none absolute inset-y-0 start-0 z-2 flex items-center"
            :class="props.compact ? 'ps-2' : 'ps-3.5'"
        >
            <svg
                class="text-base-content opacity-70"
                :class="props.compact ? 'h-3.5 w-3.5' : 'h-4 w-4'"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="currentColor"
                viewBox="0 0 24 24"
            >
                <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm11-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l3 3a1 1 0 0 0 1.414-1.414L13 11.586V8Z" clip-rule="evenodd"/>
            </svg>
        </div>
        <input
            ref="inputRef"
            type="text"
            class="input input-bordered w-full"
            :class="[
                props.compact ? 'input-sm ps-8' : 'ps-10',
                props.error ? 'input-error' : '',
            ]"
            :placeholder="placeholder"
            :value="displayValue"
            readonly
            @click="open"
        />

        <div
            v-show="isOpen"
            ref="popoverRef"
            class="absolute z-20 mt-1 w-full rounded-box border border-base-300 bg-base-100 shadow"
            :class="props.compact ? 'p-2' : 'p-3'"
        >
            <div class="grid grid-cols-2" :class="props.compact ? 'gap-2' : 'gap-3'">
                <div>
                    <div class="label mb-0.5 p-0" :class="props.compact ? '' : 'mb-1'">
                        <span class="opacity-70" :class="props.compact ? 'text-xs' : 'label-text'">Часы</span>
                    </div>
                    <select
                        v-model="selectedHour"
                        class="select select-bordered w-full"
                        :class="props.compact ? 'select-sm' : ''"
                    >
                        <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>
                <div>
                    <div class="label mb-0.5 p-0" :class="props.compact ? '' : 'mb-1'">
                        <span class="opacity-70" :class="props.compact ? 'text-xs' : 'label-text'">Минуты</span>
                    </div>
                    <select
                        v-model="selectedMinute"
                        class="select select-bordered w-full"
                        :class="props.compact ? 'select-sm' : ''"
                    >
                        <option v-for="m in minutes" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2" :class="props.compact ? 'mt-2' : 'mt-3'">
                <button type="button" class="btn btn-ghost btn-sm" @click="clear">Очистить</button>
                <button type="button" class="btn btn-primary btn-sm" @click="apply">Готово</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>


