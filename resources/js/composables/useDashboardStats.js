import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    addDays,
    addMonths,
    formatDateToIso,
    formatRuMonth,
    formatRuShortDate,
    getDayStart,
    getMonthEnd,
    getMonthStart,
    getWeekEnd,
    getWeekStart,
    parseIsoDate,
} from '@/utils/dashboardDates.js';

const NAVIGABLE_PRESETS = ['today', 'week', 'month'];

/**
 * Encapsulates the shared "filtered stats dashboard" behavior used by the
 * Admin / Trader / Merchant main pages: period presets + navigation, custom
 * date range, the async gear-filter dropdown and Inertia navigation. Pages
 * supply role-specific config and keep only their layout/chart wiring.
 *
 * @param {Object} config
 * @param {string} config.routeName Inertia route for the dashboard page.
 * @param {string} config.filterOptionsRouteName Axios route for filter options.
 * @param {import('vue').ComputedRef<string>} config.activeStatsMode 'deals' | 'payouts'.
 * @param {Object} config.filterTypesByMode { deals: FilterType[], payouts: FilterType[] }.
 * @param {Object} config.filterPropMap Maps `selectedFilters` prop keys to filter keys.
 * @param {(typeKey: string) => Object} [config.buildFilterOptionParams] Extra request params per type.
 * @param {(typeKey: string) => boolean} [config.isSingleSelect] Whether a filter allows one value.
 * @param {() => Object} [config.extraRequestData] Extra params merged into every navigation.
 */
export function useDashboardStats(config) {
    const {
        routeName,
        filterOptionsRouteName,
        activeStatsMode,
        filterTypesByMode,
        filterPropMap,
        buildFilterOptionParams = () => ({}),
        isSingleSelect = () => false,
        extraRequestData = () => ({}),
    } = config;

    const page = usePage();
    const selectedPeriodPresetProp = computed(() => page.props.selectedPeriodPreset || 'month');
    const selectedDateFromProp = computed(() => page.props.selectedDateFrom || '');
    const selectedDateToProp = computed(() => page.props.selectedDateTo || '');
    const selectedFiltersProp = computed(() => page.props.selectedFilters || {});

    const filterKeys = Object.values(filterPropMap);

    const buildFilterState = (initial) => filterKeys.reduce((state, key) => {
        state[key] = initial();
        return state;
    }, {});

    const mapPropFilters = (prop) => {
        const state = buildFilterState(() => []);
        Object.entries(filterPropMap).forEach(([propKey, filterKey]) => {
            state[filterKey] = prop?.[propKey] || [];
        });
        return state;
    };

    const processing = ref(false);
    const isMobile = ref(false);

    const selectedPeriodPreset = ref(selectedPeriodPresetProp.value || 'month');
    const selectedDateFrom = ref(selectedDateFromProp.value || '');
    const selectedDateTo = ref(selectedDateToProp.value || '');
    const selectedPeriodCursor = ref('');
    const periodCursors = ref({ today: '', week: '', month: '' });

    const activeFilterType = ref(null);
    const selectedFilters = ref(mapPropFilters(selectedFiltersProp.value));
    const selectedOptions = ref(buildFilterState(() => []));
    const searchQueries = ref(buildFilterState(() => ''));
    const searchResults = ref(buildFilterState(() => []));
    const loadingOptions = ref(buildFilterState(() => false));
    const searchDebounceTimers = {};

    const gearFilterTypes = computed(() => (
        filterTypesByMode[activeStatsMode.value] || filterTypesByMode.deals || []
    ));

    const canNavigateByPeriod = computed(() => NAVIGABLE_PRESETS.includes(selectedPeriodPreset.value));

    const getDefaultCursorByPreset = (preset) => {
        const now = new Date();
        if (preset === 'month') {
            return formatDateToIso(getMonthStart(now));
        }
        if (preset === 'week') {
            return formatDateToIso(getWeekStart(now));
        }
        return formatDateToIso(getDayStart(now));
    };

    const setPresetCursor = (preset, cursor) => {
        if (!NAVIGABLE_PRESETS.includes(preset) || !cursor) {
            return;
        }
        periodCursors.value[preset] = cursor;
        if (selectedPeriodPreset.value === preset) {
            selectedPeriodCursor.value = cursor;
        }
    };

    const initializePeriodCursors = () => {
        periodCursors.value.today = getDefaultCursorByPreset('today');
        periodCursors.value.week = getDefaultCursorByPreset('week');
        periodCursors.value.month = getDefaultCursorByPreset('month');
    };

    const resolvePeriodAnchorDate = (cursor = null, preset = selectedPeriodPreset.value) => {
        const anchorFor = (date) => {
            if (preset === 'month') {
                return getMonthStart(date);
            }
            if (preset === 'week') {
                return getWeekStart(date);
            }
            return getDayStart(date);
        };
        return anchorFor(
            parseIsoDate(cursor)
            || parseIsoDate(selectedDateFrom.value)
            || parseIsoDate(selectedDateTo.value)
            || new Date(),
        );
    };

    const selectedPeriodLabel = computed(() => {
        if (!canNavigateByPeriod.value) {
            return '';
        }
        if (selectedPeriodPreset.value === 'month') {
            return formatRuMonth(resolvePeriodAnchorDate(selectedPeriodCursor.value, 'month'));
        }
        if (selectedPeriodPreset.value === 'today') {
            return formatRuShortDate(resolvePeriodAnchorDate(selectedPeriodCursor.value, 'today'));
        }
        const weekStartDate = resolvePeriodAnchorDate(selectedPeriodCursor.value, 'week');
        return `${formatRuShortDate(weekStartDate)} — ${formatRuShortDate(getWeekEnd(weekStartDate))}`;
    });

    const buildPeriodRequestData = (options) => {
        const requestData = {
            period: selectedPeriodPreset.value,
            mode: activeStatsMode.value,
            ...extraRequestData(),
        };
        const cursor = options.periodCursor || selectedPeriodCursor.value;

        if (selectedPeriodPreset.value === 'month') {
            const anchor = resolvePeriodAnchorDate(cursor, 'month');
            setPresetCursor('month', formatDateToIso(getMonthStart(anchor)));
            selectedDateFrom.value = formatDateToIso(getMonthStart(anchor));
            selectedDateTo.value = formatDateToIso(getMonthEnd(anchor));
            requestData.date_from = selectedDateFrom.value;
            requestData.date_to = selectedDateTo.value;
        } else if (selectedPeriodPreset.value === 'today') {
            const anchor = resolvePeriodAnchorDate(cursor, 'today');
            setPresetCursor('today', formatDateToIso(anchor));
            selectedDateFrom.value = formatDateToIso(getDayStart(anchor));
            selectedDateTo.value = formatDateToIso(getDayStart(anchor));
            requestData.date_from = selectedDateFrom.value;
            requestData.date_to = selectedDateTo.value;
        } else if (selectedPeriodPreset.value === 'week') {
            const weekStartDate = resolvePeriodAnchorDate(cursor, 'week');
            setPresetCursor('week', formatDateToIso(weekStartDate));
            selectedDateFrom.value = formatDateToIso(weekStartDate);
            selectedDateTo.value = formatDateToIso(getWeekEnd(weekStartDate));
            requestData.date_from = selectedDateFrom.value;
            requestData.date_to = selectedDateTo.value;
        } else if (selectedPeriodPreset.value === 'custom' && selectedDateFrom.value && selectedDateTo.value) {
            requestData.date_from = selectedDateFrom.value;
            requestData.date_to = selectedDateTo.value;
        }

        gearFilterTypes.value.forEach((filterType) => {
            const selectedIds = selectedFilters.value[filterType.key] || [];
            if (selectedIds.length > 0) {
                requestData[filterType.requestKey] = selectedIds;
            }
        });

        return requestData;
    };

    const applyFilter = (options = {}) => {
        if (processing.value) {
            return;
        }
        processing.value = true;
        router.visit(route(routeName), {
            data: buildPeriodRequestData(options),
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                processing.value = false;
            },
        });
    };

    const setPeriodPreset = (preset) => {
        if (selectedPeriodPreset.value === preset) {
            return;
        }
        selectedPeriodPreset.value = preset;
        if (NAVIGABLE_PRESETS.includes(preset)) {
            setPresetCursor(preset, getDefaultCursorByPreset(preset));
        }
        if (preset !== 'custom') {
            applyFilter();
            return;
        }
        if (selectedDateFrom.value && selectedDateTo.value) {
            applyFilter();
        }
    };

    const navigatePeriod = (step) => {
        if (!canNavigateByPeriod.value) {
            return;
        }
        const anchor = resolvePeriodAnchorDate(selectedPeriodCursor.value, selectedPeriodPreset.value);
        let nextAnchor;
        if (selectedPeriodPreset.value === 'month') {
            nextAnchor = getMonthStart(addMonths(anchor, step));
        } else if (selectedPeriodPreset.value === 'week') {
            nextAnchor = getWeekStart(addDays(anchor, step * 7));
        } else {
            nextAnchor = getDayStart(addDays(anchor, step));
        }
        const nextCursor = formatDateToIso(nextAnchor);
        setPresetCursor(selectedPeriodPreset.value, nextCursor);
        applyFilter({ periodCursor: nextCursor });
    };

    const setCustomRange = (from, to) => {
        selectedPeriodPreset.value = 'custom';
        selectedDateFrom.value = from;
        selectedDateTo.value = to;
    };

    const loadFilterOptions = async (typeKey, query = '') => {
        loadingOptions.value[typeKey] = true;
        try {
            const params = {
                query,
                selected_ids: selectedFilters.value[typeKey] || [],
                mode: activeStatsMode.value,
                ...buildFilterOptionParams(typeKey, {
                    selectedFilters: selectedFilters.value,
                    dateFrom: selectedDateFrom.value,
                    dateTo: selectedDateTo.value,
                }),
            };
            const response = await axios.get(route(filterOptionsRouteName, { type: typeKey }), { params });
            const options = Array.isArray(response.data) ? response.data : [];
            searchResults.value[typeKey] = options;

            const selectedIdsSet = new Set((selectedFilters.value[typeKey] || []).map((id) => Number(id)));
            const nextSelected = options.filter((option) => selectedIdsSet.has(Number(option.value)));
            const previousSelected = selectedOptions.value[typeKey] || [];
            selectedOptions.value[typeKey] = [
                ...nextSelected,
                ...previousSelected.filter((option) => selectedIdsSet.has(Number(option.value))),
            ].filter((option, index, array) => (
                array.findIndex((item) => Number(item.value) === Number(option.value)) === index
            ));
        } catch (error) {
            console.error('Ошибка загрузки фильтров статистики', error);
        } finally {
            loadingOptions.value[typeKey] = false;
        }
    };

    const getDisplayedOptions = (typeKey) => {
        const selected = selectedOptions.value[typeKey] || [];
        const selectedIdsSet = new Set((selectedFilters.value[typeKey] || []).map((id) => Number(id)));
        const rest = (searchResults.value[typeKey] || []).filter((option) => !selectedIdsSet.has(Number(option.value)));
        return [...selected, ...rest].filter((option, index, array) => (
            array.findIndex((item) => Number(item.value) === Number(option.value)) === index
        ));
    };

    const isOptionSelected = (typeKey, optionValue) => (selectedFilters.value[typeKey] || [])
        .some((id) => Number(id) === Number(optionValue));

    const toggleFilterOption = (typeKey, option, checked) => {
        const current = selectedFilters.value[typeKey] || [];
        if (checked) {
            if (isSingleSelect(typeKey)) {
                selectedFilters.value[typeKey] = [Number(option.value)];
                selectedOptions.value[typeKey] = [option];
                return;
            }
            if (!current.some((id) => Number(id) === Number(option.value))) {
                selectedFilters.value[typeKey] = [...current, Number(option.value)];
            }
            if (!selectedOptions.value[typeKey].some((item) => Number(item.value) === Number(option.value))) {
                selectedOptions.value[typeKey] = [option, ...(selectedOptions.value[typeKey] || [])];
            }
            return;
        }
        selectedFilters.value[typeKey] = current.filter((id) => Number(id) !== Number(option.value));
        selectedOptions.value[typeKey] = (selectedOptions.value[typeKey] || [])
            .filter((item) => Number(item.value) !== Number(option.value));
    };

    const bulkSelectFilterOptions = (mode, typeKey = activeFilterType.value) => {
        if (mode === 'none') {
            selectedFilters.value[typeKey] = [];
            selectedOptions.value[typeKey] = [];
            return;
        }
        const options = getDisplayedOptions(typeKey);
        const toSelect = mode === 'active_only'
            ? options.filter((option) => !option.is_archived)
            : options;
        selectedFilters.value[typeKey] = toSelect.map((option) => Number(option.value));
        selectedOptions.value[typeKey] = [...toSelect];
    };

    const selectFilterType = (typeKey) => {
        activeFilterType.value = typeKey;
        loadFilterOptions(typeKey, searchQueries.value[typeKey] || '');
    };

    const resetAdvancedFilters = () => {
        selectedFilters.value = buildFilterState(() => []);
        selectedOptions.value = buildFilterState(() => []);
        searchQueries.value = buildFilterState(() => '');
        applyFilter();
    };

    const hasActiveAdvancedFilters = computed(() => gearFilterTypes.value.some((filterType) => (
        Array.isArray(selectedFilters.value[filterType.key]) && selectedFilters.value[filterType.key].length > 0
    )));

    const updateIsMobile = () => {
        if (typeof window === 'undefined') {
            return;
        }
        isMobile.value = window.innerWidth < 640;
    };

    const prefetchSelectedFilterOptions = () => {
        gearFilterTypes.value.forEach((filterType) => {
            if ((selectedFilters.value[filterType.key] || []).length > 0) {
                loadFilterOptions(filterType.key, '');
            }
        });
    };

    watch([selectedDateFrom, selectedDateTo], () => {
        if (selectedPeriodPreset.value === 'custom' && selectedDateFrom.value && selectedDateTo.value) {
            applyFilter();
        }
    });

    watch(selectedPeriodPresetProp, (newValue) => {
        const value = newValue || 'month';
        selectedPeriodPreset.value = value;
        if (NAVIGABLE_PRESETS.includes(value)) {
            setPresetCursor(value, formatDateToIso(resolvePeriodAnchorDate(selectedDateFromProp.value, value)));
        }
    });

    watch(selectedDateFromProp, (newValue) => {
        selectedDateFrom.value = newValue || '';
        if (NAVIGABLE_PRESETS.includes(selectedPeriodPreset.value)) {
            setPresetCursor(selectedPeriodPreset.value, formatDateToIso(resolvePeriodAnchorDate(newValue, selectedPeriodPreset.value)));
        }
    });

    watch(selectedDateToProp, (newValue) => {
        selectedDateTo.value = newValue || '';
    });

    watch(selectedFiltersProp, (newFilters) => {
        selectedFilters.value = mapPropFilters(newFilters);
    }, { deep: true });

    filterKeys.forEach((key) => {
        watch(() => searchQueries.value[key], (query) => {
            clearTimeout(searchDebounceTimers[key]);
            searchDebounceTimers[key] = setTimeout(() => {
                loadFilterOptions(key, query || '');
            }, 300);
        });
    });

    onMounted(() => {
        initializePeriodCursors();
        updateIsMobile();
        window.addEventListener('resize', updateIsMobile);
        if (NAVIGABLE_PRESETS.includes(selectedPeriodPreset.value)) {
            setPresetCursor(
                selectedPeriodPreset.value,
                formatDateToIso(resolvePeriodAnchorDate(selectedDateFrom.value, selectedPeriodPreset.value)),
            );
        }
        if (gearFilterTypes.value.length > 0) {
            activeFilterType.value = gearFilterTypes.value[0].key;
        }
        prefetchSelectedFilterOptions();
    });

    onBeforeUnmount(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', updateIsMobile);
        }
        Object.values(searchDebounceTimers).forEach((timer) => clearTimeout(timer));
    });

    return {
        processing,
        isMobile,
        selectedPeriodPreset,
        selectedDateFrom,
        selectedDateTo,
        canNavigateByPeriod,
        selectedPeriodLabel,
        setPeriodPreset,
        navigatePeriod,
        setCustomRange,
        applyFilter,
        gearFilterTypes,
        activeFilterType,
        selectedFilters,
        selectedOptions,
        searchQueries,
        searchResults,
        loadingOptions,
        hasActiveAdvancedFilters,
        loadFilterOptions,
        getDisplayedOptions,
        isOptionSelected,
        toggleFilterOption,
        bulkSelectFilterOptions,
        selectFilterType,
        resetAdvancedFilters,
    };
}
