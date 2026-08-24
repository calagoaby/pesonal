import { recurring } from "../../utils/mockDb";

export default defineEventHandler((event) => {
    const id = Number(getRouterParam(event, 'id'))
    const template = recurring.find(item => item.id === id)

    if (!template) {
        setResponseStatus(event, 404)

        return { message: 'That standing order is gone.' }
    }

    return { data: template }
})