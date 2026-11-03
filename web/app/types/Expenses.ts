import type { Expense } from '#shared/types/Expense'

/** View model — the API list is flat, the roll groups it by day. */
export interface DailyExpense {
    date: string
    /** centavos — the whole day under the current filter, not just the loaded rows */
    total: number
    items: Expense[]
}