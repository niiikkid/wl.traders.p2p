<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    options: {
        type: Array,
        required: true
    },
    modelValue: {
        type: Array,
        default: () => []
    },
    labelKey: {
        type: String,
        default: 'label'
    },
    valueKey: {
        type: String,
        default: 'value'
    },
    enableSearch: {
        type: Boolean,
        default: false
    },
    placeholder: {
        type: String,
        default: 'Выберите опции'
    },
    singleSelect: {
        type: Boolean,
        default: false
    },
    canUnselect: {
        type: Function,
        default: () => true
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const selectedOptions = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : []);
const isOpen = ref(false);
const searchQuery = ref('');
const rootEl = ref(null);

// Следим за внешними изменениями v-model и синхронизируем локальное состояние
watch(
    () => props.modelValue,
    (newValue) => {
        selectedOptions.value = Array.isArray(newValue) ? [...newValue] : [];
    },
    { immediate: true, deep: true }
);

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        searchQuery.value = '';
    }
};

const selectOption = (option) => {
    if (props.disabled) return;
    const optionValue = option[props.valueKey];
    
    if (props.singleSelect) {
        if (selectedOptions.value.length > 0 && !props.canUnselect(selectedOptions.value[0])) {
            return;
        }
        selectedOptions.value = [optionValue];
    } else {
        if (selectedOptions.value.includes(optionValue)) {
            if (!props.canUnselect(optionValue)) {
                return;
            }
            selectedOptions.value = selectedOptions.value.filter(item => item !== optionValue);
        } else {
            selectedOptions.value.push(optionValue);
        }
    }
    emit('update:modelValue', selectedOptions.value);
    emit('change', selectedOptions.value);
};

const isSelected = (option) => selectedOptions.value.includes(option[props.valueKey]);

const selectedLabels = computed(() =>
    props.options.filter(opt => selectedOptions.value.includes(opt[props.valueKey])).map(opt => opt[props.labelKey]).join(', ')
);

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => 
        option[props.labelKey].toLowerCase().includes(query)
    );
});

const onSearchInput = (event) => {
    if (props.disabled) return;
    event.stopPropagation();
};

watch(
    () => props.disabled,
    (state) => {
        if (state) {
            isOpen.value = false;
            searchQuery.value = '';
        }
    }
);

// Закрытие по клику вне компонента
const handleClickOutside = (event) => {
    if (rootEl.value && !rootEl.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="rootEl">
        <div
            class="input input-bordered w-full justify-between focus:outline-none focus:ring-0"
            @click.stop="toggleDropdown"
            :tabindex="disabled ? -1 : 0"
            role="button"
            :aria-disabled="disabled ? 'true' : 'false'"
            :class="{ 'input-disabled opacity-70 cursor-not-allowed pointer-events-none': disabled }"
        >
            <span class="truncate text-left">{{ selectedLabels || placeholder }}</span>
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
        <div v-if="isOpen" class="absolute left-0 top-full z-50 w-full mt-1 p-0 shadow bg-base-100 rounded-box border border-base-300 overflow-x-hidden max-h-60 overflow-y-auto" tabindex="0" @click.stop>
            <div v-if="enableSearch" class="p-2 border-b border-base-300">
                <input
                    type="text"
                    v-model="searchQuery"
                    class="input input-bordered input-sm w-full"
                    placeholder="Поиск..."
                    @click="onSearchInput"
                    :disabled="disabled"
                />
            </div>
            <ul class="menu menu-sm w-full">
                <li v-for="option in filteredOptions" :key="option[valueKey]" class="">
                    <a @click.prevent="selectOption(option)" class="flex items-center gap-2"
                       :class="{
                           'opacity-50 pointer-events-none': disabled ||
                               (singleSelect && selectedOptions.length > 0 && !canUnselect(selectedOptions[0])) ||
                               (isSelected(option) && !canUnselect(option[valueKey]))
                       }">
                        <input
                            :type="singleSelect ? 'radio' : 'checkbox'"
                            :class="singleSelect ? 'radio radio-sm' : 'checkbox checkbox-sm'"
                            :checked="isSelected(option)"
                            :name="singleSelect ? 'multiselect-radio' : ''"
                            :disabled="disabled || (singleSelect && selectedOptions.length > 0 && !canUnselect(selectedOptions[0])) ||
                                     (isSelected(option) && !canUnselect(option[valueKey]))"
                        />
                        <span class="truncate">{{ option[labelKey] }}</span>
                    </a>
                </li>
                <li v-if="enableSearch && filteredOptions.length === 0" class="px-4 py-2 opacity-70">
                    Ничего не найдено
                </li>
            </ul>
        </div>
    </div>
</template>
