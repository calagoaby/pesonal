import type { RecurringExpense } from "#shared/types/Recurring";
import { deleteRecurring, restoreRecurring, updateRecurring } from "~/repositories/recurring";
import { provideRecurringForm } from "./context/RecurringFormContext";

export function useEditRecurring(loaded: RecurringExpense) {
    // Seed the shared form from the template, then provide it to the form component.
    const form = provideRecurringForm({
        amount: loaded.amount,
        category_id: loaded.category.id,
        note: loaded.note ?? '',
        day_of_month: loaded.day_of_month,
        start_date: loaded.start_date,
        end_date: loaded.end_date,
        active: loaded.active,
    })

    // The row as the server last confirmed it — the audit line reads this. A save
    // swaps in the row PATCH returns, so "last changed" reflects the write live.
    const entry = ref(loaded)

    const is_removing = ref(false)


    /** Functions */
    async function save() {
        const category = form.category_id.value

        if (!form.can_save.value || category === null) {
            return false
        }

        let updated: RecurringExpense | undefined
        const saved = await form.submit(async () => {
            updated = await updateRecurring(entry.value.id, {
                amount: form.amount.value,
                category_id: category,
                note: form.note.value.trim() || null,
                day_of_month: form.day_of_month.value,
                start_date: form.start_date.value,
                end_date: form.end_date.value,
                active: form.active.value,
            })
        })

        if (saved && updated) {
            entry.value = updated
            clearNuxtData(['recurring', `recurring-${updated.id}`])
        }

        return saved
    }


    async function remove() {
        if (is_removing.value) {
            return false
        }

        is_removing.value = true
        form.failure.value = ''

        try {
            await deleteRecurring(entry.value.id)
            // Only the template's own keys — deleting it leaves already-materialized
            // expenses alone, so the roll and the month totals are still accurate.
            clearNuxtData(['recurring', `recurring-${entry.value.id}`])

            return true
        } catch (error) {
            form.failure.value = (error as ApiRequestError).message

            return false
        } finally {
            is_removing.value = false
        }
    }

    // Undo a delete. The same template id comes back, so the month it already charged
    // still counts and the job doesn't charge it again.
    async function restore(removed: RecurringExpense): Promise<boolean> {
        try {
            await restoreRecurring(removed.id)

            // The list is the screen the undo runs from, so refetch it live.
            refreshNuxtData('recurring')

            return true
        } catch {
            // The toast has already dismissed; the caller surfaces the failure.
            return false
        }
    }


    return {
        ...form,
        entry,
        is_removing,

        save,
        remove,
        restore
    }
}