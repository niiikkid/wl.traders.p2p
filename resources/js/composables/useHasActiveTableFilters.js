import { computed } from 'vue';
import { useTableFiltersStore } from '@/store/tableFilters.js';

const normalizeValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }
    if (Array.isArray(value)) {
        return value
            .map((item) => normalizeValue(item))
            .flat()
            .filter((item) => item !== '');
    }
    if (typeof value === 'object') {
        return Object.values(value)
            .map((item) => normalizeValue(item))
            .flat()
            .filter((item) => item !== '');
    }
    if (typeof value === 'boolean' || typeof value === 'number') {
        return value ? ['1'] : [];
    }
    if (typeof value === 'string') {
        return value.trim().length ? [value.trim()] : [];
    }
    return [];
};

/**
 * Есть ли у текущей таблицы ненулевые фильтры (для бейджа и т.п.).
 */
export function useHasActiveTableFilters() {
    const tableFiltersStore = useTableFiltersStore();

    return computed(() => {
        const filters = tableFiltersStore.getFilters;
        if (!filters || typeof filters !== 'object') {
            return false;
        }

        return Object.values(filters).some((value) => normalizeValue(value).length > 0);
    });
}
