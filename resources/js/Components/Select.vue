<script setup>
const model = defineModel({
    required: true,
});
defineProps({
    error: {
        type: Boolean,
        default: false,
    },
    /** '', 'sm' — compact select */
    size: {
        type: String,
        default: '',
    },
    items: {
        type: Object,
    },
    value: {
        type: String,
    },
    name: {
        type: String,
    },
    default_title: {
        type: String,
    },
    default_value: {
        default: "0",
    },
    required: {
        type: Boolean,
        default: true,
    },
});

</script>

<template>
    <select
        :class="[
            !error
                ? 'select select-bordered appearance-none w-full'
                : 'select select-bordered appearance-none select-error w-full',
            size === 'sm' ? 'select-sm text-xs' : '',
        ]"
        :required="required"
        v-model="model"
    >
        <option :value="default_value" selected>{{ default_title }}</option>
        <option v-for="item in items" :value="item[value]">{{ item[name] }}</option>
    </select>
</template>

<style scoped>

</style>
