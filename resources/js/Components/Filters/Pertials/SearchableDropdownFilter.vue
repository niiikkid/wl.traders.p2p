<script setup>
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {useTableFiltersStore} from '@/store/tableFilters.js';

const tableFiltersStore = useTableFiltersStore();

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

const model = computed({
    get: () => tableFiltersStore.filters[props.name] ?? '',
    set: (value) => {
        tableFiltersStore.filters[props.name] = value;
    },
});

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
        <button
            class="input input-bordered input-sm w-full flex items-center justify-between focus:outline-none focus:ring-0"
            type="button"
            @click.stop="toggleDropdown"
            :aria-expanded="isOpen ? 'true' : 'false'"
        >
            <div class="flex w-full items-center gap-2 min-w-0">
                <span v-if="selectedCount" class="badge badge-primary badge-xs flex-none">
                    {{ selectedCount }}
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/60 flex-none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                </svg>
                <span class="truncate">{{ title }}</span>
                <svg class="ml-auto size-4 text-base-content/60 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </div>
        </button>

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
