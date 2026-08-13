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
    // The lock/clear/run/sort-errors dance lives in submitRequest so the category
    // form runs the identical sequence; this just binds it to these refs.
    function submit(write: () => Promise<unknown>) {
        return submitRequest(write, { is_saving, errors, failure })
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