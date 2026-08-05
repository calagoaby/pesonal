import type { Expense } from "#shared/types/Expense";
import { listExpenses } from "~/repositories/expenses";
import type { DailyExpense } from "~/types/Expenses";

export async function useMonthExpense() {
    /** States */
    const month = ref('2026-06')
    /** Null means every category — the query param is dropped, not sent empty. */
    const category_id = ref<number | null>(null)

    const { data, status, error, refresh } = await useAsyncData(
        'month-expenses',
        () => listExpenses({
            month: month.value,
            category_id: category_id.value ?? undefined,
        }),
        { watch: [month, category_id] },
    )


    /** Computed */
    const meta = computed(() => data.value?.meta)
    /** Grouped over the whole list, so a day split across pages prints one header. */
    const daily_expenses = computed(() => groupByDate(data.value?.items ?? []))


    /** Functions */
    function goToPreviousMonth() {
        month.value = shiftMonth(month.value, -1)
    }

    function goToNextMonth() {
        month.value = shiftMonth(month.value, 1)
    }

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
        category_id,
        meta,
        status,
        error,
        daily_expenses,

        goToPreviousMonth,
        goToNextMonth,
        refresh
    }
}