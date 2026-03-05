<script setup>
import {getCurrentInstance} from "vue"


const props = defineProps({
    label: {
        type: String,
    },
    items: {
        type: Array,
    },
    value: {
        type: String,
    },
    name: {
        type: String,
    },
});

const model = defineModel({
    required: true,
});

const change = (item, event) => {
    if (event.target.checked) {
        model.value = item;
    } else {
        model.value = null;
    }
};

const {uid} = getCurrentInstance()
</script>

<template>
    <div class="dropdown">
        <div tabindex="0" role="button" class="input input-bordered w-48 flex items-center justify-between focus:outline-none focus:ring-0">
            <template v-if="model">{{ 'Валюта: '}}<span class="ml-1">{{ model }}</span></template>
            <template v-else>{{ label }}</template>
            <svg class="w-2.5 h-2.5 ms-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
            </svg>
        </div>

        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52 border border-base-300">
            <li v-for="(item, index) in items" :key="index">
                <label class="label cursor-pointer justify-start gap-3 px-2 py-2">
                    <input
                        :id="'radio-'+uid+'-'+index"
                        type="radio"
                        :name="'radio'+uid"
                        :value="item[value]"
                        :checked="model === item[value]"
                        @change="change(item[value], $event)"
                        class="radio radio-sm"
                    >
                    <span :for="'radio-'+uid+'-'+index" class="label-text text-sm">{{ item[name] }}</span>
                </label>
            </li>
        </ul>
    </div>
</template>

<style scoped>

</style>
