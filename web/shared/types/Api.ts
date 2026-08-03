export interface ListMeta {
    current_page: number
    per_page: number
    /** row count, not a sum of amounts */
    total: number
    last_page: number
}

/** `errors` is present only on a 422. */
export interface ApiError {
    message: string
    errors?: Record<string, string[]>
}