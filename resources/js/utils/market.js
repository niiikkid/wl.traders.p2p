export const REMOVED_MARKET_VALUES = ['rapira'];

export const DEFAULT_RUB_MARKET = 'bybit';

/**
 * @param {string|{ value?: string }|null|undefined} value
 * @returns {boolean}
 */
export function isRemovedMarket(value) {
    if (value === null || value === undefined || value === '') {
        return false;
    }

    const normalized = typeof value === 'string'
        ? value
        : (value?.value ?? String(value));

    return REMOVED_MARKET_VALUES.includes(String(normalized).toLowerCase());
}

/**
 * @param {Array<{ name?: string, value?: string }>|null|undefined} markets
 * @returns {Array<{ name?: string, value?: string }>}
 */
export function filterMarketOptions(markets) {
    if (!Array.isArray(markets)) {
        return [];
    }

    return markets.filter((market) => !isRemovedMarket(market?.value ?? market));
}

/**
 * @param {Record<string, unknown>|null|undefined} markets
 * @returns {string[]}
 */
export function filterMarketGroupKeys(markets) {
    if (!markets || typeof markets !== 'object') {
        return [];
    }

    return Object.keys(markets).filter((key) => !isRemovedMarket(key));
}
