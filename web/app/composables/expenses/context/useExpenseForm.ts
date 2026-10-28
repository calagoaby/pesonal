import type { ExpenseItem } from "#shared/types/Expense";
import type { ItemRow } from "~/utils/items";

export interface ExpenseFormSeed {
    amount?: number
    category_id?: number | null
    date?: string
    note?: string
    items?: ExpenseItem[]
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
    const items = ref<ItemRow[]>(toItemRows(seed.items ?? []))

    const is_saving = ref(false)
    /** Per-field messages from a 422. */
    const errors = ref<Record<string, string[]>>({})
    /** Anything else that stopped the write — network, 500. */
    const failure = ref('')


    /** Computed */
    const items_total = computed(() => itemsTotal(items.value))
    /** Items are a partial breakdown of the typed total: less or equal, never more. */
    const items_over = computed(() => items_total.value > amount.value)
    const has_half_item = computed(() => hasHalfItem(items.value))
    const can_save = computed(() => amount.value > 0
        && category_id.value !== null
        && !items_over.value
        && !has_half_item.value)


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
        items,
        is_saving,
        errors,
        failure,
        items_total,
        items_over,
        has_half_item,
        can_save,

        submit
    }
}