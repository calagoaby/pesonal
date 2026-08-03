/** Mirrors the Category resource in docs/api-contract.md */

/** What a nested `category` looks like when read off another resource. */
export interface CategoryRef {
    id: number
    name: string
    /** Hex, #RRGGBB */
    color: string
    emoji: string
}

/** The full resource, as returned by /api/categories. */
export interface Category extends CategoryRef {
    created_at: string
}