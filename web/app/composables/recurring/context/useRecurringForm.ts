import type { ExpenseItem } from "#shared/types/Expense";
import type { ItemRow } from "~/utils/items";

export interface RecurringFormSeed {
    amount?: number
    category_id?: number | null
    note?: string
    day_of_month?: number
    start_date?: string
    end_date?: string | null
    active?: boolean
    items?: ExpenseItem[]
}

/** The fields every standing-order form binds, plus the shared write plumbing. */
export function useRecurringForm(seed: RecurringFormSeed = {}) {
    const { public: config } = useRuntimeConfig()
    const today = todayIn(config.timezone)


    /** States */
    const amount = ref(seed.amount ?? 0)
    /** Null until picked. The contract requires it, so there's no safe default. */
    const category_id = ref<number | null>(seed.category_id ?? null)
    const note = ref(seed.note ?? '')
    const day_of_month = ref(seed.day_of_month ?? Number(today.slice(8, 10)))
    const start_date = ref(seed.start_date ?? today)
    /** Null = open-ended, a first-class choice, not a blank date. */
    const end_date = ref<string | null>(seed.end_date ?? null)
    const active = ref(seed.active ?? true)
    const items = ref<ItemRow[]>(toItemRows(seed.items ?? []))

    const is_saving = ref(false)
    const errors = ref<Record<string, string[]>>({})
    const failure = ref('')


    /** Computed */
    const items_total = computed(() => itemsTotal(items.value))
    /** Items are a partial breakdown of the typed amount: less or equal, never more. */
    const items_over = computed(() => items_total.value > amount.value)
    const has_half_item = computed(() => hasHalfItem(items.value))
    const can_save = computed(() => amount.value > 0
        && category_id.value !== null
        && !items_over.value
        && !has_half_item.value)


    /** Functions */
    function submit(write: () => Promise<unknown>) {
        return submitRequest(write, { is_saving, errors, failure })
    }


    return {
        amount,
        category_id,
        note,
        day_of_month,
        start_date,
        end_date,
        active,
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