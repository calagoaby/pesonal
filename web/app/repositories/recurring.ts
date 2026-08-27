import type { RecurringExpense } from "#shared/types/Recurring";

const resource = '/recurring'

export function listRecurring() {
    return apiGet<RecurringExpense[]>(resource)
}