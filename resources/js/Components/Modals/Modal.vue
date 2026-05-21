<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';
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
    stackLevel: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close', 'onShow', 'onHide']);
const slots = useSlots();
const modalRootRef = ref(null);
const modalBoxRef = ref(null);
const asideCoordinates = ref({ top: 0, left: 0 });
const asideReady = ref(false);
let modalResizeObserver = null;

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

const modalStyle = computed(() => {
    if (props.stackLevel <= 0) {
        return undefined;
    }

    return { zIndex: 999 + props.stackLevel * 10 };
});

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

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show && isTopmostOpenModal()) {
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

const hasAside = computed(() => !!slots.aside);

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
        if (!value) {
            asideReady.value = false;
            return;
        }

        await prepareAsidePosition();
    }
);

onMounted(() => {
    window.addEventListener('resize', updateAsideCoordinates);
    window.addEventListener('scroll', updateAsideCoordinates, true);

    if (typeof ResizeObserver !== 'undefined') {
        modalResizeObserver = new ResizeObserver(() => {
            updateAsideCoordinates();
        });
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateAsideCoordinates);
    window.removeEventListener('scroll', updateAsideCoordinates, true);
    modalResizeObserver?.disconnect();
    modalResizeObserver = null;
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
            :class="['modal p-1 sm:p-6', show ? 'modal-open' : '']"
            :style="modalStyle"
            @keydown.esc.prevent="close"
        >
            <div ref="modalBoxRef" class="modal-box max-h-[calc(100dvh-3rem)] sm:max-h-[calc(100dvh-4rem)] overflow-auto" :class="maxWidthClass">
                <slot v-if="show" />
            </div>
            <div
                v-if="show && hasAside && asideReady"
                class="pointer-events-none fixed hidden xl:block"
                :style="{ top: `${asideCoordinates.top}px`, left: `${asideCoordinates.left}px` }"
            >
                <div class="pointer-events-auto">
                    <slot name="aside" />
                </div>
            </div>
            <div class="modal-backdrop" @click="close" />
        </div>
    </Teleport>

</template>
