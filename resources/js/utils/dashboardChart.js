/**
 * Normalizes a `{ labels, data, shadowData? }` chart source coming from the
 * backend so the x-axis labels stay compact and readable for the given period
 * preset (day numbers for month/today, `dd.mm` for custom/all, `dd.mm HH:mm`
 * for week). Mirrors the previous per-page logic so charts render identically.
 *
 * @param {{ labels: any[], data: number[], shadowData?: number[] }} source
 * @param {string} periodPreset
 * @returns {{ labels: any[], data: number[], shadowData: number[] }}
 */
export const normalizeChartLabels = (source, periodPreset) => {
    if (!source || !Array.isArray(source.labels) || !Array.isArray(source.data)) {
        return { labels: [], data: [], shadowData: [] };
    }

    const shadowData = Array.isArray(source.shadowData) ? source.shadowData : [];

    if (periodPreset === 'custom' || periodPreset === 'all') {
        return {
            data: source.data,
            shadowData,
            labels: source.labels.map((label) => {
                if (typeof label !== 'string') {
                    return label;
                }
                const dateMonthMatch = label.match(/(\d{1,2})\.(\d{1,2})/);
                if (dateMonthMatch) {
                    const day = dateMonthMatch[1].padStart(2, '0');
                    const month = dateMonthMatch[2].padStart(2, '0');
                    return `${day}.${month}`;
                }
                return label;
            }),
        };
    }

    if (periodPreset === 'week') {
        return {
            data: source.data,
            shadowData,
            labels: source.labels.map((label) => {
                if (typeof label !== 'string') {
                    return label;
                }
                const dateTimeMatch = label.match(/(\d{1,2}\.\d{1,2})\s+(\d{1,2}:\d{2})/);
                if (dateTimeMatch) {
                    return `${dateTimeMatch[1]} ${dateTimeMatch[2]}`;
                }
                return label;
            }),
        };
    }

    return {
        data: source.data,
        shadowData,
        labels: source.labels.map((label) => {
            if (typeof label !== 'string') {
                return label;
            }
            const onlyNumber = label.match(/\d+/);
            return onlyNumber ? onlyNumber[0] : label;
        }),
    };
};

/**
 * On small screens keeps only the last `limit` points so charts stay legible.
 *
 * @param {{ labels: any[], data: number[], shadowData?: number[] }} source
 * @param {boolean} isMobile
 * @param {number} limit
 * @returns {{ labels: any[], data: number[], shadowData: number[] }}
 */
export const getLastPoints = (source, isMobile, limit = 10) => {
    if (!source || !Array.isArray(source.data) || !Array.isArray(source.labels)) {
        return { data: [], labels: [], shadowData: [] };
    }
    if (!isMobile) {
        return {
            data: source.data,
            labels: source.labels,
            shadowData: Array.isArray(source.shadowData) ? source.shadowData : [],
        };
    }
    const startIndex = Math.max(source.data.length - limit, 0);
    return {
        data: source.data.slice(startIndex),
        labels: source.labels.slice(startIndex),
        shadowData: Array.isArray(source.shadowData) ? source.shadowData.slice(startIndex) : [],
    };
};
