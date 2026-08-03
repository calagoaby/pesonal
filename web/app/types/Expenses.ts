import type { Expense } from '#shared/types/Expense'

/**
 * View model — not a contract shape. The API returns a flat, date-desc list;
 * the receipt roll groups it by day for display only.
 */
export interface DailyExpense {
    date: string
    items: Expense[]
}