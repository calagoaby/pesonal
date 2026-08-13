import { useExpenseForm, type ExpenseFormSeed } from "./useExpenseForm";

type expense_form_context = ReturnType<typeof useExpenseForm>

const key = Symbol('expense-form')

export const provideExpenseForm = (seed?: ExpenseFormSeed) => {
    const form = useExpenseForm(seed)

    provide(key, form)

    return form
}

export const useExpenseFormContext = () => {
    const form = inject<expense_form_context>(key)

    if (!form) {
        throw new Error('useExpenseFormContext')
    }

    return form
}
