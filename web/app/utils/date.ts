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

export interface CalendarDay {
    /** YYYY-MM-DD */
    date: string
    day: number
    /** False for the leading/trailing days borrowed from the neighbouring months. */
    in_month: boolean
}

function toDateString(date: Date) {
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

/**
 * Today as a calendar date in a fixed zone. Pinning the zone is what keeps a
 * UTC server and a Manila browser from rendering different days and tripping
 * hydration. `en-CA` is the locale that formats as YYYY-MM-DD.
 */
export function todayIn(timezone: string) {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date())
}

/**
 * @param date_string YYYY-MM-DD
 * @return the same, shifted by delta days
 */
export function shiftDay(date_string: string, delta: number) {
    const [year = 0, month = 1, day = 1] = date_string.split('-').map(Number)

    return toDateString(new Date(year, month - 1, day + delta))
}

/**
 * Whole weeks for one month, padded with neighbouring days so the grid is square.
 * @param month YYYY-MM
 */
export function buildCalendarGrid(month: string): CalendarDay[] {
    const [year = 0, index = 1] = month.split('-').map(Number)
    const leading = new Date(year, index - 1, 1).getDay()
    const days = new Date(year, index, 0).getDate()
    const cells = Math.ceil((leading + days) / 7) * 7

    return Array.from({ length: cells }, (_, cell) => {
        const date = new Date(year, index - 1, cell - leading + 1)

        return {
            date: toDateString(date),
            day: date.getDate(),
            in_month: date.getMonth() === index - 1,
        }
    })
}

/**
 * @return Jun 17
 */
export function formatShortDate(
    date_string: string,
    locale: string,
) {
    return toLocalDate(date_string).toLocaleDateString(locale, {
        month: 'short',
        day: 'numeric',
    })
}

/**
 * @param month YYYY-MM
 * @return the same, shifted by delta months
 */
export function shiftMonth(month: string, delta: number) {
    const [year = 0, index = 1] = month.split('-').map(Number)
    const date = new Date(year, index - 1 + delta, 1)

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

/**
 * @return Wed, Jun 17, 2026
 */
export function formatFullDate(
    date_string: string,
    locale: string,
) {
    return toLocalDate(date_string).toLocaleDateString(locale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

/**
 * Full ISO timestamps (created_at / updated_at) carry a real time, so unlike the
 * calendar dates above they must be read in a fixed zone, not the viewer's.
 * @param iso an ISO 8601 timestamp
 * @return Jun 17, 2026 · 10:03
 */
export function formatTimestamp(
    iso: string,
    locale: string,
    timezone: string,
) {
    const formatted = new Date(iso).toLocaleString(locale, {
        timeZone: timezone,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    })

    // "Jun 17, 2026, 10:03" → swap the comma before the time for the dot separator.
    return formatted.replace(/,(\s\d{1,2}:\d{2})$/, ' ·$1')
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