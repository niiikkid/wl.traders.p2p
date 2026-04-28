<script setup>
import {computed} from "vue";

const props = defineProps({
    size: {
        type: String,
        default: "",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: "button",
    },
});

const sizeClass = computed(() => {
    if (props.size === "sm") {
        return "btn-sm";
    }
    if (props.size === "xs") {
        return "btn-xs";
    }
    return "";
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="['btn', 'btn-primary', sizeClass]"
    >
        <slot />
    </button>
</template>
