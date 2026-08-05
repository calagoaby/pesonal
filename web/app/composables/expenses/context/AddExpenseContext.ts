import { useAddExpense } from "./useAddExpense";

type add_expense_context = ReturnType<typeof useAddExpense>

const key = Symbol('add-expense')

export const provideAddExpense = () => {
    const flow = useAddExpense()

    provide(key, flow)

    return flow
}

export const useAddExpenseContext = () => {
    const flow = inject<add_expense_context>(key)

    if (!flow) {
        throw new Error('useAddExpenseContext')
    }

    return flow
}