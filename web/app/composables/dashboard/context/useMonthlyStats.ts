import { getMonthlyStats } from "~/repositories/stats";
import type { CategoryRow } from "~/types/Categories";

export function useMonthlyStats() {
    /** States */
    const month = ref('2026-06')

    const { data, status, error, refresh } = useAsyncData(
        'monthly-stats',
        () => getMonthlyStats(month.value),
        { watch: [month] },
    )


    /** Computed */
    const total = computed(() => data.value?.total ?? 0)
    const change_pct = computed(() => data.value?.change_pct ?? null)

    /** The contract has no transaction count — it's the sum of the per-category counts. */
    const transaction_count = computed(() =>
        (data.value?.by_category ?? []).reduce((sum, row) => sum + row.count, 0)
    )

    /** Averaged over every day of the month, not only the days that had spending. */
    const daily_average = computed(() => {
        const days = data.value?.daily.length ?? 0

        return days ? Math.round(total.value / days) : 0
    })

    const category_rows = computed<CategoryRow[]>(() =>
        (data.value?.by_category ?? []).map(row => ({
            name: row.category.name,
            color: row.category.color,
            amount: row.total,
            percentage: total.value ? Math.round((row.total / total.value) * 100) : 0,
        }))
    )

    const daily_totals = computed(() => (data.value?.daily ?? []).map(day => day.total))
    const peak_total = computed(() => Math.max(...daily_totals.value, 0))
    const peak_index = computed(() => peak_total.value ? daily_totals.value.indexOf(peak_total.value) : -1)

    const daily_percentages = computed(() =>
        daily_totals.value.map(amount => peak_total.value ? (amount / peak_total.value) * 100 : 0)
    )

    const peak_date = computed(() => data.value?.daily[peak_index.value]?.date ?? '')


    /** Functions */
    function goToPreviousMonth() {
        month.value = shiftMonth(month.value, -1)
    }

    function goToNextMonth() {
        month.value = shiftMonth(month.value, 1)
    }


    return {
        month,
        total,
        change_pct,
        transaction_count,
        daily_average,
        category_rows,
        daily_percentages,
        peak_index,
        peak_date,
        status,
        error,

        goToPreviousMonth,
        goToNextMonth,
        refresh
    }
}