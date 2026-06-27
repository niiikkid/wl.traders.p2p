<script setup>
import {useFilterModel} from "@/composables/useFilterModel.js";

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
