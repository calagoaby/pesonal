import type { Expense } from "#shared/types/Expense";
import { listExpenses } from "~/repositories/expenses";
import type { DailyExpense } from "~/types/Expenses";

export async function useMonthExpense() {
    const { public: config } = useRuntimeConfig()

    /** States */
    const month = ref(currentMonthIn(config.timezone))
    /** Null means every category — the query param is dropped, not sent empty. */
    const category_id = ref<number | null>(null)

    const page = ref(1)
    /** Pages 2+ only. Page 1 stays in useAsyncData so it still server-renders. */
    const appended = ref<Expense[]>([])
    /** day_totals from pages 2+. */
    const appended_totals = ref<Record<string, number>>({})
    const is_loading_more = ref(false)
    const load_more_error = ref('')

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
    const expenses = computed(() => [...(data.value?.items ?? []), ...appended.value])
    const loaded_count = computed(() => expenses.value.length)
    const has_more = computed(() => !!meta.value && page.value < meta.value.last_page)
    // Page 1 last: a refresh refetches only page 1, so its figures are the freshest.
    const day_totals = computed(() => ({ ...appended_totals.value, ...meta.value?.day_totals }))
    /** Grouped over the whole list, so a day split across pages prints one header. */
    const daily_expenses = computed(() => groupByDate(expenses.value))


    /** Functions */
    function goToPreviousMonth() {
        month.value = shiftMonth(month.value, -1)
    }

    function goToNextMonth() {
        month.value = shiftMonth(month.value, 1)
    }

    async function loadMore() {
        if (!has_more.value || is_loading_more.value) {
            return
        }

        is_loading_more.value = true
        load_more_error.value = ''

        try {
            const next = page.value + 1
            const { items, meta: next_meta } = await listExpenses({
                month: month.value,
                category_id: category_id.value ?? undefined,
                page: next,
            })

            appended.value = [...appended.value, ...items]
            appended_totals.value = { ...appended_totals.value, ...next_meta.day_totals }
            page.value = next
        } catch (failure) {
            load_more_error.value = (failure as ApiRequestError).message
        } finally {
            is_loading_more.value = false
        }
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

        // A date the API left out (shouldn't happen) falls back to its loaded rows.
        return Array.from(days, ([date, day_items]) => ({
            date,
            total: day_totals.value[date] ?? day_items.reduce((sum, item) => sum + item.amount, 0),
            items: day_items,
        }))
    }


    // A filter defines the result set; pagination only walks it. Change either and
    // the walk starts over, so anything already appended belongs to a different set.
    watch([month, category_id], () => {
        page.value = 1
        appended.value = []
        appended_totals.value = {}
        load_more_error.value = ''
    })


    return {
        month,
        category_id,
        meta,
        status,
        error,
        daily_expenses,
        loaded_count,
        has_more,
        is_loading_more,
        load_more_error,

        goToPreviousMonth,
        goToNextMonth,
        loadMore,
        refresh
    }
}