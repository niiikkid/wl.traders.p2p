const ICON_BASE = '/images/currencies/';
const GENERIC_ICON_URL = `${ICON_BASE}generic.svg`;

/**
 * @param {unknown} value
 * @returns {string}
 */
export function normalizeIconKey(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

/**
 * @param {unknown} key
 * @returns {string}
 */
export function getCurrencyIconUrl(key) {
    const normalized = normalizeIconKey(key);

    if (!normalized) {
        return GENERIC_ICON_URL;
    }

    return `${ICON_BASE}${normalized}.svg`;
}

export function getGenericCurrencyIconUrl() {
    return GENERIC_ICON_URL;
}
