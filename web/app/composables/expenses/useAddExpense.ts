export function useAddExpense() {
    const { public: config } = useRuntimeConfig()


    /** States — the draft entry, POSTed as { amount, date, category_id, note? }. */
    const amount = ref(0)
    /** Null until picked. The contract requires it, so there's no safe default. */
    const category_id = ref<number | null>(null)
    const date = ref(todayIn(config.timezone))
    const note = ref('')


    return {
        amount,
        category_id,
        date,
        note
    }
}