/**
 * Truncate trust balance down to 2 decimal places for trader transfer "transfer all".
 */
export function truncateTrustBalanceForTransfer(trustBalance) {
    const normalized = String(trustBalance ?? '').trim();

    if (normalized === '') {
        return '0.00';
    }

    const parts = normalized.split('.');
    const integer = parts[0] || '0';

    if (parts.length === 1) {
        return `${integer}.00`;
    }

    const fraction = (parts[1] ?? '').slice(0, 2).padEnd(2, '0');

    return `${integer}.${fraction}`;
}
