import type { CategoryRef } from "#shared/types/Category";

export function useAddExpense() {
    /** States — the draft entry, POSTed as { amount, date, category_id, note? }. */
    const amount = ref(0)
    const category = ref<CategoryRef>({ id: 3, name: 'Food', color: '#C9692E', emoji: '🍔' })
    // Hardcoded rather than today's date: `new Date()` differs between server and
    // client and would break hydration. Needs a client-side default when it goes live.
    const date = ref('2026-06-17')
    const note = ref('')


    return {
        amount,
        category,
        date,
        note
    }
}