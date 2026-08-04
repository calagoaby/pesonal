export function useAddExpense() {
    /** States — the draft entry, POSTed as { amount, date, category_id, note? }. */
    const amount = ref(0)
    /** Null until picked. The contract requires it, so there's no safe default. */
    const category_id = ref<number | null>(null)
    // Hardcoded rather than today's date: `new Date()` differs between server and
    // client and would break hydration. Needs a client-side default when it goes live.
    const date = ref('2026-06-17')
    const note = ref('')


    return {
        amount,
        category_id,
        date,
        note
    }
}