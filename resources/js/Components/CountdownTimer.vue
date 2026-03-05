<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    end: {
        type: String,
        required: true,
    },
    muted: {
        type: Boolean,
        default: false,
    },
});

const remainingMs = ref(0);
let timer = null;

const parseEnd = () => new Date(props.end).getTime();

const updateRemaining = () => {
    const diff = parseEnd() - Date.now();
    remainingMs.value = diff > 0 ? diff : 0;
    if (remainingMs.value === 0 && timer) {
        clearInterval(timer);
        timer = null;
    }
};

const format = computed(() => {
    const totalSeconds = Math.floor(remainingMs.value / 1000);
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
});

const restart = () => {
    if (timer) {
        clearInterval(timer);
    }
    updateRemaining();
    timer = setInterval(updateRemaining, 1000);
};

onMounted(() => {
    restart();
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
});

watch(
    () => props.end,
    () => {
        restart();
    }
);
</script>

<template>
    <span :class="['font-mono tabular-nums', muted ? 'text-neutral' : '']">
        {{ format }}
    </span>
</template>

