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
        return '—';
    }

    return intervals
        .map((interval) => `${interval.starts_at}-${interval.ends_at}`)
        .join(', ');
}

function resolveIntervalText(status, todayIntervals, currentInterval, nextInterval) {
    if (status === SCHEDULE_STATUS.DAY_OFF) {
        return '—';
    }

    if (status === SCHEDULE_STATUS.WORKING && currentInterval) {
        return `${currentInterval.starts_at}-${currentInterval.ends_at}`;
    }

    if (status === SCHEDULE_STATUS.BREAK_UNTIL && nextInterval) {
        return `${nextInterval.starts_at}-${nextInterval.ends_at}`;
    }

    if (status === SCHEDULE_STATUS.STARTS_LATER && nextInterval) {
        return `${nextInterval.starts_at}-${nextInterval.ends_at}`;
    }

    return formatIntervalList(todayIntervals);
}

/**
 * @param {object|null} schedule
 * @param {number} offsetMs
 * @returns {{ status: string, statusLabel: string, scheduleName: string|null, intervalText: string }}
 */
export function resolvePaymentDetailScheduleDisplay(schedule, offsetMs = 0) {
    if (!schedule) {
        return {
            status: SCHEDULE_STATUS.NOT_CONFIGURED,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.NOT_CONFIGURED],
            scheduleName: null,
            intervalText: '—',
        };
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
            intervalText: '—',
        };
    }

    if (todayIntervals.length === 0) {
        return {
            status: SCHEDULE_STATUS.DAY_OFF,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.DAY_OFF],
            scheduleName: schedule.name,
            intervalText: '—',
        };
    }

    const sortedIntervals = [...todayIntervals].sort(
        (left, right) => normalizeTime(left.starts_at).localeCompare(normalizeTime(right.starts_at)),
    );

    const currentInterval = findCurrentInterval(sortedIntervals, time);

    if (currentInterval) {
        return {
            status: SCHEDULE_STATUS.WORKING,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.WORKING],
            scheduleName: schedule.name,
            intervalText: resolveIntervalText(
                SCHEDULE_STATUS.WORKING,
                sortedIntervals,
                currentInterval,
                null,
            ),
        };
    }

    const nextInterval = findNextInterval(sortedIntervals, time);

    if (!nextInterval) {
        return {
            status: SCHEDULE_STATUS.FINISHED,
            statusLabel: STATUS_LABELS[SCHEDULE_STATUS.FINISHED],
            scheduleName: schedule.name,
            intervalText: formatIntervalList(sortedIntervals),
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
            intervalText: resolveIntervalText(
                SCHEDULE_STATUS.BREAK_UNTIL,
                sortedIntervals,
                null,
                nextInterval,
            ),
        };
    }

    return {
        status: SCHEDULE_STATUS.STARTS_LATER,
        statusLabel: STATUS_LABELS[SCHEDULE_STATUS.STARTS_LATER],
        scheduleName: schedule.name,
        intervalText: resolveIntervalText(
            SCHEDULE_STATUS.STARTS_LATER,
            sortedIntervals,
            null,
            nextInterval,
        ),
    };
}

export function scheduleStatusBadgeClass(status) {
    switch (status) {
        case SCHEDULE_STATUS.WORKING:
            return 'badge-success badge-outline';
        case SCHEDULE_STATUS.BREAK_UNTIL:
        case SCHEDULE_STATUS.STARTS_LATER:
            return 'badge-warning badge-outline';
        case SCHEDULE_STATUS.FINISHED:
        case SCHEDULE_STATUS.DAY_OFF:
            return 'badge-neutral badge-outline';
        case SCHEDULE_STATUS.INVALID:
            return 'badge-error badge-outline';
        case SCHEDULE_STATUS.NOT_CONFIGURED:
        default:
            return 'badge-ghost';
    }
}
