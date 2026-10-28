import { createExpense } from "~/repositories/expenses";
import { provideExpenseForm } from "./context/ExpenseFormContext";

export function useAddExpense() {
    const { public: config } = useRuntimeConfig()

    // Owns the shared form state and provides it to the form component below.
    const form = provideExpenseForm()


    /** Functions */
    async function save() {
        const category = form.category_id.value

        if (!form.can_save.value || category === null) {
            return false
        }

        const saved = await form.submit(() => createExpense({
            amount: form.amount.value,
            date: form.date.value,
            category_id: category,
            note: form.note.value.trim() || null,
            items: itemsPayload(form.items.value),
        }))

        if (saved) {
            // The roll, the month totals and recent items are all stale now. Clear
            // rather than refresh: those keys belong to screens that aren't mounted
            // here, so there's nothing to refresh — dropping the cache makes them
            // refetch when they next appear.
            clearNuxtData(['month-expenses', 'monthly-stats', 'recent-items'])
            reset()
        }

        return saved
    }

    function reset() {
        form.amount.value = 0
        form.category_id.value = null
        form.date.value = todayIn(config.timezone)
        form.note.value = ''
        form.items.value = []
    }


    return {
        ...form,

        save,
        reset
    }
}