import type { CategoryRef } from './Category'

/**
 * Mirrors the Expense resource in docs/api-contract.md.
 * Asymmetric by design: reads return the nested `category` object, writes send `category_id`.
 */
export interface Expense {
    id: number
    /** Integer centavos. 15000 = ₱150.00 */
    amount: number
    /** Calendar date, YYYY-MM-DD — no time component */
    date: string
    note: string | null
    category: CategoryRef
    /** Set when this row was materialized from a recurring template */
    recurring_id: number | null
    created_at: string
    updated_at: string
}

/** Body for POST /api/expenses and PATCH /api/expenses/{id}. */
export interface ExpensePayload {
    amount: number
    date: string
    category_id: number
    note?: string
}