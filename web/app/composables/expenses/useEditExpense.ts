import type { Expense } from "#shared/types/Expense";
import { deleteExpense, restoreExpense, updateExpense } from "~/repositories/expenses";
import { provideExpenseForm } from "./context/ExpenseFormContext";

export function useEditExpense(loaded: Expense) {
    // Seed the shared form from the entry, then provide it to the form component.
    const form = provideExpenseForm({
        amount: loaded.amount,
        category_id: loaded.category.id,
        date: loaded.date,
        note: loaded.note ?? '',
    })

    // The row as the server last confirmed it — the audit line reads this. A save
    // swaps in the row PATCH returns, so "last changed" reflects the write with no
    // refetch: the response is already the canonical updated resource.
    const entry = ref(loaded)

    const is_removing = ref(false)


    /** Functions */
    async function save() {
        const category = form.category_id.value

        if (!form.can_save.value || category === null) {
            return false
        }

        let updated: Expense | undefined
        const saved = await form.submit(async () => {
            updated = await updateExpense(entry.value.id, {
                amount: form.amount.value,
                date: form.date.value,
                category_id: category,
                note: form.note.value.trim() || null,
            })
        })

        if (saved && updated) {
            entry.value = updated
            // Drop the caches the write invalidated — the roll and totals live on
            // screens that aren't mounted here — plus this entry's own key, so a
            // later re-open refetches rather than serving the pre-edit copy.
            clearNuxtData(['month-expenses', 'monthly-stats', 'recent-items', `expense-${updated.id}`])
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
            await deleteExpense(entry.value.id)
            clearNuxtData(['month-expenses', 'monthly-stats', 'recent-items', `expense-${entry.value.id}`])

            return true
        } catch (error) {
            form.failure.value = (error as ApiRequestError).message

            return false
        } finally {
            is_removing.value = false
        }
    }

    // Undo a void. The row was only hidden, so the same row comes back — re-creating it
    // would lose recurring_id and let the standing-order job charge the month twice.
    async function restore(removed: Expense): Promise<boolean> {
        try {
            await restoreExpense(removed.id)

            // The roll is the screen the undo runs from, so refetch it live; the others
            // just drop their cache and refetch the next time they're shown.
            clearNuxtData(['monthly-stats', 'recent-items'])
            refreshNuxtData('month-expenses')

            return true
        } catch {
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