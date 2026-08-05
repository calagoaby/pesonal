import type { Expense, ExpensePayload } from "#shared/types/Expense";

const resource = '/expenses'

export function listExpenses(query: { month?: string, category_id?: number, page?: number, per_page?: number }) {
    return apiGetList<Expense>(resource, query)
}

export function createExpense(payload: ExpensePayload) {
    return apiPost<Expense>(resource, payload)
}