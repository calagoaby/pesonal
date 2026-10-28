import type { CategoryRef } from './Category'

/** What part of a total was spent on. Never adds up to more than the record's amount. */
export interface ExpenseItem {
    name: string
    /** centavos */
    amount: number
}

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
    /** `[]` when there are none, in the order they were entered. */
    items: ExpenseItem[]
    created_at: string
    updated_at: string
}

/**
 * A `type`, not an `interface`, so it satisfies the index signature the fetch
 * layer's body parameter expects — interfaces don't get an implicit one.
 * Request payload types should follow suit.
 */
export type ExpensePayload = {
    amount: number
    date: string
    category_id: number
    note?: string | null
    /** On PATCH: absent leaves them, `[]` clears them, an array replaces them. Never null. */
    items?: ExpenseItem[]
}