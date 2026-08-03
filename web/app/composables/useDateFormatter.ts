export function useDateFormatter() {
    const { public: config } = useRuntimeConfig()


    /** Functions */
    function formatDailyDate(date: string) {
        return formatWeekdayDate(date, config.locale, config.timezone).replaceAll(',', '')
    }


    return {
        formatDailyDate
    }
}