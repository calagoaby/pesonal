import { categories } from "../../utils/mockDb";

export default defineEventHandler(async (event) => {
    const id = Number(getRouterParam(event, 'id'))
    const category = categories.find(item => item.id === id)

    if (!category) {
        setResponseStatus(event, 404)

        return { message: 'That category is gone.' }
    }

    const body = await readBody(event)
    const errors: Record<string, string[]> = {}

    // PATCH is partial: validate only the fields that were sent.
    if (body?.name !== undefined && (typeof body.name !== 'string' || !body.name.trim())) {
        errors.name = ['The name field is required.']
    }

    if (body?.color !== undefined && (typeof body.color !== 'string' || !/^#[0-9A-Fa-f]{6}$/.test(body.color))) {
        errors.color = ['The color must be a hex value.']
    }

    if (body?.emoji !== undefined && (typeof body.emoji !== 'string' || !body.emoji)) {
        errors.emoji = ['The emoji field is required.']
    }

    if (Object.keys(errors).length) {
        setResponseStatus(event, 422)

        return { message: 'The given data was invalid.', errors }
    }

    // Mutated in place. Expenses snapshot the category ref at creation, so already
    // recorded rows keep their old name/colour — a mock trait, not a contract rule.
    if (body.name !== undefined) {
        category.name = body.name.trim()
    }

    if (body.color !== undefined) {
        category.color = body.color
    }

    if (body.emoji !== undefined) {
        category.emoji = body.emoji
    }

    return { data: category }
})