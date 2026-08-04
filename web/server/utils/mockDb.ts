import type { CategoryRef } from "#shared/types/Category";
import type { Expense } from "#shared/types/Expense";

/**
 * Stand-in dataset until the Laravel endpoints land. Deliberately generated from
 * fixed rules rather than random values — a mock that changes between requests
 * makes pagination and SSR impossible to reason about.
 */

export const categories: CategoryRef[] = [
    { id: 1, name: 'Housing', color: '#534F8C', emoji: '🏠' },
    { id: 2, name: 'Groceries', color: '#5E7A3E', emoji: '🛒' },
    { id: 3, name: 'Food', color: '#C9692E', emoji: '🍔' },
    { id: 4, name: 'Transport', color: '#3F6F97', emoji: '🚗' },
    { id: 5, name: 'Fun', color: '#B0577B', emoji: '🎉' },
    { id: 6, name: 'Utilities', color: '#7A6A3E', emoji: '💡' },
    { id: 7, name: 'Health', color: '#2F7D52', emoji: '💊' },
    { id: 8, name: 'Other', color: '#8A857B', emoji: '🧾' },
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

function findCategory(id: number): CategoryRef {
    return categories.find(category => category.id === id) ?? categories[categories.length - 1]!
}

function buildExpenses(): Expense[] {
    const expenses: Expense[] = []
    let id = 1000

    // Newest first, matching the contract's date desc ordering.
    for (let day = 30; day >= 1; day--) {
        const per_day = day % 3 === 0 ? 1 : 2

        for (let slot = 0; slot < per_day; slot++) {
            const template = templates[(day + slot) % templates.length]!
            const date = `2026-06-${String(day).padStart(2, '0')}`
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
    }

    // The one materialized row, so recurring_id is exercised somewhere.
    expenses.push({
        id: 900,
        amount: 1500000,
        date: '2026-06-01',
        note: 'Rent share',
        category: findCategory(1),
        recurring_id: 1,
        created_at: '2026-06-01T00:00:00Z',
        updated_at: '2026-06-01T00:00:00Z',
    })

    return expenses
}

export const expenses: Expense[] = buildExpenses()