/**
 * @return Tue, Jun 17
 */
export function formatWeekdayDate(
    date_string: string,
    locale: string,
    timezone: string,
) {
    const date = new Date(date_string);

    return date.toLocaleDateString(locale, {
        timeZone: timezone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}