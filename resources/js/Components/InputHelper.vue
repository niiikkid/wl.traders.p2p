<script setup>
const text = defineModel({
    type: String,
    required: true,
});
</script>

<template>
    <p id="helper-text-explanation" class="mt-2 text-sm text-base-content/70">{{ text }}</p>
</template>
