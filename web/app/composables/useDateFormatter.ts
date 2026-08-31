export function useDateFormatter() {
    const { public: config } = useRuntimeConfig()


    /** Functions */
    function formatDailyDate(date: string) {
        return formatWeekdayDate(date, config.locale).replaceAll(',', '')
    }

    function formatMonthLabel(month: string) {
        return formatMonthYear(month, config.locale)
    }

    function formatFieldDate(date: string) {
        return formatFullDate(date, config.locale).replace(',', ' ·')
    }

    function formatListDate(date: string) {
        return formatShortDate(date, config.locale)
    }

    function formatStamp(iso: string) {
        return formatTimestamp(iso, config.locale, config.timezone)
    }

    function formatMonthAndYear(date: string) {
        return formatMonthShort(date, config.locale)
    }

    function formatMonthName(month: string) {
        return formatMonthNameLong(month, config.locale)
    }

    function formatMonthAbbrev(month: string) {
        return formatMonthNameShort(month, config.locale)
    }


    return {
        formatDailyDate,
        formatMonthLabel,
        formatFieldDate,
        formatListDate,
        formatStamp,
        formatMonthAndYear,
        formatMonthName,
        formatMonthAbbrev
    }
}