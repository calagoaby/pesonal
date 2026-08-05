import { createExpense } from "~/repositories/expenses";

export function useAddExpense() {
    const { public: config } = useRuntimeConfig()


    /** States — the draft entry, POSTed as { amount, date, category_id, note? }. */
    const amount = ref(0)
    /** Null until picked. The contract requires it, so there's no safe default. */
    const category_id = ref<number | null>(null)
    const date = ref(todayIn(config.timezone))
    const note = ref('')

    const is_saving = ref(false)
    /** Per-field messages from a 422. */
    const errors = ref<Record<string, string[]>>({})
    /** Anything else that stopped the save — network, 500. */
    const failure = ref('')


    /** Computed */
    const can_save = computed(() => amount.value > 0 && category_id.value !== null)


    /** Functions */
    async function save() {
        const category = category_id.value

        if (!can_save.value || is_saving.value || category === null) {
            return false
        }

        is_saving.value = true
        errors.value = {}
        failure.value = ''

        try {
            await createExpense({
                amount: amount.value,
                date: date.value,
                category_id: category,
                note: note.value.trim() || undefined,
            })

            // The roll, the month totals and recent items are all stale now. Clear
            // rather than refresh: those keys belong to screens that aren't mounted
            // here, so there's nothing to refresh — dropping the cache makes them
            // refetch when they next appear.
            clearNuxtData(['month-expenses', 'monthly-stats', 'recent-items'])
            reset()

            return true
        } catch (error) {
            const request_error = error as ApiRequestError

            errors.value = request_error.errors ?? {}
            failure.value = request_error.errors ? '' : request_error.message

            return false
        } finally {
            is_saving.value = false
        }
    }

    function reset() {
        amount.value = 0
        category_id.value = null
        date.value = todayIn(config.timezone)
        note.value = ''
    }


    return {
        amount,
        category_id,
        date,
        note,
        is_saving,
        errors,
        failure,
        can_save,

        save,
        reset
    }
}