import { recurring } from "../utils/mockDb";

/** Unpaginated — the contract loads the whole set, which is what makes the
 *  monthly-commitment total on this screen honest. */
export default defineEventHandler(() => {
    return { data: recurring }
})