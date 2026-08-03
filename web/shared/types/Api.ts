/** Shapes shared by every endpoint — see docs/api-contract.md */

/** The `meta` block every paginated list response carries. */
export interface ListMeta {
    current_page: number
    per_page: number
    /** Row COUNT, not a sum of amounts. */
    total: number
    last_page: number
}

/** The error body behind every 4xx/5xx. `errors` is present only on a 422. */
export interface ApiError {
    message: string
    errors?: Record<string, string[]>
}