<script setup>
const model = defineModel({
    required: true,
});
defineProps({
    error: {
        type: Boolean,
        default: false,
    },
    rows: {
        type: Number,
        default: 4
    },
    required: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <textarea
        id="message"
        v-model="model"
        :rows="rows"
        :required="required"
        :class="!error
           ? 'textarea textarea-bordered w-full'
           : 'textarea textarea-bordered textarea-error w-full'"
    >
    </textarea>
</template>
