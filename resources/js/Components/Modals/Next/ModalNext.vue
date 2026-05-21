<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { releaseSelectionAndFocusBeforeModalOpen } from '@/utils/releaseSelectionAndFocusBeforeModalOpen.js';

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
            releaseSelectionAndFocusBeforeModalOpen();
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
    const map = {
        sm: 'justify-self-center max-w-none w-[min(24rem,calc(100vw-1.5rem))]',
        md: 'justify-self-center max-w-none w-[min(28rem,calc(100vw-1.5rem))]',
        lg: 'justify-self-center max-w-none w-[min(32rem,calc(100vw-1.5rem))]',
        xl: 'justify-self-center max-w-none w-[min(36rem,calc(100vw-1.5rem))]',
        '2xl': 'justify-self-center max-w-none w-[min(42rem,calc(100vw-1.5rem))]',
        '3xl': 'justify-self-center max-w-none w-[min(48rem,calc(100vw-1.5rem))]',
        '4xl': 'justify-self-center max-w-none w-[min(56rem,calc(100vw-1.5rem))]',
        '5xl': 'justify-self-center max-w-none w-[min(64rem,calc(100vw-1.5rem))]',
        '6xl': 'justify-self-center max-w-none w-[min(72rem,calc(100vw-1.5rem))]',
        '7xl': 'justify-self-center max-w-none w-[min(80rem,calc(100vw-1.5rem))]',
    };
    return map[props.maxWidth] ?? map['2xl'];
});
</script>

<template>
    <Teleport defer to="body">
        <div
            data-modal-next-root
            :class="['modal modal-middle p-1.5 sm:p-5', show ? 'modal-open' : '']"
            @keydown.esc.prevent="close"
        >
            <div
                class="modal-box relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-base-300/60 bg-base-100 p-0 text-sm leading-normal text-base-content antialiased shadow-xl sm:text-base max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)]"
                :class="maxWidthClass"
            >
                <slot v-if="show" />
            </div>
            <div class="modal-backdrop bg-neutral/40 backdrop-blur-[2px]" @click="close" />
        </div>
    </Teleport>
</template>
