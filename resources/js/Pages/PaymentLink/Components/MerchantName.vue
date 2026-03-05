<script setup>
const props = defineProps({
    name: {
        type: String,
    },
});
</script>

<template>
    <h2 class="text-xl sm:text-2xl font-semibold text-base-content">{{ name }}</h2>
</template>

<style scoped>

</style>
