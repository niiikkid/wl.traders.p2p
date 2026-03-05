<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: [String, Array],
    },
});

const displayMessage = computed(() => {
    if (Array.isArray(props.message)) {
        return props.message[0] || '';
    }
    return props.message || '';
});
</script>

<template>
    <div v-show="displayMessage">
        <p class="text-sm text-error">
            {{ displayMessage }}
        </p>
    </div>
</template>
