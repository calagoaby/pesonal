import type { Expense } from '#shared/types/Expense'

/** View model — the API list is flat, the roll groups it by day. */
export interface DailyExpense {
    date: string
    items: Expense[]
}