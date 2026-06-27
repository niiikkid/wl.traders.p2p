<script setup>
import {ref, watch, onMounted, onUnmounted, nextTick, inject} from "vue";
import Pikaday from "pikaday";
import {useFilterModel} from "@/composables/useFilterModel.js";
import FilterField from "@/Components/Filters/Partials/FilterField.vue";
import CalendarIcon from "@/Components/Filters/Icons/CalendarIcon.vue";

const props = defineProps({
    name: {
        type: String,
    },
    title: {
        type: String,
    },
    label: {
        type: String,
        default: '',
    },
});

const applyFilters = inject('applyFilters', null);
const model = useFilterModel(props.name);

const dateInputRef = ref(null);
let picker = null;

// Форматирование даты для отображения в формате DD/MM/YYYY
const formatDateForDisplay = (date) => {
    if (!date) return "";
    const d = new Date(date);
    if (isNaN(d.getTime())) return "";

    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();

    return `${day}/${month}/${year}`;
};

// Парсинг даты из формата DD.MM.YYYY
const parseDateFromDisplay = (dateString) => {
    if (!dateString) return null;

    const match = dateString.match(/^(\d{2})[./](\d{2})[./](\d{4})$/);
    if (match) {
        const [, day, month, year] = match;
        return new Date(year, month - 1, day);
    }

    return null;
};

onMounted(async () => {
    await nextTick();

    if (dateInputRef.value) {
        picker = new Pikaday({
            field: dateInputRef.value,
            format: 'DD/MM/YYYY',
            i18n: {
                previousMonth: 'Предыдущий месяц',
                nextMonth: 'Следующий месяц',
                months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                weekdays: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
                weekdaysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']
            },
            onSelect: function(date) {
                model.value = date ? formatDateForDisplay(date) : "";
            }
        });

        // Устанавливаем начальное значение если оно есть
        if (model.value) {
            const parsedDate = parseDateFromDisplay(model.value);
            if (parsedDate) {
                picker.setDate(parsedDate);
                dateInputRef.value.value = formatDateForDisplay(parsedDate);
            }
        }
    }
});

onUnmounted(() => {
    if (picker) {
        picker.destroy();
    }
});

// Следим за изменениями модели и обновляем picker
watch(model, (newValue) => {
    if (picker && newValue !== dateInputRef.value.value) {
        const parsedDate = parseDateFromDisplay(newValue);
        if (parsedDate) {
            picker.setDate(parsedDate);
            dateInputRef.value.value = formatDateForDisplay(parsedDate);
        } else {
            picker.setDate(null);
            dateInputRef.value.value = "";
        }
    }
});
</script>

<template>
    <FilterField :label="label">
        <div class="relative w-full">
            <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none z-2">
                <CalendarIcon class="size-4 text-base-content/60"/>
            </div>
            <input
                ref="dateInputRef"
                type="text"
                class="input input-bordered input-sm w-full ps-10"
                :placeholder="title || 'Выберите дату'"
                readonly
                @keydown.enter.prevent="applyFilters && applyFilters()"
            >
        </div>
    </FilterField>
</template>
