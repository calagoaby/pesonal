import type { MonthlyStats, MonthTotal } from "#shared/types/Stats";

export function getMonthlyStats(month: string) {
    return apiGet<MonthlyStats>('/stats/monthly', { month })
}

export function getTrendStats(months = 6) {
    return apiGet<MonthTotal[]>('/stats/trend', { months })
}