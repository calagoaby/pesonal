import type { Category } from "#shared/types/Category";
import { categories } from "../utils/mockDb";

export default defineEventHandler(async (event) => {
    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    if (typeof body?.name !== 'string' || !body.name.trim()) {
        errors.name = ['The name field is required.']
    }

    if (typeof body?.color !== 'string' || !/^#[0-9A-Fa-f]{6}$/.test(body.color)) {
        errors.color = ['The color must be a hex value.']
    }

    if (typeof body?.emoji !== 'string' || !body.emoji) {
        errors.emoji = ['The emoji field is required.']
    }

    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    const now = new Date().toISOString()
    const created: Category = {
        id: Math.max(...categories.map(item => item.id)) + 1,
        name: body.name.trim(),
        color: body.color,
        emoji: body.emoji,
        created_at: now,
    }

    categories.push(created)
    setResponseStatus(event, 201)

    return { data: created }
})