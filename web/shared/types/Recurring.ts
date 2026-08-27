import type { CategoryRef } from './Category'

/** A template the backend materializes into real Expense rows. v1 recurs monthly. */
export interface RecurringExpense {
    id: number
    /** centavos */
    amount: number
    note: string | null
    /** 1–31, clamped to the month length at materialization */
    day_of_month: number
    /** YYYY-MM-DD */
    start_date: string
    /** YYYY-MM-DD, or null for open-ended */
    end_date: string | null
    /** false = paused: kept, but no longer materialized */
    active: boolean
    category: CategoryRef
    created_at: string
    updated_at: string
}

/**
 * A `type`, not an `interface`, so it satisfies the index signature the fetch
 * layer's body parameter expects — interfaces don't get an implicit one.
 */
export type RecurringPayload = {
    amount: number
    category_id: number
    day_of_month: number
    start_date: string
    note?: string | null
    end_date?: string | null
    active?: boolean
}