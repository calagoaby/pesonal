import type { ListMeta } from "#shared/types/Api";
import type { CategoryRef } from "#shared/types/Category";
import type { Expense } from "#shared/types/Expense";
import type { DailyExpense } from "~/types/Expenses";

/** Stand-ins until /api/categories lands. */
const mock_categories = {
    housing: { id: 1, name: 'Housing', color: '#534F8C', emoji: '🏠' },
    groceries: { id: 2, name: 'Groceries', color: '#5E7A3E', emoji: '🛒' },
    food: { id: 3, name: 'Food', color: '#C9692E', emoji: '🍔' },
    transport: { id: 4, name: 'Transport', color: '#3F6F97', emoji: '🚗' },
    fun: { id: 5, name: 'Fun', color: '#B0577B', emoji: '🎉' },
    other: { id: 8, name: 'Other', color: '#8A857B', emoji: '🧾' },
} satisfies Record<string, CategoryRef>

export function useMonthExpense() {
    /** States — shaped like GET /api/expenses: flat, date desc, plus meta. */
    const month = ref('2026-06')
    const meta = ref<ListMeta>({
        current_page: 1,
        per_page: 20,
        total: 138,
        last_page: 7,
    })
    const expenses = ref<Expense[]>([
        {
            id: 1284,
            amount: 68000,
            date: '2026-06-17',
            note: 'Lunch with team',
            category: mock_categories.food,
            recurring_id: null,
            created_at: '2026-06-17T10:03:00Z',
            updated_at: '2026-06-17T10:03:00Z',
        },
        {
            id: 1283,
            amount: 24000,
            date: '2026-06-17',
            note: 'Grab to office',
            category: mock_categories.transport,
            recurring_id: null,
            created_at: '2026-06-17T01:12:00Z',
            updated_at: '2026-06-17T01:12:00Z',
        },
        {
            id: 1282,
            amount: 15000,
            date: '2026-06-17',
            note: 'Kape sa umaga',
            category: mock_categories.food,
            recurring_id: null,
            created_at: '2026-06-17T00:41:00Z',
            updated_at: '2026-06-17T00:41:00Z',
        },
        {
            id: 1281,
            amount: 215000,
            date: '2026-06-16',
            note: 'SM Groceries',
            category: mock_categories.groceries,
            recurring_id: null,
            created_at: '2026-06-16T09:20:00Z',
            updated_at: '2026-06-16T09:20:00Z',
        },
        {
            id: 1280,
            amount: 30000,
            date: '2026-06-16',
            note: 'Load',
            category: mock_categories.other,
            recurring_id: null,
            created_at: '2026-06-16T03:05:00Z',
            updated_at: '2026-06-16T03:05:00Z',
        },
        {
            id: 1279,
            amount: 1500000,
            date: '2026-06-15',
            note: 'Rent share',
            category: mock_categories.housing,
            recurring_id: 1,
            created_at: '2026-06-15T00:00:00Z',
            updated_at: '2026-06-15T00:00:00Z',
        },
        {
            id: 1278,
            amount: 18000,
            date: '2026-06-15',
            note: 'Milk tea run',
            category: mock_categories.fun,
            recurring_id: null,
            created_at: '2026-06-15T08:47:00Z',
            updated_at: '2026-06-15T08:47:00Z',
        },
    ])


    /** Computed — grouped over the whole list, so a day split across pages prints one header. */
    const daily_expenses = computed(() => groupByDate(expenses.value))


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
        daily_expenses
    }
}