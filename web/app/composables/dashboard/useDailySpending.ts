export function useDailySpending() {
    /** States */
    const daily_amounts = [92, 45, 3, 23, 5, 56, 6, 4, 45, 62, 32, 46, 7, 6, 45, 10, 3, 45, 90, 31, 33, 32, 58, 31, 13, 32, 10, 33, 28, 41]


    /** Computed */
    const max_total = computed(() => Math.max(...daily_amounts))

    const spending_percentages = computed(() => {
        return daily_amounts.map((spent) => {
            return (spent / max_total.value) * 100;
        })
    })

    const is_peak_index = computed(() => daily_amounts.findIndex((spent) => spent === max_total.value))

    return {
        spending_percentages,
        is_peak_index,
    }
}