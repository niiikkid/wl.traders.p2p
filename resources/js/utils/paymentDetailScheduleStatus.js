export const SCHEDULE_STATUS = {
    NOT_CONFIGURED: 'not_configured',
    WORKING: 'working',
    DAY_OFF: 'day_off',
    STARTS_LATER: 'starts_later',
    BREAK_UNTIL: 'break_until',
    FINISHED: 'finished',
    INVALID: 'invalid',
};

const STATUS_LABELS = {
    [SCHEDULE_STATUS.NOT_CONFIGURED]: 'Без расписания',
    [SCHEDULE_STATUS.WORKING]: 'Работает',
    [SCHEDULE_STATUS.DAY_OFF]: 'Выходной',
    [SCHEDULE_STATUS.STARTS_LATER]: 'Скоро начнёт работу',
    [SCHEDULE_STATUS.FINISHED]: 'Рабочее время закончилось',
    [SCHEDULE_STATUS.INVALID]: 'Некорректное расписание',
};

const ISO_WEEKDAY_LABELS = {
    1: 'Пн',
    2: 'Вт',
    3: 'Ср',
    4: 'Чт',
    5: 'Пт',
    6: 'Сб',
    7: 'Вс',
};

function normalizeTime(time) {
    if (typeof time !== 'string' || !time) {
        return '00:00:00';
    }

    if (/^\d{2}:\d{2}$/.test(time)) {
        return `${time}:00`;
    }

    return time;
}

function formatDisplayTime(time) {
    return normalizeTime(time).slice(0, 5);
}

function formatTimeInTimezone(date, timeZone) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(date);

    const get = (type) => parts.find((part) => part.type === type)?.value ?? '00';

    return `${get('hour')}:${get('minute')}:${get('second')}`;
}

export function formatServerScheduleDateTime(serverNow, serverTimezone) {
    if (!serverNow) {
        return null;
    }

    try {
        return new Date(serverNow).toLocaleString('ru-RU', {
            timeZone: serverTimezone || undefined,
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
        });
    } catch {
        return null;
    }
}

function getIsoWeekdayInTimezone(date, timeZone) {
    try {
        const dateKey = new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(date);
        const [year, month, day] = dateKey.split('-').map(Number);
        const utcDate = new Date(Date.UTC(year, month - 1, day));
        const weekday = utcDate.getUTCDay();

        return weekday === 0 ? 7 : weekday;
    } catch {
        return null;
    }
}

export function formatServerScheduleClock(date, serverTimezone) {
    const timezone = serverTimezone || 'UTC';

    try {
        const isoWeekday = getIsoWeekdayInTimezone(date, timezone);
        const time = new Intl.DateTimeFormat('ru-RU', {
            timeZone: timezone,
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(date);

        return {
            weekday: ISO_WEEKDAY_LABELS[isoWeekday] ?? '—',
            time,
            timezone,
        };
    } catch {
        return {
            weekday: '—',
            time: '—:—',
            timezone,
        };
    }
}

function intervalContains(interval, time) {
    const startsAt = normalizeTime(interval.starts_at);
    const endsAt = normalizeTime(interval.ends_at);

    return startsAt <= time && endsAt > time;
}

function findCurrentInterval(intervals, time) {
    return intervals.find((interval) => intervalContains(interval, time)) ?? null;
}

function findNextInterval(intervals, time) {
    return intervals.find((interval) => normalizeTime(interval.starts_at) > time) ?? null;
}

function formatIntervalList(intervals) {
    if (!intervals?.length) {
        return null;
    }

    return intervals
        .map((interval) => `${interval.starts_at}-${interval.ends_at}`)
        .join(', ');
}

function buildIntervalItems(intervals) {
    if (!intervals?.length) {
        return [];
    }

    return intervals.map((interval) => `${interval.starts_at}-${interval.ends_at}`);
}

function emptyScheduleDisplay() {
    return {
        status: SCHEDULE_STATUS.NOT_CONFIGURED,
        statusLabel: STATUS_LABELS[SCHEDULE_STATUS.NOT_CONFIGURED],
        scheduleName: null,
        intervalText: null,
        intervalItems: [],
    };
}

function sortTodayIntervals(todayIntervals) {
    return [...todayIntervals].sort(
        (left, right) => normalizeTime(left.starts_at).localeCompare(normalizeTime(right.starts_at)),
    );
}

function buildIntervalDisplay(todayIntervals) {
    const sortedIntervals = sortTodayIntervals(todayIntervals);

    return {
        intervalItems: buildIntervalItems(sortedIntervals),
        intervalText: formatIntervalList(sortedIntervals),
    };
}

/**
 * @param {object|null} schedule
 * @returns {number}
 */
export function computeServerOffsetFromSchedule(schedule) {
    if (!schedule?.server_now) {
        return 0;
    }

    return new Date(schedule.server_now).getTime() - Date.now();
}

/**
 * @param {object|null} schedule
 * @returns {{ status: string, statusLabel: string, scheduleName: string|null, intervalText: string|null, intervalItems: string[] }}
 */
export function resolvePaymentDetailScheduleDisplayFromApi(schedule) {
    if (!schedule) {
        return emptyScheduleDisplay();
    }

    const todayIntervals = Array.isArray(schedule.today_intervals) ? schedule.today_intervals : [];
    const { intervalItems, intervalText } = buildIntervalDisplay(todayIntervals);
    const status = schedule.status || SCHEDULE_STATUS.NOT_CONFIGURED;
    const statusLabel = schedule.status_label
        || STATUS_LABELS[status]
        || STATUS_LABELS[SCHEDULE_STATUS.NOT_CONFIGURED];

    return {
        status,
        statusLabel,
        scheduleName: schedule.name ?? null,
        intervalText,
        intervalItems,
    };
}

/**
 * @param {object|null} schedule
 * @param {number} offsetMs
 * @returns {{ status: string, statusLabel: string, scheduleName: string|null, intervalText: string|null, intervalItems: string[] }}
 */
export function resolvePaymentDetailScheduleDisplayLive(schedule, offsetMs = 0) {
    if (!schedule) {
        return emptyScheduleDisplay();
    }

    const timezone = schedule.server_timezone || 'UTC';
    const now = new Date(Date.now() + offsetMs);
    const time = formatTimeInTimezone(now, timezone);
    const todayIntervals = Array.isArray(schedule.today_intervals) ? schedule.today_intervals : [];

    if (schedule.status === SCHEDULE_STATUS.INVALID) {
        return {
            status: SCHEDULE_STATUS.INVALID,
            statusLabel: schedule.status_label || STATUS_LABELS[SCHEDULE_STATUS.INVALID],
            scheduleName: schedule.name,
            intervalText: null,
            intervalItems: [],
        };
    }

    if (todayIntervals.length === 0) {
        return {
            status: SCHEDULE_STATUS.DAY_OFF,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.DAY_OFF],
            scheduleName: schedule.name,
            intervalText: null,
            intervalItems: [],
        };
    }

    const sortedIntervals = sortTodayIntervals(todayIntervals);
    const intervalItems = buildIntervalItems(sortedIntervals);
    const intervalText = formatIntervalList(sortedIntervals);
    const currentInterval = findCurrentInterval(sortedIntervals, time);

    if (currentInterval) {
        return {
            status: SCHEDULE_STATUS.WORKING,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.WORKING],
            scheduleName: schedule.name,
            intervalText,
            intervalItems,
        };
    }

    const nextInterval = findNextInterval(sortedIntervals, time);

    if (!nextInterval) {
        return {
            status: SCHEDULE_STATUS.FINISHED,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.FINISHED],
            scheduleName: schedule.name,
            intervalText,
            intervalItems,
        };
    }

    const hadEarlierInterval = sortedIntervals.some(
        (interval) => normalizeTime(interval.ends_at) <= time,
    );

    if (hadEarlierInterval) {
        const breakUntil = formatDisplayTime(nextInterval.starts_at);

        return {
            status: SCHEDULE_STATUS.BREAK_UNTIL,
            statusLabel: `Перерыв до ${breakUntil}`,
            scheduleName: schedule.name,
            intervalText,
            intervalItems,
        };
    }

    return {
        status: SCHEDULE_STATUS.STARTS_LATER,
        statusLabel: STATUS_LABELS[SCHEDULE_STATUS.STARTS_LATER],
        scheduleName: schedule.name,
        intervalText,
        intervalItems,
    };
}

/**
 * @param {object|null} schedule
 * @param {number} offsetMs
 * @param {{ mode?: 'api'|'live'|'auto' }} [options]
 * @returns {{ status: string, statusLabel: string, scheduleName: string|null, intervalText: string|null, intervalItems: string[] }}
 */
export function resolvePaymentDetailScheduleDisplay(schedule, offsetMs = 0, options = {}) {
    const mode = options.mode ?? 'api';

    if (mode === 'live') {
        return resolvePaymentDetailScheduleDisplayLive(schedule, offsetMs);
    }

    if (mode === 'api') {
        if (schedule?.status) {
            return resolvePaymentDetailScheduleDisplayFromApi(schedule);
        }

        return resolvePaymentDetailScheduleDisplayLive(schedule, offsetMs);
    }

    return resolvePaymentDetailScheduleDisplayLive(schedule, offsetMs);
}

export function scheduleStatusBadgeClass(status) {
    switch (status) {
        case SCHEDULE_STATUS.WORKING:
            return 'badge-success badge-outline';
        case SCHEDULE_STATUS.BREAK_UNTIL:
        case SCHEDULE_STATUS.STARTS_LATER:
            return 'badge-warning badge-outline';
        case SCHEDULE_STATUS.DAY_OFF:
            return 'badge-error badge-outline';
        case SCHEDULE_STATUS.FINISHED:
            return 'badge-accent badge-outline';
        case SCHEDULE_STATUS.INVALID:
            return 'badge-error badge-outline';
        case SCHEDULE_STATUS.NOT_CONFIGURED:
        default:
            return 'badge-ghost badge-outline';
    }
}

const SUMMARY_SHORT_LABELS = {
    [SCHEDULE_STATUS.WORKING]: 'Работают',
    [SCHEDULE_STATUS.STARTS_LATER]: 'Скоро начнут',
    [SCHEDULE_STATUS.BREAK_UNTIL]: 'Перерыв',
    [SCHEDULE_STATUS.DAY_OFF]: 'Выходной',
    [SCHEDULE_STATUS.FINISHED]: 'Закончили',
    [SCHEDULE_STATUS.INVALID]: 'Ошибка',
    [SCHEDULE_STATUS.NOT_CONFIGURED]: 'Без расписания',
};

const SUMMARY_STATUS_ORDER = [
    SCHEDULE_STATUS.WORKING,
    SCHEDULE_STATUS.STARTS_LATER,
    SCHEDULE_STATUS.BREAK_UNTIL,
    SCHEDULE_STATUS.DAY_OFF,
    SCHEDULE_STATUS.FINISHED,
    SCHEDULE_STATUS.INVALID,
    SCHEDULE_STATUS.NOT_CONFIGURED,
];

const SUMMARY_COUNT_LABELS = {
    [SCHEDULE_STATUS.WORKING]: {
        one: 'реквизит работает',
        few: 'реквизита работают',
        many: 'реквизитов работают',
    },
    [SCHEDULE_STATUS.STARTS_LATER]: {
        one: 'реквизит скоро начнёт работу',
        few: 'реквизита скоро начнут работу',
        many: 'реквизитов скоро начнут работу',
    },
    [SCHEDULE_STATUS.BREAK_UNTIL]: {
        one: 'реквизит на перерыве',
        few: 'реквизита на перерыве',
        many: 'реквизитов на перерыве',
    },
    [SCHEDULE_STATUS.DAY_OFF]: {
        one: 'реквизит отдыхает сегодня',
        few: 'реквизита отдыхают сегодня',
        many: 'реквизитов отдыхают сегодня',
    },
    [SCHEDULE_STATUS.FINISHED]: {
        one: 'реквизит уже отработал сегодня',
        few: 'реквизита уже отработали сегодня',
        many: 'реквизитов уже отработали сегодня',
    },
    [SCHEDULE_STATUS.INVALID]: {
        one: 'реквизит с ошибкой расписания',
        few: 'реквизита с ошибкой расписания',
        many: 'реквизитов с ошибкой расписания',
    },
    [SCHEDULE_STATUS.NOT_CONFIGURED]: {
        one: 'реквизит без расписания',
        few: 'реквизита без расписания',
        many: 'реквизитов без расписания',
    },
};

function pluralizeCount(count, forms) {
    const abs = Math.abs(count) % 100;
    const last = abs % 10;

    if (abs > 10 && abs < 20) {
        return forms.many;
    }

    if (last > 1 && last < 5) {
        return forms.few;
    }

    if (last === 1) {
        return forms.one;
    }

    return forms.many;
}

function normalizePaymentDetailRows(paymentDetails) {
    if (Array.isArray(paymentDetails)) {
        return paymentDetails;
    }

    return paymentDetails?.data ?? [];
}

function buildSummaryItemsFromCounts(counts) {
    return SUMMARY_STATUS_ORDER
        .filter((status) => (counts[status] ?? 0) > 0)
        .map((status) => ({
            status,
            count: counts[status],
            shortLabel: SUMMARY_SHORT_LABELS[status],
            countLabel: `${counts[status]} ${pluralizeCount(counts[status], SUMMARY_COUNT_LABELS[status])}`,
            badgeClass: scheduleStatusBadgeClass(status),
        }));
}

/**
 * @param {{ total?: number, with_schedule?: number, counts?: Record<string, number> }|null|undefined} summaryPayload
 * @returns {{ total: number, withSchedule: number, counts: Record<string, number>, items: Array<{ status: string, count: number, shortLabel: string, countLabel: string, badgeClass: string }> }}
 */
export function buildScheduleSummaryDisplay(summaryPayload) {
    const counts = summaryPayload?.counts ?? {};
    const total = summaryPayload?.total ?? 0;
    const withSchedule = summaryPayload?.with_schedule ?? 0;

    return {
        total,
        withSchedule,
        counts,
        items: buildSummaryItemsFromCounts(counts),
    };
}

/**
 * @param {Array|object|null} paymentDetails
 * @param {number} offsetMs
 * @returns {{ total: number, withSchedule: number, counts: Record<string, number>, items: Array<{ status: string, count: number, shortLabel: string, countLabel: string, badgeClass: string }> }}
 */
export function computePaymentDetailScheduleSummary(paymentDetails, offsetMs = 0, options = {}) {
    const rows = normalizePaymentDetailRows(paymentDetails);
    const counts = Object.fromEntries(
        Object.values(SCHEDULE_STATUS).map((status) => [status, 0]),
    );
    const mode = options.mode ?? 'live';

    for (const detail of rows) {
        const display = resolvePaymentDetailScheduleDisplay(detail?.schedule ?? null, offsetMs, { mode });
        counts[display.status] += 1;
    }

    const withSchedule = rows.length - counts[SCHEDULE_STATUS.NOT_CONFIGURED];

    return {
        total: rows.length,
        withSchedule,
        counts,
        items: buildSummaryItemsFromCounts(counts),
    };
}
