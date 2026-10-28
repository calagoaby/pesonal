import type { Ref } from 'vue'
import type { ItemRow } from '~/utils/items'

/** The API's cap: a receipt-sized list. */
const MAX_ITEMS = 20

/**
 * The Items section of a form: the rows, the one keypad that prices them, and every
 * message the section shows. Knows nothing about expenses — the standing-order form
 * uses it too — so `total` and `errors` come in as getters.
 */
export function useItems(items: Ref<ItemRow[]>, total: () => number, errors: () => Record<string, string[]>) {
    /** States */
    const root = useTemplateRef<HTMLElement>('root')
    const is_keypad_open = ref(false)
    /** The row the keypad is pricing. */
    const editing_key = ref<number | null>(null)


    /** Computed */
    const items_total = computed(() => itemsTotal(items.value))
    // A zero total just means the amount isn't typed yet; Save is already blocked then.
    const is_over = computed(() => total() > 0 && items_total.value > total())
    const can_add = computed(() => items.value.length < MAX_ITEMS)
    const counter = computed(() => `${formatAmountWithCurrency(items_total.value)} of ${formatAmountWithCurrency(total())}`)

    const over_message = computed(() => {
        const excess = formatAmountWithCurrency(items_total.value - total())

        return `The items add up to ${excess} more than the total. Raise the total or trim an item.`
    })

    // The keypad edits whichever row opened it, through this one binding.
    const editing_amount = computed({
        get: () => items.value.find(row => row.key === editing_key.value)?.amount ?? 0,
        set: (amount: number) => {
            if (editing_key.value !== null) {
                updateRow(editing_key.value, { amount })
            }
        }
    })

    // Running sum in entry order: the rows that carry the list past the total turn red.
    const past_total_keys = computed(() => {
        const keys = new Set<number>()
        let sum = 0

        for (const row of items.value) {
            sum += row.amount

            if (total() > 0 && sum > total() && row.amount > 0) {
                keys.add(row.key)
            }
        }

        return keys
    })

    // The server numbers `items.N.*` errors by the payload it got, which had the blank
    // rows dropped, so N counts the non-blank rows only.
    const row_errors = computed(() => {
        const sent = items.value.filter(row => !isBlankItem(row))
        const messages = new Map<number, string>()

        for (const field of Object.keys(errors())) {
            const match = /^items\.(\d+)\.(name|amount)$/.exec(field)
            const row = match ? sent[Number(match[1])] : undefined

            if (!match || !row || messages.has(row.key)) {
                continue
            }

            // The server's amount message talks about centavos; this reads better beside a price.
            messages.set(row.key, match[2] === 'name'
                ? 'This item needs a name, 40 characters at most.'
                : 'This price has to be more than ₱0.00.')
        }

        return messages
    })

    const list_error = computed(() => errors().items?.[0] ?? '')
    const has_half_item = computed(() => hasHalfItem(items.value))


    /** Functions */
    function updateRow(key: number, change: Partial<ItemRow>) {
        items.value = items.value.map(row => row.key === key ? { ...row, ...change } : row)
    }

    function handleNameInput(key: number, event: Event) {
        updateRow(key, { name: (event.target as HTMLInputElement).value })
    }

    function openPrice(key: number) {
        editing_key.value = key
        is_keypad_open.value = true
    }

    async function addItem() {
        if (!can_add.value) {
            return
        }

        const row = blankItemRow(items.value)
        items.value = [...items.value, row]

        // A new row is for typing into, so the cursor goes straight to its name.
        await nextTick()
        root.value?.querySelector<HTMLInputElement>(`[data-item-key="${row.key}"]`)?.focus()
    }

    function removeItem(key: number) {
        items.value = items.value.filter(row => row.key !== key)
    }

    /** Which half of a half-filled row is missing, to mark it. */
    function missingPart(row: ItemRow): 'name' | 'price' | null {
        if (!isHalfItem(row)) {
            return null
        }

        return row.amount === 0 ? 'price' : 'name'
    }


    return {
        is_keypad_open,
        editing_amount,
        is_over,
        can_add,
        counter,
        over_message,
        past_total_keys,
        row_errors,
        list_error,
        has_half_item,

        handleNameInput,
        openPrice,
        addItem,
        removeItem,
        missingPart
    }
}