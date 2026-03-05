<script setup>
import {computed, watch, ref, onMounted, onBeforeUnmount} from "vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const tableFiltersStore = useTableFiltersStore();

const props = defineProps({
    name: {
        type: String,
    },
    title: {
        type: String,
        default: 'Фильтр'
    }
});

const model = computed({
    get: () => tableFiltersStore.filters[props.name] ?? '',
    set: (val) => {
        tableFiltersStore.filters[props.name] = val
    }
})

const normalizedValue = computed(() => {
    const value = model.value ?? '';

    if (Array.isArray(value)) {
        return value.filter(Boolean).map(String);
    }

    if (typeof value === 'string') {
        return value
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length)
            .map(String);
    }

    return [];
});

const selectedOptions = computed(() => {
    let options = tableFiltersStore.getFiltersVariants[props.name] ?? [];

    return options.map(i => {
        i.selected = normalizedValue.value.includes(String(i.value));

        return i;
    })
})

watch(
    () => selectedOptions.value,
    () => {
        model.value = selectedOptions.value.filter(o => o.selected).map(o => o.value).join(',');
    },
    { deep: true }
);

const selectedCount = computed(() => {
    return selectedOptions.value.filter(o => o.selected).length
})

// Управляем открытием вручную, чтобы клик по пунктам не закрывал список
const isOpen = ref(false);
const rootRef = ref(null);
const toggleOpen = (e) => {
    e?.stopPropagation?.();
    isOpen.value = !isOpen.value;
};
const close = () => {
    isOpen.value = false;
};
const onDocumentClick = (e) => {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        close();
    }
};
onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="w-full dropdown" :class="{'dropdown-open': isOpen}">
        <button
            :id="`filterDropdownButton-${$.uid}`"
            class="input input-bordered input-sm w-full flex items-center justify-between focus:outline-none focus:ring-0"
            type="button"
            @click.stop="toggleOpen"
            :aria-expanded="isOpen ? 'true' : 'false'"
        >
            <div class="flex w-full items-center gap-2">
                <span v-if="selectedCount" class="badge badge-primary badge-xs flex-none">
                    {{ selectedCount }}
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/60 flex-none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                </svg>
                <span class="text-nowrap">{{ title }}</span>
                <svg class="ml-auto size-4 text-base-content/60 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </div>
        </button>
        <div
            :id="`filterDropdown-${$.uid}`"
            class="dropdown-content z-10 w-64 max-w-full p-3 bg-base-100 rounded-box shadow border border-base-300"
            v-show="isOpen"
            @click.stop
        >
            <h6 class="mb-3 text-sm font-medium">
                {{ title }}
            </h6>
            <ul class="space-y-2 text-sm" aria-labelledby="dropdownDefault">
                <li
                    v-for="option in selectedOptions"
                    class="flex items-center"
                >
                    <label class="flex items-center gap-2 w-full cursor-pointer select-none rounded px-2 py-1" @click.stop>
                        <input
                            type="checkbox"
                            :value="option.value"
                            v-model="option.selected"
                            class="checkbox checkbox-sm"
                        />
                        <span class="text-sm font-medium">{{ option.name }}</span>
                    </label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>

</style>
