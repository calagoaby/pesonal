import type { MonthlyStats } from "#shared/types/Stats";

export function getMonthlyStats(month: string) {
    return apiGet<MonthlyStats>('/stats/monthly', { month })
}