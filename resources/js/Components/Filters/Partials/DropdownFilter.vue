<script setup>
import {computed, watch, ref, onMounted, onBeforeUnmount} from "vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import {useFilterModel} from "@/composables/useFilterModel.js";
import FilterDropdownTrigger from "@/Components/Filters/Partials/FilterDropdownTrigger.vue";

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

const model = useFilterModel(props.name, '');

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
    const options = tableFiltersStore.getFiltersVariants[props.name] ?? [];

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

const selectedCount = computed(() => selectedOptions.value.filter(o => o.selected).length);

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
        <FilterDropdownTrigger
            :title="title"
            :selected-count="selectedCount"
            :is-open="isOpen"
            @toggle="toggleOpen"
        />
        <div
            class="dropdown-content z-10 mt-1 w-64 max-w-full p-3 bg-base-100 rounded-box shadow border border-base-300"
            v-show="isOpen"
            @click.stop
        >
            <h6 class="mb-3 text-sm font-medium">
                {{ title }}
            </h6>
            <ul class="space-y-1 text-sm">
                <li v-for="option in selectedOptions" :key="option.value" class="flex items-center">
                    <label class="flex items-center gap-2 w-full cursor-pointer select-none rounded px-2 py-1 hover:bg-base-200" @click.stop>
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
