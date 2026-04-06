<script setup>
import { nextTick, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: null,
    },
});

const isOpen = ref(false);
const trigger = ref(null);
const popover = ref(null);
const position = ref({ top: 0, left: 0 });

const updatePosition = () => {
    if (!trigger.value || !popover.value) {
        return;
    }

    const triggerRect = trigger.value.getBoundingClientRect();
    const popoverRect = popover.value.getBoundingClientRect();
    const viewportPadding = 8;
    const offset = 8;

    const left = Math.min(
        Math.max(triggerRect.left, viewportPadding),
        Math.max(window.innerWidth - popoverRect.width - viewportPadding, viewportPadding)
    );

    let top = triggerRect.bottom + offset;

    if (top + popoverRect.height > window.innerHeight - viewportPadding) {
        top = triggerRect.top - popoverRect.height - offset;
    }

    if (top < viewportPadding) {
        top = viewportPadding;
    }

    position.value = { top, left };
};

const open = async () => {
    isOpen.value = true;
    await nextTick();
    updatePosition();
};

const close = () => {
    isOpen.value = false;
};

const toggle = async () => {
    if (isOpen.value) {
        close();
        return;
    }

    await open();
};

const onDocumentClick = (event) => {
    if (!isOpen.value) {
        return;
    }

    if (trigger.value?.contains(event.target) || popover.value?.contains(event.target)) {
        return;
    }

    close();
};

const onEscape = (event) => {
    if (event.key === "Escape" && isOpen.value) {
        close();
    }
};

const onViewportChange = () => {
    if (!isOpen.value) {
        return;
    }

    updatePosition();
};

onMounted(() => {
    document.addEventListener("click", onDocumentClick);
    document.addEventListener("keydown", onEscape);
    window.addEventListener("resize", onViewportChange);
    window.addEventListener("scroll", onViewportChange, true);
});

onUnmounted(() => {
    document.removeEventListener("click", onDocumentClick);
    document.removeEventListener("keydown", onEscape);
    window.removeEventListener("resize", onViewportChange);
    window.removeEventListener("scroll", onViewportChange, true);
});
</script>

<template>
    <span class="inline-flex shrink-0 align-middle">
        <button
            ref="trigger"
            type="button"
            class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-info"
            aria-label="Показать подсказку"
            :aria-expanded="isOpen"
            @click.stop="toggle"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-4"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                />
            </svg>
        </button>

        <teleport to="body">
            <div
                v-if="isOpen"
                ref="popover"
                class="fixed z-[10000] w-80 max-w-[calc(100vw-1rem)] rounded-box border border-base-300 bg-base-100 p-3 text-left text-xs leading-5 text-base-content shadow-xl"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                @click.stop
            >
                <div v-if="title" class="mb-1 font-medium">
                    {{ title }}
                </div>
                <div class="whitespace-pre-line break-words">
                    {{ text }}
                </div>
            </div>
        </teleport>
    </span>
</template>
