/**
 * @param {string|null|undefined} roleName
 * @returns {string|null}
 */
export function roleLabel(roleName) {
    if (!roleName) {
        return null;
    }

    const labels = {
        'Super Admin': 'администратор',
        Trader: 'трейдер',
        Merchant: 'мерчант',
        'Team Leader': 'тимлидер',
        Support: 'поддержка',
        Analyst: 'аналитик',
        'Provider Liquidity': 'провайдер ликвидности',
    };

    return labels[roleName] ?? null;
}
