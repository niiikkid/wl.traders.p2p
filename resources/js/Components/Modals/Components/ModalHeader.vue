<script setup>
const emit = defineEmits(['close']);

const props = defineProps({
    title: {
        type: String,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <div class="flex items-center justify-between pb-3">
        <h3 class="hidden sm:block text-xl font-semibold">
            {{ title }}
        </h3>
        <h3 class="block sm:hidden text-sm font-semibold">
            {{ title }}
        </h3>
        <button @click.prevent="close" type="button" class="btn btn-ghost btn-sm btn-circle">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
            <span class="sr-only">Закрыть</span>
        </button>
    </div>
    <div class="divider my-0"></div>
</template>

<style scoped>

</style>
