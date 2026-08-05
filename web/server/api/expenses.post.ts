import type { Expense } from "#shared/types/Expense";
import { categories, expenses } from "../utils/mockDb";

export default defineEventHandler(async (event) => {
    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    if (!Number.isInteger(body?.amount) || body.amount <= 0) {
        errors.amount = ['The amount field must be a positive number of centavos.']
    }

    if (typeof body?.date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.date)) {
        errors.date = ['The date field must be a YYYY-MM-DD calendar date.']
    }

    const category = categories.find(item => item.id === body?.category_id)

    if (!category) {
        errors.category_id = ['The selected category is invalid.']
    }

    // Returned rather than thrown: createError wraps the body in its own envelope,
    // and the contract wants exactly { message, errors } on a 422.
    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    const now = new Date().toISOString()
    const created: Expense = {
        id: Math.max(...expenses.map(expense => expense.id)) + 1,
        amount: body.amount,
        date: body.date,
        note: body.note ?? null,
        category: {
            id: category!.id,
            name: category!.name,
            color: category!.color,
            emoji: category!.emoji,
        },
        recurring_id: null,
        created_at: now,
        updated_at: now,
    }

    // Newest first, matching the list's date desc ordering.
    const at = expenses.findIndex(expense => expense.date <= created.date)
    expenses.splice(at < 0 ? expenses.length : at, 0, created)

    setResponseStatus(event, 201)

    return { data: created }
})