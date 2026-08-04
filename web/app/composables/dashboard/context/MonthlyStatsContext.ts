import { useMonthlyStats } from "./useMonthlyStats";

type monthly_stats_context = ReturnType<typeof useMonthlyStats>

const key = Symbol('monthly-stats')

export const provideMonthlyStats = () => {
    const flow = useMonthlyStats()

    provide(key, flow)

    return flow
}

export const useMonthlyStatsContext = () => {
    const flow = inject<monthly_stats_context>(key)

    if (!flow) {
        throw new Error('useMonthlyStatsContext')
    }

    return flow
}