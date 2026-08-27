import { recurring } from "../../utils/mockDb";

export default defineEventHandler((event) => {
    const id = Number(getRouterParam(event, 'id'))
    const index = recurring.findIndex(item => item.id === id)

    if (index < 0) {
        setResponseStatus(event, 404)

        return { message: 'That standing order is gone.' }
    }

    // Only the template goes. Expenses it already materialized keep their
    // recurring_id and stay on the roll, exactly as the contract requires.
    recurring.splice(index, 1)
    setResponseStatus(event, 204)

    return null
})