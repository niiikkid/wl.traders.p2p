import { computed } from 'vue';
import { useTableFiltersStore } from '@/store/tableFilters.js';

/**
 * Двусторонняя привязка одного фильтра к стору таблицы.
 * Убирает повторяющийся computed get/set в каждом партиале фильтра.
 *
 * @param {string} name Ключ фильтра (должен совпадать с бэкендом).
 * @param {*} fallback Значение по умолчанию, когда фильтр не задан.
 * @returns {import('vue').WritableComputedRef<*>}
 */
export function useFilterModel(name, fallback = undefined) {
    const tableFiltersStore = useTableFiltersStore();

    return computed({
        get: () => tableFiltersStore.filters[name] ?? fallback,
        set: (value) => {
            tableFiltersStore.filters[name] = value;
        },
    });
}
