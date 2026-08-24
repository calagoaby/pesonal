import type { RecurringExpense, RecurringPayload } from "#shared/types/Recurring";

const resource = '/recurring'

export function listRecurring() {
    return apiGet<RecurringExpense[]>(resource)
}

export function createRecurring(payload: RecurringPayload) {
    return apiPost<RecurringExpense>(resource, payload)
}