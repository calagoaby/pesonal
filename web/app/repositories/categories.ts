import type { Category } from "#shared/types/Category";

const resource = '/categories'

export function listCategories() {
    return apiGet<Category[]>(resource)
}