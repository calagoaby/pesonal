import { createRecurring } from "~/repositories/recurring";
import { provideRecurringForm } from "./context/RecurringFormContext";

export function useAddRecurring() {
    const { public: config } = useRuntimeConfig()

    // Owns the shared form state and provides it to the form component below.
    const form = provideRecurringForm()


    /** Functions */
    async function save() {
        const category = form.category_id.value

        if (!form.can_save.value || category === null) {
            return false
        }

        const saved = await form.submit(() => createRecurring({
            amount: form.amount.value,
            category_id: category,
            day_of_month: form.day_of_month.value,
            start_date: form.start_date.value,
            note: form.note.value.trim() || null,
            end_date: form.end_date.value,
            active: form.active.value,
            items: itemsPayload(form.items.value),
        }))

        if (saved) {
            clearNuxtData(['recurring'])
            reset()
        }

        return saved
    }

    function reset() {
        const today = todayIn(config.timezone)

        form.amount.value = 0
        form.category_id.value = null
        form.note.value = ''
        form.day_of_month.value = Number(today.slice(8, 10))
        form.start_date.value = today
        form.end_date.value = null
        form.active.value = true
        form.items.value = []
    }


    return {
        ...form,

        save,
        reset
    }
}