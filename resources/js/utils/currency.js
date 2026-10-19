export function compareCurrenciesUsdFirst(left, right) {
    return Number(String(right ?? '').toLowerCase() === 'usd')
        - Number(String(left ?? '').toLowerCase() === 'usd');
}

export function sortCurrenciesUsdFirst(items, getCode = (currency) => currency) {
    return [...items].sort((left, right) => compareCurrenciesUsdFirst(getCode(left), getCode(right)));
}
