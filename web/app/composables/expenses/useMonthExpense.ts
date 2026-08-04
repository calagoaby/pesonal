import type { Expense } from "#shared/types/Expense";
import { listExpenses } from "~/repositories/expenses";
import type { DailyExpense } from "~/types/Expenses";

export async function useMonthExpense() {
    /** States */
    const month = ref('2026-06')

    const { data, status, error, refresh } = await useAsyncData(
        'month-expenses',
        () => listExpenses({ month: month.value }),
        { watch: [month] },
    )


    /** Computed */
    const meta = computed(() => data.value?.meta)
    /** Grouped over the whole list, so a day split across pages prints one header. */
    const daily_expenses = computed(() => groupByDate(data.value?.items ?? []))


    /** Functions */
    function groupByDate(items: Expense[]): DailyExpense[] {
        const days = new Map<string, Expense[]>()

        for (const item of items) {
            const day = days.get(item.date)

            if (day) {
                day.push(item)
                continue
            }

            days.set(item.date, [item])
        }

        return Array.from(days, ([date, day_items]) => ({ date, items: day_items }))
    }


    return {
        month,
        meta,
        status,
        error,
        daily_expenses,

        refresh
    }
}