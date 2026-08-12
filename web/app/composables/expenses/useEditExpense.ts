import type { Expense } from "#shared/types/Expense";
import { updateExpense } from "~/repositories/expenses";
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
                note: form.note.value.trim() || undefined,
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


    return {
        ...form,
        entry,

        save
    }
}
