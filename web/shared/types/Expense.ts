import type { CategoryRef } from './Category'

/** Reads return the nested `category`, writes send `category_id`. */
export interface Expense {
    id: number
    /** centavos */
    amount: number
    /** YYYY-MM-DD */
    date: string
    note: string | null
    category: CategoryRef
    recurring_id: number | null
    created_at: string
    updated_at: string
}

export interface ExpensePayload {
    amount: number
    date: string
    category_id: number
    note?: string
}