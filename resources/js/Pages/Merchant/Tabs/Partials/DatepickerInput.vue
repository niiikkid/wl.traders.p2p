<script setup>
import {getCurrentInstance, onMounted, onUnmounted, ref, watch, nextTick} from "vue";
import Pikaday from "pikaday";

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Выберите дату',
    },
    error: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const instance = getCurrentInstance();
const uid = instance.uid;

const inputRef = ref(null);
let picker = null;

const formatDateForDisplay = (date) => {
    if (!date) return '';
    const d = new Date(date);
    if (isNaN(d.getTime())) return '';
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    // В этом компоненте используем dd/mm/yyyy
    return `${day}/${month}/${year}`;
};

const parseDateFromDisplay = (dateString) => {
    if (!dateString) return null;
    const normalized = dateString.replaceAll('.', '/');
    const match = normalized.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (match) {
        const [, dd, mm, yyyy] = match;
        return new Date(Number(yyyy), Number(mm) - 1, Number(dd));
    }
    return null;
};

onMounted(async () => {
    await nextTick();
    if (!inputRef.value) return;

    picker = new Pikaday({
        field: inputRef.value,
        format: 'DD/MM/YYYY',
        i18n: {
            previousMonth: 'Предыдущий месяц',
            nextMonth: 'Следующий месяц',
            months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            weekdays: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
            weekdaysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']
        },
        onSelect: (date) => {
            const out = date ? formatDateForDisplay(date) : '';
            emit('update:modelValue', out);
            emit('change', out);
        }
    });

    // Инициализируем значение из v-model
    if (props.modelValue) {
        const parsed = parseDateFromDisplay(props.modelValue);
        if (parsed) {
            picker.setDate(parsed);
            inputRef.value.value = formatDateForDisplay(parsed);
        } else {
            inputRef.value.value = '';
        }
    } else {
        inputRef.value.value = '';
    }
});

onUnmounted(() => {
    if (picker) {
        picker.destroy();
    }
});

watch(() => props.modelValue, (newVal) => {
    if (!inputRef.value || !picker) return;
    const currentInputVal = inputRef.value.value || '';
    if ((newVal || '') === currentInputVal) return;
    const parsed = parseDateFromDisplay(newVal || '');
    if (parsed) {
        picker.setDate(parsed, true);
        inputRef.value.value = formatDateForDisplay(parsed);
    } else {
        picker.setDate(null);
        inputRef.value.value = '';
    }
});
</script>

<template>
    <div class="relative w-full">
        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none z-10">
            <svg class="w-4 h-4 text-base-content opacity-60" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z"/>
            </svg>
        </div>
        <input
            ref="inputRef"
            :id="`date-datepicker-${uid}`"
            type="text"
            class="input input-bordered w-full ps-10 pika-single"
            :class="error ? 'input-error' : ''"
            :placeholder="placeholder"
            readonly
        >
    </div>
</template>

<style scoped>
</style> 