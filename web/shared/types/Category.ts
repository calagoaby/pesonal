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