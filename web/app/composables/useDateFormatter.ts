export function useDateFormatter() {
    const { public: config } = useRuntimeConfig()


    /** Functions */
    function formatDailyDate(date: string) {
        return formatWeekdayDate(date, config.locale).replaceAll(',', '')
    }

    function formatMonthLabel(month: string) {
        return formatMonthYear(month, config.locale)
    }


    return {
        formatDailyDate,
        formatMonthLabel
    }
}