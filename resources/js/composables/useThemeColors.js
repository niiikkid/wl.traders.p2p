import { onBeforeUnmount, onMounted, ref } from 'vue';

const DEFAULT_TOKENS = [
    'primary',
    'secondary',
    'accent',
    'info',
    'success',
    'warning',
    'error',
    'base-content',
    'base-300',
];

/**
 * Resolves DaisyUI semantic color tokens (e.g. `primary`, `base-content`) into
 * concrete `rgb(...)` strings that ECharts can paint on a canvas, and keeps them
 * in sync when the active `data-theme` changes.
 *
 * A single hidden probe element per hook instance reads the computed color of
 * `var(--color-<token>)`, which the browser resolves to an rgb triple.
 *
 * @param {string[]} tokens
 * @returns {{ colors: import('vue').Ref<Record<string, string>>, resolve: (token: string) => string }}
 */
export function useThemeColors(tokens = DEFAULT_TOKENS) {
    const colors = ref({});
    let probe = null;
    let observer = null;
    let scheduledUpdate = false;

    const resolve = (token) => {
        if (!probe) {
            return '';
        }
        probe.style.color = `var(--color-${token})`;
        return getComputedStyle(probe).color || '';
    };

    const refresh = () => {
        const next = {};
        tokens.forEach((token) => {
            next[token] = resolve(token);
        });
        colors.value = next;
    };

    onMounted(() => {
        if (typeof document === 'undefined') {
            return;
        }
        probe = document.createElement('span');
        probe.style.position = 'absolute';
        probe.style.left = '-9999px';
        probe.style.width = '0';
        probe.style.height = '0';
        probe.style.pointerEvents = 'none';
        probe.setAttribute('aria-hidden', 'true');
        document.body.appendChild(probe);
        refresh();

        observer = new MutationObserver(() => {
            if (scheduledUpdate) {
                return;
            }
            scheduledUpdate = true;
            requestAnimationFrame(() => {
                refresh();
                scheduledUpdate = false;
            });
        });
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme'],
        });
    });

    onBeforeUnmount(() => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        if (probe && probe.parentNode) {
            probe.parentNode.removeChild(probe);
        }
        probe = null;
    });

    return { colors, resolve, refresh };
}

/**
 * Turns an `rgb(r, g, b)` string into an `rgba(r, g, b, a)` string. Falls back
 * to the original value when the input is not a plain rgb triple.
 *
 * @param {string} rgb
 * @param {number} alpha
 * @returns {string}
 */
export function withAlpha(rgb, alpha) {
    if (typeof rgb !== 'string') {
        return rgb;
    }
    const match = rgb.match(/^rgba?\(([^)]+)\)$/);
    if (!match) {
        return rgb;
    }
    const [r, g, b] = match[1].split(',').map((part) => part.trim());
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
