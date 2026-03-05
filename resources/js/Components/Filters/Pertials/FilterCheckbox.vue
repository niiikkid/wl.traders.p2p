<script setup>
import {computed} from "vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const tableFiltersStore = useTableFiltersStore();

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
</script>

<template>
    <div class="form-control w-full flex items-center">
        <label :for="`checkbox-${$.uid}`" class="label cursor-pointer justify-start gap-2 py-0">
            <input
                v-model="model"
                :id="`checkbox-${$.uid}`"
                type="checkbox"
                value=""
                class="checkbox checkbox-sm"
            >
            <span class="text-sm">{{ title }}</span>
        </label>
    </div>
</template>

<style scoped>

</style>
