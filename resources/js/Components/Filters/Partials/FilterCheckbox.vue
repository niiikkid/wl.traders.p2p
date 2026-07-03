<script setup>
import {useFilterModel} from "@/composables/useFilterModel.js";
import FilterField from "@/Components/Filters/Partials/FilterField.vue";

const props = defineProps({
    name: {
        type: String,
    },
    title: {
        type: String,
    },
});

const model = useFilterModel(props.name);
</script>

<template>
    <FilterField>
        <label :for="`checkbox-${$.uid}`" class="flex h-8 cursor-pointer items-center gap-2">
            <input
                v-model="model"
                :id="`checkbox-${$.uid}`"
                type="checkbox"
                value=""
                class="checkbox checkbox-sm shrink-0"
            >
            <span class="text-sm leading-none">{{ title }}</span>
        </label>
    </FilterField>
</template>
