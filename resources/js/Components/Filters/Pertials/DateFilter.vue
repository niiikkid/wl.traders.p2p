<script setup>
import {computed, ref, watch, onMounted, onUnmounted, nextTick, inject} from "vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import Pikaday from "pikaday";

const tableFiltersStore = useTableFiltersStore();
const applyFilters = inject('applyFilters', null);

const props = defineProps({
    name: {
        type: String,
    },
    title: {
        type: String,
    },
});

const model = computed({
    get: () => tableFiltersStore.filters[props.name],
    set: (val) => {
        tableFiltersStore.filters[props.name] = val
    }
})

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
                if (date) {
                    model.value = formatDateForDisplay(date);
                } else {
                    model.value = "";
                }
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
    <div class="form-control w-full">
        <div class="relative w-full">
        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none z-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/60">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
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
    </div>
</template>
