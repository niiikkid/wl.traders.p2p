export const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Пн' },
    { value: 2, label: 'Вт' },
    { value: 3, label: 'Ср' },
    { value: 4, label: 'Чт' },
    { value: 5, label: 'Пт' },
    { value: 6, label: 'Сб' },
    { value: 7, label: 'Вс' },
];

const TIME_PATTERN = /^(\d{2}):(\d{2})$/;

export const trimTime = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }

    return value.length >= 5 ? value.slice(0, 5) : value;
};

const compareTime = (left, right) => trimTime(left).localeCompare(trimTime(right));

const intervalKey = (interval) => `${trimTime(interval.starts_at)}|${trimTime(interval.ends_at)}`;

const groupIntervalsByDay = (intervals) => {
    const byDay = {};

    for (const interval of intervals || []) {
        const day = Number(interval.day_of_week);

        if (day < 1 || day > 7) {
            continue;
        }

        if (!byDay[day]) {
            byDay[day] = [];
        }

        byDay[day].push({
            starts_at: trimTime(interval.starts_at),
            ends_at: trimTime(interval.ends_at),
        });
    }

    for (const day of Object.keys(byDay)) {
        byDay[day].sort((left, right) => compareTime(left.starts_at, right.starts_at));
    }

    return byDay;
};

export const createEmptyEditorState = (name = '') => ({
    name,
    defaultDays: [1, 2, 3, 4, 5, 6, 7],
    defaultStart: '09:00',
    defaultEnd: '19:00',
    dayOverrides: {},
});

export const intervalsToEditorState = (intervals, name = '') => {
    const byDay = groupIntervalsByDay(intervals);
    const daysWithIntervals = Object.keys(byDay).map(Number).sort((a, b) => a - b);

    if (!daysWithIntervals.length) {
        return createEmptyEditorState(name);
    }

    const singleIntervalDays = daysWithIntervals.filter((day) => byDay[day].length === 1);

    if (singleIntervalDays.length) {
        const patternCounts = new Map();

        for (const day of singleIntervalDays) {
            const key = intervalKey(byDay[day][0]);
            patternCounts.set(key, (patternCounts.get(key) || 0) + 1);
        }

        let dominantKey = null;
        let dominantCount = 0;

        for (const [key, count] of patternCounts.entries()) {
            if (count > dominantCount) {
                dominantKey = key;
                dominantCount = count;
            }
        }

        if (dominantKey) {
            const [defaultStart, defaultEnd] = dominantKey.split('|');
            const defaultDays = [];
            const dayOverrides = {};

            for (const day of daysWithIntervals) {
                const dayIntervals = byDay[day];

                if (dayIntervals.length === 1 && intervalKey(dayIntervals[0]) === dominantKey) {
                    defaultDays.push(day);
                    continue;
                }

                dayOverrides[day] = {
                    enabled: true,
                    intervals: dayIntervals.map((interval) => ({ ...interval })),
                };
            }

            return {
                name,
                defaultDays: defaultDays.sort((a, b) => a - b),
                defaultStart,
                defaultEnd,
                dayOverrides,
            };
        }
    }

    const dayOverrides = {};

    for (const day of daysWithIntervals) {
        dayOverrides[day] = {
            enabled: true,
            intervals: byDay[day].map((interval) => ({ ...interval })),
        };
    }

    return {
        name,
        defaultDays: [],
        defaultStart: '09:00',
        defaultEnd: '19:00',
        dayOverrides,
    };
};

export const editorStateToIntervals = (state) => {
    const intervals = [];

    for (let day = 1; day <= 7; day += 1) {
        const override = state.dayOverrides?.[day];

        if (override?.enabled && override.intervals?.length) {
            for (const interval of override.intervals) {
                intervals.push({
                    day_of_week: day,
                    starts_at: trimTime(interval.starts_at),
                    ends_at: trimTime(interval.ends_at),
                });
            }

            continue;
        }

        if ((state.defaultDays || []).includes(day)) {
            intervals.push({
                day_of_week: day,
                starts_at: trimTime(state.defaultStart),
                ends_at: trimTime(state.defaultEnd),
            });
        }
    }

    return intervals.sort((left, right) => {
        if (left.day_of_week !== right.day_of_week) {
            return left.day_of_week - right.day_of_week;
        }

        return compareTime(left.starts_at, right.starts_at);
    });
};

const validateTimeValue = (value, fieldKey, errors) => {
    if (!TIME_PATTERN.test(trimTime(value))) {
        errors[fieldKey] = 'Укажите время в формате HH:mm.';

        return false;
    }

    return true;
};

const validateIntervalList = (intervals, prefix, errors) => {
    const normalized = [];

    for (let index = 0; index < intervals.length; index += 1) {
        const interval = intervals[index];
        const startsKey = `${prefix}.${index}.starts_at`;
        const endsKey = `${prefix}.${index}.ends_at`;

        if (!validateTimeValue(interval.starts_at, startsKey, errors)) {
            continue;
        }

        if (!validateTimeValue(interval.ends_at, endsKey, errors)) {
            continue;
        }

        const starts_at = trimTime(interval.starts_at);
        const ends_at = trimTime(interval.ends_at);

        if (starts_at >= ends_at) {
            errors[endsKey] = 'Время окончания должно быть позже начала.';
            continue;
        }

        normalized.push({ starts_at, ends_at });
    }

    normalized.sort((left, right) => compareTime(left.starts_at, right.starts_at));

    for (let index = 1; index < normalized.length; index += 1) {
        if (normalized[index - 1].ends_at > normalized[index].starts_at) {
            errors[prefix] = 'Интервалы одного дня не должны пересекаться.';
            break;
        }
    }

    return normalized.length > 0;
};

export const validateEditorStateLocally = (state) => {
    const errors = {};

    if (!state.name?.trim()) {
        errors.name = 'Укажите название расписания.';
    }

    if (!(state.defaultDays || []).length && !Object.values(state.dayOverrides || {}).some((override) => override?.enabled)) {
        errors.defaultDays = 'Выберите хотя бы один рабочий день или задайте переопределение.';
    }

    if ((state.defaultDays || []).length) {
        validateTimeValue(state.defaultStart, 'defaultStart', errors);
        validateTimeValue(state.defaultEnd, 'defaultEnd', errors);

        if (!errors.defaultStart && !errors.defaultEnd && trimTime(state.defaultStart) >= trimTime(state.defaultEnd)) {
            errors.defaultEnd = 'Время окончания должно быть позже начала.';
        }
    }

    for (const option of WEEKDAY_OPTIONS) {
        const override = state.dayOverrides?.[option.value];

        if (!override?.enabled) {
            continue;
        }

        if (!override.intervals?.length) {
            errors[`dayOverrides.${option.value}`] = 'Добавьте хотя бы один интервал.';
            continue;
        }

        validateIntervalList(override.intervals, `dayOverrides.${option.value}`, errors);
    }

    const intervals = editorStateToIntervals(state);

    if (!intervals.length) {
        errors.intervals = 'Добавьте хотя бы один рабочий день и интервал.';
    }

    return {
        valid: Object.keys(errors).length === 0,
        errors,
        intervals,
    };
};

export const toggleDefaultDay = (state, day) => {
    const days = new Set(state.defaultDays || []);

    if (days.has(day)) {
        days.delete(day);
    } else {
        days.add(day);
    }

    return {
        ...state,
        defaultDays: [...days].sort((left, right) => left - right),
    };
};

export const setDayOverrideEnabled = (state, day, enabled) => {
    const dayOverrides = { ...(state.dayOverrides || {}) };

    if (!enabled) {
        delete dayOverrides[day];

        return { ...state, dayOverrides };
    }

    const existing = dayOverrides[day];
    const intervals = existing?.intervals?.length
        ? existing.intervals.map((interval) => ({ ...interval }))
        : [{
            starts_at: trimTime(state.defaultStart) || '09:00',
            ends_at: trimTime(state.defaultEnd) || '19:00',
        }];

    dayOverrides[day] = { enabled: true, intervals };

    return { ...state, dayOverrides };
};

export const addDayOverrideInterval = (state, day) => {
    const dayOverrides = { ...(state.dayOverrides || {}) };
    const override = dayOverrides[day] || { enabled: true, intervals: [] };
    const intervals = [...(override.intervals || [])];
    const last = intervals[intervals.length - 1];

    intervals.push({
        starts_at: last ? trimTime(last.ends_at) : trimTime(state.defaultStart) || '09:00',
        ends_at: trimTime(state.defaultEnd) || '19:00',
    });

    dayOverrides[day] = { enabled: true, intervals };

    return { ...state, dayOverrides };
};

export const removeDayOverrideInterval = (state, day, index) => {
    const dayOverrides = { ...(state.dayOverrides || {}) };
    const override = dayOverrides[day];

    if (!override) {
        return state;
    }

    const intervals = [...(override.intervals || [])];
    intervals.splice(index, 1);

    if (!intervals.length) {
        delete dayOverrides[day];
    } else {
        dayOverrides[day] = { enabled: true, intervals };
    }

    return { ...state, dayOverrides };
};
