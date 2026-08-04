import type { ListMeta } from "#shared/types/Api";
import { expenses } from "../utils/mockDb";

const MAX_PER_PAGE = 100

export default defineEventHandler((event) => {
    const query = getQuery(event)

    const month = typeof query.month === 'string' ? query.month : undefined
    const category_id = Number(query.category_id) || undefined
    const page = Math.max(Number(query.page) || 1, 1)
    const per_page = Math.min(Number(query.per_page) || 20, MAX_PER_PAGE)

    const matched = expenses.filter(expense => {
        if (month && !expense.date.startsWith(month)) {
            return false
        }

        return !category_id || expense.category.id === category_id
    })

    const start = (page - 1) * per_page
    const meta: ListMeta = {
        current_page: page,
        per_page,
        total: matched.length,
        last_page: Math.max(Math.ceil(matched.length / per_page), 1),
    }

    return {
        data: matched.slice(start, start + per_page),
        meta,
    }
})