import { categories, expenses } from "../../utils/mockDb";

export default defineEventHandler((event) => {
    const id = Number(getRouterParam(event, 'id'))
    const index = categories.findIndex(item => item.id === id)

    if (index < 0) {
        setResponseStatus(event, 404)

        return { message: 'That category is gone.' }
    }

    // The contract refuses (409) if any expense still references the category. The
    // count lives in the message since the error shape carries only { message }.
    const used = expenses.filter(item => item.category.id === id).length

    if (used > 0) {
        setResponseStatus(event, 409)

        return { message: `Still tags ${used} ${used === 1 ? 'expense' : 'expenses'}. Move or delete those first, then it can go.` }
    }

    categories.splice(index, 1)
    setResponseStatus(event, 204)

    return null
})