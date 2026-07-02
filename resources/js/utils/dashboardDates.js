/**
 * Pure date helpers shared by the role dashboards. All work in the browser's
 * local timezone and speak ISO `Y-m-d` strings, matching the backend contract.
 */

const russianMonthFormatter = new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    year: 'numeric',
});
const russianShortDateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'short',
});

export const parseIsoDate = (value) => {
    if (!value) {
        return null;
    }
    const [year, month, day] = String(value).split('-').map((item) => Number(item));
    if (!year || !month || !day) {
        return null;
    }
    const parsedDate = new Date(year, month - 1, day);
    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
};

export const formatDateToIso = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

export const getMonthStart = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
export const getMonthEnd = (date) => new Date(date.getFullYear(), date.getMonth() + 1, 0);
export const getDayStart = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

const getWeekDayNumber = (date) => {
    const dayNumber = date.getDay();
    return dayNumber === 0 ? 7 : dayNumber;
};

export const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
export const addMonths = (date, months) => new Date(date.getFullYear(), date.getMonth() + months, 1);
export const getWeekStart = (date) => addDays(getDayStart(date), 1 - getWeekDayNumber(date));
export const getWeekEnd = (date) => addDays(getWeekStart(date), 6);

export const formatRuShortDate = (date) => russianShortDateFormatter.format(date).replace('.', '');
export const formatRuMonth = (date) => {
    const label = russianMonthFormatter.format(date);
    return label.charAt(0).toUpperCase() + label.slice(1);
};
