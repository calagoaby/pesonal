export function useDailySpending() {
    /** States */
    const daily_spendings = [92, 45, 3, 23, 5, 56, 6, 4, 45, 62, 32, 46, 7, 6, 45, 10, 3, 45, 90, 31, 13, 33, 32, 58, 31, 13, 32, 10, 33, 28, 67, 41]


    /** Computed */
    const max_total = computed(() => Math.max(...daily_spendings))

    const spending_percentages = computed(() => {
        return daily_spendings.map((spent) => {
            return (spent / max_total.value) * 100;
        })
    })

    return {
        spending_percentages
    }
}