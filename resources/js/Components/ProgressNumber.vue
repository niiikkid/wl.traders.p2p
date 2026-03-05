<script setup>
import {computed} from "vue";

const props = defineProps({
    current: {
        type: String,
    },
    total: {
        type: String,
    },
});

const percent = computed(() => {
    return props.current / (props.total/100);
});

const color = computed(() => {
    if (percent.value < 40) {
        return 'bg-error';
    } else if (percent.value > 40 && percent.value < 80) {
        return 'bg-warning';
    } else if (percent.value > 80) {
        return 'bg-success';
    }
})

</script>

<template>
    <div class="flex justify-end mb-1">
        <span class="text-xs font-semibold text-base-content text-nowrap">
            {{ current }} / {{ total }}
        </span>
    </div>
    <div class="w-full bg-primary rounded-full h-1.5">
        <div class="h-1.5 rounded-full" :class="color" :style="'width: '+ percent + '%'"></div>
    </div>
</template>

<style scoped>

</style>
