<script setup>
const props = defineProps({
    text: {
        type: String,
    },
});
</script>

<template>
    <button
        type="button"
        class="btn btn-primary w-full"
    >
        {{ text }}
    </button>
</template>

<style scoped>

</style>
