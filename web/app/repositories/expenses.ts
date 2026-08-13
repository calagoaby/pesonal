import type { Expense, ExpensePayload } from "#shared/types/Expense";

const resource = '/expenses'

export function listExpenses(query: { month?: string, category_id?: number, page?: number, per_page?: number }) {
    return apiGetList<Expense>(resource, query)
}

export function createExpense(payload: ExpensePayload) {
    return apiPost<Expense>(resource, payload)
}

export function getExpense(id: number) {
    return apiGet<Expense>(`${resource}/${id}`)
}

export function updateExpense(id: number, payload: Partial<ExpensePayload>) {
    return apiPatch<Expense>(`${resource}/${id}`, payload)
}

export function deleteExpense(id: number) {
    return apiDelete(`${resource}/${id}`)
}