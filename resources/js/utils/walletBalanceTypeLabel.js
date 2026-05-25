/**
 * @param {string|null|undefined} balanceType
 * @param {{ sharedReserveContext?: boolean }} [options]
 * @returns {string|null}
 */
export function walletBalanceTypeLabel(balanceType, options = {}) {
    if (!balanceType) {
        return null;
    }

    const sharedReserveContext = options.sharedReserveContext === true;

    const labels = {
        trust: 'Траст',
        merchant: 'Мерчант',
        teamleader: sharedReserveContext ? 'Доход тимлидера' : 'Тимлид',
        provider: 'Провайдер',
        agent: 'Агент',
        reserve: sharedReserveContext ? 'Страховой резерв' : 'Резерв',
    };

    return labels[balanceType] ?? balanceType;
}
