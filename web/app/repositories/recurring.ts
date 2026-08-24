import type { RecurringExpense, RecurringPayload } from "#shared/types/Recurring";

const resource = '/recurring'

export function listRecurring() {
    return apiGet<RecurringExpense[]>(resource)
}

export function getRecurring(id: number) {
    return apiGet<RecurringExpense>(`${resource}/${id}`)
}

export function createRecurring(payload: RecurringPayload) {
    return apiPost<RecurringExpense>(resource, payload)
}

export function updateRecurring(id: number, payload: Partial<RecurringPayload>) {
    return apiPatch<RecurringExpense>(`${resource}/${id}`, payload)
}