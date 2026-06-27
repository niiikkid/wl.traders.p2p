<script setup>
import { computed } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'end',
        validator: (value) => ['start', 'center', 'end', 'between'].includes(value),
    },
});

const alignClass = computed(() => {
    return {
        start: 'justify-start',
        center: 'justify-center',
        end: 'justify-end',
        between: 'justify-between',
    }[props.align] ?? 'justify-end';
});
</script>

<template>
    <footer
        class="modal-action mt-0 shrink-0 border-t border-base-300/50 bg-base-200/20 px-4 py-2.5 sm:px-5 sm:py-3"
    >
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2" :class="alignClass">
            <slot />
        </div>
    </footer>
</template>
