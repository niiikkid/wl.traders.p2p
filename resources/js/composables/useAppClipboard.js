import { useClipboard } from '@vueuse/core';

/**
 * Clipboard helper with execCommand fallback when Clipboard API is unavailable.
 */
export function useAppClipboard(options = {}) {
    return useClipboard({ legacy: true, ...options });
}
