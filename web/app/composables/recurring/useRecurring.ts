import type { RecurringExpense } from "#shared/types/Recurring";
import { listRecurring } from "~/repositories/recurring";

export async function useRecurring() {
    const { data, status, error, refresh } = await useAsyncData('recurring', () => listRecurring())


    /** Computed */
    const templates = computed(() => data.value ?? [])

    /** Sorted by day_of_month, then grouped under an "Every Nth" heading. */
    const groups = computed(() => {
        const sorted = [...templates.value].sort((a, b) => a.day_of_month - b.day_of_month || a.id - b.id)
        const days = new Map<number, RecurringExpense[]>()

        for (const template of sorted) {
            const day = days.get(template.day_of_month)

            if (day) {
                day.push(template)
                continue
            }

            days.set(template.day_of_month, [template])
        }

        return Array.from(days, ([day, items]) => ({ day, items }))
    })

    /** Only active templates leave the account, so paused ones don't count here. */
    const monthly_total = computed(() =>
        templates.value
            .filter(template => template.active)
            .reduce((sum, template) => sum + template.amount, 0)
    )

    const active_count = computed(() => templates.value.filter(template => template.active).length)


    return {
        templates,
        groups,
        monthly_total,
        active_count,
        status,
        error,

        refresh
    }
}