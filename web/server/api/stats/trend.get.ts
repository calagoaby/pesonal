import type { MonthTotal } from "#shared/types/Stats";
import { expenses } from "../../utils/mockDb";

const MAX_MONTHS = 24

/** The window ends with the current month, so a month with no expenses is a
 *  legitimate zero — the line must stay contiguous rather than skip it. */
export default defineEventHandler((event) => {
    const query = getQuery(event)
    const requested = Number(query.months) || 6
    const months = Math.min(Math.max(requested, 1), MAX_MONTHS)

    const now = new Date()
    const data: MonthTotal[] = []

    for (let step = months - 1; step >= 0; step--) {
        const date = new Date(now.getFullYear(), now.getMonth() - step, 1)
        const month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`

        data.push({
            month,
            total: expenses
                .filter(expense => expense.date.startsWith(month))
                .reduce((sum, expense) => sum + expense.amount, 0),
        })
    }

    return { data }
})