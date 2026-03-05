<script setup>
import {computed, getCurrentInstance, ref} from "vue"


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

const selectedTimes = computed(() => {
    return props.items.filter((item) => {
        return model.value.includes(item[props.value]);
    })
})

const change = (item, event) => {
    if (event.target.checked) {
        model.value.push(item);
    } else {
        const index = model.value.indexOf(item);
        if (index > -1) {
            model.value.splice(index, 1);
        }
    }
};

const {uid} = getCurrentInstance()
</script>

<template>
    <div class="sm:flex">
        <div class="dropdown">
            <div tabindex="0" role="button" class="input input-bordered w-48 flex items-center justify-between focus:outline-none focus:ring-0">
                {{ label }}
                <svg class="w-2.5 h-2.5 ms-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                </svg>
            </div>
            <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52 border border-base-300">
                <li v-for="(item, index) in items" :key="index" class="">
                    <label class="label cursor-pointer justify-start gap-3 px-2 py-2">
                        <input
                            :id="'checkbox-'+uid+'-'+index"
                            type="checkbox"
                            :value="item[value]"
                            :checked="selectedTimes.some(e => e[value] === item[value])"
                            @change="change(item[value], $event)"
                            class="checkbox checkbox-sm"
                        >
                        <span :for="'checkbox-'+uid+'-'+index" class="label-text text-sm">{{ item[name] }}</span>
                    </label>
                </li>
            </ul>
        </div>

        <div class="sm:ml-3 mt-3 sm:mt-0">
            <span v-for="item in selectedTimes" class="badge badge-ghost mr-2 my-1">
                {{item[name]}}
            </span>
        </div>
    </div>
</template>

<style scoped>

</style>
