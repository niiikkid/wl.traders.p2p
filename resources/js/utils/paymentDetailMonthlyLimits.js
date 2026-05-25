export function formatMonthlyLimitResetLabel(resetDay) {
    if (resetDay === null || resetDay === undefined || resetDay === '') {
        return 'пока не настроено';
    }

    return `сброс ${resetDay}`;
}
