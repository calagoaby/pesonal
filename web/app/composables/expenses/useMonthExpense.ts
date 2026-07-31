import type { DailyExpenses, Expenses, MonthlyExpenses } from "~/types/Expenses";

export function useMonthExpense() {
    /** States */
    const month_expenses = <MonthlyExpenses>{
        month: '2026-06',
        days: <DailyExpenses[]>[
            {
                date: '2026-06-17',
                items: <Expenses[]>[
                    {
                        description: 'Lunch with team',
                        category: 'Food',
                        amount: 68000
                    },
                    {
                        description: 'Grab to office',
                        category: 'Transport',
                        amount: 24000
                    },
                    {
                        description: 'Kape sa umaga',
                        category: 'Food',
                        amount: 15000
                    },
                ]
            },
            {
                date: '2026-06-16',
                items: [
                    {
                        description: 'SM Groceries',
                        category: 'Groceries',
                        amount: 215000
                    },
                    {
                        description: 'Load',
                        category: 'Other',
                        amount: 30000
                    },
                ]
            },
            {
                date: '2026-06-15',
                items: [
                    {
                        description: 'Rent share',
                        category: 'Housing',
                        amount: 1500000
                    },
                    {
                        description: 'Milk tea run',
                        category: 'Fun',
                        amount: 18000
                    },
                ]
            },
        ]
    }

    return {
        month_expenses
    }
}