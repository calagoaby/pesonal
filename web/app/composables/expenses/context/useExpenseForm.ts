export interface ExpenseFormSeed {
    amount?: number
    category_id?: number | null
    date?: string
    note?: string
}

/** The fields every expense form binds, plus the write plumbing add and edit share. */
export function useExpenseForm(seed: ExpenseFormSeed = {}) {
    const { public: config } = useRuntimeConfig()


    /** States */
    const amount = ref(seed.amount ?? 0)
    /** Null until picked. The contract requires it, so there's no safe default. */
    const category_id = ref<number | null>(seed.category_id ?? null)
    const date = ref(seed.date ?? todayIn(config.timezone))
    const note = ref(seed.note ?? '')

    const is_saving = ref(false)
    /** Per-field messages from a 422. */
    const errors = ref<Record<string, string[]>>({})
    /** Anything else that stopped the write — network, 500. */
    const failure = ref('')


    /** Computed */
    const can_save = computed(() => amount.value > 0 && category_id.value !== null)


    /** Functions */
    // The one submit dance add and edit share: lock, clear the last attempt, run
    // the write, and sort an ApiRequestError into field errors (422) or a single
    // failure line (everything else). Returns whether the write went through.
    async function submit(write: () => Promise<unknown>): Promise<boolean> {
        if (is_saving.value) {
            return false
        }

        is_saving.value = true
        errors.value = {}
        failure.value = ''

        try {
            await write()

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


    return {
        amount,
        category_id,
        date,
        note,
        is_saving,
        errors,
        failure,
        can_save,

        submit
    }
}
