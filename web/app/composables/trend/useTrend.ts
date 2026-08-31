import type { MonthTotal } from "#shared/types/Stats";
import { getTrendStats } from "~/repositories/stats";

export interface TrendRow {
    /** YYYY-MM */
    month: string
    /** centavos */
    total: number
    /** Percent change against the month before it; null for the oldest point. */
    change_pct: number | null
    is_current: boolean
}

/**
 * Shared by the dashboard card and the trend screen, so both read one 'trend'
 * cache entry rather than fetching the same window twice.
 */
export async function useTrend(months = 6) {
    const { data, status, error, refresh } = await useAsyncData('trend', () => getTrendStats(months))


    /** Computed */
    const points = computed<MonthTotal[]>(() => data.value ?? [])
    const totals = computed(() => points.value.map(point => point.total))

    /** Averaged over every month in the window, including the ones that spent nothing. */
    const average = computed(() =>
        points.value.length
            ? Math.round(totals.value.reduce((sum, total) => sum + total, 0) / points.value.length)
            : 0
    )

    const total = computed(() => totals.value.reduce((sum, amount) => sum + amount, 0))
    const peak_total = computed(() => Math.max(...totals.value, 0))

    /** -1 when nothing was spent in the window, so no point gets the peak marker. */
    const peak_index = computed(() => peak_total.value ? totals.value.indexOf(peak_total.value) : -1)
    const current_index = computed(() => points.value.length - 1)

    /** Newest first — the reverse of the wire order, which the line needs. */
    const rows = computed<TrendRow[]>(() =>
        points.value
            .map((point, index) => {
                const previous = index > 0 ? points.value[index - 1]!.total : null

                return {
                    month: point.month,
                    total: point.total,
                    change_pct: previous
                        ? Math.round(((point.total - previous) / previous) * 100)
                        : null,
                    is_current: index === current_index.value,
                }
            })
            .reverse()
    )


    return {
        points,
        rows,
        average,
        total,
        peak_index,
        current_index,
        status,
        error,

        refresh
    }
}