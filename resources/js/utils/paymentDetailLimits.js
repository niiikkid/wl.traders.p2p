/**
 * Helpers for rendering payment-detail limits and turnover figures.
 *
 * These are shared between the "Реквизиты" page (desktop table + mobile cards)
 * and the extracted limit/meta panels so the formatting stays consistent.
 */

export function normalizeNumber(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
}

export function formatInteger(value) {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 0,
    }).format(Math.trunc(number));
}

export function formatMoneyAmount(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const normalized = String(value).replace(/\s/g, '').replace(',', '.');
    const number = Number(normalized);

    if (!Number.isFinite(number)) {
        return String(value);
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

export function hasLimit(limit) {
    return normalizeNumber(limit) > 0;
}

export function percentFrom(current, limit) {
    const currentValue = normalizeNumber(current);
    const limitValue = normalizeNumber(limit);

    if (limitValue <= 0) {
        return 0;
    }

    return Math.min(100, (currentValue / limitValue) * 100);
}

export function percentLabel(percent) {
    if (!Number.isFinite(percent)) {
        return '0%';
    }

    return `${Math.round(percent)}%`;
}

/**
 * Traffic-light color tokens driven by how "full" a limit is.
 * Low usage = success, mid = warning, near/over limit = error.
 */
export function usageTextClass(percent, hasActiveLimit = true) {
    if (!hasActiveLimit) {
        return 'text-base-content/40';
    }

    if (percent < 40) {
        return 'text-success';
    }

    if (percent < 80) {
        return 'text-warning';
    }

    return 'text-error';
}

export function usageProgressClass(percent, hasActiveLimit = true) {
    if (!hasActiveLimit) {
        return '';
    }

    if (percent < 40) {
        return 'progress-success';
    }

    if (percent < 80) {
        return 'progress-warning';
    }

    return 'progress-error';
}

export function radialStyle(value, size = '2.6rem', thickness = '3px') {
    return {
        '--value': value,
        '--size': size,
        '--thickness': thickness,
    };
}
