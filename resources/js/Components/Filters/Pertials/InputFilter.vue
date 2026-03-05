<script setup>
import {useTableFiltersStore} from "@/store/tableFilters.js";
import {computed, inject} from "vue";

const tableFiltersStore = useTableFiltersStore();
const applyFilters = inject('applyFilters', null);

const props = defineProps({
    name: {
        type: String,
    },
    placeholder: {
        type: String,
    },
});

const model = computed({
    get: () => tableFiltersStore.filters[props.name],
    set: (val) => {
        tableFiltersStore.filters[props.name] = val
    }
})
</script>

<template>
    <div class="form-control w-full">
        <input
            type="text"
            :id="$.uid"
            v-model="model"
            :placeholder="placeholder"
            class="input input-bordered input-sm w-full"
            @keydown.enter.prevent="applyFilters && applyFilters()"
        >
    </div>
</template>

<style scoped>

</style>
