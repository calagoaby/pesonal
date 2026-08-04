import type { CategoryTotal, DailyTotal, MonthlyStats } from "#shared/types/Stats";
import { expenses } from "../../utils/mockDb";

function monthTotal(month: string) {
    return expenses
        .filter(expense => expense.date.startsWith(month))
        .reduce((sum, expense) => sum + expense.amount, 0)
}

function previousMonth(month: string) {
    const [year = 0, index = 1] = month.split('-').map(Number)
    const date = new Date(year, index - 2, 1)

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

/** Day 0 of the next month is the last day of this one. */
function daysInMonth(month: string) {
    const [year = 0, index = 1] = month.split('-').map(Number)

    return new Date(year, index, 0).getDate()
}

export default defineEventHandler((event) => {
    const query = getQuery(event)
    const month = typeof query.month === 'string' ? query.month : '2026-06'

    const in_month = expenses.filter(expense => expense.date.startsWith(month))
    const total = in_month.reduce((sum, expense) => sum + expense.amount, 0)
    const previous_total = monthTotal(previousMonth(month))

    const by_category = new Map<number, CategoryTotal>()

    for (const expense of in_month) {
        const row = by_category.get(expense.category.id)

        if (row) {
            row.total += expense.amount
            row.count += 1
            continue
        }

        by_category.set(expense.category.id, {
            category: expense.category,
            total: expense.amount,
            count: 1,
        })
    }

    const daily: DailyTotal[] = []

    for (let day = 1; day <= daysInMonth(month); day++) {
        const date = `${month}-${String(day).padStart(2, '0')}`

        daily.push({
            date,
            total: in_month
                .filter(expense => expense.date === date)
                .reduce((sum, expense) => sum + expense.amount, 0),
        })
    }

    const data: MonthlyStats = {
        month,
        total,
        previous_total,
        change_pct: previous_total
            ? Math.round(((total - previous_total) / previous_total) * 1000) / 10
            : null,
        by_category: Array.from(by_category.values()).sort((a, b) => b.total - a.total),
        daily,
    }

    return { data }
})