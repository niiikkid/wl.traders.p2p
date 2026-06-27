<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';
import { releaseSelectionAndFocusBeforeModalOpen } from '@/utils/releaseSelectionAndFocusBeforeModalOpen.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: '2xl',
    },
    position: {
        type: String,
        default: 'middle',
        validator: (value) => ['middle', 'top', 'bottom'].includes(value),
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    stackLevel: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close', 'update:show', 'opened', 'closed']);
const slots = useSlots();

const modalRootRef = ref(null);
const modalBoxRef = ref(null);
const asideCoordinates = ref({ top: 0, left: 0 });
const asideReady = ref(false);
let modalResizeObserver = null;

const hasAside = computed(() => !!slots.aside);

const sizeClass = computed(() => {
    const map = {
        sm: 'w-[min(24rem,calc(100vw-1.5rem))]',
        md: 'w-[min(28rem,calc(100vw-1.5rem))]',
        lg: 'w-[min(32rem,calc(100vw-1.5rem))]',
        xl: 'w-[min(36rem,calc(100vw-1.5rem))]',
        '2xl': 'w-[min(42rem,calc(100vw-1.5rem))]',
        '3xl': 'w-[min(48rem,calc(100vw-1.5rem))]',
        '4xl': 'w-[min(56rem,calc(100vw-1.5rem))]',
        '5xl': 'w-[min(64rem,calc(100vw-1.5rem))]',
        '6xl': 'w-[min(72rem,calc(100vw-1.5rem))]',
        '7xl': 'w-[min(80rem,calc(100vw-1.5rem))]',
    };

    return map[props.size] ?? map['2xl'];
});

const positionClass = computed(() => {
    return {
        middle: 'modal-middle',
        top: 'modal-top',
        bottom: 'modal-bottom',
    }[props.position] ?? 'modal-middle';
});

const stackStyle = computed(() => {
    if (props.stackLevel <= 0) {
        return undefined;
    }

    return { zIndex: 999 + props.stackLevel * 10 };
});

const close = () => {
    if (!props.closeable) {
        return;
    }

    emit('close');
    emit('update:show', false);
};

const isTopmostOpenModal = () => {
    if (!modalRootRef.value) {
        return false;
    }

    const openModals = [...document.querySelectorAll('.modal.modal-open')];

    if (openModals.length === 0) {
        return false;
    }

    const topModal = openModals.reduce((best, element) => {
        const zIndex = Number.parseInt(window.getComputedStyle(element).zIndex, 10) || 0;
        const bestZIndex = Number.parseInt(window.getComputedStyle(best).zIndex, 10) || 0;

        return zIndex >= bestZIndex ? element : best;
    });

    return topModal === modalRootRef.value;
};

const closeOnEscape = (event) => {
    if (event.key === 'Escape' && props.show && isTopmostOpenModal()) {
        close();
    }
};

const updateAsideCoordinates = () => {
    if (!props.show || !hasAside.value || !modalBoxRef.value) {
        return;
    }

    const rect = modalBoxRef.value.getBoundingClientRect();
    asideCoordinates.value = {
        top: rect.top,
        left: rect.right + 16,
    };
};

const prepareAsidePosition = async () => {
    asideReady.value = false;
    await nextTick();
    await new Promise((resolve) => requestAnimationFrame(resolve));
    updateAsideCoordinates();
    asideReady.value = true;
};

watch(
    () => props.show,
    async (value) => {
        if (value) {
            releaseSelectionAndFocusBeforeModalOpen();
            document.body.style.overflow = 'hidden';
            emit('opened');

            if (hasAside.value) {
                await prepareAsidePosition();
            }

            return;
        }

        asideReady.value = false;
        document.body.style.overflow = null;
        emit('closed');
    }
);

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('resize', updateAsideCoordinates);
    window.addEventListener('scroll', updateAsideCoordinates, true);

    if (typeof ResizeObserver !== 'undefined') {
        modalResizeObserver = new ResizeObserver(() => updateAsideCoordinates());
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', updateAsideCoordinates);
    window.removeEventListener('scroll', updateAsideCoordinates, true);
    modalResizeObserver?.disconnect();
    modalResizeObserver = null;
    document.body.style.overflow = null;
});

watch(
    modalBoxRef,
    (element, previousElement) => {
        if (previousElement) {
            modalResizeObserver?.unobserve(previousElement);
        }

        if (element) {
            modalResizeObserver?.observe(element);
            updateAsideCoordinates();
        }
    },
    { flush: 'post' }
);
</script>

<template>
    <Teleport defer to="body">
        <div
            ref="modalRootRef"
            data-modal-root
            :class="['modal p-1.5 sm:p-5', positionClass, show ? 'modal-open' : '']"
            :style="stackStyle"
        >
            <div
                ref="modalBoxRef"
                class="modal-box relative flex min-h-0 min-w-0 max-w-none flex-col overflow-hidden rounded-2xl border border-base-300/60 bg-base-100 p-0 text-sm leading-normal text-base-content antialiased shadow-xl justify-self-center max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] sm:text-base"
                :class="sizeClass"
            >
                <slot v-if="show" :close="close" />
            </div>

            <div
                v-if="show && hasAside && asideReady"
                class="pointer-events-none fixed hidden xl:block"
                :style="{ top: `${asideCoordinates.top}px`, left: `${asideCoordinates.left}px` }"
            >
                <div class="pointer-events-auto">
                    <slot name="aside" :close="close" />
                </div>
            </div>

            <div class="modal-backdrop bg-neutral/40 backdrop-blur-[2px]" @click="close" />
        </div>
    </Teleport>
</template>
