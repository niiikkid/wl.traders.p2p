<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'onShow', 'onHide']);

watch(
    () => props.show,
    () => {
        if (props.show) {
            emit('onShow');
            document.body.style.overflow = 'hidden';
        } else {
            emit('onHide');
            document.body.style.overflow = null;
        }
    }
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
        '5xl': 'sm:max-w-5xl',
        '6xl': 'sm:max-w-6xl',
        '7xl': 'sm:max-w-7xl',
    }[props.maxWidth];
});
</script>

<template>
    <Teleport defer to="body">
        <div :class="['modal p-1 sm:p-6', show ? 'modal-open' : '']" @keydown.esc.prevent="close">
            <div class="modal-box max-h-[calc(100dvh-3rem)] sm:max-h-[calc(100dvh-4rem)] overflow-auto" :class="maxWidthClass">
                <slot v-if="show" />
            </div>
            <div class="modal-backdrop" @click="close" />
        </div>
    </Teleport>

</template>
