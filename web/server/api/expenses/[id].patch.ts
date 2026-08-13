import type { Expense } from "#shared/types/Expense";
import { categories, expenses } from "../../utils/mockDb";

export default defineEventHandler(async (event) => {
    const id = Number(getRouterParam(event, 'id'))
    const index = expenses.findIndex(item => item.id === id)

    if (index < 0) {
        setResponseStatus(event, 404)

        return { message: 'That entry is not on the roll.' }
    }

    const existing = expenses[index]!
    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    // PATCH is partial: validate only the fields that were actually sent.
    if (body?.amount !== undefined && (!Number.isInteger(body.amount) || body.amount <= 0)) {
        errors.amount = ['The amount field must be a positive number of centavos.']
    }

    if (body?.date !== undefined && (typeof body.date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.date))) {
        errors.date = ['The date field must be a YYYY-MM-DD calendar date.']
    }

    let category = existing.category

    if (body?.category_id !== undefined) {
        const found = categories.find(item => item.id === body.category_id)

        if (found) {
            category = { id: found.id, name: found.name, color: found.color, emoji: found.emoji }
        } else {
            errors.category_id = ['The selected category is invalid.']
        }
    }

    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    const updated: Expense = {
        ...existing,
        amount: body.amount ?? existing.amount,
        date: body.date ?? existing.date,
        note: body.note !== undefined ? (body.note ?? null) : existing.note,
        category,
        updated_at: new Date().toISOString(),
    }

    // The list depends on the array staying date desc, id desc. The date can move,
    // so pull the row and slot it back in at the right spot rather than in place.
    expenses.splice(index, 1)
    const at = expenses.findIndex(item =>
        item.date < updated.date || (item.date === updated.date && item.id < updated.id)
    )
    expenses.splice(at < 0 ? expenses.length : at, 0, updated)

    return { data: updated }
})
