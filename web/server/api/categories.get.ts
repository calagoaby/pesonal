import { categories } from "../utils/mockDb";

/** Unpaginated — the contract treats categories as a small set, so no meta. */
export default defineEventHandler(() => {
    return { data: categories }
})