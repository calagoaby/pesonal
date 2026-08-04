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


    return {
        formatDailyDate,
        formatMonthLabel,
        formatFieldDate,
        formatListDate
    }
}