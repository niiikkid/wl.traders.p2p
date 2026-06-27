<script setup>
import {inject} from "vue";
import {useFilterModel} from "@/composables/useFilterModel.js";
import FilterField from "@/Components/Filters/Partials/FilterField.vue";

const props = defineProps({
    name: {
        type: String,
    },
    placeholder: {
        type: String,
    },
    label: {
        type: String,
        default: '',
    },
});

const applyFilters = inject('applyFilters', null);
const model = useFilterModel(props.name);
</script>

<template>
    <FilterField :label="label">
        <input
            type="text"
            :id="$.uid"
            v-model="model"
            :placeholder="placeholder"
            class="input input-bordered input-sm w-full"
            @keydown.enter.prevent="applyFilters && applyFilters()"
        >
    </FilterField>
</template>
