<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

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
    allowToggleOff: {
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
    },
    teleportDropdown: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const DROPDOWN_GAP_PX = 4;
const DROPDOWN_Z_INDEX = 10060;

const selectedOptions = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : []);
const isOpen = ref(false);
const searchQuery = ref('');
const rootEl = ref(null);
const dropdownEl = ref(null);
const dropdownStyle = ref({});

// Следим за внешними изменениями v-model и синхронизируем локальное состояние
watch(
    () => props.modelValue,
    (newValue) => {
        selectedOptions.value = Array.isArray(newValue) ? [...newValue] : [];
    },
    { immediate: true, deep: true }
);

const updateDropdownPosition = () => {
    if (!props.teleportDropdown || !isOpen.value || !rootEl.value) {
        return;
    }

    const rect = rootEl.value.getBoundingClientRect();

    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + DROPDOWN_GAP_PX}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: DROPDOWN_Z_INDEX,
    };
};

const toggleDropdown = async () => {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (!isOpen.value) {
        searchQuery.value = '';
        return;
    }

    await nextTick();
    updateDropdownPosition();
};

const selectOption = (option) => {
    if (props.disabled) return;
    const optionValue = option[props.valueKey];
    
    if (props.singleSelect) {
        const alreadySelected = selectedOptions.value.includes(optionValue);

        if (alreadySelected && props.allowToggleOff) {
            if (!props.canUnselect(optionValue)) {
                return;
            }
            selectedOptions.value = [];
            emit('update:modelValue', selectedOptions.value);
            emit('change', selectedOptions.value);
            return;
        }

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

const dropdownPanelClass = 'p-0 shadow bg-base-100 rounded-box border border-base-300 overflow-x-hidden max-h-60 overflow-y-auto';

const dropdownPositionClass = computed(() => (
    props.teleportDropdown
        ? ''
        : 'absolute left-0 top-full z-50 mt-1 w-full'
));

// Close on outside click
const handleClickOutside = (event) => {
    const target = event.target;

    if (rootEl.value?.contains(target)) {
        return;
    }

    if (dropdownEl.value?.contains(target)) {
        return;
    }

    isOpen.value = false;
};

const handleReposition = () => {
    updateDropdownPosition();
};

let removeRepositionListeners = null;

const attachRepositionListeners = () => {
    const handler = () => handleReposition();

    window.addEventListener('resize', handler);
    window.addEventListener('scroll', handler, true);

    return () => {
        window.removeEventListener('resize', handler);
        window.removeEventListener('scroll', handler, true);
    };
};

watch(isOpen, async (open) => {
    removeRepositionListeners?.();
    removeRepositionListeners = null;

    if (!open || !props.teleportDropdown) {
        return;
    }

    await nextTick();
    updateDropdownPosition();
    removeRepositionListeners = attachRepositionListeners();
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    removeRepositionListeners?.();
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
        <Teleport to="body" :disabled="!teleportDropdown">
            <div
                v-if="isOpen"
                ref="dropdownEl"
                :style="teleportDropdown ? dropdownStyle : undefined"
                :class="[dropdownPanelClass, dropdownPositionClass]"
                tabindex="0"
                @click.stop
            >
                <div v-if="enableSearch" class="border-b border-base-300 p-2">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="input input-bordered input-sm w-full"
                        placeholder="Поиск..."
                        :disabled="disabled"
                        @click="onSearchInput"
                    />
                </div>
                <ul class="menu menu-sm w-full">
                    <li v-for="option in filteredOptions" :key="option[valueKey]">
                        <a
                            class="flex items-center gap-2"
                            href="#"
                            :class="{
                                'pointer-events-none opacity-50': disabled
                                    || (singleSelect && selectedOptions.length > 0 && !canUnselect(selectedOptions[0]))
                                    || (isSelected(option) && !canUnselect(option[valueKey])),
                            }"
                            @click.prevent="selectOption(option)"
                        >
                            <input
                                :type="singleSelect ? 'radio' : 'checkbox'"
                                :class="singleSelect ? 'radio radio-sm' : 'checkbox checkbox-sm'"
                                :checked="isSelected(option)"
                                :name="singleSelect ? 'multiselect-radio' : ''"
                                :disabled="disabled
                                    || (singleSelect && selectedOptions.length > 0 && !canUnselect(selectedOptions[0]))
                                    || (isSelected(option) && !canUnselect(option[valueKey]))"
                            />
                            <span class="truncate">{{ option[labelKey] }}</span>
                        </a>
                    </li>
                    <li v-if="enableSearch && filteredOptions.length === 0" class="px-4 py-2 opacity-70">
                        Ничего не найдено
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
