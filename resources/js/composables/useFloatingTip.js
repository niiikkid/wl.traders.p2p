import { nextTick, onBeforeUnmount, ref, unref, watch } from 'vue';

const GAP_PX = 8;

/**
 * @param {import('vue').Ref<HTMLElement | null> | HTMLElement | null} triggerRef
 * @param {import('vue').MaybeRefOrGetter<string>} placement
 */
export function useFloatingTip(triggerRef, placement = 'top') {
    const visible = ref(false);
    const style = ref({});

    const updatePosition = () => {
        const el = unref(triggerRef);

        if (!el || !visible.value) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const side = unref(placement) ?? 'top';
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        let left = centerX;
        let top = rect.top - GAP_PX;
        let transform = 'translate(-50%, -100%)';

        if (side === 'bottom') {
            top = rect.bottom + GAP_PX;
            transform = 'translate(-50%, 0)';
        } else if (side === 'left') {
            left = rect.left - GAP_PX;
            top = centerY;
            transform = 'translate(-100%, -50%)';
        } else if (side === 'right') {
            left = rect.right + GAP_PX;
            top = centerY;
            transform = 'translate(0, -50%)';
        }

        style.value = {
            left: `${left}px`,
            top: `${top}px`,
            transform,
        };
    };

    const show = () => {
        visible.value = true;
        nextTick(updatePosition);
    };

    const hide = () => {
        visible.value = false;
    };

    let removeListeners = null;

    const attachListeners = () => {
        const handler = () => updatePosition();
        window.addEventListener('scroll', handler, true);
        window.addEventListener('resize', handler);

        return () => {
            window.removeEventListener('scroll', handler, true);
            window.removeEventListener('resize', handler);
        };
    };

    watch(visible, (isVisible) => {
        removeListeners?.();
        removeListeners = null;

        if (isVisible) {
            removeListeners = attachListeners();
        }
    });

    onBeforeUnmount(() => {
        removeListeners?.();
    });

    return {
        visible,
        style,
        show,
        hide,
        updatePosition,
    };
}
