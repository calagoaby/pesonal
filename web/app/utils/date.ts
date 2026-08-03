/**
 * Calendar dates carry no time, so they must not go through a timezone:
 * `new Date('2026-06-17')` is UTC midnight, which lands on the 16th behind UTC.
 */
function toLocalDate(date_string: string) {
    const [year = 0, month = 1, day = 1] = date_string.split('-').map(Number)

    return new Date(year, month - 1, day)
}

/**
 * @return Tue, Jun 17
 */
export function formatWeekdayDate(
    date_string: string,
    locale: string,
) {
    return toLocalDate(date_string).toLocaleDateString(locale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}

/**
 * @param month_string YYYY-MM
 * @return June 2026
 */
export function formatMonthYear(
    month_string: string,
    locale: string,
) {
    return toLocalDate(`${month_string}-01`).toLocaleDateString(locale, {
        month: 'long',
        year: 'numeric',
    })
}