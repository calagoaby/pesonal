import type { CategoryRef } from './Category'

export interface CategoryTotal {
    category: CategoryRef
    total: number
    count: number
}

export interface DailyTotal {
    /** YYYY-MM-DD */
    date: string
    total: number
}

export interface MonthlyStats {
    /** YYYY-MM */
    month: string
    total: number
    previous_total: number
    /** null when previous_total is 0 — no baseline to compare against */
    change_pct: number | null
    by_category: CategoryTotal[]
    /** Zero-filled for every day of the month so bars render contiguously. */
    daily: DailyTotal[]
}

/** One point on the trend line. `GET /api/stats/trend` returns these oldest → newest. */
export interface MonthTotal {
    /** YYYY-MM */
    month: string
    total: number
}