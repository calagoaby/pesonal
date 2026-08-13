/** The nested `category` read off another resource. */
export interface CategoryRef {
    id: number
    name: string
    color: string
    emoji: string
}

export interface Category extends CategoryRef {
    created_at: string
}

/**
 * A `type`, not an `interface`, so it satisfies the index signature the fetch
 * layer's body parameter expects — interfaces don't get an implicit one.
 */
export type CategoryPayload = {
    name: string
    color: string
    emoji: string
}