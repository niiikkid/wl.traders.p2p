import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Minimal dropdown state helper with outside-click dismissal. Bind `el` to the
 * dropdown root element; clicks outside it close the dropdown.
 */
export function useDropdown() {
    const isOpen = ref(false);
    const el = ref(null);

    const blurActive = () => {
        if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
    };

    const open = () => {
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
        blurActive();
    };

    const toggle = () => {
        if (isOpen.value) {
            close();
        } else {
            open();
        }
    };

    const handlePointerDown = (event) => {
        if (!isOpen.value) {
            return;
        }
        const target = event.target;
        if (
            el.value instanceof HTMLElement
            && target instanceof Node
            && el.value.contains(target)
        ) {
            return;
        }
        close();
    };

    onMounted(() => {
        if (typeof document !== 'undefined') {
            document.addEventListener('pointerdown', handlePointerDown, true);
        }
    });

    onBeforeUnmount(() => {
        if (typeof document !== 'undefined') {
            document.removeEventListener('pointerdown', handlePointerDown, true);
        }
    });

    return { isOpen, el, open, close, toggle };
}
