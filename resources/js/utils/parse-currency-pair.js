/**
 * @param {string} value
 * @returns {string}
 */
function normalizeCode(value) {
    return value.trim().toUpperCase();
}

/**
 * @param {{
 *   pair?: unknown,
 *   base_currency?: unknown,
 *   quote_currency?: unknown,
 * }} input
 * @returns {{ baseCurrency: string, quoteCurrency: string, label: string }}
 */
export function parseCurrencyPair(input) {
    const baseRaw = typeof input.base_currency === 'string' ? input.base_currency.trim() : '';
    const quoteRaw = typeof input.quote_currency === 'string' ? input.quote_currency.trim() : '';

    if (baseRaw && quoteRaw) {
        const baseCurrency = normalizeCode(baseRaw);
        const quoteCurrency = normalizeCode(quoteRaw);

        return {
            baseCurrency,
            quoteCurrency,
            label: `${baseCurrency}/${quoteCurrency}`,
        };
    }

    const pairRaw = typeof input.pair === 'string' ? input.pair.trim() : '';

    if (!pairRaw) {
        return { baseCurrency: '', quoteCurrency: '', label: '' };
    }

    const [basePart, quotePart] = pairRaw.split('/').map((part) => part.trim());

    if (basePart && quotePart) {
        const baseCurrency = normalizeCode(basePart);
        const quoteCurrency = normalizeCode(quotePart);

        return {
            baseCurrency,
            quoteCurrency,
            label: `${baseCurrency}/${quoteCurrency}`,
        };
    }

    return { baseCurrency: '', quoteCurrency: '', label: pairRaw };
}
