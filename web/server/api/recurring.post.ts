import type { RecurringExpense } from "#shared/types/Recurring";
import { categories, recurring } from "../utils/mockDb";

export default defineEventHandler(async (event) => {
    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    if (!Number.isInteger(body?.amount) || body.amount <= 0) {
        errors.amount = ['The amount field must be a positive number of centavos.']
    }

    const category = categories.find(item => item.id === body?.category_id)

    if (!category) {
        errors.category_id = ['The selected category is invalid.']
    }

    if (!Number.isInteger(body?.day_of_month) || body.day_of_month < 1 || body.day_of_month > 31) {
        errors.day_of_month = ['The day of month must be between 1 and 31.']
    }

    if (typeof body?.start_date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.start_date)) {
        errors.start_date = ['The start date must be a YYYY-MM-DD calendar date.']
    }

    const has_end = body?.end_date !== undefined && body.end_date !== null

    if (has_end && (typeof body.end_date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.end_date))) {
        errors.end_date = ['The end date must be a YYYY-MM-DD calendar date.']
    } else if (has_end && !errors.start_date && body.end_date < body.start_date) {
        errors.end_date = ['The end date must be on or after the start date.']
    }

    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    const now = new Date().toISOString()
    const created: RecurringExpense = {
        id: recurring.length ? Math.max(...recurring.map(item => item.id)) + 1 : 1,
        amount: body.amount,
        note: body.note ?? null,
        day_of_month: body.day_of_month,
        start_date: body.start_date,
        end_date: has_end ? body.end_date : null,
        active: body.active ?? true,
        category: {
            id: category!.id,
            name: category!.name,
            color: category!.color,
            emoji: category!.emoji,
        },
        created_at: now,
        updated_at: now,
    }

    recurring.push(created)
    setResponseStatus(event, 201)

    return { data: created }
})