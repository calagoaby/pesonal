import type { Category, CategoryRef } from "#shared/types/Category";
import type { Expense } from "#shared/types/Expense";
import type { RecurringExpense } from "#shared/types/Recurring";

/**
 * Stand-in dataset until the Laravel endpoints land. Deliberately generated from
 * fixed rules rather than random values — a mock that changes between requests
 * makes pagination and SSR impossible to reason about.
 */

const seeded_at = '2026-01-01T00:00:00Z'

export const categories: Category[] = [
    { id: 1, name: 'Housing', color: '#534F8C', emoji: '🏠', created_at: seeded_at },
    { id: 2, name: 'Groceries', color: '#5E7A3E', emoji: '🛒', created_at: seeded_at },
    { id: 3, name: 'Food', color: '#C9692E', emoji: '🍔', created_at: seeded_at },
    { id: 4, name: 'Transport', color: '#3F6F97', emoji: '🚗', created_at: seeded_at },
    { id: 5, name: 'Fun', color: '#B0577B', emoji: '🎉', created_at: seeded_at },
    { id: 6, name: 'Utilities', color: '#7A6A3E', emoji: '💡', created_at: seeded_at },
    { id: 7, name: 'Health', color: '#2F7D52', emoji: '💊', created_at: seeded_at },
    { id: 8, name: 'Other', color: '#8A857B', emoji: '🧾', created_at: seeded_at },
]

const templates = [
    { note: 'Lunch with team', amount: 68000, category_id: 3 },
    { note: 'Grab to office', amount: 24000, category_id: 4 },
    { note: 'Kape sa umaga', amount: 15000, category_id: 3 },
    { note: 'SM Groceries', amount: 215000, category_id: 2 },
    { note: 'Load', amount: 30000, category_id: 8 },
    { note: 'Milk tea run', amount: 18000, category_id: 5 },
    { note: 'Jeep fare', amount: 4800, category_id: 4 },
    { note: 'Meralco', amount: 182000, category_id: 6 },
    { note: 'Vitamins', amount: 52000, category_id: 7 },
    { note: 'Merienda', amount: 12000, category_id: 3 },
]

/** Expenses embed the ref shape — no created_at on a nested category. */
function findCategory(id: number): CategoryRef {
    const category = categories.find(item => item.id === id) ?? categories[categories.length - 1]!

    return {
        id: category.id,
        name: category.name,
        color: category.color,
        emoji: category.emoji,
    }
}

const months = [
    { month: '2026-07', days: 31 },
    { month: '2026-06', days: 30 },
    { month: '2026-05', days: 31 },
]

function buildExpenses(): Expense[] {
    const expenses: Expense[] = []
    let id = 2000

    // Newest first, matching the contract's date desc ordering.
    for (const { month, days } of months) {
        for (let day = days; day >= 1; day--) {
            const per_day = day % 3 === 0 ? 1 : 2

            for (let slot = 0; slot < per_day; slot++) {
                const template = templates[(day + slot) % templates.length]!
                const date = `${month}-${String(day).padStart(2, '0')}`
                const timestamp = `${date}T0${slot + 1}:00:00Z`

                expenses.push({
                    id: id--,
                    amount: template.amount,
                    date,
                    note: template.note,
                    category: findCategory(template.category_id),
                    recurring_id: null,
                    created_at: timestamp,
                    updated_at: timestamp,
                })
            }

            // Rent lands on the 1st, so recurring_id is exercised every month.
            if (day === 1) {
                expenses.push({
                    id: id--,
                    amount: 1500000,
                    date: `${month}-01`,
                    note: 'Rent share',
                    category: findCategory(1),
                    recurring_id: 1,
                    created_at: `${month}-01T00:00:00Z`,
                    updated_at: `${month}-01T00:00:00Z`,
                })
            }
        }
    }

    return expenses
}

export const expenses: Expense[] = buildExpenses()

// Standing orders. Active amounts sum to ₱18,700; Gym is paused. Days spread so
// the list groups into several "Every Nth" sections.
export const recurring: RecurringExpense[] = [
    { id: 1, amount: 1500000, note: 'Apartment rent', day_of_month: 1, start_date: '2026-01-01', end_date: null, active: true, category: findCategory(1), created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z' },
    { id: 2, amount: 180000, note: 'Internet', day_of_month: 5, start_date: '2026-03-01', end_date: null, active: true, category: findCategory(6), created_at: '2026-03-01T00:00:00Z', updated_at: '2026-03-01T00:00:00Z' },
    { id: 3, amount: 19400, note: 'Spotify', day_of_month: 15, start_date: '2026-02-01', end_date: null, active: true, category: findCategory(5), created_at: '2026-02-01T00:00:00Z', updated_at: '2026-02-01T00:00:00Z' },
    { id: 4, amount: 150000, note: 'Gym', day_of_month: 15, start_date: '2026-01-01', end_date: null, active: false, category: findCategory(7), created_at: '2026-01-01T00:00:00Z', updated_at: '2026-05-01T00:00:00Z' },
    { id: 5, amount: 62000, note: 'Water bill', day_of_month: 20, start_date: '2026-01-01', end_date: null, active: true, category: findCategory(6), created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z' },
    { id: 6, amount: 108600, note: 'Netflix', day_of_month: 20, start_date: '2026-01-01', end_date: '2026-12-01', active: true, category: findCategory(5), created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z' },
]