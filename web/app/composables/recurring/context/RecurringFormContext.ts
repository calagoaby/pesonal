import { useRecurringForm, type RecurringFormSeed } from "./useRecurringForm";

type recurring_form_context = ReturnType<typeof useRecurringForm>

const key = Symbol('recurring-form')

export const provideRecurringForm = (seed?: RecurringFormSeed) => {
    const form = useRecurringForm(seed)

    provide(key, form)

    return form
}

export const useRecurringFormContext = () => {
    const form = inject<recurring_form_context>(key)

    if (!form) {
        throw new Error('useRecurringFormContext')
    }

    return form
}