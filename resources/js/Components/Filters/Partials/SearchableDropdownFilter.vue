<script setup>
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {useFilterModel} from '@/composables/useFilterModel.js';
import FilterDropdownTrigger from '@/Components/Filters/Partials/FilterDropdownTrigger.vue';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: 'Фильтр',
    },
    placeholder: {
        type: String,
        default: 'Поиск...',
    },
    routeName: {
        type: String,
        required: true,
    },
    requestType: {
        type: String,
        required: true,
    },
    requestMode: {
        type: String,
        default: null,
    },
});

const model = useFilterModel(props.name, '');

const parseCsvValues = (value) => {
    if (Array.isArray(value)) {
        return value
            .map((item) => String(item).trim())
            .filter((item) => item.length > 0);
    }

    if (typeof value === 'string') {
        return value
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0);
    }

    return [];
};

const selectedIds = ref(parseCsvValues(model.value));
const selectedOptions = ref([]);
const searchResults = ref([]);
const searchQuery = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const rootRef = ref(null);
let debounceTimer = null;

const uniqueOptions = (items) => {
    const visited = new Set();

    return items.filter((item) => {
        const key = String(item.value);
        if (visited.has(key)) {
            return false;
        }

        visited.add(key);

        return true;
    });
};

const normalizeOption = (option) => ({
    value: String(option?.value ?? ''),
    label: String(option?.label ?? option?.name ?? ''),
    subtitle: option?.subtitle ? String(option.subtitle) : '',
});

const selectedCount = computed(() => selectedIds.value.length);

const displayedOptions = computed(() => {
    const selectedSet = new Set(selectedIds.value.map((item) => String(item)));
    const selected = selectedOptions.value.filter((item) => selectedSet.has(String(item.value)));
    const rest = searchResults.value.filter((item) => !selectedSet.has(String(item.value)));

    return uniqueOptions([...selected, ...rest]);
});

const isSelected = (value) => selectedIds.value.includes(String(value));

const syncModel = () => {
    model.value = selectedIds.value.join(',');
};

const loadOptions = async (query = '') => {
    isLoading.value = true;

    try {
        const response = await axios.get(route(props.routeName, {type: props.requestType}), {
            params: {
                query,
                selected_ids: selectedIds.value,
                mode: props.requestMode,
            },
        });

        const options = Array.isArray(response.data)
            ? response.data.map(normalizeOption).filter((item) => item.value && item.label)
            : [];

        searchResults.value = uniqueOptions(options);

        const selectedSet = new Set(selectedIds.value.map((item) => String(item)));
        const mappedSelected = options.filter((option) => selectedSet.has(String(option.value)));
        const keptSelected = selectedOptions.value.filter((option) => selectedSet.has(String(option.value)));
        selectedOptions.value = uniqueOptions([...mappedSelected, ...keptSelected]);
    } catch (error) {
        console.error('Ошибка загрузки опций фильтра', error);
    } finally {
        isLoading.value = false;
    }
};

const toggleOption = (option, event) => {
    const checked = event.target.checked;
    const value = String(option.value);

    if (checked) {
        if (!selectedIds.value.includes(value)) {
            selectedIds.value = [...selectedIds.value, value];
        }

        if (!selectedOptions.value.some((item) => String(item.value) === value)) {
            selectedOptions.value = uniqueOptions([option, ...selectedOptions.value]);
        }

        syncModel();

        return;
    }

    selectedIds.value = selectedIds.value.filter((item) => item !== value);
    selectedOptions.value = selectedOptions.value.filter((item) => String(item.value) !== value);
    syncModel();
};

const openDropdown = () => {
    isOpen.value = true;
    loadOptions(searchQuery.value);
};

const closeDropdown = () => {
    isOpen.value = false;
};

const toggleDropdown = (event) => {
    event?.stopPropagation?.();

    if (isOpen.value) {
        closeDropdown();

        return;
    }

    openDropdown();
};

const onDocumentClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        closeDropdown();
    }
};

watch(
    () => model.value,
    (value) => {
        const nextIds = parseCsvValues(value);
        const currentCsv = selectedIds.value.join(',');
        const nextCsv = nextIds.join(',');

        if (currentCsv === nextCsv) {
            return;
        }

        selectedIds.value = nextIds;
        selectedOptions.value = selectedOptions.value.filter((item) => nextIds.includes(String(item.value)));
    },
);

watch(searchQuery, (value) => {
    if (!isOpen.value) {
        return;
    }

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loadOptions(value);
    }, 300);
});

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="w-full dropdown" :class="{'dropdown-open': isOpen}">
        <FilterDropdownTrigger
            :title="title"
            :selected-count="selectedCount"
            :is-open="isOpen"
            @toggle="toggleDropdown"
        />

        <div
            class="dropdown-content z-20 mt-1 w-72 max-w-[calc(100vw-2rem)] p-3 bg-base-100 rounded-box shadow border border-base-300"
            v-show="isOpen"
            @click.stop
        >
            <div class="space-y-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="input input-bordered input-sm w-full"
                    :placeholder="placeholder"
                >

                <div class="max-h-64 overflow-y-auto border border-base-300 rounded-md p-2 space-y-1">
                    <div v-if="isLoading" class="text-sm text-base-content/60 py-2">
                        Загрузка...
                    </div>

                    <div
                        v-for="option in displayedOptions"
                        :key="`${name}-${option.value}`"
                        class="w-full"
                    >
                        <label class="flex w-full cursor-pointer items-center gap-3 px-2 py-1">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm shrink-0"
                                :checked="isSelected(option.value)"
                                @change="toggleOption(option, $event)"
                            >
                            <span class="flex flex-col min-w-0">
                                <span class="text-sm leading-4 break-words">{{ option.label }}</span>
                                <span v-if="option.subtitle" class="text-xs leading-4 text-base-content/50 break-words mt-0.5">
                                    {{ option.subtitle }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div v-if="!isLoading && displayedOptions.length === 0" class="text-sm text-base-content/60 py-2">
                        Ничего не найдено
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
