import { expenses } from "../../utils/mockDb";

export default defineEventHandler((event) => {
    const id = Number(getRouterParam(event, 'id'))
    const expense = expenses.find(item => item.id === id)

    if (!expense) {
        setResponseStatus(event, 404)

        return { message: 'That entry is not on the roll.' }
    }

    return { data: expense }
})
