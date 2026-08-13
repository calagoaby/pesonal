import { expenses } from "../../utils/mockDb";

export default defineEventHandler((event) => {
    const id = Number(getRouterParam(event, 'id'))
    const index = expenses.findIndex(item => item.id === id)

    if (index < 0) {
        setResponseStatus(event, 404)

        return { message: 'That entry is not on the roll.' }
    }

    expenses.splice(index, 1)
    setResponseStatus(event, 204)

    return null
})
