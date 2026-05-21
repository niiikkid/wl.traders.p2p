export const releaseSelectionAndFocusBeforeModalOpen = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    try {
        const selection = window.getSelection?.();
        if (selection && selection.rangeCount > 0) {
            selection.removeAllRanges();
        }
    } catch (error) {
        // ignore browser-specific selection errors
    }

    try {
        const activeElement = document.activeElement;
        if (activeElement && typeof activeElement.blur === 'function') {
            activeElement.blur();
        }
    } catch (error) {
        // ignore focus errors
    }
};
