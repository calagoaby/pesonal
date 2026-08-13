import type { Category, CategoryPayload } from "#shared/types/Category";

const resource = '/categories'

export function listCategories() {
    return apiGet<Category[]>(resource)
}

export function createCategory(payload: CategoryPayload) {
    return apiPost<Category>(resource, payload)
}

export function updateCategory(id: number, payload: Partial<CategoryPayload>) {
    return apiPatch<Category>(`${resource}/${id}`, payload)
}

export function deleteCategory(id: number) {
    return apiDelete(`${resource}/${id}`)
}