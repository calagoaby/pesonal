import { categories, recurring } from "../../utils/mockDb";

export default defineEventHandler(async (event) => {
    const id = Number(getRouterParam(event, 'id'))
    const index = recurring.findIndex(item => item.id === id)

    if (index < 0) {
        setResponseStatus(event, 404)

        return { message: 'That standing order is gone.' }
    }

    const existing = recurring[index]!
    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    // PATCH is partial: validate only the fields that were sent.
    if (body?.amount !== undefined && (!Number.isInteger(body.amount) || body.amount <= 0)) {
        errors.amount = ['The amount field must be a positive number of centavos.']
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

    if (body?.day_of_month !== undefined && (!Number.isInteger(body.day_of_month) || body.day_of_month < 1 || body.day_of_month > 31)) {
        errors.day_of_month = ['The day of month must be between 1 and 31.']
    }

    if (body?.start_date !== undefined && (typeof body.start_date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.start_date))) {
        errors.start_date = ['The start date must be a YYYY-MM-DD calendar date.']
    }

    if (body?.end_date !== undefined && body.end_date !== null && (typeof body.end_date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(body.end_date))) {
        errors.end_date = ['The end date must be a YYYY-MM-DD calendar date.']
    }

    // Cross-field: end must be on or after start, using whichever is being written
    // now with the existing value as fallback.
    const start = body?.start_date ?? existing.start_date
    const end = body?.end_date === undefined ? existing.end_date : body.end_date

    if (!errors.start_date && !errors.end_date && end !== null && end < start) {
        errors.end_date = ['The end date must be on or after the start date.']
    }

    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    const updated = {
        ...existing,
        amount: body.amount ?? existing.amount,
        note: body.note !== undefined ? (body.note ?? null) : existing.note,
        day_of_month: body.day_of_month ?? existing.day_of_month,
        start_date: body.start_date ?? existing.start_date,
        end_date: body.end_date === undefined ? existing.end_date : body.end_date,
        active: body.active ?? existing.active,
        category,
        updated_at: new Date().toISOString(),
    }

    recurring[index] = updated

    return { data: updated }
})